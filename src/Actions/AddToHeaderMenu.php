<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Actions;

use Atrium\Admin\Actions\ActionResult;
use Atrium\Admin\Actions\RecordAction;
use Atrium\Admin\Support\RecordSearch;
use Atrium\Menus\Models\Menu;
use Illuminate\Database\Eloquent\Model;

/**
 * The button the menus module puts on every record that has a page on the
 * site: the record goes to the end of the site's header menu.
 */
final class AddToHeaderMenu extends RecordAction
{
    public function handle(Model $record): ActionResult
    {
        $menu = Menu::query()->where('code', 'header')->whereNull('language')->first();
        $title = app(RecordSearch::class)->label($record);

        if ($menu === null) {
            return ActionResult::error(__('atrium-menus::menus.actions.no_header'));
        }

        if ($menu->items()->where('linkable_type', $record::class)->where('linkable_id', $record->getKey())->exists()) {
            return ActionResult::error(__('atrium-menus::menus.actions.already', ['title' => $title]));
        }

        $menu->items()->create([
            'sort' => $menu->items()->whereNull('parent_id')->max('sort') + 10,
            'title' => $title,
            'linkable_type' => $record::class,
            'linkable_id' => $record->getKey(),
        ]);

        return ActionResult::success(__('atrium-menus::menus.actions.added', ['title' => $title]));
    }
}
