<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Models\MenuItem;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\Fixtures\Section;
use Atrium\Menus\Tests\TestCase;
use Inertia\Testing\AssertableInertia;

/**
 * Editors build a menu in its form: the items as one tree, saved at once.
 */
final class AdminTest extends TestCase
{
    public function test_the_form_has_the_tree_with_the_item_fields(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->get('/admin/menus/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Resource/Create')
                ->where('fields', fn ($fields) => collect($fields)->contains(fn (array $field): bool => $field['type'] === 'tree'
                    && $field['name'] === 'tree'
                    && collect($field['fields'])->pluck('name')->all() === ['title', 'link', 'target', 'icon', 'active', 'source', 'source_depth']
                    && collect($field['fields'])->firstWhere('name', 'link')['targets'] === [
                        ['value' => Section::class, 'label' => 'Sections'],
                        ['value' => Article::class, 'label' => 'Articles'],
                    ])));
    }

    public function test_a_menu_is_created_with_its_tree(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/menus', [
                'name' => 'Main menu',
                'code' => 'header',
                'language' => '',
                'active' => true,
                'tree' => [
                    $this->item('Shop', '/shop', [
                        $this->item('Delivery', null, [], ['link' => ['type' => 'record', 'model' => Article::class, 'id' => $article->id]]),
                    ], ['source' => Section::class, 'source_depth' => 2]),
                    $this->item('Contacts', '/contacts', [], ['target' => '_blank', 'icon' => 'icon icon-phone', 'active' => false]),
                ],
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.menus.index'));

        $menu = Menu::query()->sole();
        $this->assertNull($menu->language);
        $this->assertNotNull($menu->site_id);

        [$shop, $delivery, $contacts] = MenuItem::query()->orderBy('id')->get()->all();
        $this->assertSame(['/shop', null, 0, Section::class, 2], [$shop->url, $shop->parent_id, $shop->sort > 0 ? 0 : 1, $shop->source, $shop->source_depth]);
        $this->assertSame([$shop->id, Article::class, $article->id, null], [$delivery->parent_id, $delivery->linkable_type, $delivery->linkable_id, $delivery->url]);
        $this->assertSame(['_blank', 'icon icon-phone', false], [$contacts->target, $contacts->icon, $contacts->active]);
        $this->assertGreaterThan($shop->sort, $contacts->sort);

        $this->get("/admin/menus/{$menu->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('item.tree.0.title', 'Shop')
                ->where('item.tree.0.children.0.link', ['type' => 'record', 'model' => Article::class, 'id' => $article->id, 'label' => 'Delivery'])
                ->where('item.tree.1.link', ['type' => 'url', 'url' => '/contacts']));
    }

    public function test_saving_moves_keeps_and_deletes_items(): void
    {
        $menu = $this->menu('header', [
            $this->item('One', '/one', [$this->item('Child', '/child')]),
            $this->item('Two', '/two'),
            $this->item('Three', '/three'),
        ]);
        $ids = MenuItem::query()->orderBy('id')->pluck('id', 'title');

        // Two goes first and takes Child; One loses it; Three is removed;
        // Four is new.
        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/menus/{$menu->id}", [
                'name' => 'Menu',
                'code' => 'header',
                'active' => true,
                'tree' => [
                    ['id' => $ids['Two'], ...$this->item('Two', '/two', [['id' => $ids['Child'], ...$this->item('Child', '/child')]])],
                    ['id' => $ids['One'], ...$this->item('One renamed', '/one')],
                    $this->item('Four', '/four'),
                ],
            ])
            ->assertSessionDoesntHaveErrors();

        $items = MenuItem::query()->orderBy('sort')->get()->keyBy('title');
        $this->assertEqualsCanonicalizing(['Two', 'Child', 'One renamed', 'Four'], $items->keys()->all());
        $this->assertSame($ids['Two'], $items['Two']->id);
        $this->assertSame($ids['Two'], $items['Child']->parent_id);
        $this->assertSame($ids['One'], $items['One renamed']->id);
        $this->assertNull($items['One renamed']->parent_id);
        $this->assertTrue($items['Two']->sort < $items['One renamed']->sort && $items['One renamed']->sort < $items['Four']->sort);
        $this->assertFalse(MenuItem::query()->whereKey($ids['Three'])->exists());

        // Without a tree the items stay.
        $this->put("/admin/menus/{$menu->id}", ['name' => 'Header', 'code' => 'header', 'active' => true])->assertSessionDoesntHaveErrors();
        $this->assertSame(4, MenuItem::query()->count());

        $this->delete("/admin/menus/{$menu->id}")->assertRedirect(route('admin.menus.index'));
        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_items_of_another_menu_cannot_be_taken_over(): void
    {
        $other = $this->menu('footer', [$this->item('Theirs', '/theirs')]);
        $theirs = MenuItem::query()->sole();
        $menu = $this->menu('header', []);

        $this->actingAs($this->admin(), 'admin')
            ->put("/admin/menus/{$menu->id}", ['name' => 'Menu', 'code' => 'header', 'active' => true, 'tree' => [['id' => $theirs->id, ...$this->item('Mine', '/mine')]]])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Theirs', $theirs->fresh()?->title);
        $this->assertSame($other->id, $theirs->fresh()?->menu_id);
        $this->assertSame(['Mine'], MenuItem::query()->where('menu_id', $menu->id)->pluck('title')->all());
    }

    public function test_a_code_is_unique_per_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch']);
        $this->menu('header', []);
        $this->menu('header', [], 'de');
        $this->actingAs($this->admin(), 'admin');

        $this->post('/admin/menus', ['name' => 'Again', 'code' => 'header', 'language' => '', 'active' => true])
            ->assertSessionHasErrors('code');
        $this->post('/admin/menus', ['name' => 'Again', 'code' => 'header', 'language' => 'de', 'active' => true])
            ->assertSessionHasErrors('code');
        $this->post('/admin/menus', ['name' => 'Footer', 'code' => 'Not a code!', 'active' => true])
            ->assertSessionHasErrors('code');

        $this->assertSame(2, Menu::query()->count());
    }

    public function test_broken_items_are_refused(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $menu = ['name' => 'Menu', 'code' => 'header', 'active' => true];

        $this->post('/admin/menus', [...$menu, 'tree' => [$this->item('Bad', 'javascript:alert(1)')]])->assertSessionHasErrors('tree');
        $this->post('/admin/menus', [...$menu, 'tree' => [$this->item('', '/x')]])->assertSessionHasErrors('tree');
        $this->post('/admin/menus', [...$menu, 'tree' => [$this->item('Gone', null, [], ['link' => ['type' => 'record', 'model' => Article::class, 'id' => 999]])]])->assertSessionHasErrors('tree');

        $deep = $this->item('5', '/5');
        foreach (['4', '3', '2', '1'] as $title) {
            $deep = $this->item($title, '/'.$title, [$deep]);
        }
        $this->post('/admin/menus', [...$menu, 'tree' => [$deep]])->assertSessionHasErrors('tree');

        $this->assertSame(0, Menu::query()->count());
    }
}
