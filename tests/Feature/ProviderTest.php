<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Language;
use Atrium\Menus\Menus;
use Atrium\Menus\MenusServiceProvider;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Models\MenuItem;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\Fixtures\Event;
use Atrium\Menus\Tests\Fixtures\Page;
use Atrium\Menus\Tests\Fixtures\Section;
use Atrium\Menus\Tests\TestCase;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use stdClass;

/**
 * What the service provider wires: publishing, translations, views, the
 * theme's menus and the cache that follows the data.
 */
final class ProviderTest extends TestCase
{
    private const VERSION = 'atrium-menus:version';

    public function test_the_views_and_the_translations_can_be_published(): void
    {
        $views = ServiceProvider::pathsToPublish(MenusServiceProvider::class, 'atrium-menus-views');
        $lang = ServiceProvider::pathsToPublish(MenusServiceProvider::class, 'atrium-menus-lang');

        $this->assertSame([resource_path('views/vendor/atrium-menus')], array_values($views));
        $this->assertSame([$this->app->langPath('vendor/atrium-menus')], array_values($lang));
        $this->assertDirectoryExists(array_key_first($views));
        $this->assertFileExists(array_key_first($views).'/menu.blade.php');
        $this->assertDirectoryExists(array_key_first($lang));
        $this->assertFileExists(array_key_first($lang).'/en/menus.php');
    }

    public function test_nothing_is_published_outside_the_console(): void
    {
        $groups = new ReflectionProperty(ServiceProvider::class, 'publishGroups');
        $paths = new ReflectionProperty(ServiceProvider::class, 'publishes');
        $console = new ReflectionProperty($this->app, 'isRunningInConsole');
        $before = [$groups->getValue(), $paths->getValue(), $console->getValue($this->app)];

        try {
            $groups->setValue(null, []);
            $paths->setValue(null, []);
            $console->setValue($this->app, false);

            (new MenusServiceProvider($this->app))->boot();

            $this->assertSame([], ServiceProvider::pathsToPublish(MenusServiceProvider::class, 'atrium-menus-views'));
            $this->assertSame([], ServiceProvider::pathsToPublish(MenusServiceProvider::class, 'atrium-menus-lang'));
        } finally {
            $groups->setValue(null, $before[0]);
            $paths->setValue(null, $before[1]);
            $console->setValue($this->app, $before[2]);
        }
    }

    public function test_the_translations_and_the_views_are_loaded(): void
    {
        $this->assertTrue(view()->exists('atrium-menus::menu'));
        $this->assertTrue(view()->exists('atrium-menus::partials.items'));
        $this->assertTrue(view()->exists('atrium-menus::theme.header'));
        $this->assertTrue(view()->exists('atrium-menus::theme.footer'));
        $this->assertNotSame('atrium-menus::menus.code_taken', __('atrium-menus::menus.code_taken'));
        $this->assertNotSame('atrium-menus::menus.fields.language', __('atrium-menus::menus.fields.language'));
    }

    public function test_the_component_is_registered_under_its_tag(): void
    {
        $this->assertSame(\Atrium\Menus\View\Components\Menu::class, Blade::getClassComponentAliases()['atrium-menu'] ?? null);
    }

    public function test_the_service_is_shared(): void
    {
        $this->assertTrue($this->app->isShared(Menus::class));
    }

    public function test_the_theme_takes_its_header_and_footer_menus_from_the_module(): void
    {
        $this->assertSame('atrium-menus::theme.header', config('atrium-theme.header_menu'));
        $this->assertSame('atrium-menus::theme.footer', config('atrium-theme.footer_menu'));
    }

    #[WithConfig('atrium-theme.header_menu', 'my-app::header')]
    public function test_a_header_the_app_chose_is_kept(): void
    {
        $this->assertSame('my-app::header', config('atrium-theme.header_menu'));
        $this->assertSame('atrium-menus::theme.footer', config('atrium-theme.footer_menu'));
    }

    #[WithConfig('atrium-theme.footer_menu', 'my-app::footer')]
    public function test_a_footer_the_app_chose_is_kept(): void
    {
        $this->assertSame('atrium-menus::theme.header', config('atrium-theme.header_menu'));
        $this->assertSame('my-app::footer', config('atrium-theme.footer_menu'));
    }

    /**
     * @return array<string,array{0:Closure(Model):mixed}>
     */
    public static function changes(): array
    {
        return [
            'save' => [fn (Model $model) => $model->forceFill(['updated_at' => now()->addMinute()])->save()],
            'delete' => [fn (Model $model) => $model->delete()],
        ];
    }

    /**
     * @return array<string,array{0:Closure(self):Model,1:Closure(Model):mixed}>
     */
    public static function recordsThatConcernMenus(): array
    {
        $makers = [
            'a menu' => fn (self $test): Model => $test->menu('footer', [])->refresh(),
            'a menu item' => fn (self $test): Model => $test->menu('footer', [$test->item('Shop', '/shop')])->items()->firstOrFail(),
            'a record with a public address' => fn (): Model => Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']),
            'a section with a public address' => fn (): Model => Section::query()->create(['name' => 'Phones', 'code' => 'phones']),
            'a record that lists itself in the sitemap' => fn (): Model => Page::query()->create(['name' => 'About', 'code' => 'about']),
            'an event' => fn (): Model => Event::query()->create(['name' => 'Fair', 'code' => 'fair']),
        ];

        $cases = [];

        foreach ($makers as $label => $make) {
            foreach (self::changes() as $change => [$run]) {
                $cases["$change of $label"] = [$make, $run];
            }
        }

        return $cases;
    }

    /**
     * @param  Closure(self):Model  $make
     * @param  Closure(Model):mixed  $change
     */
    #[DataProvider('recordsThatConcernMenus')]
    public function test_a_change_to_what_a_menu_shows_drops_the_cached_menus(Closure $make, Closure $change): void
    {
        $record = $make($this);
        $this->resolve('footer');
        $before = Cache::get(self::VERSION);

        $change($record);

        $this->assertNotNull($before);
        $this->assertNotSame($before, Cache::get(self::VERSION));
    }

    /**
     * @return array<string,array{0:Closure(self):Model}>
     */
    public static function recordsThatDoNot(): array
    {
        return [
            'an administrator' => [fn (): Model => AdminUser::query()->create(['name' => 'Editor', 'email' => 'editor@example.com', 'password' => 'secret-password', 'is_active' => true])],
            'a language' => [fn (): Model => Language::query()->create(['code' => 'de', 'name' => 'Deutsch'])],
        ];
    }

    /**
     * @param  Closure(self):Model  $make
     */
    #[DataProvider('recordsThatDoNot')]
    public function test_a_change_to_anything_else_keeps_the_cached_menus(Closure $make): void
    {
        $this->menu('footer', [$this->item('Shop', '/shop')]);
        $this->resolve('footer');
        $before = Cache::get(self::VERSION);

        $record = $make($this);
        $record->forceFill(['updated_at' => now()->addMinute()])->save();
        $record->delete();

        $this->assertNotNull($before);
        $this->assertSame($before, Cache::get(self::VERSION));
    }

    /**
     * @return array<string,array{0:string,1:array<int,mixed>}>
     */
    public static function strangePayloads(): array
    {
        return [
            'an empty payload' => ['saved', []],
            'a string' => ['saved', ['Menu']],
            'null' => ['saved', [null]],
            'an object that is not a model' => ['saved', [new stdClass]],
            'a deleted event with a string' => ['deleted', ['Menu']],
            'a deleted event with no payload' => ['deleted', []],
        ];
    }

    /**
     * @param  array<int,mixed>  $payload
     */
    #[DataProvider('strangePayloads')]
    public function test_an_event_that_carries_no_model_is_ignored(string $event, array $payload): void
    {
        $this->menu('footer', [$this->item('Shop', '/shop')]);
        $this->resolve('footer');
        $before = Cache::get(self::VERSION);

        $this->app['events']->dispatch("eloquent.$event: Whatever", $payload);

        $this->assertNotNull($before);
        $this->assertSame($before, Cache::get(self::VERSION));
    }

    public function test_the_cache_follows_a_rename_made_in_the_panel(): void
    {
        $menu = $this->menu('footer', [$this->item('Shop', '/shop')]);
        $this->assertSame(['Shop' => []], $this->titles($this->resolve('footer')['items'] ?? []));

        $menu->items()->firstOrFail()->update(['title' => 'Store']);

        $this->assertSame(['Store' => []], $this->titles($this->resolve('footer')['items'] ?? []));
    }

    /**
     * @return array<string,array{0:Closure(self):Model,1:bool}>
     */
    public static function concerned(): array
    {
        return [
            'a menu' => [fn (): Model => new Menu, true],
            'a menu item' => [fn (): Model => new MenuItem, true],
            'a model with a public address' => [fn (): Model => new Article, true],
            'a tree with a public address' => [fn (): Model => new Section, true],
            'a model with a sitemap address' => [fn (): Model => new Page, true],
            'a model without pages' => [fn (): Model => new AdminUser, false],
            'a language' => [fn (): Model => new Language, false],
        ];
    }

    /**
     * @param  Closure(self):Model  $make
     */
    #[DataProvider('concerned')]
    public function test_it_knows_which_records_may_show_in_a_menu(Closure $make, bool $expected): void
    {
        $this->assertSame($expected, $this->app->make(Menus::class)->concerns($make($this)));
    }
}
