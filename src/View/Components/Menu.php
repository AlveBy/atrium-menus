<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\View\Components;

use Atrium\Menus\Menus;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A menu of the current site as nested lists:
 *
 *     <x-atrium-menu code="header" />
 *     <x-atrium-menu code="footer" class="footer-menu" label="Site map" />
 *
 * The current page's link gets aria-current="page", its item and the items
 * above it the class is-active. Nothing is drawn when the site has no
 * active menu with this code.
 */
final class Menu extends Component
{
    /** @var array{code:string,name:string,items:array<int,array<string,mixed>>}|null */
    public ?array $menu;

    public function __construct(
        public string $code,
        public string $class = 'menu',
        public ?string $label = null,
        ?string $language = null,
    ) {
        $this->menu = app(Menus::class)->get($code, $language);
    }

    public function shouldRender(): bool
    {
        return $this->menu !== null && $this->menu['items'] !== [];
    }

    public function render(): View
    {
        return view('atrium-menus::menu');
    }
}
