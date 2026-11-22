<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus;

use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Modules\Modules;
use Atrium\Admin\Support\RecordUrls;
use Atrium\Menus\Actions\AddToHeaderMenu;
use Atrium\Menus\View\Components\Menu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class MenusServiceProvider extends ServiceProvider
{
    public const MODULE = 'alveby/atrium-menus';

    /**
     * The model and the migrations come from extra.atrium in composer.json,
     * once `atrium:module:install alveby/atrium-menus` has run.
     */
    public function register(): void
    {
        $this->app->singleton(Menus::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'atrium-menus');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'atrium-menus');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/atrium-menus'),
            ], 'atrium-menus-views');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/atrium-menus'),
            ], 'atrium-menus-lang');
        }

        // Required but not installed: no tables, so no menus to draw.
        if (! $this->app->make(Modules::class)->isInstalled(self::MODULE)) {
            return;
        }

        Blade::component('atrium-menu', Menu::class);

        // The theme draws the header and footer menus from here; an app that
        // set its own views keeps them.
        if (config('atrium-theme.header_menu') === null) {
            config()->set('atrium-theme.header_menu', 'atrium-menus::theme.header');
        }

        if (config('atrium-theme.footer_menu') === null) {
            config()->set('atrium-theme.footer_menu', 'atrium-menus::theme.footer');
        }

        // Any record with a page on the site can go to the header menu from
        // its own list and form. The button needs the right to edit menus.
        Hook::filter('atrium.module.actions', function (array $actions, string $model): array {
            if (! $this->app->make(RecordUrls::class)->hasPages($model)) {
                return $actions;
            }

            return [...$actions, AddToHeaderMenu::make('add-to-header-menu')
                ->label(__('atrium-menus::menus.actions.add_to_header'))
                ->permission('menus.update')];
        });

        $flush = function (string $event, array $payload): void {
            $record = $payload[0] ?? null;
            $menus = $this->app->make(Menus::class);

            if ($record instanceof Model && $menus->concerns($record)) {
                $menus->flush();
            }
        };

        Event::listen('eloquent.saved: *', $flush);
        Event::listen('eloquent.deleted: *', $flush);
    }
}
