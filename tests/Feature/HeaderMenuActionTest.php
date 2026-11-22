<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Actions\LinkAction;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Menus\Actions\AddToHeaderMenu;
use Atrium\Menus\Models\MenuItem;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\TestCase;
use Inertia\Testing\AssertableInertia;

/**
 * The button the menus module puts on the records of other modules: a
 * record with a page on the site goes to the end of the header menu.
 */
final class HeaderMenuActionTest extends TestCase
{
    private const BUTTON = ['type' => 'record', 'name' => 'add-to-header-menu', 'label' => 'Add to header menu', 'confirm' => null];

    public function test_records_with_pages_get_the_button_others_do_not(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/articles')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('actions', [self::BUTTON]));
        $this->get('/admin/menus')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('actions', []));
    }

    public function test_buttons_of_other_modules_stay(): void
    {
        Hook::filter('atrium.module.actions', fn (array $actions): array => [...$actions,
            LinkAction::make('help')->label('Help')->url('/help'),
            LinkAction::make('print')->label('Print')->url('/print'),
        ], 5);
        $links = [
            ['type' => 'link', 'name' => 'help', 'label' => 'Help', 'confirm' => null, 'new_tab' => false],
            ['type' => 'link', 'name' => 'print', 'label' => 'Print', 'confirm' => null, 'new_tab' => false],
        ];
        $this->actingAs($this->admin(), 'admin');

        $this->get('/admin/articles')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('actions', [...$links, self::BUTTON]));
        $this->get('/admin/menus')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('actions', $links));
    }

    public function test_the_button_needs_the_right_to_edit_menus(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $this->menu('header', []);

        $this->actingAs($this->editor(['articles.view', 'articles.update']), 'admin');
        $this->get('/admin/articles')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('actions', []));
        $this->post("/admin/articles/{$article->id}/actions/add-to-header-menu")->assertForbidden();

        $this->actingAs($this->editor(['articles.view', 'menus.update'], 'other@example.com'), 'admin');
        $this->get('/admin/articles')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('actions', [self::BUTTON]));
        $this->post("/admin/articles/{$article->id}/actions/add-to-header-menu")->assertSessionHas('success', '“Delivery” is added to the end of the header menu.');

        $this->assertSame(1, MenuItem::query()->count());
    }

    public function test_the_record_goes_to_the_end_of_the_top_level(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $menu = $this->menu('header', [$this->item('Shop', '/shop', [$this->item('Phones', '/phones')]), $this->item('About', '/about')]);
        MenuItem::query()->where('title', 'Phones')->update(['sort' => 900]);
        $german = $this->menu('header', [], 'de');
        $this->actingAs($this->admin(), 'admin');

        $this->from('/admin/articles')->post("/admin/articles/{$article->id}/actions/add-to-header-menu")
            ->assertRedirect('/admin/articles')
            ->assertSessionHas('success', '“Delivery” is added to the end of the header menu.');

        $added = MenuItem::query()->where('linkable_type', Article::class)->sole();
        $about = MenuItem::query()->where('title', 'About')->sole();
        $this->assertSame([$menu->id, null, $about->sort + 10, 'Delivery', $article->id], [$added->menu_id, $added->parent_id, $added->sort, $added->title, $added->linkable_id]);
        $this->assertSame(0, $german->items()->count(), 'The menu for all languages, not one of them.');
    }

    public function test_an_empty_header_menu_starts_at_ten(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $this->menu('header', []);

        $this->assertSame('“Delivery” is added to the end of the header menu.', AddToHeaderMenu::make('add')->handle($article)->message());
        $this->assertSame(10, MenuItem::query()->sole()->sort);
    }

    public function test_a_record_already_in_the_menu_is_not_added_twice(): void
    {
        [$delivery, $returns] = array_map(fn (string $name) => Article::query()->create(['name' => $name, 'code' => strtolower($name)]), ['Delivery', 'Returns']);
        $menu = $this->menu('header', []);
        $menu->items()->create(['title' => 'Elsewhere', 'sort' => 10, 'linkable_type' => Article::class, 'linkable_id' => $returns->id]);
        $menu->items()->create(['title' => 'Same id', 'sort' => 20, 'linkable_type' => 'App\\Models\\Other', 'linkable_id' => $delivery->id]);
        $action = AddToHeaderMenu::make('add');

        $result = $action->handle($returns);
        $this->assertFalse($result->isSuccessful());
        $this->assertSame('“Returns” is already in the header menu.', $result->message());

        $this->assertTrue($action->handle($delivery)->isSuccessful(), 'Another module’s record with the same id is not this one.');
        $this->assertSame(3, $menu->items()->count());
    }

    public function test_a_site_without_a_header_menu_is_told_so(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $this->menu('footer', []);
        $this->menu('header', [], 'en');

        $result = AddToHeaderMenu::make('add')->handle($article);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('The site has no header menu for all languages. Create a menu with the code “header”.', $result->message());
        $this->assertSame(0, MenuItem::query()->count());
    }

    /**
     * @param  array<int,string>  $permissions
     */
    private function editor(array $permissions, string $email = 'editor@example.com'): AdminUser
    {
        $user = AdminUser::query()->create(['name' => 'Editor', 'email' => $email, 'password' => 'secret-password', 'is_active' => true]);
        $user->roles()->attach(AdminRole::query()->create(['name' => 'Role of '.$email, 'permissions' => $permissions]));

        return $user->fresh() ?? $user;
    }
}
