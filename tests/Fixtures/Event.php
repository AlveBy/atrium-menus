<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Fixtures;

use Atrium\Admin\Traits\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * A module with dates of its own and no `active` or `sort` column, whose
 * name is written per language.
 */
class Event extends Model
{
    use Translatable;

    protected $table = 'menu_test_events';

    protected $guarded = [];

    /** @var array<int,string> */
    protected array $translatable = ['name'];
}
