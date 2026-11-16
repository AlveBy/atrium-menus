<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\Fixtures\Section;
use Atrium\Menus\Tests\TestCase;

/**
 * Items that list a module's records under themselves, and keep up with
 * them without anyone touching the menu.
 */
final class DynamicTest extends TestCase
{
    /**
     * @return array<string,Section>
     */
    private function sections(): array
    {
        $phones = Section::query()->create(['name' => 'Phones', 'code' => 'phones', 'sort' => 10]);
        $laptops = Section::query()->create(['name' => 'Laptops', 'code' => 'laptops', 'sort' => 20]);
        $android = Section::query()->create(['name' => 'Android', 'code' => 'android', 'parent_id' => $phones->id]);
        $pixel = Section::query()->create(['name' => 'Pixel', 'code' => 'pixel', 'parent_id' => $android->id]);
        Section::query()->create(['name' => 'Hidden', 'code' => 'hidden', 'parent_id' => $phones->id, 'active' => false]);

        return compact('phones', 'laptops', 'android', 'pixel');
    }

    public function test_an_item_unfolds_the_top_of_a_tree_to_its_depth(): void
    {
        $this->sections();
        $this->menu('header', [$this->item('Shop', '/shop', [$this->item('Sale', '/sale')], ['source' => Section::class, 'source_depth' => 2])]);

        $items = $this->resolve('header', '/shop/phones/android')['items'] ?? [];

        $this->assertSame(['Shop' => ['Sale' => [], 'Phones' => ['Android' => []], 'Laptops' => []]], $this->titles($items));
        $this->assertSame('http://localhost/shop/phones/android', $items[0]['children'][1]['children'][0]['url']);
        $this->assertTrue($items[0]['children'][1]['children'][0]['current']);
        $this->assertTrue($items[0]['children'][1]['active']);
    }

    public function test_an_item_linked_to_a_section_unfolds_that_section(): void
    {
        ['phones' => $phones] = $this->sections();
        $this->menu('header', [$this->item('Phones', null, [], [
            'link' => ['type' => 'record', 'model' => Section::class, 'id' => $phones->id],
            'source' => Section::class,
            'source_depth' => 3,
        ])]);

        $this->assertSame(['Phones' => ['Android' => ['Pixel' => []]]], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_a_flat_module_lists_its_records(): void
    {
        Article::query()->create(['name' => 'Second', 'code' => 'second', 'sort' => 20]);
        Article::query()->create(['name' => 'First', 'code' => 'first', 'sort' => 10]);
        Article::query()->create(['name' => 'Draft', 'code' => 'draft', 'active' => false]);
        $this->menu('footer', [$this->item('Articles', null, [], ['source' => Article::class])]);

        $this->assertSame(['Articles' => ['First' => [], 'Second' => []]], $this->titles($this->resolve('footer')['items'] ?? []));
    }

    public function test_the_menu_follows_its_records_and_its_own_changes(): void
    {
        ['laptops' => $laptops] = $this->sections();
        $menu = $this->menu('header', [$this->item('Shop', '/shop', [], ['source' => Section::class])]);

        $this->assertSame(['Shop' => ['Phones' => [], 'Laptops' => []]], $this->titles($this->resolve('header')['items'] ?? []));

        $laptops->update(['name' => 'Notebooks']);
        Section::query()->create(['name' => 'Tablets', 'code' => 'tablets', 'sort' => 30]);
        $this->assertSame(['Shop' => ['Phones' => [], 'Notebooks' => [], 'Tablets' => []]], $this->titles($this->resolve('header')['items'] ?? []));

        $laptops->delete();
        $this->assertSame(['Shop' => ['Phones' => [], 'Tablets' => []]], $this->titles($this->resolve('header')['items'] ?? []));

        $menu->afterAdminSave(['tree' => [$this->item('Store', '/shop')]]);
        $menu->touch();
        $this->assertSame(['Store' => []], $this->titles($this->resolve('header')['items'] ?? []));

        $menu->update(['active' => false]);
        $this->assertNull($this->resolve('header'));
    }
}
