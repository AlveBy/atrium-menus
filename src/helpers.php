<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

use Atrium\Menus\Menus;

if (! function_exists('atrium_menu')) {
    /**
     * The menu with this code in the current language, with the current page
     * marked, or null when the site has none: for a theme that draws its
     * menu itself.
     *
     *     $items = atrium_menu('footer')['items'] ?? [];
     *
     * @return array{code:string,name:string,items:array<int,array<string,mixed>>}|null
     */
    function atrium_menu(string $code, ?string $language = null): ?array
    {
        return app(Menus::class)->get($code, $language);
    }
}
