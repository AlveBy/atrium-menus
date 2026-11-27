<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Builder\BlockTypes;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;
use InvalidArgumentException;

/**
 * Required with Composer but not installed with atrium:module:install: no
 * menus in the panel, and the theme keeps its own menu.
 */
final class NotInstalledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('atrium.modules', []);
    }

    public function test_the_module_stays_out_of_the_panel_and_the_theme(): void
    {
        Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);

        $this->actingAs($this->admin(), 'admin')->get('/admin/menus')->assertNotFound();
        // Other modules get no "Add to header menu" button either.
        $this->get('/admin/articles')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('actions', []));
        $this->assertNull(config('atrium-theme.header_menu'));
        $this->get('/articles')->assertOk()->assertSee('<nav class="menu" aria-label="Main menu">', false);
    }

    public function test_the_component_is_not_registered(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')]);

        // A markup of its own: a compiled copy from another test would hide
        // that the tag is unknown.
        $this->expectException(InvalidArgumentException::class);

        Blade::render('<x-atrium-menu code="header" data-probe="not-installed" />');
    }

    public function test_saving_records_does_not_touch_the_menu_cache(): void
    {
        Cache::put('atrium-menus:version', 'before', 60);

        Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $this->menu('header', [$this->item('Shop', '/shop')]);

        $this->assertSame('before', Cache::get('atrium-menus:version'));
    }

    public function test_the_site_builder_does_not_offer_its_block(): void
    {
        $types = $this->app->make(BlockTypes::class);

        $this->assertNull($types->get('menu'));
        $this->assertArrayNotHasKey('menu', $types->all());
    }
}
