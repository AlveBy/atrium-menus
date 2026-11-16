<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests;

use Atrium\Admin\AtriumServiceProvider;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Menus\Menus;
use Atrium\Menus\MenusServiceProvider;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\Fixtures\Event;
use Atrium\Menus\Tests\Fixtures\Section;
use Atrium\Theme\ThemeServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Tighten\Ziggy\ZiggyServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            ZiggyServiceProvider::class,
            AtriumServiceProvider::class,
            ThemeServiceProvider::class,
            MenusServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];
        $config->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $config->set('app.name', 'Shop');
        $target = env('DB_CONNECTION');
        $config->set('database.default', in_array($target, ['mysql', 'pgsql'], true) ? $target : 'testing');
        $config->set('cache.default', 'array');
        $config->set('atrium.demo', false);
        $config->set('atrium.modules', ['alveby/atrium-menus']);
        $config->set('atrium.resources', [Section::class, Article::class]);
        $config->set('atrium.public_urls', [
            Section::class => ['prefix' => 'shop', 'tree' => Section::class, 'parent' => 'parent_id'],
            Article::class => ['prefix' => 'articles'],
            Event::class => ['prefix' => 'events'],
        ]);
        $config->set('atrium.auth.two_factor.enabled', false);
        $config->set('atrium.dashboard.timings.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        PublicUrl::forgetPaths();
    }

    /**
     * On MySQL and PostgreSQL the whole suite shares one database: the menu
     * tables and the test modules' tables come with these.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function admin(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ]);

        $admin->forceFill(['is_superadmin' => true])->save();

        return $admin;
    }

    /**
     * A menu with its items, written the way the form writes them.
     *
     * @param  array<int,array<string,mixed>>  $tree
     */
    protected function menu(string $code, array $tree, ?string $language = null, string $name = 'Menu'): Menu
    {
        $menu = Menu::query()->create(['code' => $code, 'name' => $name, 'language' => $language, 'active' => true]);
        $menu->afterAdminSave(['tree' => $tree]);

        return $menu;
    }

    /**
     * @param  array<int,array<string,mixed>>  $children
     * @param  array<string,mixed>  $extra
     * @return array<string,mixed>
     */
    protected function item(string $title, ?string $url = null, array $children = [], array $extra = []): array
    {
        return [
            'title' => $title,
            'link' => $url === null ? null : ['type' => 'url', 'url' => $url],
            'children' => $children,
            ...$extra,
        ];
    }

    /**
     * @return array{code:string,name:string,items:array<int,array<string,mixed>>}|null
     */
    protected function resolve(string $code, string $currentUrl = '/', string $language = 'en'): ?array
    {
        return $this->app->make(Menus::class)->get($code, $language, 'http://localhost'.$currentUrl);
    }

    /**
     * Titles of a resolved tree, nested: ['Shop' => ['Phones' => []]].
     *
     * @param  array<int,array<string,mixed>>  $items
     * @return array<string,mixed>
     */
    protected function titles(array $items): array
    {
        $titles = [];

        foreach ($items as $item) {
            $titles[$item['title']] = $this->titles($item['children']);
        }

        return $titles;
    }
}
