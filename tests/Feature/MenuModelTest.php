<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Models\Site;
use Atrium\Admin\Support\Sites;
use Atrium\Menus\Models\Menu;
use Atrium\Menus\Models\MenuItem;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\Fixtures\Section;
use Atrium\Menus\Tests\TestCase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

/**
 * The tree of a menu: how it is written from the form and the API and how
 * it is read back, and what a menu needs to be saved.
 */
final class MenuModelTest extends TestCase
{
    /**
     * @return array<string,array{0:array<string,mixed>,1:array<string,mixed>}>
     */
    public static function writtenItems(): array
    {
        $record = ['type' => 'record', 'model' => Article::class, 'id' => 7];

        return [
            'the title is trimmed' => [['title' => '  Shop  '], ['title' => 'Shop']],
            'no title' => [['title' => null], ['title' => '']],
            'a number as the title' => [['title' => 123], ['title' => '123']],

            'an address is trimmed' => [['link' => ['type' => 'url', 'url' => '  /shop  ']], ['url' => '/shop', 'linkable_type' => null, 'linkable_id' => null]],
            'an address type without an address' => [['link' => ['type' => 'url']], ['url' => '', 'linkable_type' => null, 'linkable_id' => null]],
            'a record' => [['link' => $record], ['url' => null, 'linkable_type' => Article::class, 'linkable_id' => 7]],
            'a record with its number as text' => [['link' => [...$record, 'id' => '7']], ['url' => null, 'linkable_type' => Article::class, 'linkable_id' => 7]],
            'an address in a record link is ignored' => [['link' => [...$record, 'url' => '/ignored']], ['url' => null, 'linkable_type' => Article::class, 'linkable_id' => 7]],
            'a record in an address link is ignored' => [['link' => ['type' => 'url', 'url' => '/shop', 'model' => Article::class, 'id' => 7]], ['url' => '/shop', 'linkable_type' => null, 'linkable_id' => null]],
            'an unknown type of link' => [['link' => ['type' => 'page', 'url' => '/shop']], ['url' => null, 'linkable_type' => null, 'linkable_id' => null]],
            'a link that is a string' => [['link' => '/shop'], ['url' => null, 'linkable_type' => null, 'linkable_id' => null]],
            'no link' => [['link' => null], ['url' => null, 'linkable_type' => null, 'linkable_id' => null]],

            'a new window' => [['target' => '_blank'], ['target' => '_blank']],
            'the same window' => [['target' => ''], ['target' => null]],
            'another target' => [['target' => '_self'], ['target' => null]],
            'a target that only looks like one' => [['target' => 'blank'], ['target' => null]],
            'a target that is a list' => [['target' => ['_blank']], ['target' => null]],
            'no target' => [[], ['target' => null]],

            'an icon' => [['icon' => 'icon icon-phone'], ['icon' => 'icon icon-phone']],
            'an icon is trimmed' => [['icon' => '  icon-phone  '], ['icon' => 'icon-phone']],
            'an empty icon' => [['icon' => ''], ['icon' => null]],
            'an icon of spaces' => [['icon' => '   '], ['icon' => null]],
            'an icon that is a number' => [['icon' => 123], ['icon' => null]],
            'an icon that is a list' => [['icon' => ['x']], ['icon' => null]],
            'no icon' => [[], ['icon' => null]],

            'active' => [['active' => true], ['active' => true]],
            'not active' => [['active' => false], ['active' => false]],
            'active as 1' => [['active' => '1'], ['active' => true]],
            'not active as 0' => [['active' => '0'], ['active' => false]],
            'active as on' => [['active' => 'on'], ['active' => true]],
            'active as yes' => [['active' => 'yes'], ['active' => true]],
            'active as true' => [['active' => 'true'], ['active' => true]],
            'not active as false' => [['active' => 'false'], ['active' => false]],
            'not active as off' => [['active' => 'off'], ['active' => false]],
            'not active as nonsense' => [['active' => 'maybe'], ['active' => false]],
            'not active as an empty string' => [['active' => ''], ['active' => false]],
            'active by default' => [[], ['active' => true]],
            'active when null' => [['active' => null], ['active' => true]],

            'a source' => [['source' => Article::class], ['source' => Article::class]],
            'an empty source' => [['source' => ''], ['source' => null]],
            'a source that is a number' => [['source' => 123], ['source' => null]],
            'a source that is null' => [['source' => null], ['source' => null]],
            'no source' => [[], ['source' => null]],

            'depth zero is one' => [['source_depth' => 0], ['source_depth' => 1]],
            'a negative depth is one' => [['source_depth' => -5], ['source_depth' => 1]],
            'depth one' => [['source_depth' => 1], ['source_depth' => 1]],
            'depth three' => [['source_depth' => 3], ['source_depth' => 3]],
            'depth five' => [['source_depth' => 5], ['source_depth' => 5]],
            'depth six is five' => [['source_depth' => 6], ['source_depth' => 5]],
            'a huge depth is five' => [['source_depth' => 99999], ['source_depth' => 5]],
            'a depth as text' => [['source_depth' => '4'], ['source_depth' => 4]],
            'a depth that is not a number' => [['source_depth' => 'deep'], ['source_depth' => 1]],
            'a depth with a fraction' => [['source_depth' => 4.9], ['source_depth' => 4]],
            'a depth that is null' => [['source_depth' => null], ['source_depth' => 1]],
            'no depth' => [[], ['source_depth' => 1]],
        ];
    }

    /**
     * @param  array<string,mixed>  $extra
     * @param  array<string,mixed>  $expected
     */
    #[DataProvider('writtenItems')]
    public function test_an_item_is_stored_the_way_the_form_sends_it(array $extra, array $expected): void
    {
        $menu = $this->menu('header', [$this->item('Item', null, [], $extra)]);

        $row = MenuItem::query()->where('menu_id', $menu->id)->sole();

        $this->assertSame($expected, $row->only(array_keys($expected)));
        $this->assertSame($menu->id, $row->menu_id);
        $this->assertNull($row->parent_id);
        $this->assertSame(10, $row->sort);
    }

    public function test_items_are_stored_in_their_order_under_their_parents(): void
    {
        $menu = $this->menu('header', [
            $this->item('First', '/1', [$this->item('Child A', '/1/a'), $this->item('Child B', '/1/b', [$this->item('Grandchild', '/1/b/g')])]),
            $this->item('Second', '/2'),
            $this->item('Third', '/3', [$this->item('Only child', '/3/c')]),
        ]);

        $rows = MenuItem::query()->where('menu_id', $menu->id)->get()->keyBy('title');

        $this->assertSame([null, 10], [$rows['First']->parent_id, $rows['First']->sort]);
        $this->assertSame([null, 20], [$rows['Second']->parent_id, $rows['Second']->sort]);
        $this->assertSame([null, 30], [$rows['Third']->parent_id, $rows['Third']->sort]);
        $this->assertSame([$rows['First']->id, 10], [$rows['Child A']->parent_id, $rows['Child A']->sort]);
        $this->assertSame([$rows['First']->id, 20], [$rows['Child B']->parent_id, $rows['Child B']->sort]);
        $this->assertSame([$rows['Child B']->id, 10], [$rows['Grandchild']->parent_id, $rows['Grandchild']->sort]);
        $this->assertSame([$rows['Third']->id, 10], [$rows['Only child']->parent_id, $rows['Only child']->sort]);
    }

    public function test_a_tree_sent_with_text_keys_is_stored_in_the_order_it_came(): void
    {
        $menu = Menu::query()->create(['code' => 'header', 'name' => 'Menu', 'active' => true]);

        $menu->afterAdminSave(['tree' => ['z' => $this->item('First', '/1', ['b' => $this->item('Inner', '/i')]), 'a' => $this->item('Second', '/2')]]);

        $this->assertSame(['First' => 10, 'Inner' => 10, 'Second' => 20], MenuItem::query()->orderBy('id')->pluck('sort', 'title')->all());
    }

    public function test_an_item_that_is_not_an_item_is_skipped_but_keeps_its_place(): void
    {
        $menu = Menu::query()->create(['code' => 'header', 'name' => 'Menu', 'active' => true]);

        $menu->afterAdminSave(['tree' => [$this->item('First', '/1'), 'junk', null, 7, $this->item('Second', '/2')]]);

        $this->assertSame(['First' => 10, 'Second' => 50], MenuItem::query()->orderBy('id')->pluck('sort', 'title')->all());
    }

    public function test_children_that_are_not_a_list_are_ignored(): void
    {
        $menu = Menu::query()->create(['code' => 'header', 'name' => 'Menu', 'active' => true]);

        $menu->afterAdminSave(['tree' => [$this->item('Alone', '/a', [], ['children' => 'none'])]]);

        $this->assertSame(['Alone'], MenuItem::query()->pluck('title')->all());
    }

    public function test_saving_again_keeps_the_ids_of_the_items(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop', [$this->item('Phones', '/phones')])]);
        $shop = MenuItem::query()->where('title', 'Shop')->sole();
        $phones = MenuItem::query()->where('title', 'Phones')->sole();

        $menu->afterAdminSave(['tree' => [
            $this->item('Phones', '/phones', [], ['id' => $phones->id]),
            $this->item('Store', '/store', [], ['id' => (string) $shop->id]),
        ]]);

        $this->assertEquals([$phones->id => ['Phones', null, 10], $shop->id => ['Store', null, 20]], MenuItem::query()->get()->mapWithKeys(fn (MenuItem $item): array => [$item->id => [$item->title, $item->parent_id, $item->sort]])->all());
    }

    public function test_an_id_that_is_not_one_creates_an_item(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);
        $shop = MenuItem::query()->sole();

        $menu->afterAdminSave(['tree' => [
            $this->item('Shop', '/shop', [], ['id' => 'abc']),
            $this->item('Blog', '/blog', [], ['id' => null]),
            $this->item('News', '/news', [], ['id' => 987654]),
        ]]);

        $this->assertSame(['Shop', 'Blog', 'News'], MenuItem::query()->orderBy('sort')->pluck('title')->all());
        $this->assertNull(MenuItem::query()->find($shop->id), 'the old item is gone: no id of it came');
    }

    public function test_one_item_id_cannot_be_used_twice(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);
        $shop = MenuItem::query()->sole();

        $menu->afterAdminSave(['tree' => [
            $this->item('Shop', '/shop', [], ['id' => $shop->id]),
            $this->item('Copy', '/copy', [], ['id' => $shop->id]),
        ]]);

        $this->assertSame(['Shop', 'Copy'], MenuItem::query()->orderBy('sort')->pluck('title')->all());
        $this->assertSame($shop->id, MenuItem::query()->where('title', 'Shop')->value('id'));
        $this->assertNotSame($shop->id, MenuItem::query()->where('title', 'Copy')->value('id'));
    }

    public function test_an_item_moves_to_another_parent_with_its_id(): void
    {
        $menu = $this->menu('header', [$this->item('A', '/a', [$this->item('Moving', '/m')]), $this->item('B', '/b')]);
        $moving = MenuItem::query()->where('title', 'Moving')->sole();
        $b = MenuItem::query()->where('title', 'B')->sole();

        $menu->afterAdminSave(['tree' => [
            $this->item('A', '/a', [], ['id' => MenuItem::query()->where('title', 'A')->value('id')]),
            $this->item('B', '/b', [$this->item('Moving', '/m', [], ['id' => $moving->id])], ['id' => $b->id]),
        ]]);

        $this->assertSame($b->id, $moving->fresh()?->parent_id);
        $this->assertSame(3, MenuItem::query()->count());
    }

    public function test_items_that_left_the_tree_are_deleted_and_other_menus_are_left_alone(): void
    {
        $other = $this->menu('footer', [$this->item('Other', '/other')]);
        $menu = $this->menu('header', [$this->item('Keep', '/keep'), $this->item('Drop', '/drop', [$this->item('Dropped child', '/dc')])]);
        $keep = MenuItem::query()->where('title', 'Keep')->sole();

        $menu->afterAdminSave(['tree' => [$this->item('Keep', '/keep', [], ['id' => $keep->id])]]);

        $this->assertSame(['Keep'], MenuItem::query()->where('menu_id', $menu->id)->pluck('title')->all());
        $this->assertSame(['Other'], MenuItem::query()->where('menu_id', $other->id)->pluck('title')->all());
    }

    public function test_an_empty_tree_deletes_every_item(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);

        $menu->afterAdminSave(['tree' => []]);

        $this->assertSame(0, MenuItem::query()->count());
    }

    /**
     * @return array<string,array{0:array<string,mixed>}>
     */
    public static function savesWithoutATree(): array
    {
        return [
            'no tree' => [['name' => 'Renamed']],
            'a tree that is null' => [['tree' => null]],
            'a tree that is a string' => [['tree' => 'nothing']],
            'a tree that is a number' => [['tree' => 0]],
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     */
    #[DataProvider('savesWithoutATree')]
    public function test_a_save_without_a_tree_leaves_the_items_as_they_are(array $data): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);

        $menu->afterAdminSave($data);

        $this->assertSame(['Shop'], MenuItem::query()->pluck('title')->all());
    }

    public function test_the_items_the_menu_holds_are_read_again_after_a_save(): void
    {
        $menu = $this->menu('header', [$this->item('Old', '/old')]);
        $this->assertSame(['Old'], $menu->items->pluck('title')->all());

        $menu->afterAdminSave(['tree' => [$this->item('New', '/new')]]);

        $this->assertSame(['New'], $menu->items->pluck('title')->all());
    }

    public function test_a_save_that_fails_changes_nothing(): void
    {
        $menu = $this->menu('header', [$this->item('Old', '/old')]);
        MenuItem::creating(function (MenuItem $item): void {
            if ($item->title === 'Boom') {
                throw new RuntimeException('Cannot save this one.');
            }
        });

        try {
            $menu->afterAdminSave(['tree' => [$this->item('Fine', '/fine'), $this->item('Boom', '/boom')]]);
            $this->fail('The save should have failed.');
        } catch (RuntimeException) {
            $this->assertSame(['Old'], MenuItem::query()->pluck('title')->all());
        }
    }

    public function test_deleting_a_menu_deletes_its_items_and_only_its_own(): void
    {
        $other = $this->menu('footer', [$this->item('Other', '/other')]);
        $menu = $this->menu('header', [$this->item('Shop', '/shop', [$this->item('Phones', '/phones')])]);

        $menu->delete();

        $this->assertSame(['Other'], MenuItem::query()->pluck('title')->all());
        $this->assertSame($other->id, MenuItem::query()->sole()->menu_id);
    }

    public function test_the_tree_is_read_back_as_the_form_and_the_api_see_it(): void
    {
        $delivery = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $menu = $this->menu('header', [
            $this->item('Shop', '/shop', [
                $this->item('Delivery', null, [], ['link' => ['type' => 'record', 'model' => Article::class, 'id' => $delivery->id], 'target' => '_blank']),
            ], ['icon' => 'icon-shop', 'source' => Section::class, 'source_depth' => 3]),
            $this->item('Help', null, [], ['active' => false]),
        ]);
        $ids = MenuItem::query()->pluck('id', 'title');

        $this->assertSame([
            [
                'id' => $ids['Shop'],
                'title' => 'Shop',
                'link' => ['type' => 'url', 'url' => '/shop'],
                'target' => '',
                'icon' => 'icon-shop',
                'active' => true,
                'source' => Section::class,
                'source_depth' => 3,
                'children' => [
                    [
                        'id' => $ids['Delivery'],
                        'title' => 'Delivery',
                        'link' => ['type' => 'record', 'model' => Article::class, 'id' => $delivery->id, 'label' => 'Delivery'],
                        'target' => '_blank',
                        'icon' => '',
                        'active' => true,
                        'source' => '',
                        'source_depth' => 1,
                        'children' => [],
                    ],
                ],
            ],
            [
                'id' => $ids['Help'],
                'title' => 'Help',
                'link' => null,
                'target' => '',
                'icon' => '',
                'active' => false,
                'source' => '',
                'source_depth' => 1,
                'children' => [],
            ],
        ], $menu->fresh()?->tree);
    }

    public function test_the_tree_is_part_of_the_menu_as_an_array_and_as_json(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);

        $this->assertSame('Shop', $menu->fresh()?->toArray()['tree'][0]['title']);
        $this->assertSame('Shop', json_decode((string) $menu->fresh()?->toJson(), true)['tree'][0]['title']);
    }

    public function test_a_menu_that_is_not_saved_has_no_tree(): void
    {
        $this->assertSame([], (new Menu)->tree);
        $this->assertSame([], (new Menu(['code' => 'x']))->toArray()['tree']);
    }

    public function test_the_tree_comes_in_the_order_of_sort_and_then_of_numbers(): void
    {
        $menu = Menu::query()->create(['code' => 'header', 'name' => 'Menu', 'active' => true]);

        foreach ([['Third', 30], ['First tied', 10], ['Second tied', 10], ['Early', 5]] as [$title, $sort]) {
            MenuItem::query()->create(['menu_id' => $menu->id, 'title' => $title, 'url' => '/x', 'sort' => $sort]);
        }

        $this->assertSame(['Early', 'First tied', 'Second tied', 'Third'], array_column($menu->tree, 'title'));
    }

    public function test_an_address_wins_over_a_record_in_the_tree(): void
    {
        $menu = $this->menu('header', [$this->item('Both', '/both')]);
        MenuItem::query()->update(['linkable_type' => Article::class, 'linkable_id' => 1]);

        $this->assertSame(['type' => 'url', 'url' => '/both'], $menu->tree[0]['link']);
    }

    public function test_the_tree_names_the_records_of_every_module_with_one_read_each(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $section = Section::query()->create(['name' => 'Phones', 'code' => 'phones']);
        $menu = $this->menu('header', [
            $this->item('A', null, [], ['link' => ['type' => 'record', 'model' => Article::class, 'id' => $article->id]]),
            $this->item('S', null, [], ['link' => ['type' => 'record', 'model' => Section::class, 'id' => $section->id]]),
            $this->item('Again', null, [], ['link' => ['type' => 'record', 'model' => Article::class, 'id' => $article->id]]),
        ]);

        $labels = array_map(fn (array $item): string => $item['link']['label'], $menu->tree);

        $this->assertSame(['Delivery', 'Phones', 'Delivery'], $labels);
    }

    public function test_a_record_that_is_gone_or_a_class_that_is_not_there_is_named_by_its_number(): void
    {
        $gone = Article::query()->create(['name' => 'Gone', 'code' => 'gone']);
        $menu = $this->menu('header', [
            $this->item('Gone', null, [], ['link' => ['type' => 'record', 'model' => Article::class, 'id' => $gone->id]]),
            $this->item('Ghost', null, [], ['link' => ['type' => 'record', 'model' => 'No\\Such\\Model', 'id' => 42]]),
        ]);
        $goneId = $gone->id;
        $gone->delete();

        $labels = array_map(fn (array $item): string => $item['link']['label'], $menu->fresh()?->tree ?? []);

        $this->assertSame(['#'.$goneId, '#42'], $labels);
    }

    public function test_a_tree_stops_at_fifty_levels(): void
    {
        $menu = Menu::query()->create(['code' => 'header', 'name' => 'Menu', 'active' => true]);
        $parent = null;

        for ($level = 1; $level <= 55; $level++) {
            $parent = MenuItem::query()->create(['menu_id' => $menu->id, 'parent_id' => $parent?->id, 'title' => 'Level '.$level, 'url' => '/l', 'sort' => 10]);
        }

        $depth = 0;

        for ($nodes = $menu->tree; $nodes !== []; $nodes = $nodes[0]['children']) {
            $depth++;
        }

        $this->assertSame(50, $depth);
    }

    public function test_the_language_of_a_form_is_empty_for_every_language_and_a_code_otherwise(): void
    {
        $menu = new Menu;

        $this->assertNull($menu->transformAdminData(['code' => 'header', 'language' => ''])['language']);
        $this->assertSame('de', $menu->transformAdminData(['code' => 'header', 'language' => 'de'])['language']);
        $this->assertNull($menu->transformAdminData(['code' => 'header', 'language' => 123])['language']);
        $this->assertNull($menu->transformAdminData(['code' => 'header', 'language' => null])['language']);
        $this->assertArrayNotHasKey('language', $menu->transformAdminData(['code' => 'header']));
    }

    public function test_other_fields_of_a_form_pass_through(): void
    {
        $data = ['code' => 'header', 'name' => 'Main', 'active' => true, 'tree' => [['title' => 'Shop']]];

        $this->assertSame($data, (new Menu)->transformAdminData($data));
    }

    /**
     * @return array<string,array{0:string,1:string|null,2:bool}>
     */
    public static function newMenus(): array
    {
        return [
            'the same code for every language, taken' => ['header', null, true],
            'the same code in a language, free' => ['header', 'de', false],
            'another code for every language' => ['footer', null, false],
            'another code in a language' => ['footer', 'de', false],
        ];
    }

    #[DataProvider('newMenus')]
    public function test_a_code_taken_for_every_language_is_refused_for_it_only(string $code, ?string $language, bool $refused): void
    {
        $this->menu('header', [], null);

        $this->assertSame($refused, $this->refused(new Menu, ['code' => $code, 'language' => (string) $language]));
    }

    /**
     * @return array<string,array{0:string,1:string|null,2:bool}>
     */
    public static function newMenusAmongLanguages(): array
    {
        return [
            'the same code in the same language, taken' => ['header', 'de', true],
            'the same code in another language, free' => ['header', 'ru', false],
            'the same code for every language, free' => ['header', null, false],
            'another code in the same language' => ['footer', 'de', false],
        ];
    }

    #[DataProvider('newMenusAmongLanguages')]
    public function test_a_code_taken_in_a_language_is_refused_for_that_language_only(string $code, ?string $language, bool $refused): void
    {
        $this->menu('header', [], 'de');

        $this->assertSame($refused, $this->refused(new Menu, ['code' => $code, 'language' => (string) $language]));
    }

    public function test_a_menu_does_not_clash_with_itself(): void
    {
        $menu = $this->menu('header', []);

        $this->assertFalse($this->refused($menu, ['code' => 'header', 'language' => '']));
        $this->assertFalse($this->refused($menu, ['name' => 'Renamed']), 'a save without a code keeps the code it has');
        $this->assertFalse($this->refused($menu, ['language' => '']), 'a save without a code or a language keeps both');
    }

    public function test_a_menu_clashes_with_another_when_its_code_or_language_changes(): void
    {
        $this->menu('header', [], 'de');
        $this->menu('footer', [], 'de');
        $menu = $this->menu('header', [], 'ru');

        $this->assertTrue($this->refused($menu, ['language' => 'de']), 'the language changes to one where the code is taken');
        $this->assertFalse($this->refused($menu, ['language' => 'fr']));
        $this->assertFalse($this->refused($menu, ['code' => 'footer']), 'footer is taken in German, not in Russian');
        $this->assertTrue($this->refused($menu, ['code' => 'footer', 'language' => 'de']));
    }

    public function test_a_menu_renamed_to_a_code_that_is_taken_is_refused(): void
    {
        $this->menu('footer', [], 'ru');
        $menu = $this->menu('header', [], 'ru');

        $this->assertTrue($this->refused($menu, ['code' => 'footer']));
        $this->assertFalse($this->refused($menu, ['code' => 'sidebar']));
        $this->assertFalse($this->refused($menu, ['name' => 'Renamed']), 'it does not clash with itself');
    }

    public function test_the_site_is_never_taken_from_the_data(): void
    {
        $this->assertArrayNotHasKey('site_id', (new Menu)->transformAdminData(['code' => 'header', 'site_id' => 99, 'name' => 'Menu']));
        $this->assertSame(['code' => 'header', 'name' => 'Menu'], (new Menu)->transformAdminData(['code' => 'header', 'site_id' => 99, 'name' => 'Menu']));
    }

    public function test_the_refusal_names_the_code_field(): void
    {
        $this->menu('header', []);

        try {
            (new Menu)->transformAdminData(['code' => 'header', 'language' => '']);
            $this->fail('The code is taken.');
        } catch (ValidationException $e) {
            $this->assertSame(['code' => ['A menu with this code already exists for this language.']], $e->errors());
        }
    }

    public function test_a_code_taken_on_another_site_is_free_here(): void
    {
        $sites = app(Sites::class);
        $second = Site::query()->create(['code' => 'de', 'name' => 'Deutschland', 'domains' => ['example.de'], 'languages' => ['de']]);
        $sites->flush();

        $sites->setCurrent($sites->default());
        $this->menu('header', []);

        $sites->setCurrent($second);
        $this->assertFalse($this->refused(new Menu, ['code' => 'header', 'language' => '']));

        $sites->setCurrent($sites->default());
        $this->assertTrue($this->refused(new Menu, ['code' => 'header', 'language' => '']));
    }

    public function test_the_menu_is_labelled_for_the_panel(): void
    {
        $this->assertSame('Menus', Menu::getAdminLabel());
        $this->assertSame('Add menu', Menu::getAdminCreateLabel());
        $this->assertSame('code', Menu::getDefaultSortColumn());
        $this->assertSame('asc', Menu::getDefaultSortDirection());
    }

    public function test_an_item_knows_its_menu(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);

        $this->assertSame($menu->id, MenuItem::query()->sole()->menu->id);
    }

    public function test_the_relation_and_the_accessors_are_public(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', '/shop')]);

        $this->assertSame($menu->id, MenuItem::query()->sole()->menu()->sole()->id);
        $this->assertSame($menu->tree, $menu->getTreeAttribute());
        $this->assertSame('Shop', $menu->getTreeAttribute()[0]['title']);
        $this->assertSame([], (new Menu)->getTreeAttribute());
        // Eloquent forwards a static call to a protected method too, so only
        // the declaration tells.
        $this->assertTrue((new \ReflectionMethod(Menu::class, 'itemFields'))->isPublic());
        $this->assertTrue((new \ReflectionMethod(Menu::class, 'itemFields'))->isStatic());
        $this->assertSame(['title', 'link', 'target', 'icon', 'active', 'source', 'source_depth'], array_map(
            fn ($field): string => $field->getName(),
            Menu::itemFields(),
        ));
    }

    /**
     * @return array<string,array{0:string,1:mixed,2:mixed}>
     */
    public static function casts(): array
    {
        return [
            'menu id' => ['menu_id', '7', 7],
            'parent id' => ['parent_id', '8', 8],
            'sort' => ['sort', '30', 30],
            'linked id' => ['linkable_id', '9', 9],
            'source depth' => ['source_depth', '3', 3],
            'active as on' => ['active', 1, true],
            'active as off' => ['active', 0, false],
        ];
    }

    #[DataProvider('casts')]
    public function test_an_item_reads_its_columns_as_the_right_types(string $column, mixed $stored, mixed $read): void
    {
        $item = new MenuItem;
        $item->setRawAttributes([$column => $stored]);

        $this->assertSame($read, $item->getAttribute($column));
    }

    public function test_an_item_is_written_with_its_link_as_it_came(): void
    {
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $menu = $this->menu('header', [$this->item('Delivery', null, [], ['link' => ['type' => 'record', 'model' => Article::class, 'id' => (string) $article->id]])]);

        $item = $menu->items()->sole();

        $this->assertSame(Article::class, $item->linkable_type);
        $this->assertSame($article->id, $item->linkable_id);
    }

    public function test_an_item_without_a_depth_unfolds_one_level(): void
    {
        $menu = $this->menu('header', [$this->item('Shop', null, [], ['source' => Section::class])]);

        $this->assertSame(1, $menu->items()->sole()->source_depth);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function refused(Menu $menu, array $data): bool
    {
        try {
            $menu->transformAdminData($data);

            return false;
        } catch (ValidationException) {
            return true;
        }
    }
}
