<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\Fixtures\Section;
use Atrium\Menus\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

/**
 * What the site shows: <x-atrium-menu>, atrium_menu() and the theme's
 * header and footer.
 */
final class RenderTest extends TestCase
{
    public function test_the_component_draws_nested_lists_with_the_current_page_marked(): void
    {
        $phones = Section::query()->create(['name' => 'Phones', 'code' => 'phones']);
        $this->menu('header', [
            $this->item('Shop', '/shop', [
                $this->item('Phones', null, [], ['link' => ['type' => 'record', 'model' => Section::class, 'id' => $phones->id]]),
            ]),
            $this->item('Blog', 'https://blog.example.com', [], ['target' => '_blank', 'icon' => 'icon-rss']),
            $this->item('Hidden', '/hidden', [], ['active' => false]),
            $this->item('Help'),
        ], name: 'Main menu');

        $this->get('/shop/phones');
        $html = Blade::render('<x-atrium-menu code="header" />');

        $this->assertStringContainsString('<nav class="menu" aria-label="Main menu" data-menu="header">', $html);
        $this->assertMatchesRegularExpression('#<li\s+class="is-active has-children"\s*>\s*<a href="/shop"\s*>Shop</a>#', $html);
        $this->assertMatchesRegularExpression('#<li\s+class="is-active"\s*>\s*<a href="http://localhost/shop/phones"\s+aria-current="page">Phones</a>#', $html);
        $this->assertStringContainsString('target="_blank" rel="noopener"', $html);
        $this->assertStringContainsString('<span class="menu-icon icon-rss" aria-hidden="true"></span> Blog', $html);
        $this->assertStringContainsString('<span>Help</span>', $html);
        $this->assertStringNotContainsString('Hidden', $html);

        $this->assertSame('', trim(Blade::render('<x-atrium-menu code="nope" />')), 'no menu, nothing drawn');
        $this->assertNull(atrium_menu('nope'));
        $this->assertSame('Main menu', atrium_menu('header')['name'] ?? null);
    }

    public function test_the_deepest_matching_item_is_active_when_no_address_is_exact(): void
    {
        $this->menu('header', [
            $this->item('Home', '/'),
            $this->item('Shop', '/shop', [$this->item('Phones', '/shop/phones')]),
            $this->item('Top', '#top'),
        ]);

        $menu = $this->resolve('header', '/shop/phones/super-phone');
        [$home, $shop, $top] = $menu['items'] ?? [];

        $this->assertFalse($home['active'], 'the home page matches only itself');
        $this->assertFalse($top['active']);
        $this->assertTrue($shop['active']);
        $this->assertFalse($shop['current']);
        $this->assertTrue($shop['children'][0]['active']);
        $this->assertFalse($shop['children'][0]['current'], 'a page below the item: active, not current');

        $this->assertTrue($this->resolve('header', '/')['items'][0]['current'] ?? false);
        $this->assertFalse($this->resolve('header', '/about')['items'][1]['active'] ?? true);
    }

    public function test_links_to_hidden_or_deleted_records_disappear(): void
    {
        $off = Article::query()->create(['name' => 'Old', 'code' => 'old', 'active' => false]);
        $gone = Article::query()->create(['name' => 'Gone', 'code' => 'gone']);
        $ok = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $link = fn (Article $article): array => ['link' => ['type' => 'record', 'model' => Article::class, 'id' => $article->id]];

        $this->menu('footer', [
            $this->item('Old', null, [], $link($off)),
            $this->item('Gone', null, [], $link($gone)),
            $this->item('Delivery', null, [], $link($ok)),
        ]);
        $gone->delete();

        $this->assertSame(['Delivery' => []], $this->titles($this->resolve('footer')['items'] ?? []));
    }

    public function test_a_menu_in_the_language_wins_over_one_for_all(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch']);
        $this->menu('header', [$this->item('Shop', '/shop')]);
        $this->menu('header', [$this->item('Laden', '/de/shop')], 'de');
        Menu::query()->create(['code' => 'header', 'name' => 'Off', 'language' => 'ru', 'active' => false]);

        $this->assertSame(['Shop' => []], $this->titles($this->resolve('header')['items'] ?? []));
        $this->assertSame(['Laden' => []], $this->titles($this->resolve('header', '/de', 'de')['items'] ?? []));
        $this->assertSame(['Shop' => []], $this->titles($this->resolve('header', '/ru', 'ru')['items'] ?? []), 'an inactive menu falls back');
    }

    public function test_the_theme_draws_the_header_and_footer_menus(): void
    {
        Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);

        // No menu yet: the theme's own links to the modules.
        $this->get('/articles')
            ->assertOk()
            ->assertSee('<nav class="menu" aria-label="Main menu">', false)
            ->assertSee('aria-current="page" >Articles</a>', false);

        $this->menu('header', [$this->item('Read', '/articles'), $this->item('Contacts', '/contacts')], name: 'Header');
        $this->menu('footer', [$this->item('Privacy', '/privacy')], name: 'Footer');

        $this->get('/articles/delivery')
            ->assertOk()
            ->assertSee('data-menu="header"', false)
            ->assertSeeInOrder(['<header', 'Read', 'Contacts', '</header>', '<footer', 'Privacy', '</footer>'], false)
            ->assertDontSee('>Sections</a>', false);
    }
}
