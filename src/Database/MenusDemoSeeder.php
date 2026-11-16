<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Database;

use Atrium\Admin\Modules\DemoSeeder;
use Atrium\Admin\Modules\Soft;
use Atrium\Admin\Support\PublicUrl;
use Atrium\Admin\Support\Sites;
use Atrium\Menus\Models\Menu;

/**
 * A header and a footer menu on the default site in the installer's
 * language (`resources/demo/{locale}`, English when it has none) for every
 * language, and menus of their own for English, German and Russian. Each links the demo
 * modules that are installed; the catalog item unfolds its sections two
 * levels deep and the FAQ item its categories.
 */
final class MenusDemoSeeder extends DemoSeeder
{
    private const CATALOG = 'Atrium\\Catalog\\Models\\CatalogSection';

    private const FAQ = 'Atrium\\Faq\\Models\\Category';

    private const NEWS = 'Atrium\\News\\Models\\News';

    private const STORES = 'Atrium\\Stores\\Models\\Store';

    /** Models the header unfolds, and how many levels deep. */
    private const UNFOLDED = [self::CATALOG => 2, self::FAQ => 1];

    /** Languages the demo turns on, each with menus of its own. */
    private const LANGUAGES = ['en', 'de', 'ru'];

    public function run(): bool
    {
        if (Menu::query()->withoutGlobalScopes()->exists()) {
            return false;
        }

        $site = app(Sites::class)->default();
        $main = app()->getLocale();

        // The installer's language for every language; the others the demo
        // turns on get menus of their own.
        $menus = [[null, $main]];

        foreach (array_diff(self::LANGUAGES, [$main]) as $language) {
            $menus[] = [$language, $language];
        }

        foreach ($menus as [$language, $locale]) {
            $texts = $this->texts($locale);
            $prefix = $language === null ? '' : '/'.$language;

            foreach (['header', 'footer'] as $code) {
                $menu = new Menu([
                    'site_id' => $site->id,
                    'language' => $language,
                    'code' => $code,
                    'name' => $texts[$code],
                    'active' => true,
                ]);
                $menu->save();
                $menu->afterAdminSave(['tree' => $this->items($code, $texts, $prefix)]);
            }
        }

        return true;
    }

    /**
     * The titles written for a language, English when there are none.
     *
     * @return array<string,string>
     */
    private function texts(string $locale): array
    {
        $file = dirname(__DIR__, 2)."/resources/demo/{$locale}/menus.php";

        return require is_file($file) ? $file : dirname(__DIR__, 2).'/resources/demo/en/menus.php';
    }

    /**
     * @param  array<string,string>  $texts
     * @return array<int,array<string,mixed>>
     */
    private function items(string $code, array $texts, string $prefix): array
    {
        $header = $code === 'header';
        $items = $header ? [] : [$this->item($texts['home'], $prefix.'/')];

        foreach ([['catalog', self::CATALOG], ['news', self::NEWS], ['stores', self::STORES], ['faq', self::FAQ]] as [$key, $model]) {
            $rule = Soft::available($model) ? PublicUrl::ruleFor($model) : null;

            if ($rule === null || $rule['prefix'] === '') {
                continue;
            }

            $item = $this->item($texts[$key], $prefix.'/'.$rule['prefix']);

            // The header unfolds the sections; the footer stays one line.
            if ($header && isset(self::UNFOLDED[$model])) {
                $item['source'] = $model;
                $item['source_depth'] = self::UNFOLDED[$model];
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    private function item(string $title, string $url): array
    {
        return [
            'title' => $title,
            'link' => ['type' => 'url', 'url' => $url],
            'active' => true,
            'children' => [],
        ];
    }
}
