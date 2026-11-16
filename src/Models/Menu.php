<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Models;

use Atrium\Admin\Columns\BooleanColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\CheckboxField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\LinkField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\TreeField;
use Atrium\Admin\Support\ContentLanguages;
use Atrium\Admin\Support\RecordSearch;
use Atrium\Admin\Traits\BelongsToSite;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A menu of a site: the header, the footer, a sidebar. The theme asks for
 * it by code (<x-atrium-menu code="header" />); a menu in the current
 * language wins over one for every language (language null).
 *
 * The items are edited as one tree in the menu's form and come back to it
 * as the `tree` attribute; the API reads and writes the same attribute.
 *
 * @property int $id
 * @property int|null $site_id
 * @property string|null $language
 * @property string $code
 * @property string $name
 * @property bool $active
 */
class Menu extends Model implements Administrable
{
    use BelongsToSite, InteractsWithAdmin;

    /** How deep editors may nest items; unfolded records go deeper. */
    public const MAX_DEPTH = 4;

    protected $table = 'menus';

    protected $fillable = ['site_id', 'language', 'code', 'name', 'active'];

    protected $appends = ['tree'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /**
     * @return HasMany<MenuItem,$this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort')->orderBy('id');
    }

    public static function getAdminLabel(): string
    {
        return __('atrium-menus::menus.module');
    }

    public static function getAdminCreateLabel(): string
    {
        return __('atrium-menus::menus.create');
    }

    public static function getDefaultSortColumn(): string
    {
        return 'code';
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAdministrationFields(): array
    {
        $languages = ['' => __('atrium-menus::menus.fields.all_languages')];

        foreach (app(ContentLanguages::class)->all() as $language) {
            $languages[$language->code] = $language->name;
        }

        return [
            CheckboxField::make('active')->label(__('atrium::fields.standard.active'))->default(true),
            TextField::make('name')->label(__('atrium::fields.standard.name'))->required()->rules(['max:255']),
            TextField::make('code')
                ->label(__('atrium::fields.standard.code'))
                ->hint(__('atrium-menus::menus.fields.code_hint'))
                ->required()
                ->rules(['max:64', 'regex:/^[a-z0-9_-]+$/']),
            SelectField::make('language')
                ->label(__('atrium-menus::menus.fields.language'))
                ->options($languages)
                ->default(''),
            TreeField::make('tree')
                ->label(__('atrium-menus::menus.fields.items'))
                ->addLabel(__('atrium-menus::menus.fields.add_item'))
                ->titleField('title')
                ->maxDepth(self::MAX_DEPTH)
                ->fields(self::itemFields()),
        ];
    }

    /**
     * The fields of one item of the tree.
     *
     * @return array<int,Field>
     */
    public static function itemFields(): array
    {
        $sources = ['' => __('atrium-menus::menus.fields.source_none')]
            + LinkField::make('link')->targets();

        return [
            TextField::make('title')->label(__('atrium-menus::menus.fields.title'))->required()->rules(['max:255']),
            LinkField::make('link')->label(__('atrium-menus::menus.fields.link')),
            SelectField::make('target')->label(__('atrium-menus::menus.fields.target'))->options([
                '' => __('atrium-menus::menus.fields.same_window'),
                '_blank' => __('atrium-menus::menus.fields.new_window'),
            ])->default(''),
            TextField::make('icon')
                ->label(__('atrium-menus::menus.fields.icon'))
                ->hint(__('atrium-menus::menus.fields.icon_hint'))
                ->rules(['max:100', 'regex:/^[A-Za-z0-9 _:-]*$/']),
            CheckboxField::make('active')->label(__('atrium::fields.standard.active'))->default(true),
            SelectField::make('source')
                ->label(__('atrium-menus::menus.fields.source'))
                ->hint(__('atrium-menus::menus.fields.source_hint'))
                ->options($sources)
                ->default(''),
            NumberField::make('source_depth')
                ->label(__('atrium-menus::menus.fields.source_depth'))
                ->default(1)
                ->rules(['integer', 'min:1', 'max:5']),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('id')->label(__('atrium::fields.standard.id'))->sortable(),
            TextColumn::make('name')->label(__('atrium::fields.standard.name'))->sortable()->searchable(),
            TextColumn::make('code')->label(__('atrium::fields.standard.code'))->sortable()->searchable(),
            TextColumn::make('language')->label(__('atrium-menus::menus.fields.language'))->sortable(),
            BooleanColumn::make('active')->label(__('atrium::fields.standard.active')),
        ];
    }

    /**
     * A code is unique per site and language: two "header" menus for all
     * languages could not be told apart.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function transformAdminData(array $data): array
    {
        // The site is the one picked in the panel (or `?site=` in the API),
        // never a value from the request body, though `site_id` is fillable
        // for the seeder.
        unset($data['site_id']);

        if (array_key_exists('language', $data)) {
            $data['language'] = is_string($data['language']) && $data['language'] !== '' ? $data['language'] : null;
        }

        $code = $data['code'] ?? $this->code;
        $language = array_key_exists('language', $data) ? $data['language'] : $this->language;

        $taken = static::query()
            ->where('code', $code)
            ->when($language === null, fn ($query) => $query->whereNull('language'), fn ($query) => $query->where('language', $language))
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['code' => __('atrium-menus::menus.code_taken')]);
        }

        return $data;
    }

    /**
     * Writes the tree. Items keep their ids (a link to an item stays), new
     * ones are created, the ones no longer in the tree are deleted. A save
     * without a tree (an API call that changes only the name) leaves the
     * items as they are.
     *
     * @param  array<string,mixed>  $data
     */
    public function afterAdminSave(array $data): void
    {
        if (! array_key_exists('tree', $data) || ! is_array($data['tree'])) {
            return;
        }

        DB::transaction(function () use ($data): void {
            $existing = $this->items()->pluck('id')->all();
            $kept = [];
            $this->writeItems($data['tree'], null, $existing, $kept);

            $gone = array_diff($existing, $kept);

            if ($gone !== []) {
                MenuItem::query()->whereKey($gone)->delete();
            }
        });

        $this->unsetRelation('items');
    }

    /**
     * @param  array<int,mixed>  $items
     * @param  array<int,int>  $existing
     * @param  array<int,mixed>  $kept
     */
    private function writeItems(array $items, ?int $parent, array $existing, array &$kept): void
    {
        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $link = is_array($item['link'] ?? null) ? $item['link'] : [];
            $type = $link['type'] ?? null;
            $source = is_string($item['source'] ?? null) && $item['source'] !== '' ? $item['source'] : null;

            $attributes = [
                'menu_id' => $this->id,
                'parent_id' => $parent,
                'sort' => ($index + 1) * 10,
                'title' => trim((string) ($item['title'] ?? '')),
                'url' => $type === LinkField::URL ? trim((string) ($link['url'] ?? '')) : null,
                'linkable_type' => $type === LinkField::RECORD ? $link['model'] : null,
                'linkable_id' => $type === LinkField::RECORD ? $link['id'] : null,
                'target' => ($item['target'] ?? '') === '_blank' ? '_blank' : null,
                'icon' => is_string($item['icon'] ?? null) && trim($item['icon']) !== '' ? trim($item['icon']) : null,
                'active' => filter_var($item['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'source' => $source,
                'source_depth' => max(1, min(5, (int) data_get($item, 'source_depth'))),
            ];

            $id = is_numeric($item['id'] ?? null) ? (int) $item['id'] : null;

            if ($id !== null && in_array($id, $existing, true) && ! in_array($id, $kept, true)) {
                MenuItem::query()->whereKey($id)->update($attributes);
            } else {
                $id = MenuItem::query()->create($attributes)->getKey();
            }

            $kept[] = $id;
            $this->writeItems(is_array($item['children'] ?? null) ? $item['children'] : [], $id, $existing, $kept);
        }
    }

    protected static function booted(): void
    {
        // However the menu goes (the form, the API), its items go with it.
        static::deleting(fn (Menu $menu) => MenuItem::query()->where('menu_id', $menu->id)->delete());
    }

    /**
     * The items as the form and the API see them: nested, with the link in
     * the shape of LinkField.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getTreeAttribute(): array
    {
        $items = $this->items;
        $labels = $this->linkLabels($items->all());
        $byParent = $items->groupBy(fn (MenuItem $item): int => (int) $item->parent_id);

        $build = function (int $parent, int $depth) use (&$build, $byParent, $labels): array {
            if ($depth > 50) {
                return [];
            }

            return $byParent->get($parent, collect())->map(fn (MenuItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'link' => match (true) {
                    $item->url !== null => ['type' => LinkField::URL, 'url' => $item->url],
                    $item->linkable_type !== null => [
                        'type' => LinkField::RECORD,
                        'model' => $item->linkable_type,
                        'id' => $item->linkable_id,
                        'label' => $labels[$item->linkable_type][$item->linkable_id] ?? '#'.$item->linkable_id,
                    ],
                    default => null,
                },
                'target' => (string) $item->target,
                'icon' => (string) $item->icon,
                'active' => $item->active,
                'source' => (string) $item->source,
                'source_depth' => $item->source_depth,
                'children' => $build($item->id, $depth + 1),
            ])->values()->all();
        };

        return $build(0, 1);
    }

    /**
     * The names of the linked records, one query per model.
     *
     * @param  array<int,MenuItem>  $items
     * @return array<string,array<int,string>>
     */
    private function linkLabels(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            if ($item->linkable_type !== null && is_subclass_of($item->linkable_type, Model::class)) {
                $ids[$item->linkable_type][] = $item->linkable_id;
            }
        }

        $search = app(RecordSearch::class);
        $labels = [];

        foreach ($ids as $model => $keys) {
            foreach ($model::query()->whereKey($keys)->get() as $record) {
                $labels[$model][$record->getKey()] = $search->label($record);
            }
        }

        return $labels;
    }
}
