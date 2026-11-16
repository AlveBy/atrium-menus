<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Modules\Module;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Support\Sites;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Models\MenuItem;
use Atrium\Menus\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The demo menus of `atrium:install --demo`.
 */
final class DemoTest extends TestCase
{
    public function test_the_demo_adds_header_and_footer_menus_once(): void
    {
        $modules = app(Modules::class);
        $module = $modules->find('alveby/atrium-menus');

        $this->assertNotNull($module);
        $this->assertTrue($modules->seedDemo($module));
        $this->assertSame(6, Menu::query()->count());
        $this->assertSame(['footer', 'header'], Menu::query()->whereNull('language')->orderBy('code')->pluck('code')->all());

        // None of the demo modules is installed here: the footer has only
        // the home page, the header nothing.
        $this->assertSame(['Home', 'Startseite', 'Главная'], MenuItem::query()->orderBy('id')->pluck('title')->all());

        $this->assertFalse($modules->seedDemo($module));
        $this->assertSame(6, Menu::query()->count());

        $this->get('/')->assertOk()->assertSee('data-menu="footer"', false)->assertSee('>Home</a>', false);
    }

    /**
     * @return array<string,array{0:string,1:?string,2:array<int,array<string,mixed>>}>
     */
    public static function demoMenus(): array
    {
        $item = fn (string $title, string $url, ?string $source = null, ?int $depth = null): array => [
            'title' => $title, 'url' => $url, 'source' => $source, 'source_depth' => $depth ?? 1,
        ];
        $catalog = 'Atrium\\Catalog\\Models\\CatalogSection';
        $help = 'Atrium\\Faq\\Models\\Category';

        return [
            'header for every language' => ['header', null, [
                $item('Catalog', '/catalog', $catalog, 2),
                $item('Help', '/help', $help, 1),
            ]],
            'header in German' => ['header', 'de', [
                $item('Katalog', '/de/catalog', $catalog, 2),
                $item('Hilfe', '/de/help', $help, 1),
            ]],
            'header in Russian' => ['header', 'ru', [
                $item('Каталог', '/ru/catalog', $catalog, 2),
                $item('Помощь', '/ru/help', $help, 1),
            ]],
            'footer for every language' => ['footer', null, [
                $item('Home', '/'),
                $item('Catalog', '/catalog'),
                $item('Help', '/help'),
            ]],
            'footer in German' => ['footer', 'de', [
                $item('Startseite', '/de/'),
                $item('Katalog', '/de/catalog'),
                $item('Hilfe', '/de/help'),
            ]],
            'footer in Russian' => ['footer', 'ru', [
                $item('Главная', '/ru/'),
                $item('Каталог', '/ru/catalog'),
                $item('Помощь', '/ru/help'),
            ]],
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $expected
     */
    #[DataProvider('demoMenus')]
    public function test_the_demo_links_the_modules_that_are_installed(string $code, ?string $language, array $expected): void
    {
        $this->withInstalledDemoModules();

        $this->assertTrue(app(Modules::class)->seedDemo($this->menusModule()));

        $menu = Menu::query()->where('code', $code)->when($language === null, fn ($query) => $query->whereNull('language'), fn ($query) => $query->where('language', $language))->sole();
        $items = $menu->items()->orderBy('sort')->orderBy('id')->get();

        $this->assertTrue($menu->active);
        $this->assertSame($expected, $items->map(fn (MenuItem $item): array => [
            'title' => $item->title, 'url' => $item->url, 'source' => $item->source, 'source_depth' => $item->source_depth,
        ])->all());
        $this->assertSame([true], $items->pluck('active')->unique()->values()->all());
        $this->assertSame([null], $items->pluck('parent_id')->unique()->values()->all());
    }

    public function test_the_demo_menus_have_names_in_their_language(): void
    {
        $this->withInstalledDemoModules();
        app(Modules::class)->seedDemo($this->menusModule());

        $names = Menu::query()->orderBy('id')->get()->map(fn (Menu $menu): string => ($menu->language ?? '-').':'.$menu->code.':'.$menu->name)->all();

        $this->assertEqualsCanonicalizing([
            '-:header:Main menu', '-:footer:Footer',
            'de:header:Hauptmenü', 'de:footer:Fußzeile',
            'ru:header:Главное меню', 'ru:footer:Подвал',
        ], $names);
    }

    /**
     * @return array<string,array{0:string,1:array<int,string>,2:array<int,string>}>
     */
    public static function installerLanguages(): array
    {
        return [
            'German' => ['de', [
                '-:header:Hauptmenü', '-:footer:Fußzeile',
                'en:header:Main menu', 'en:footer:Footer',
                'ru:header:Главное меню', 'ru:footer:Подвал',
            ], ['Startseite', 'Katalog', 'Hilfe']],
            'Japanese' => ['ja', [
                '-:header:メインメニュー', '-:footer:フッター',
                'en:header:Main menu', 'en:footer:Footer',
                'de:header:Hauptmenü', 'de:footer:Fußzeile',
                'ru:header:Главное меню', 'ru:footer:Подвал',
            ], ['ホーム', 'カタログ', 'ヘルプ']],
            'Russian' => ['ru', [
                '-:header:Главное меню', '-:footer:Подвал',
                'en:header:Main menu', 'en:footer:Footer',
                'de:header:Hauptmenü', 'de:footer:Fußzeile',
            ], ['Главная', 'Каталог', 'Помощь']],
            'Brazilian Portuguese' => ['pt_BR', [
                '-:header:Menu principal', '-:footer:Rodapé',
                'en:header:Main menu', 'en:footer:Footer',
                'de:header:Hauptmenü', 'de:footer:Fußzeile',
                'ru:header:Главное меню', 'ru:footer:Подвал',
            ], ['Início', 'Catálogo', 'Ajuda']],
            'a language with no demo of its own' => ['xx', [
                '-:header:Main menu', '-:footer:Footer',
                'en:header:Main menu', 'en:footer:Footer',
                'de:header:Hauptmenü', 'de:footer:Fußzeile',
                'ru:header:Главное меню', 'ru:footer:Подвал',
            ], ['Home', 'Catalog', 'Help']],
        ];
    }

    /**
     * @param  array<int,string>  $menus
     * @param  array<int,string>  $footer
     */
    #[DataProvider('installerLanguages')]
    public function test_the_menus_for_every_language_are_in_the_installers_language(string $locale, array $menus, array $footer): void
    {
        $this->withInstalledDemoModules();
        app()->setLocale($locale);

        $this->assertTrue(app(Modules::class)->seedDemo($this->menusModule()));

        $names = Menu::query()->orderBy('id')->get()->map(fn (Menu $menu): string => ($menu->language ?? '-').':'.$menu->code.':'.$menu->name)->all();
        $this->assertSame($menus, $names);

        $menu = Menu::query()->whereNull('language')->where('code', 'footer')->sole();
        $this->assertSame($footer, $menu->items()->orderBy('sort')->pluck('title')->all());
        $this->assertSame(['/', '/catalog', '/help'], $menu->items()->orderBy('sort')->pluck('url')->all());
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function demoLanguages(): array
    {
        $languages = [];

        foreach (['en', 'ru', 'uk', 'be', 'kk', 'uz', 'de', 'fr', 'es', 'pt_BR', 'it', 'pl', 'tr', 'zh_CN', 'ja'] as $locale) {
            $languages[$locale] = [$locale];
        }

        return $languages;
    }

    #[DataProvider('demoLanguages')]
    public function test_every_language_has_every_title_of_the_demo(string $locale): void
    {
        $root = dirname(__DIR__, 2).'/resources/demo';
        $english = require $root.'/en/menus.php';
        $texts = require $root."/{$locale}/menus.php";

        $this->assertSame(array_keys($english), array_keys($texts));

        foreach ($texts as $text) {
            $this->assertIsString($text);
            $this->assertNotSame('', trim($text));
        }
    }

    public function test_the_demo_is_written_in_the_fifteen_languages_of_the_panel(): void
    {
        $this->assertCount(15, glob(dirname(__DIR__, 2).'/resources/demo/*/menus.php') ?: []);
    }

    public function test_the_demo_menus_belong_to_the_default_site(): void
    {
        $this->withInstalledDemoModules();
        app(Modules::class)->seedDemo($this->menusModule());

        $this->assertSame([app(Sites::class)->default()->id], Menu::query()->withoutGlobalScopes()->pluck('site_id')->unique()->values()->all());
    }

    public function test_a_module_with_a_rule_but_not_installed_is_left_out(): void
    {
        $this->withInstalledDemoModules(except: 'alveby/atrium-faq');

        app(Modules::class)->seedDemo($this->menusModule());

        $this->assertNotContains('Help', MenuItem::query()->pluck('title')->all());
        $this->assertContains('Catalog', MenuItem::query()->pluck('title')->all());
    }

    public function test_a_module_installed_but_without_a_public_address_is_left_out(): void
    {
        $this->withInstalledDemoModules();
        config()->set('atrium.public_urls', array_diff_key((array) config('atrium.public_urls'), ['Atrium\\Faq\\Models\\Category' => 1]));
        PublicUrl::forgetPaths();

        app(Modules::class)->seedDemo($this->menusModule());

        $this->assertNotContains('Help', MenuItem::query()->pluck('title')->all());
    }

    public function test_the_demo_does_not_run_over_menus_of_another_site(): void
    {
        $other = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        Menu::query()->create(['site_id' => $other->id, 'code' => 'header', 'name' => 'Theirs', 'active' => true]);

        $this->assertFalse(app(Modules::class)->seedDemo($this->menusModule()));
        $this->assertSame(['Theirs'], Menu::query()->withoutGlobalScopes()->pluck('name')->all());
    }

    public function test_the_demo_menus_go_to_the_default_site_whichever_site_is_current(): void
    {
        $this->withInstalledDemoModules();
        $other = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $sites = app(Sites::class);
        $sites->flush();
        $default = $sites->default();
        $sites->setCurrent($other);

        app(Modules::class)->seedDemo($this->menusModule());

        $this->assertSame([$default->id], Menu::query()->withoutGlobalScopes()->pluck('site_id')->unique()->values()->all());
    }

    public function test_a_module_with_a_public_address_but_no_sections_gets_a_plain_link(): void
    {
        $this->withInstalledDemoModules();
        config()->set('atrium.public_urls', [
            ...(array) config('atrium.public_urls'),
            'Atrium\\News\\Models\\News' => ['prefix' => 'news'],
            'Atrium\\Stores\\Models\\Store' => ['prefix' => 'stores'],
        ]);
        PublicUrl::forgetPaths();

        app(Modules::class)->seedDemo($this->menusModule());

        foreach (['header', 'footer'] as $code) {
            $items = Menu::query()->where('code', $code)->whereNull('language')->sole()->items()->orderBy('sort')->get();
            $plain = $items->whereIn('title', ['News', 'Stores']);

            $this->assertSame(['News', 'Stores'], $plain->pluck('title')->all(), $code);
            $this->assertSame(['/news', '/stores'], $plain->pluck('url')->all(), $code);
            $this->assertSame([null], $plain->pluck('source')->unique()->values()->all(), $code);
            $this->assertSame([1], $plain->pluck('source_depth')->unique()->values()->all(), $code);
        }
    }

    private function menusModule(): Module
    {
        $module = app(Modules::class)->find('alveby/atrium-menus');
        $this->assertNotNull($module);

        return $module;
    }

    private function withInstalledDemoModules(?string $except = null): void
    {
        foreach (['Atrium\\Catalog\\Models\\CatalogSection', 'Atrium\\Faq\\Models\\Category', 'Atrium\\News\\Models\\News', 'Atrium\\Stores\\Models\\Store'] as $class) {
            if (! class_exists($class)) {
                $this->markTestSkipped("$class is not installed with this package.");
            }
        }

        config()->set('atrium.modules', array_values(array_diff(
            ['alveby/atrium-menus', 'alveby/atrium-catalog', 'alveby/atrium-faq', 'alveby/atrium-news', 'alveby/atrium-stores'],
            [$except],
        )));
        config()->set('atrium.public_urls', [
            'Atrium\\Catalog\\Models\\CatalogSection' => ['prefix' => 'catalog', 'tree' => 'Atrium\\Catalog\\Models\\CatalogSection', 'parent' => 'parent_id'],
            'Atrium\\Faq\\Models\\Category' => ['prefix' => 'help'],
            'Atrium\\News\\Models\\News' => ['prefix' => ''],
        ]);
        PublicUrl::forgetPaths();
    }
}
