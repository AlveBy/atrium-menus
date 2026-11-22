<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Menus\Menus;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Models\MenuItem;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\Fixtures\Ghost;
use Atrium\Menus\Tests\Fixtures\Section;
use Atrium\Menus\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * How a menu of the site is found, built, kept and marked for the page shown.
 */
final class ResolveTest extends TestCase
{
    /**
     * Which items are marked for the page shown: the title of every item and
     * "current", "active" (it or an item below it is the page) or "none".
     *
     * @return array<string,array{0:array<int,array<string,mixed>>,1:string,2:array<string,string>}>
     */
    public static function markedItems(): array
    {
        $flat = static fn (array $urls): array => array_map(
            static fn (string $title, string $url): array => ['title' => $title, 'url' => $url, 'children' => []],
            array_keys($urls),
            $urls,
        );

        return [
            'the exact address' => [$flat(['A' => '/a', 'B' => '/b']), 'http://localhost/b', ['A' => 'none', 'B' => 'current']],
            'a page below an item' => [$flat(['A' => '/a']), 'http://localhost/a/x/y', ['A' => 'active']],
            'the longest prefix wins' => [$flat(['A' => '/a', 'B' => '/a/b']), 'http://localhost/a/b/c', ['A' => 'none', 'B' => 'active']],
            'the exact address beats a longer prefix' => [$flat(['A' => '/a/b', 'B' => '/a/b/c']), 'http://localhost/a/b', ['A' => 'current', 'B' => 'none']],
            'a name that merely starts the same' => [$flat(['A' => '/a']), 'http://localhost/ab', ['A' => 'none']],
            'another page' => [$flat(['A' => '/a']), 'http://localhost/b', ['A' => 'none']],
            'the home page for itself' => [$flat(['Home' => '/']), 'http://localhost', ['Home' => 'current']],
            'the home page is not above every page' => [$flat(['Home' => '/']), 'http://localhost/a', ['Home' => 'none']],
            'a slash at the end of the item' => [$flat(['A' => '/a/']), 'http://localhost/a', ['A' => 'current']],
            'a slash at the end of the page' => [$flat(['A' => '/a']), 'http://localhost/a/', ['A' => 'current']],
            'an encoded address' => [$flat(['A' => '/caf%C3%A9']), 'http://localhost/café', ['A' => 'current']],
            'a Cyrillic address' => [$flat(['A' => '/%D0%BA%D0%B0%D1%82%D0%B0%D0%BB%D0%BE%D0%B3']), 'http://localhost/каталог/phones', ['A' => 'active']],
            'the case of the path counts' => [$flat(['A' => '/A']), 'http://localhost/a', ['A' => 'none']],
            'a query in the item' => [$flat(['A' => '/a?utm=1']), 'http://localhost/a', ['A' => 'current']],
            'a query in the page' => [$flat(['A' => '/a']), 'http://localhost/a?page=2', ['A' => 'current']],
            'an absolute address of this site' => [$flat(['A' => 'http://localhost/a']), 'http://localhost/a', ['A' => 'current']],
            'an absolute address in capitals' => [$flat(['A' => 'HTTP://LOCALHOST/a']), 'http://localhost/a', ['A' => 'current']],
            'a secure address of this site' => [$flat(['A' => 'https://localhost/a']), 'http://localhost/a', ['A' => 'current']],
            'an address of another site' => [$flat(['A' => 'https://example.com/a']), 'http://localhost/a', ['A' => 'none']],
            'a site that only ends the same' => [$flat(['A' => 'https://notlocalhost/a']), 'http://localhost/a', ['A' => 'none']],
            'an address without a scheme' => [$flat(['A' => '//localhost/a']), 'http://localhost/a', ['A' => 'none']],
            'a scheme that only ends in http' => [$flat(['A' => 'xhttp://localhost/a']), 'http://localhost/a', ['A' => 'none']],
            'another protocol' => [$flat(['A' => 'ftp://localhost/a']), 'http://localhost/a', ['A' => 'none']],
            'a mail link' => [$flat(['A' => 'mailto:me@localhost']), 'http://localhost/a', ['A' => 'none']],
            'a phone link' => [$flat(['A' => 'tel:+4930123456']), 'http://localhost/a', ['A' => 'none']],
            'an anchor' => [$flat(['A' => '#a']), 'http://localhost/a', ['A' => 'none']],
            'a script' => [$flat(['A' => 'javascript:void(0)']), 'http://localhost/a', ['A' => 'none']],
            'a relative address' => [$flat(['A' => 'a']), 'http://localhost/a', ['A' => 'none']],
            'an empty address' => [$flat(['A' => '']), 'http://localhost/a', ['A' => 'none']],
            'the first of two equal addresses' => [$flat(['A' => '/a', 'B' => '/a']), 'http://localhost/a', ['A' => 'current', 'B' => 'none']],
            'nothing matches among several' => [$flat(['A' => '/a', 'B' => '/b']), 'http://localhost/c', ['A' => 'none', 'B' => 'none']],
            'a heading above the page' => [
                [['title' => 'Help', 'url' => null, 'children' => [['title' => 'FAQ', 'url' => '/help/faq', 'children' => []], ['title' => 'Terms', 'url' => '/help/terms', 'children' => []]]]],
                'http://localhost/help/faq',
                ['Help' => 'active', 'FAQ' => 'current', 'Terms' => 'none'],
            ],
            'an item above a deeper page' => [
                [['title' => 'Shop', 'url' => '/shop', 'children' => [['title' => 'Phones', 'url' => '/shop/phones', 'children' => []]]], ['title' => 'Blog', 'url' => '/blog', 'children' => []]],
                'http://localhost/shop/phones/pixel',
                ['Shop' => 'active', 'Phones' => 'active', 'Blog' => 'none'],
            ],
            'a parent address is the page' => [
                [['title' => 'Shop', 'url' => '/shop', 'children' => [['title' => 'Phones', 'url' => '/shop/phones', 'children' => []]]]],
                'http://localhost/shop',
                ['Shop' => 'current', 'Phones' => 'none'],
            ],
            'a child deeper in a later branch' => [
                [['title' => 'A', 'url' => '/a', 'children' => []], ['title' => 'B', 'url' => null, 'children' => [['title' => 'C', 'url' => '/b/c', 'children' => [['title' => 'D', 'url' => '/b/c/d', 'children' => []]]]]]],
                'http://localhost/b/c/d',
                ['A' => 'none', 'B' => 'active', 'C' => 'active', 'D' => 'current'],
            ],
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $tree
     * @param  array<string,string>  $expected
     */
    #[DataProvider('markedItems')]
    public function test_the_page_shown_is_marked_in_the_menu(array $tree, string $currentUrl, array $expected): void
    {
        $this->menu('header', array_map(fn (array $node): array => $this->node($node), $tree));

        $menu = app(Menus::class)->get('header', 'en', $currentUrl);

        $this->assertSame($expected, $this->states($menu['items'] ?? []));
    }

    public function test_a_menu_is_returned_with_every_field_of_every_item(): void
    {
        $this->menu('header', [
            $this->item('Shop', '/shop', [$this->item('Phones', 'https://example.com/phones', [], ['target' => '_blank', 'icon' => 'icon-phone'])]),
            $this->item('Help'),
        ], name: 'Main menu');

        $this->assertSame([
            'code' => 'header',
            'name' => 'Main menu',
            'items' => [
                [
                    'title' => 'Shop', 'url' => '/shop', 'target' => null, 'icon' => null, 'current' => false, 'active' => false,
                    'children' => [
                        ['title' => 'Phones', 'url' => 'https://example.com/phones', 'target' => '_blank', 'icon' => 'icon-phone', 'current' => false, 'active' => false, 'children' => []],
                    ],
                ],
                ['title' => 'Help', 'url' => null, 'target' => null, 'icon' => null, 'current' => false, 'active' => false, 'children' => []],
            ],
        ], $this->resolve('header', '/elsewhere'));
    }

    public function test_the_address_of_the_request_is_the_page_shown_by_default(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop'), $this->item('Blog', '/blog')]);

        $this->get('/blog?page=2');

        $this->assertSame(['Shop' => 'none', 'Blog' => 'current'], $this->states(app(Menus::class)->get('header', 'en')['items'] ?? []));
    }

    public function test_the_language_of_the_application_is_the_language_of_the_menu_by_default(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')]);
        $this->menu('header', [$this->item('Laden', '/laden')], 'de');

        $this->assertSame(['Shop' => []], $this->titles(app(Menus::class)->get('header')['items'] ?? []));

        $this->app->setLocale('de');

        $this->assertSame(['Laden' => []], $this->titles(app(Menus::class)->get('header')['items'] ?? []));
    }

    public function test_a_menu_for_every_language_serves_a_language_without_one(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')]);
        $this->menu('header', [$this->item('Laden', '/laden')], 'de');

        $this->assertSame(['Shop' => []], $this->titles($this->resolve('header', '/', 'fr')['items'] ?? []));
    }

    public function test_the_language_of_the_menu_wins_whichever_was_made_first(): void
    {
        $this->menu('header', [$this->item('Laden', '/laden')], 'de');
        $this->menu('header', [$this->item('Shop', '/shop')]);

        $this->assertSame(['Laden' => []], $this->titles($this->resolve('header', '/', 'de')['items'] ?? []));
    }

    public function test_a_menu_of_another_language_is_not_shown(): void
    {
        $this->menu('header', [$this->item('Laden', '/laden')], 'de');

        $this->assertNull($this->resolve('header', '/', 'en'));
    }

    public function test_menus_are_told_apart_by_their_code(): void
    {
        $this->menu('header', [$this->item('Top', '/top')]);
        $this->menu('footer', [$this->item('Bottom', '/bottom')]);

        $this->assertSame(['Top' => []], $this->titles($this->resolve('header')['items'] ?? []));
        $this->assertSame(['Bottom' => []], $this->titles($this->resolve('footer')['items'] ?? []));
        $this->assertNull($this->resolve('sidebar'));
    }

    public function test_an_inactive_menu_is_not_shown(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')])->update(['active' => false]);

        $this->assertNull($this->resolve('header'));
    }

    public function test_an_inactive_item_takes_its_children_with_it(): void
    {
        $this->menu('header', [
            $this->item('Off', '/off', [$this->item('Child', '/off/child')], ['active' => false]),
            $this->item('On', '/on'),
        ]);

        $this->assertSame(['On' => []], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_an_item_with_a_link_to_a_record_of_a_missing_class_goes(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop'), $this->item('Ghost')]);
        MenuItem::query()->where('title', 'Ghost')->update(['linkable_type' => 'No\\Such\\Model', 'linkable_id' => 1]);

        $this->assertSame(['Shop' => []], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_a_record_without_an_address_leaves_the_menu_with_its_own_item(): void
    {
        $nowhere = Article::query()->create(['name' => 'Nowhere', 'code' => '']);
        $fine = Article::query()->create(['name' => 'Fine', 'code' => 'fine']);
        $link = fn (Article $article): array => ['link' => ['type' => 'record', 'model' => Article::class, 'id' => $article->id]];

        $this->menu('header', [
            $this->item('Nowhere', null, [$this->item('Child', '/child')], $link($nowhere)),
            $this->item('Fine', null, [], $link($fine)),
            $this->item('Heading'),
        ]);

        $this->assertSame(['Fine' => [], 'Heading' => []], $this->titles($this->resolve('header')['items'] ?? []));
        $this->assertSame('http://localhost/articles/fine', $this->resolve('header')['items'][0]['url'] ?? null);
    }

    public function test_the_address_of_an_item_wins_over_its_record(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $this->menu('header', [$this->item('Delivery', '/custom')]);
        MenuItem::query()->update(['linkable_type' => Article::class, 'linkable_id' => $article->id]);

        $this->assertSame('/custom', $this->resolve('header')['items'][0]['url'] ?? null);
    }

    public function test_the_records_of_an_inactive_item_are_not_even_read(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $this->menu('header', [$this->item('Delivery', null, [], ['active' => false, 'link' => ['type' => 'record', 'model' => Article::class, 'id' => $article->id]])]);

        DB::enableQueryLog();
        $this->assertNull(($this->resolve('header')['items'] ?? [])[0] ?? null);
        $reads = $this->readsOf('menu_test_articles');
        DB::disableQueryLog();

        $this->assertCount(0, $reads);
    }

    public function test_the_linked_records_are_read_once_per_model(): void
    {
        $first = Article::query()->create(['name' => 'First', 'code' => 'first']);
        $second = Article::query()->create(['name' => 'Second', 'code' => 'second']);
        $link = fn (Article $article): array => ['link' => ['type' => 'record', 'model' => Article::class, 'id' => $article->id]];
        $this->menu('header', [
            $this->item('First', null, [], $link($first)),
            $this->item('Again', null, [], $link($first)),
            $this->item('Second', null, [], $link($second)),
        ]);

        DB::enableQueryLog();
        $titles = $this->titles($this->resolve('header')['items'] ?? []);
        $reads = $this->readsOf('menu_test_articles');
        DB::disableQueryLog();

        $this->assertSame(['First' => [], 'Again' => [], 'Second' => []], $titles);
        $this->assertCount(1, $reads);
    }

    public function test_items_deeper_than_the_menu_allows_are_not_drawn(): void
    {
        $menu = Menu::query()->create(['code' => 'header', 'name' => 'Menu', 'active' => true]);
        $parent = null;

        for ($level = 1; $level <= 8; $level++) {
            $parent = MenuItem::query()->create(['menu_id' => $menu->id, 'parent_id' => $parent?->id, 'title' => 'Level '.$level, 'url' => '/l'.$level, 'sort' => 10]);
        }

        $titles = $this->titles($this->resolve('header')['items'] ?? []);
        $depth = 0;

        for ($node = $titles; $node !== []; $node = (array) reset($node)) {
            $depth++;
        }

        $this->assertSame(Menu::MAX_DEPTH + 1, $depth);
        $this->assertSame('Level 1', array_key_first($titles));
    }

    public function test_the_menu_is_built_once_and_kept(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')]);
        $this->resolve('header');

        DB::enableQueryLog();
        $this->resolve('header');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries);
    }

    public function test_a_missing_menu_is_kept_too(): void
    {
        $this->assertNull($this->resolve('header'));

        DB::enableQueryLog();
        $this->assertNull($this->resolve('header'));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame([], $queries);
    }

    public function test_the_kept_menu_lives_an_hour(): void
    {
        $this->travelTo('2026-03-10 12:00:00');
        $this->menu('header', [$this->item('Shop', '/shop')]);
        $this->resolve('header');

        // Changed behind the menu's back: no event, so the cache is all there is.
        DB::table('menu_items')->update(['title' => 'Store']);

        $this->travelTo('2026-03-10 12:59:59');
        $this->assertSame(['Shop' => []], $this->titles($this->resolve('header')['items'] ?? []));

        $this->travelTo('2026-03-10 13:00:00');
        $this->assertSame(['Store' => []], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_the_kept_menu_is_filed_under_the_site_the_language_the_code_and_the_version(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')]);
        $this->resolve('header');

        $version = Cache::get('atrium-menus:version');
        $site = app(Sites::class)->current()->id;

        $this->assertIsString($version);
        $this->assertSame(12, strlen($version));
        $this->assertTrue(Cache::has("atrium-menus:{$site}:en:".md5('header').":{$version}"));
    }

    public function test_the_first_request_starts_the_version_cache(): void
    {
        $this->assertNull(Cache::get('atrium-menus:version'));

        $this->assertNull($this->resolve('nothing'));

        $version = Cache::get('atrium-menus:version');
        $this->assertIsString($version);
        $this->assertSame(12, strlen($version));
        $this->assertSame($version, Cache::get('atrium-menus:version'));
    }

    public function test_the_service_is_one_for_the_whole_request(): void
    {
        $this->assertSame(app(Menus::class), app(Menus::class));
    }

    public function test_links_to_records_of_several_models_all_show(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $section = Section::query()->create(['name' => 'Phones', 'code' => 'phones']);
        $this->menu('header', [
            $this->item('A', null, [], ['link' => ['type' => 'record', 'model' => Article::class, 'id' => $article->id]]),
            $this->item('S', null, [], ['link' => ['type' => 'record', 'model' => Section::class, 'id' => $section->id]]),
        ]);

        $this->assertSame(['A' => [], 'S' => []], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_flushing_starts_a_new_version(): void
    {
        $this->resolve('header');
        $before = Cache::get('atrium-menus:version');

        app(Menus::class)->flush();
        $after = Cache::get('atrium-menus:version');

        $this->assertNotSame($before, $after);
        $this->assertSame(12, strlen((string) $after));
    }

    public function test_the_application_language_comes_back_after_the_menu_is_built(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')]);

        $this->resolve('header', '/', 'de');

        $this->assertSame('en', $this->app->getLocale());
    }

    public function test_the_application_language_comes_back_when_building_fails(): void
    {
        config()->set('atrium.public_urls.'.Ghost::class, ['prefix' => 'ghosts']);
        $this->menu('header', [$this->item('Shop', '/shop', [], ['source' => Ghost::class])]);

        try {
            // In a savepoint: on PostgreSQL the failed query would abort the
            // test's transaction, and the rollback of the migrations after it.
            DB::transaction(fn () => $this->resolve('header', '/', 'de'));
            $this->fail('The menu could not be built without the table.');
        } catch (QueryException) {
            $this->assertSame('en', $this->app->getLocale());
        }
    }

    public function test_a_menu_of_another_site_never_shows(): void
    {
        $sites = app(Sites::class);
        $main = $sites->default();
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $sites->flush();

        $sites->setCurrent($main);
        $this->menu('header', [$this->item('Main shop', '/shop')]);
        $this->menu('footer', [$this->item('Main imprint', '/imprint')]);

        $sites->setCurrent($second);
        $this->menu('header', [$this->item('German shop', '/laden')]);

        // Each site has its own, also from the cache of the other.
        $sites->setCurrent($main);
        $this->assertSame(['Main shop' => []], $this->titles($this->resolve('header')['items'] ?? []));
        $this->assertSame(['Main imprint' => []], $this->titles($this->resolve('footer')['items'] ?? []));

        $sites->setCurrent($second);
        $this->assertSame(['German shop' => []], $this->titles($this->resolve('header')['items'] ?? []));
        $this->assertNull($this->resolve('footer'), 'the footer of the main site is not the German one');

        $sites->setCurrent($main);
        $this->assertSame(['Main shop' => []], $this->titles($this->resolve('header')['items'] ?? []));
    }

    /**
     * The queries that read the rows of a table, from the log.
     *
     * @return array<int,string>
     */
    private function readsOf(string $table): array
    {
        return array_values(array_filter(
            array_column(DB::getQueryLog(), 'query'),
            static fn (string $sql): bool => preg_match('/from\W+'.$table.'\W/i', $sql) === 1,
        ));
    }

    /**
     * @param  array<string,mixed>  $node
     * @return array<string,mixed>
     */
    private function node(array $node): array
    {
        return $this->item($node['title'], $node['url'], array_map(fn (array $child): array => $this->node($child), $node['children']));
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @return array<string,string>
     */
    private function states(array $items): array
    {
        $states = [];

        foreach ($items as $item) {
            $states[$item['title']] = $item['current'] ? 'current' : ($item['active'] ? 'active' : 'none');
            $states = [...$states, ...$this->states($item['children'])];
        }

        return $states;
    }
}
