<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Auth\Permissions;
use Atrium\Admin\Http\Middleware\ResolveSite;
use Atrium\Admin\Models\AdminRole;
use Atrium\Admin\Models\AdminUser;
use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Models\MenuItem;
use Atrium\Menus\Tests\TestCase;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

/**
 * Who may open, change and delete menus in the panel and through the API,
 * and whose menus they are.
 */
final class PermissionsTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(dirname((string) (new ReflectionClass(Sanctum::class))->getFileName(), 2).'/database/migrations');
    }

    /**
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function panelActions(): array
    {
        return [
            'list' => ['get', '/admin/menus', 'menus.view'],
            'open a menu' => ['get', '/admin/menus/{id}/edit', 'menus.view'],
            'create form' => ['get', '/admin/menus/create', 'menus.create'],
            'store' => ['post', '/admin/menus', 'menus.create'],
            'update' => ['put', '/admin/menus/{id}', 'menus.update'],
            'destroy' => ['delete', '/admin/menus/{id}', 'menus.delete'],
        ];
    }

    #[DataProvider('panelActions')]
    public function test_a_panel_action_is_allowed_with_its_permission(string $method, string $url, string $permission): void
    {
        $response = $this->panel($this->editor([$permission]), $method, $url);

        $this->assertNotSame(403, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    #[DataProvider('panelActions')]
    public function test_a_panel_action_is_refused_without_its_permission(string $method, string $url, string $permission): void
    {
        $everythingElse = array_values(array_diff(app(Permissions::class)->all(), [$permission]));

        $this->panel($this->editor($everythingElse), $method, $url)->assertForbidden();

        $this->assertSame(['Main menu'], Menu::query()->pluck('name')->all());
        $this->assertSame(['Shop', 'Phones'], MenuItem::query()->orderBy('id')->pluck('title')->all());
    }

    public function test_a_guest_is_sent_to_the_login(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);

        $this->get('/admin/menus')->assertRedirect();
        $this->put("/admin/menus/{$menu->id}", ['name' => 'Hacked', 'code' => 'header'])->assertRedirect();
        $this->delete("/admin/menus/{$menu->id}")->assertRedirect();

        $this->assertSame(1, Menu::query()->count());
        $this->assertSame('Menu', Menu::query()->sole()->name);
    }

    public function test_the_panel_menu_lists_the_menus_only_for_those_who_may_view_them(): void
    {
        $with = $this->routesOf($this->actingAs($this->editor(['menus.view']), 'admin')->get('/admin'));
        $without = $this->routesOf($this->actingAs($this->editor(['menus.create'], 'other@example.com'), 'admin')->get('/admin'));

        $this->assertContains('admin.menus.index', $with);
        $this->assertNotContains('admin.menus.index', $without);
    }

    public function test_a_menu_of_another_site_is_out_of_reach_in_the_panel(): void
    {
        [$main, $second] = $this->twoSites();
        $mine = $this->menuOf($main, 'header', 'Main header');
        $theirs = $this->menuOf($second, 'header', 'German header');
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get('/admin/menus')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('items.total', 1)
            ->where('items.data.0.name', 'Main header'));

        $this->get("/admin/menus/{$mine->id}/edit")->assertOk();
        $this->get("/admin/menus/{$theirs->id}/edit")->assertNotFound();
        $this->put("/admin/menus/{$theirs->id}", ['name' => 'Hacked', 'code' => 'header', 'language' => '', 'active' => true, 'tree' => []])->assertNotFound();
        $this->delete("/admin/menus/{$theirs->id}")->assertNotFound();

        $this->assertSame('German header', Menu::query()->withoutGlobalScopes()->findOrFail($theirs->id)->name);
        $this->assertSame(1, MenuItem::query()->where('menu_id', $theirs->id)->count());

        $this->withSession([ResolveSite::SESSION_KEY => $second->id])->get("/admin/menus/{$theirs->id}/edit")->assertOk();
    }

    public function test_a_menu_made_in_the_panel_belongs_to_the_site_picked(): void
    {
        [, $second] = $this->twoSites();

        $this->actingAs($this->admin(), 'admin')
            ->withSession([ResolveSite::SESSION_KEY => $second->id])
            ->post('/admin/menus', ['name' => 'German', 'code' => 'header', 'language' => '', 'active' => true, 'tree' => []])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame($second->id, Menu::query()->withoutGlobalScopes()->sole()->site_id);
    }

    public function test_the_site_of_a_menu_cannot_be_chosen_by_the_form(): void
    {
        [$main, $second] = $this->twoSites();

        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/menus', ['name' => 'Sneaky', 'code' => 'header', 'language' => '', 'active' => true, 'tree' => [], 'site_id' => $second->id, 'id' => 999])
            ->assertSessionDoesntHaveErrors();

        $menu = Menu::query()->withoutGlobalScopes()->sole();

        $this->assertSame($main->id, $menu->site_id);
        $this->assertNotSame(999, $menu->id);
    }

    public function test_the_same_code_is_free_on_another_site(): void
    {
        [$main, $second] = $this->twoSites();
        $this->menuOf($main, 'header', 'Main header');

        $this->actingAs($this->admin(), 'admin')
            ->withSession([ResolveSite::SESSION_KEY => $second->id])
            ->post('/admin/menus', ['name' => 'German', 'code' => 'header', 'language' => '', 'active' => true, 'tree' => []])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Menu::query()->withoutGlobalScopes()->count());
    }

    public function test_the_site_shows_the_menus_of_its_own_domain_only(): void
    {
        [$main, $second] = $this->twoSites();
        $this->menuOf($main, 'footer', 'Main footer', [$this->item('Main imprint', '/imprint')]);
        $this->menuOf($second, 'footer', 'German footer', [$this->item('Impressum', '/impressum')]);

        $this->get('http://localhost/')->assertOk()->assertSee('Main imprint')->assertDontSee('Impressum');
        $this->get('http://example.de/')->assertOk()->assertSee('Impressum')->assertDontSee('Main imprint');
    }

    /**
     * @return array<string,array{0:string,1:string,2:string}>
     */
    public static function apiActions(): array
    {
        return [
            'list' => ['getJson', '/api/v1/menus', 'menus.view'],
            'show' => ['getJson', '/api/v1/menus/{id}', 'menus.view'],
            'store' => ['postJson', '/api/v1/menus', 'menus.create'],
            'update' => ['putJson', '/api/v1/menus/{id}', 'menus.update'],
            'destroy' => ['deleteJson', '/api/v1/menus/{id}', 'menus.delete'],
        ];
    }

    #[DataProvider('apiActions')]
    public function test_an_api_action_is_allowed_when_the_owner_has_the_permission(string $method, string $url, string $permission): void
    {
        $response = $this->api($this->editor([$permission]), $method, $url);

        $this->assertNotSame(403, $response->getStatusCode());
        $this->assertLessThan(400, $response->getStatusCode());
    }

    #[DataProvider('apiActions')]
    public function test_an_api_action_is_refused_when_the_owner_lacks_the_permission(string $method, string $url, string $permission): void
    {
        $everythingElse = array_values(array_diff(app(Permissions::class)->all(), [$permission]));

        $this->api($this->editor($everythingElse), $method, $url)->assertForbidden();

        $this->assertSame(['Main menu'], Menu::query()->pluck('name')->all());
        $this->assertSame(['Shop', 'Phones'], MenuItem::query()->orderBy('id')->pluck('title')->all());
    }

    public function test_a_token_that_only_reads_cannot_write_menus(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);
        $token = $this->admin()->createToken('agent', ['menus:read'])->plainTextToken;

        $this->withToken($token)->getJson("/api/v1/menus/{$menu->id}")->assertOk();
        $this->withToken($token)->putJson("/api/v1/menus/{$menu->id}", ['name' => 'Hacked', 'code' => 'header', 'active' => true, 'tree' => []])->assertForbidden();
        $this->withToken($token)->deleteJson("/api/v1/menus/{$menu->id}")->assertForbidden();

        $this->assertSame('Menu', Menu::query()->sole()->name);
        $this->assertSame(1, MenuItem::query()->count());
    }

    public function test_a_token_without_a_menus_ability_cannot_even_read_them(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')]);
        $token = $this->admin()->createToken('agent', ['pages:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/menus')->assertForbidden();
    }

    public function test_the_api_refuses_a_request_without_a_token(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')]);

        $this->getJson('/api/v1/menus')->assertUnauthorized();
    }

    public function test_a_menu_of_another_site_is_out_of_reach_through_the_api(): void
    {
        [$main, $second] = $this->twoSites();
        $mine = $this->menuOf($main, 'header', 'Main header');
        $theirs = $this->menuOf($second, 'header', 'German header');
        $token = $this->admin()->createToken('agent', ['menus:read', 'menus:write'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/menus?site=main')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $mine->id);
        $this->withToken($token)->getJson('/api/v1/menus?site=main&search=German')->assertJsonPath('meta.total', 0);
        $this->withToken($token)->getJson("/api/v1/menus/{$theirs->id}?site=main")->assertNotFound();
        $this->withToken($token)->putJson("/api/v1/menus/{$theirs->id}?site=main", ['name' => 'Hacked', 'code' => 'header', 'active' => true, 'tree' => []])->assertNotFound();
        $this->withToken($token)->deleteJson("/api/v1/menus/{$theirs->id}?site=main")->assertNotFound();

        $this->assertSame('German header', Menu::query()->withoutGlobalScopes()->findOrFail($theirs->id)->name);
        $this->assertSame(1, MenuItem::query()->where('menu_id', $theirs->id)->count());
        $this->withToken($token)->getJson("/api/v1/menus/{$theirs->id}?site=de")->assertOk()->assertJsonPath('data.name', 'German header');
    }

    public function test_the_api_refuses_a_menu_with_a_script_for_an_address_and_changes_nothing(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);
        $token = $this->admin()->createToken('agent', ['menus:read', 'menus:write'])->plainTextToken;

        $this->withToken($token)->putJson("/api/v1/menus/{$menu->id}", [
            'name' => 'Menu', 'code' => 'header', 'active' => true,
            'tree' => [$this->item('Evil', 'javascript:alert(1)')],
        ])->assertUnprocessable()->assertJsonValidationErrors('tree');

        $this->assertSame(['Shop'], MenuItem::query()->pluck('title')->all());
    }

    public function test_the_api_does_not_take_a_site_or_an_id_from_the_body(): void
    {
        [$main, $second] = $this->twoSites();
        $token = $this->admin()->createToken('agent', ['menus:read', 'menus:write'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/menus?site=main', [
            'name' => 'Sneaky', 'code' => 'header', 'active' => true, 'tree' => [], 'site_id' => $second->id, 'id' => 999,
        ])->assertSuccessful();

        $menu = Menu::query()->withoutGlobalScopes()->sole();

        $this->assertSame($main->id, $menu->site_id);
        $this->assertNotSame(999, $menu->id);
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

    private function panel(AdminUser $user, string $method, string $url): TestResponse
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop', [$this->item('Phones', '/phones')])], name: 'Main menu');
        $url = str_replace('{id}', (string) $menu->id, $url);
        $data = in_array($method, ['post', 'put'], true) ? ['name' => 'Renamed', 'code' => $method === 'post' ? 'other' : 'header', 'language' => '', 'active' => true, 'tree' => []] : [];

        return $this->actingAs($user, 'admin')->{$method}($url, $data);
    }

    private function api(AdminUser $user, string $method, string $url): TestResponse
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop', [$this->item('Phones', '/phones')])], name: 'Main menu');
        $token = $user->createToken('agent', ['menus:read', 'menus:write'])->plainTextToken;
        $url = str_replace('{id}', (string) $menu->id, $url);
        $data = in_array($method, ['postJson', 'putJson'], true) ? ['name' => 'Renamed', 'code' => $method === 'postJson' ? 'other' : 'header', 'language' => '', 'active' => true, 'tree' => []] : [];

        return $this->withToken($token)->{$method}($url, $data);
    }

    /**
     * @return array{0:Site,1:Site}
     */
    private function twoSites(): array
    {
        $sites = app(Sites::class);
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $sites->flush();

        return [$sites->default(), $second];
    }

    /**
     * @param  array<int,array<string,mixed>>  $tree
     */
    private function menuOf(Site $site, string $code, string $name, array $tree = [], ?string $language = null): Menu
    {
        $menu = Menu::query()->create(['site_id' => $site->id, 'code' => $code, 'name' => $name, 'language' => $language, 'active' => true]);
        $menu->afterAdminSave(['tree' => $tree === [] ? [$this->item('Item of '.$name, '/x')] : $tree]);

        return $menu;
    }

    /**
     * @return array<int,string>
     */
    private function routesOf(TestResponse $response): array
    {
        return array_values(array_filter(Arr::flatten(array_map(
            fn (array $item): array => [$item['route'] ?? null, ...array_column($item['children'] ?? [], 'route')],
            $response->viewData('page')['props']['menu'],
        ))));
    }
}
