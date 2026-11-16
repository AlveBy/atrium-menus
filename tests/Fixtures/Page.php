<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Fixtures;

use Atrium\Admin\Contracts\Sitemapable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A module that answers for its own addresses (Sitemapable) and has no
 * address rule in the configuration.
 */
class Page extends Model implements Sitemapable
{
    protected $table = 'menu_test_articles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public static function sitemapQuery(): Builder
    {
        return static::query()->where('active', true);
    }

    public function sitemapUrl(string $language): ?string
    {
        return $this->code === '' ? null : 'http://localhost/pages/'.$this->code;
    }
}
