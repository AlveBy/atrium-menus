<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Menus\Models\MenuItem;
use Atrium\Menus\Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use ReflectionClass;

/**
 * External programs read and write menus through the admin's API, tree
 * included; the MCP server goes through the same API.
 */
final class ApiTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    public function test_the_api_reads_and_writes_the_tree(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop', [$this->item('Phones', '/shop/phones')])]);
        $token = $this->admin()->createToken('agent', ['menus:read', 'menus:write'])->plainTextToken;

        $this->withToken($token)->getJson("/api/v1/menus/{$menu->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'header')
            ->assertJsonPath('data.tree.0.children.0.title', 'Phones');

        $this->withToken($token)->putJson("/api/v1/menus/{$menu->id}", [
            'name' => 'Menu', 'code' => 'header', 'active' => true,
            'tree' => [$this->item('News', '/news')],
        ])->assertOk();

        $this->assertSame(['News'], MenuItem::query()->pluck('title')->all());
    }
}
