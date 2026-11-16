<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus;

use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Support\RecordUrls;
use Atrium\Admin\Support\Sites;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Models\MenuItem;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The menus of the current site as the theme draws them.
 *
 * A menu is resolved once per site, language and code and kept in the cache:
 * the addresses of the linked records, the records a dynamic item unfolds.
 * Saving a menu or any record with pages changes a version key, which every
 * cache entry carries, so the next page builds the menus anew. This works on
 * every cache store, also those without tags (file, database).
 *
 * The current page is marked on every request, after the cache.
 */
final class Menus
{
    private const VERSION_KEY = 'atrium-menus:version';

    private const TTL = 3600;

    /** Records a dynamic item may list, at most, from one model. */
    private const MAX_RECORDS = 500;

    public function __construct(
        private readonly Cache $cache,
        private readonly RecordUrls $urls,
        private readonly Sites $sites,
    ) {}

    /**
     * The menu with this code in the current language, with the current page
     * marked, or null when the site has none.
     *
     * Each item: title, url (null for a heading), target, icon, current (the
     * page shown), active (it or an item below it is), children.
     *
     * @return array{code:string,name:string,items:array<int,array<string,mixed>>}|null
     */
    public function get(string $code, ?string $language = null, ?string $currentUrl = null): ?array
    {
        $language ??= app()->getLocale();
        $menu = $this->resolved($code, $language);

        if ($menu === null) {
            return null;
        }

        $menu['items'] = $this->mark($menu['items'], $currentUrl ?? request()->url());

        return $menu;
    }

    /**
     * Forget every resolved menu: a menu or a linked record has changed.
     */
    public function flush(): void
    {
        $this->cache->forever(self::VERSION_KEY, Str::random(12));
    }

    /**
     * Whether a saved record may show in a menu: a menu, its items, or a
     * record with pages.
     */
    public function concerns(Model $record): bool
    {
        return $record instanceof Menu || $record instanceof MenuItem || $this->urls->hasPages($record::class);
    }

    /**
     * @return array{code:string,name:string,items:array<int,array<string,mixed>>}|null
     */
    private function resolved(string $code, string $language): ?array
    {
        $version = $this->cache->rememberForever(self::VERSION_KEY, static fn (): string => Str::random(12));
        $key = implode(':', ['atrium-menus', $this->sites->current()->id, $language, md5($code), $version]);

        // An array: null would not be told apart from a miss.
        $menu = $this->cache->remember($key, self::TTL, fn (): array => ['menu' => $this->inLocale($language, fn (): ?array => $this->build($code, $language))]);

        return $menu['menu'];
    }

    /**
     * A model that writes its name per language answers in the locale: the
     * menu is built in the language it is for.
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    private function inLocale(string $language, callable $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale($language);

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * @return array{code:string,name:string,items:array<int,array<string,mixed>>}|null
     */
    private function build(string $code, string $language): ?array
    {
        $menu = Menu::query()
            ->where('code', $code)
            ->where('active', true)
            ->where(fn (Builder $query) => $query->where('language', $language)->orWhereNull('language'))
            ->orderByRaw('case when language is null then 1 else 0 end')
            ->first();

        if (! $menu instanceof Menu) {
            return null;
        }

        $items = $menu->items()->get();
        $records = $this->linkedRecords($items);
        $byParent = $items->groupBy(fn (MenuItem $item): int => (int) $item->parent_id);

        return [
            'code' => $menu->code,
            'name' => $menu->name,
            'items' => $this->branch($byParent, 0, $records, $language, 1),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int,Collection<int,MenuItem>>  $byParent
     * @param  array<string,array<int,Model>>  $records
     * @return array<int,array<string,mixed>>
     */
    private function branch(\Illuminate\Support\Collection $byParent, int $parent, array $records, string $language, int $depth): array
    {
        $nodes = [];

        if ($depth > Menu::MAX_DEPTH + 1) {
            return [];
        }

        foreach ($byParent->get($parent, []) as $item) {
            if (! $item->active) {
                continue;
            }

            $url = null;
            $record = null;

            if ($item->url !== null) {
                $url = $item->url;
            } elseif ($item->linkable_type !== null) {
                $record = $records[$item->linkable_type][$item->linkable_id] ?? null;
                $url = $record === null ? null : $this->urls->url($record, $language);

                // A link to a record that is gone, hidden or not written in
                // this language: the item goes too, not a dead link.
                if ($url === null) {
                    continue;
                }
            }

            $children = $this->branch($byParent, $item->id, $records, $language, $depth + 1);

            if ($item->source !== null) {
                $children = [...$children, ...$this->unfold($item->source, $item->source_depth, $record, $language)];
            }

            $nodes[] = $this->node($item->title, $url, $item->target, $item->icon, $children);
        }

        return $nodes;
    }

    /**
     * The records of a model under an item: the children of the linked
     * record when it is of the same tree, the top of the tree otherwise; a
     * model without a tree lists its records.
     *
     * @return array<int,array<string,mixed>>
     */
    private function unfold(string $source, int $depth, ?Model $linked, string $language): array
    {
        if (! is_subclass_of($source, Model::class) || ! $this->urls->hasPages($source)) {
            return [];
        }

        $rule = PublicUrl::ruleFor($source);
        $parentColumn = ($rule['tree'] ?? null) === $source ? $rule['parent'] : null;
        $records = $this->visible($source)->limit(self::MAX_RECORDS)->get();

        // A model without a tree is one flat level: every record sits under
        // the root.
        $byParent = $parentColumn === null
            ? collect([0 => $records->all()])
            : $records->groupBy(fn (Model $record): int => (int) $record->getAttribute($parentColumn));
        $root = $parentColumn !== null && $linked instanceof $source ? $linked->getKey() : 0;

        $walk = function (int|string $parent, int $level) use (&$walk, $byParent, $depth, $language): array {
            $nodes = [];

            foreach ($byParent->get($parent, []) as $record) {
                $url = $this->urls->url($record, $language);

                if ($url === null) {
                    continue;
                }

                $children = $level < $depth ? $walk($record->getKey(), $level + 1) : [];
                $nodes[] = $this->node($this->title($record, $language), $url, null, null, $children);
            }

            return $nodes;
        };

        return $walk($root, 1);
    }

    /**
     * The linked records that may be shown, one query per model.
     *
     * @param  Collection<int,MenuItem>  $items
     * @return array<string,array<int,Model>>
     */
    private function linkedRecords(Collection $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if ($item->linkable_type !== null && $item->active && is_subclass_of($item->linkable_type, Model::class)) {
                $ids[$item->linkable_type][] = $item->linkable_id;
            }
        }

        $records = [];

        foreach ($ids as $model => $keys) {
            foreach ($this->visible($model)->whereKey($keys)->get() as $record) {
                $records[$model][$record->getKey()] = $record;
            }
        }

        return $records;
    }

    /**
     * Active records within their dates, in their order.
     *
     * @param  class-string<Model>  $model
     * @return Builder<Model>
     */
    private function visible(string $model): Builder
    {
        $query = $model::query();
        $has = array_flip(Schema::connection($query->getModel()->getConnectionName())->getColumnListing($query->getModel()->getTable()));

        if (isset($has['active'])) {
            $query->where('active', true);
        }

        if (isset($has['active_from'])) {
            $query->where(fn (Builder $q) => $q->whereNull('active_from')->orWhere('active_from', '<=', now()));
        }

        if (isset($has['active_to'])) {
            $query->where(fn (Builder $q) => $q->whereNull('active_to')->orWhere('active_to', '>=', now()));
        }

        if (isset($has['sort'])) {
            $query->orderBy('sort');
        }

        return $query->orderBy($query->getModel()->getKeyName());
    }

    private function title(Model $record, string $language): string
    {
        $name = $record->getAttribute('name');

        return is_string($name) && trim($name) !== '' ? trim($name) : '#'.$record->getKey();
    }

    /**
     * @param  array<int,array<string,mixed>>  $children
     * @return array<string,mixed>
     */
    private function node(string $title, ?string $url, ?string $target, ?string $icon, array $children): array
    {
        return [
            'title' => $title,
            'url' => $url,
            'target' => $target,
            'icon' => $icon,
            'current' => false,
            'active' => false,
            'children' => $children,
        ];
    }

    /**
     * Marks the item of the page shown and the items above it. The exact
     * address wins; failing that, the deepest item whose address the page's
     * starts with (a product under its catalog section). The home page
     * matches only itself.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>>
     */
    private function mark(array $items, string $currentUrl): array
    {
        $current = $this->path($currentUrl);
        $best = null;
        $bestLength = PHP_INT_MIN;

        $find = function (array $nodes, array $trail) use (&$find, &$best, &$bestLength, $current): void {
            foreach ($nodes as $index => $node) {
                $at = [...$trail, $index];
                $path = $node['url'] === null ? null : $this->localPath($node['url']);

                if ($path !== null) {
                    if ($path === $current) {
                        $length = PHP_INT_MAX;
                    } elseif (str_starts_with($current, $path.'/')) {
                        $length = strlen($path);
                    } else {
                        $length = PHP_INT_MIN;
                    }

                    if ($length > $bestLength) {
                        [$best, $bestLength] = [$at, $length];
                    }
                }

                $find($node['children'], $at);
            }
        };

        $find($items, []);

        if ($best === null) {
            return $items;
        }

        $nodes = &$items;
        $last = array_key_last($best);

        foreach ($best as $position => $index) {
            $nodes[$index]['active'] = true;

            if ($position === $last) {
                $nodes[$index]['current'] = $bestLength === PHP_INT_MAX;
            }

            $nodes = &$nodes[$index]['children'];
        }

        unset($nodes);

        return $items;
    }

    /**
     * The path of an address on this site; null for another site, an anchor,
     * mail and phone links.
     */
    private function localPath(string $url): ?string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $this->path($url);
        }

        $host = parse_url($url, PHP_URL_HOST);

        return preg_match('#^https?://#i', $url) === 1 && is_string($host) && strcasecmp($host, (string) request()->getHost()) === 0
            ? $this->path($url)
            : null;
    }

    private function path(string $url): string
    {
        // Not parse_url(): on some systems it mangles the bytes of an address
        // written in Cyrillic or Chinese. Without the scheme and the host,
        // up to the query or the anchor.
        preg_match('~^(?:[a-z][a-z0-9+.-]*://[^/?#]*)?([^?#]*)~i', $url, $parts);

        return trim(rawurldecode($parts[1]), '/');
    }
}
