<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A module whose table is not there: reading its records fails.
 */
class Ghost extends Model
{
    protected $table = 'menu_test_ghosts';

    protected $guarded = [];
}
