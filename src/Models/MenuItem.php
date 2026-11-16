<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item of a menu: a title and where it goes. It belongs to the tree of
 * its menu through parent_id; the menu's form writes the whole tree at once
 * (Menu::afterAdminSave()).
 *
 * Where it goes: an address (url), a record of any module (linkable_type and
 * linkable_id), or nowhere (a heading for the items below). An item with a
 * source also lists that model's records under itself.
 *
 * @property int $id
 * @property int $menu_id
 * @property int|null $parent_id
 * @property int $sort
 * @property string $title
 * @property string|null $url
 * @property string|null $linkable_type
 * @property int|null $linkable_id
 * @property string|null $target
 * @property string|null $icon
 * @property bool $active
 * @property string|null $source
 * @property int $source_depth
 */
class MenuItem extends Model
{
    protected $table = 'menu_items';

    protected $fillable = [
        'menu_id', 'parent_id', 'sort', 'title', 'url', 'linkable_type', 'linkable_id',
        'target', 'icon', 'active', 'source', 'source_depth',
    ];

    protected function casts(): array
    {
        return [
            'menu_id' => 'integer',
            'parent_id' => 'integer',
            'sort' => 'integer',
            'linkable_id' => 'integer',
            'active' => 'boolean',
            'source_depth' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu,$this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
