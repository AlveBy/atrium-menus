<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Builder;

use Atrium\Admin\Builder\BlockType;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Modules\Modules;
use Atrium\Menus\Menus;
use Atrium\Menus\Models\Menu;

/**
 * The block of the site builder that shows a menu of the site. A menu is
 * chosen by its code, so each language gets its own menu of that code.
 */
final class MenuBlock extends BlockType
{
    public function label(): string
    {
        return __('atrium-menus::menus.builder.label');
    }

    public function fields(): array
    {
        return [
            SelectField::make('menu')
                ->label(__('atrium-menus::menus.builder.label'))
                ->options(Menu::query()->orderBy('name')->pluck('name', 'code')->all())
                ->required()
                ->rules('string'),
        ];
    }

    public function view(): string
    {
        return 'atrium-menus::builder';
    }

    public function data(array $settings): ?array
    {
        $code = is_string($settings['menu'] ?? null) ? $settings['menu'] : '';

        // Missing, switched off or empty: nothing to draw.
        return (app(Menus::class)->get($code)['items'] ?? []) === [] ? null : ['code' => $code];
    }

    public function available(): bool
    {
        return app(Modules::class)->available(Menu::class);
    }
}
