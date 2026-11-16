<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Fields\Field;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Models\MenuItem;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\Fixtures\Section;
use Atrium\Menus\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * What the form of a menu offers and what it accepts: every field and its
 * rules, with the values that pass and the ones that are refused.
 */
final class FormTest extends TestCase
{
    private const MISSING = '__missing__';

    public function test_the_menu_form_has_these_fields(): void
    {
        $this->assertSame([
            ['active', 'checkbox', 'Active', false, null, true],
            ['name', 'text', 'Name', true, null, null],
            ['code', 'text', 'Code', true, 'The theme asks for the menu by it: header, footer.', null],
            ['language', 'select', 'Language', false, null, ''],
            ['tree', 'tree', 'Items', false, null, null],
        ], array_map($this->described(...), Menu::getAdministrationFields()));
    }

    public function test_the_languages_of_the_menu_are_every_language_and_the_content_languages(): void
    {
        $language = collect(Menu::getAdministrationFields())->first(fn (Field $field): bool => $field->getName() === 'language')?->toArray();

        $this->assertSame([['value' => '', 'label' => 'All languages'], ['value' => 'en', 'label' => 'English']], $language['options'] ?? null);
    }

    public function test_the_tree_is_four_levels_deep_with_the_title_on_each_line(): void
    {
        $tree = collect(Menu::getAdministrationFields())->first(fn (Field $field): bool => $field->getName() === 'tree')?->toArray();

        $this->assertSame(4, $tree['maxDepth'] ?? null);
        $this->assertSame(4, Menu::MAX_DEPTH);
        $this->assertSame('title', $tree['titleField'] ?? null);
        $this->assertSame('Add item', $tree['addLabel'] ?? null);
        $this->assertSame(array_map(fn (Field $field): array => $field->toArray(), Menu::itemFields()), $tree['fields'] ?? null);
    }

    public function test_an_item_has_these_fields(): void
    {
        $this->assertSame([
            ['title', 'text', 'Title', true, null, null],
            ['link', 'link', 'Link', false, null, null],
            ['target', 'select', 'Opens in', false, null, ''],
            ['icon', 'text', 'Icon', false, 'CSS classes of an icon, for example “icon icon-phone”.', null],
            ['active', 'checkbox', 'Active', false, null, true],
            ['source', 'select', 'Unfold below', false, 'The module’s records are listed under the item and refresh by themselves.', ''],
            ['source_depth', 'number', 'Levels to unfold', false, null, 1],
        ], array_map($this->described(...), Menu::itemFields()));
    }

    public function test_the_target_and_the_source_of_an_item_are_chosen_from_a_list(): void
    {
        $fields = collect(Menu::itemFields())->keyBy(fn (Field $field): string => $field->getName());

        $this->assertSame([['value' => '', 'label' => 'The same window'], ['value' => '_blank', 'label' => 'A new window']], $fields['target']->toArray()['options']);
        $this->assertSame([
            ['value' => '', 'label' => 'Nothing'],
            ['value' => Section::class, 'label' => 'Sections'],
            ['value' => Article::class, 'label' => 'Articles'],
        ], $fields['source']->toArray()['options']);
        $this->assertSame([['value' => Section::class, 'label' => 'Sections'], ['value' => Article::class, 'label' => 'Articles']], $fields['link']->toArray()['targets']);
    }

    public function test_the_list_shows_these_columns(): void
    {
        $columns = array_map(fn ($column): array => [
            $column->toArray()['name'],
            $column->toArray()['component'],
            $column->toArray()['label'],
            $column->toArray()['sortable'],
            $column->toArray()['searchable'],
        ], Menu::getAdministrationListColumns());

        $this->assertSame([
            ['id', 'textColumn', 'ID', true, false],
            ['name', 'textColumn', 'Name', true, true],
            ['code', 'textColumn', 'Code', true, true],
            ['language', 'textColumn', 'Language', true, false],
            ['active', 'booleanColumn', 'Active', false, false],
        ], $columns);
    }

    /**
     * @return array<string,array{0:string,1:mixed,2:bool}>
     */
    public static function menuFields(): array
    {
        return [
            'a plain name' => ['name', 'Main menu', true],
            'a one-letter name' => ['name', 'M', true],
            'the longest name' => ['name', str_repeat('a', 255), true],
            'a name one too long' => ['name', str_repeat('a', 256), false],
            'an empty name' => ['name', '', false],
            'a name of spaces' => ['name', '   ', false],
            'no name' => ['name', self::MISSING, false],
            'a name that is a list' => ['name', ['Main'], false],
            'a Cyrillic name' => ['name', 'Главное меню', true],
            'a Chinese name' => ['name', '主菜单', true],
            'an Arabic name' => ['name', 'القائمة الرئيسية', true],
            'a name with emoji' => ['name', 'Menu 🪑', true],
            'a name with HTML' => ['name', '<script>alert(1)</script>', true],

            'a plain code' => ['code', 'header', true],
            'a one-letter code' => ['code', 'h', true],
            'a code with digits, a dash and an underscore' => ['code', 'side_bar-2', true],
            'the longest code' => ['code', str_repeat('a', 64), true],
            'a code one too long' => ['code', str_repeat('a', 65), false],
            'an empty code' => ['code', '', false],
            'no code' => ['code', self::MISSING, false],
            'a code in capitals' => ['code', 'Header', false],
            'a code with a space' => ['code', 'main menu', false],
            'a code with a dot' => ['code', 'main.menu', false],
            'a code with a slash' => ['code', 'a/b', false],
            'a code with a quote' => ['code', 'a"b', false],
            'a code with tags' => ['code', '<b>', false],
            'a Cyrillic code' => ['code', 'шапка', false],
            'a code with an accent' => ['code', 'ünï', false],
            'a code that is a list' => ['code', ['header'], false],

            'every language' => ['language', '', true],
            'a language of the site' => ['language', 'en', true],
            'a language that is not offered' => ['language', 'xx', false],
            'a language that is a number' => ['language', 123, false],
            'a language that is a list' => ['language', ['en'], false],
            'no language' => ['language', self::MISSING, true],

            'active' => ['active', true, true],
            'not active' => ['active', false, true],
            'active as 1' => ['active', '1', true],
            'not active as 0' => ['active', '0', true],
            'active as yes' => ['active', 'yes', false],
            'active as a list' => ['active', [1], false],
            'no flag' => ['active', self::MISSING, true],

            'no tree' => ['tree', self::MISSING, true],
            'an empty tree' => ['tree', [], true],
            'a tree that is a string' => ['tree', 'shop', false],
        ];
    }

    #[DataProvider('menuFields')]
    public function test_the_fields_of_a_menu_accept_and_refuse(string $field, mixed $value, bool $valid): void
    {
        $payload = $this->payload();
        $this->set($payload, $field, $value);

        $this->submit($payload, $valid, $field);
    }

    /**
     * @return array<string,array{0:string,1:mixed,2:bool}>
     */
    public static function itemFields(): array
    {
        return [
            'a plain title' => ['title', 'Shop', true],
            'the longest title' => ['title', str_repeat('a', 255), true],
            'a title one too long' => ['title', str_repeat('a', 256), false],
            'an empty title' => ['title', '', false],
            'a title of spaces' => ['title', '   ', false],
            'no title' => ['title', self::MISSING, false],
            'a title that is a list' => ['title', ['Shop'], false],
            'a Cyrillic title' => ['title', 'Каталог', true],
            'a Japanese title' => ['title', 'ガーデンチェア', true],
            'a Hebrew title' => ['title', 'כיסא גן', true],
            'a title with emoji' => ['title', 'Shop 🛒', true],
            'a title with HTML' => ['title', '"><img src=x onerror=alert(1)>', true],

            'every window' => ['target', '', true],
            'a new window' => ['target', '_blank', true],
            'another target' => ['target', '_top', false],
            'a target that is a list' => ['target', ['_blank'], false],
            'no target' => ['target', self::MISSING, true],

            'no icon' => ['icon', '', true],
            'icon classes' => ['icon', 'icon icon-phone', true],
            'icon classes with a colon' => ['icon', 'fa:phone', true],
            'an icon with a dash and an underscore' => ['icon', 'icon_a-b', true],
            'the longest icon' => ['icon', str_repeat('a', 100), true],
            'an icon one too long' => ['icon', str_repeat('a', 101), false],
            'an icon with a quote' => ['icon', 'a" onclick="alert(1)', false],
            'an icon with tags' => ['icon', '<b>', false],
            'an icon with a slash' => ['icon', 'a/b', false],
            'an icon with a dot' => ['icon', 'a.b', false],
            'an icon with an accent' => ['icon', 'ünï', false],
            'an icon that is a list' => ['icon', ['a'], false],
            'no icon given' => ['icon', self::MISSING, true],

            'an item that is active' => ['active', true, true],
            'an item that is not' => ['active', false, true],
            'an item active as yes' => ['active', 'yes', false],
            'no flag on the item' => ['active', self::MISSING, true],

            'no source' => ['source', '', true],
            'a source: sections' => ['source', Section::class, true],
            'a source: articles' => ['source', Article::class, true],
            'a source that is not a module of the panel' => ['source', Menu::class, false],
            'a source that is not a class' => ['source', stdClass::class, false],
            'a source that is not there' => ['source', 'No\\Such\\Model', false],
            'a source that is a list' => ['source', [Article::class], false],
            'no source given' => ['source', self::MISSING, true],

            'depth one' => ['source_depth', 1, true],
            'depth five' => ['source_depth', 5, true],
            'depth as text' => ['source_depth', '3', true],
            'depth zero' => ['source_depth', 0, false],
            'a negative depth' => ['source_depth', -1, false],
            'depth six' => ['source_depth', 6, false],
            'a depth with a fraction' => ['source_depth', 2.5, false],
            'a depth that is not a number' => ['source_depth', 'deep', false],
            'a depth that is a list' => ['source_depth', [1], false],
            'no depth given' => ['source_depth', self::MISSING, true],

            'no link' => ['link', null, true],
            'a link: a path' => ['link', ['type' => 'url', 'url' => '/shop'], true],
            'a link: an anchor' => ['link', ['type' => 'url', 'url' => '#top'], true],
            'a link: a query' => ['link', ['type' => 'url', 'url' => '?page=2'], true],
            'a link: another site' => ['link', ['type' => 'url', 'url' => 'https://example.com/a?b=c'], true],
            'a link: mail' => ['link', ['type' => 'url', 'url' => 'mailto:me@example.com'], true],
            'a link: phone' => ['link', ['type' => 'url', 'url' => 'tel:+4930123456'], true],
            'a link: a script' => ['link', ['type' => 'url', 'url' => 'javascript:alert(1)'], false],
            'a link: a script in capitals' => ['link', ['type' => 'url', 'url' => 'JaVaScRiPt:alert(1)'], false],
            'a link: a page of html in data' => ['link', ['type' => 'url', 'url' => 'data:text/html;base64,PHNjcmlwdD4='], false],
            'a link: no scheme but two slashes' => ['link', ['type' => 'url', 'url' => '//example.com/a'], false],
            'a link: a backslash trick' => ['link', ['type' => 'url', 'url' => '/\\example.com'], false],
            'a link: another protocol' => ['link', ['type' => 'url', 'url' => 'ftp://example.com/a'], false],
            'a link: an empty address' => ['link', ['type' => 'url', 'url' => ''], false],
            'a link: no address' => ['link', ['type' => 'url'], false],
            'a link: an address too long' => ['link', ['type' => 'url', 'url' => '/'.str_repeat('a', 2048)], false],
            'a link: another kind' => ['link', ['type' => 'page', 'url' => '/shop'], false],
            'a link: a record of a module' => ['link', ['type' => 'record', 'model' => Article::class, 'id' => '{article}'], true],
            'a link: a record of a module not offered' => ['link', ['type' => 'record', 'model' => Menu::class, 'id' => '{article}'], false],
            'a link: a record that is not there' => ['link', ['type' => 'record', 'model' => Article::class, 'id' => 987654], false],
            'a link: a record without a number' => ['link', ['type' => 'record', 'model' => Article::class], false],
            'a link: a record with a word for a number' => ['link', ['type' => 'record', 'model' => Article::class, 'id' => 'one'], false],
            'a link that is a string' => ['link', '/shop', false],
        ];
    }

    #[DataProvider('itemFields')]
    public function test_the_fields_of_an_item_accept_and_refuse(string $field, mixed $value, bool $valid): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $payload = $this->payload();

        if (is_array($value)) {
            array_walk_recursive($value, function (mixed &$part) use ($article): void {
                $part = $part === '{article}' ? $article->id : $part;
            });
        }

        $this->set($payload['tree'][0], $field, $value);

        $this->submit($payload, $valid, 'tree');
    }

    /**
     * @return array<string,array{0:int,1:bool}>
     */
    public static function treeDepths(): array
    {
        return [
            'one level' => [1, true],
            'three levels' => [3, true],
            'four levels, as many as the form allows' => [4, true],
            'five levels' => [5, false],
            'eight levels' => [8, false],
        ];
    }

    #[DataProvider('treeDepths')]
    public function test_items_nest_four_levels_deep(int $levels, bool $valid): void
    {
        $tree = [];

        for ($level = $levels; $level >= 1; $level--) {
            $tree = [$this->item('Level '.$level, '/l'.$level, $tree)];
        }

        $payload = $this->payload();
        $payload['tree'] = $tree;
        $response = $this->actingAs($this->admin(), 'admin')->post('/admin/menus', $payload);

        $valid ? $response->assertSessionDoesntHaveErrors() : $response->assertSessionHasErrors();
        $this->assertSame($valid ? $levels : 0, MenuItem::query()->count());
    }

    public function test_more_items_than_the_form_allows_are_refused(): void
    {
        $payload = $this->payload();
        $payload['tree'] = array_map(fn (int $number): array => $this->item('Item '.$number, '/'.$number), range(1, 501));

        $this->actingAs($this->admin(), 'admin')->post('/admin/menus', $payload)->assertSessionHasErrors('tree');

        $this->assertSame(0, Menu::query()->count());
    }

    /**
     * @return array{0:string,1:string,2:string,3:bool,4:string|null,5:mixed}
     */
    private function described(Field $field): array
    {
        $array = $field->toArray();

        return [$array['name'], $array['type'], $array['label'], $array['required'], $array['hint'], $array['default']];
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Main menu',
            'code' => 'header',
            'language' => '',
            'active' => true,
            'tree' => [$this->item('Shop', '/shop')],
        ];
    }

    /**
     * @param  array<string,mixed>  $target
     */
    private function set(array &$target, string $field, mixed $value): void
    {
        if ($value === self::MISSING) {
            unset($target[$field]);

            return;
        }

        $target[$field] = $value;
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function submit(array $payload, bool $valid, string $errorKey): void
    {
        $response = $this->actingAs($this->admin(), 'admin')->post('/admin/menus', $payload);

        if ($valid) {
            $response->assertSessionDoesntHaveErrors();
            $this->assertSame(1, Menu::query()->count());

            return;
        }

        $response->assertSessionHasErrors($errorKey);
        $this->assertSame(0, Menu::query()->count());
        $this->assertSame(0, MenuItem::query()->count());
    }
}
