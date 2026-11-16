<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Menus\Models\Menu;
use Atrium\Menus\Tests\Fixtures\Article;
use Atrium\Menus\Tests\Fixtures\Event;
use Atrium\Menus\Tests\Fixtures\Page;
use Atrium\Menus\Tests\Fixtures\Section;
use Atrium\Menus\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * Which records an item that unfolds a module lists, in what order, how
 * deep, and under what name.
 */
final class UnfoldTest extends TestCase
{
    /**
     * @return array<string,array{0:string|null,1:string|null,2:bool}>
     */
    public static function datedEvents(): array
    {
        return [
            'no dates' => [null, null, true],
            'started yesterday' => ['2026-03-09 12:00:00', null, true],
            'starts tomorrow' => ['2026-03-11 12:00:00', null, false],
            'starts this very second' => ['2026-03-10 12:00:00', null, true],
            'starts a second from now' => ['2026-03-10 12:00:01', null, false],
            'ended yesterday' => [null, '2026-03-09 12:00:00', false],
            'ends this very second' => [null, '2026-03-10 12:00:00', true],
            'ended a second ago' => [null, '2026-03-10 11:59:59', false],
            'ends tomorrow' => [null, '2026-03-11 12:00:00', true],
            'inside its dates' => ['2026-03-09 00:00:00', '2026-03-11 00:00:00', true],
            'its dates are over' => ['2026-03-01 00:00:00', '2026-03-05 00:00:00', false],
            'its dates have not come' => ['2026-03-15 00:00:00', '2026-03-20 00:00:00', false],
            'started, but ended' => ['2026-03-01 00:00:00', '2026-03-09 00:00:00', false],
        ];
    }

    #[DataProvider('datedEvents')]
    public function test_a_record_shows_only_within_its_dates(?string $from, ?string $to, bool $shown): void
    {
        $this->travelTo('2026-03-10 12:00:00');
        Event::query()->create(['name' => 'Party', 'code' => 'party', 'active_from' => $from, 'active_to' => $to]);
        $this->menu('header', [$this->item('Events', '/events', [], ['source' => Event::class])]);

        $this->assertSame(['Events' => $shown ? ['Party' => []] : []], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_an_event_has_the_name_and_the_address_of_its_page(): void
    {
        Event::query()->create(['name' => 'Party', 'code' => 'party']);
        $this->menu('header', [$this->item('Events', '/events', [], ['source' => Event::class])]);

        $child = $this->resolve('header')['items'][0]['children'][0] ?? [];

        $this->assertSame([
            'title' => 'Party',
            'url' => 'http://localhost/events/party',
            'target' => null,
            'icon' => null,
            'current' => false,
            'active' => false,
            'children' => [],
        ], $child);
    }

    public function test_a_record_is_named_in_the_language_of_the_menu(): void
    {
        Event::query()->create(['name' => 'Concert', 'code' => 'concert', 'created_at' => '2026-01-01 00:00:00']);
        $party = Event::query()->create(['name' => 'Party', 'code' => 'party', 'created_at' => '2026-01-02 00:00:00']);
        $party->setTranslation('name', 'de', 'Fest')->save();
        $this->menu('header', [$this->item('Events', '/events', [], ['source' => Event::class])]);

        $this->assertSame(['Events' => ['Concert' => [], 'Party' => []]], $this->titles($this->resolve('header', '/', 'en')['items'] ?? []));
        $this->assertSame(['Events' => ['Fest' => []]], $this->titles($this->resolve('header', '/', 'de')['items'] ?? []), 'a record not written in German has no page in German');
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function names(): array
    {
        return [
            'plain' => ['Garden chair', 'Garden chair'],
            'padded' => ['  Garden chair  ', 'Garden chair'],
            'tabs and breaks around' => ["\t\nGarden chair\r\n", 'Garden chair'],
            'empty' => ['', '#{id}'],
            'only spaces' => ['   ', '#{id}'],
            'only a line break' => ["\n", '#{id}'],
            'zero' => ['0', '0'],
            'Cyrillic' => ['Садовый стул', 'Садовый стул'],
            'Chinese' => ['花园椅', '花园椅'],
            'Arabic' => ['كرسي حديقة', 'كرسي حديقة'],
            'emoji' => ['Chair 🪑', 'Chair 🪑'],
            'HTML stays text' => ['<b>Chair</b>', '<b>Chair</b>'],
        ];
    }

    #[DataProvider('names')]
    public function test_a_record_is_listed_under_its_name_or_its_number(string $name, string $expected): void
    {
        $article = Article::query()->create(['name' => $name, 'code' => 'chair']);
        $this->menu('footer', [$this->item('Articles', null, [], ['source' => Article::class])]);

        $this->assertSame(['Articles' => [str_replace('{id}', (string) $article->id, $expected) => []]], $this->titles($this->resolve('footer')['items'] ?? []));
    }

    public function test_a_record_without_a_page_is_not_listed(): void
    {
        Article::query()->create(['name' => 'Nowhere', 'code' => '']);
        Article::query()->create(['name' => 'Fine', 'code' => 'fine']);
        $this->menu('footer', [$this->item('Articles', null, [], ['source' => Article::class])]);

        $this->assertSame(['Articles' => ['Fine' => []]], $this->titles($this->resolve('footer')['items'] ?? []));
    }

    public function test_records_come_in_their_sort_order_and_then_by_number(): void
    {
        Article::query()->create(['name' => 'Third', 'code' => 'c', 'sort' => 30]);
        Article::query()->create(['name' => 'First tied', 'code' => 'a', 'sort' => 10]);
        Article::query()->create(['name' => 'Second tied', 'code' => 'b', 'sort' => 10]);
        $this->menu('footer', [$this->item('Articles', null, [], ['source' => Article::class])]);

        $this->assertSame(['First tied', 'Second tied', 'Third'], array_keys($this->titles($this->resolve('footer')['items'] ?? [])['Articles']));
    }

    public function test_records_of_a_module_without_sort_come_in_the_order_of_their_numbers(): void
    {
        Event::query()->create(['name' => 'One', 'code' => 'one']);
        Event::query()->create(['name' => 'Two', 'code' => 'two']);
        $this->menu('footer', [$this->item('Events', null, [], ['source' => Event::class])]);

        DB::enableQueryLog();
        $titles = $this->titles($this->resolve('footer')['items'] ?? []);
        $reads = array_values(array_filter(array_column(DB::getQueryLog(), 'query'), static fn (string $sql): bool => preg_match('/from\W+menu_test_events\W/i', $sql) === 1));
        DB::disableQueryLog();

        $this->assertSame(['Events' => ['One' => [], 'Two' => []]], $titles);
        $this->assertCount(1, $reads);
        $this->assertMatchesRegularExpression('/order by\s+\W?id\W?\s+asc/i', $reads[0]);
        $this->assertStringNotContainsString('sort', $reads[0]);
        $this->assertStringNotContainsString('active"', $reads[0]);
        $this->assertStringNotContainsString('active`', $reads[0]);
    }

    public function test_the_order_by_sort_and_then_by_number_is_asked_of_the_database(): void
    {
        Article::query()->create(['name' => 'One', 'code' => 'one']);
        $this->menu('footer', [$this->item('Articles', null, [], ['source' => Article::class])]);

        DB::enableQueryLog();
        $this->resolve('footer');
        $reads = array_values(array_filter(array_column(DB::getQueryLog(), 'query'), static fn (string $sql): bool => preg_match('/from\W+menu_test_articles\W/i', $sql) === 1));
        DB::disableQueryLog();

        $this->assertCount(1, $reads);
        $this->assertMatchesRegularExpression('/order by\s+\W?sort\W?\s+asc,\s*\W?id\W?\s+asc/i', $reads[0]);
    }

    public function test_at_most_five_hundred_records_are_listed(): void
    {
        $rows = [];

        for ($number = 1; $number <= 501; $number++) {
            $rows[] = ['name' => 'Article '.$number, 'code' => 'article-'.$number, 'active' => true, 'sort' => $number];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('menu_test_articles')->insert($chunk);
        }

        $this->menu('footer', [$this->item('Articles', null, [], ['source' => Article::class])]);
        $children = $this->resolve('footer')['items'][0]['children'] ?? [];

        $this->assertCount(500, $children);
        $this->assertSame('Article 1', $children[0]['title']);
        $this->assertSame('Article 500', $children[499]['title']);
    }

    /**
     * @return array<string,array{0:int,1:array<string,mixed>}>
     */
    public static function levels(): array
    {
        return [
            'one level' => [1, ['Top' => [], 'Other' => []]],
            'two levels' => [2, ['Top' => ['Second' => []], 'Other' => []]],
            'three levels' => [3, ['Top' => ['Second' => ['Third' => []]], 'Other' => []]],
            'four levels' => [4, ['Top' => ['Second' => ['Third' => ['Fourth' => []]]], 'Other' => []]],
            'five levels, there are four' => [5, ['Top' => ['Second' => ['Third' => ['Fourth' => []]]], 'Other' => []]],
        ];
    }

    /**
     * @param  array<string,mixed>  $expected
     */
    #[DataProvider('levels')]
    public function test_a_tree_is_unfolded_to_the_depth_of_the_item(int $depth, array $expected): void
    {
        $top = Section::query()->create(['name' => 'Top', 'code' => 'top', 'sort' => 10]);
        $second = Section::query()->create(['name' => 'Second', 'code' => 'second', 'parent_id' => $top->id]);
        $third = Section::query()->create(['name' => 'Third', 'code' => 'third', 'parent_id' => $second->id]);
        Section::query()->create(['name' => 'Fourth', 'code' => 'fourth', 'parent_id' => $third->id]);
        Section::query()->create(['name' => 'Other', 'code' => 'other', 'sort' => 20]);
        $this->menu('header', [$this->item('Shop', '/shop', [], ['source' => Section::class, 'source_depth' => $depth])]);

        $this->assertSame(['Shop' => $expected], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_the_addresses_of_a_tree_follow_the_sections_above(): void
    {
        $top = Section::query()->create(['name' => 'Top', 'code' => 'top']);
        Section::query()->create(['name' => 'Second', 'code' => 'second', 'parent_id' => $top->id]);
        $this->menu('header', [$this->item('Shop', '/shop', [], ['source' => Section::class, 'source_depth' => 2])]);

        $top = $this->resolve('header')['items'][0]['children'][0] ?? [];

        $this->assertSame('http://localhost/shop/top', $top['url'] ?? null);
        $this->assertSame('http://localhost/shop/top/second', $top['children'][0]['url'] ?? null);
    }

    public function test_a_section_that_is_hidden_takes_its_sections_with_it(): void
    {
        $off = Section::query()->create(['name' => 'Off', 'code' => 'off', 'active' => false, 'sort' => 10]);
        Section::query()->create(['name' => 'Below off', 'code' => 'below-off', 'parent_id' => $off->id]);
        Section::query()->create(['name' => 'On', 'code' => 'on', 'sort' => 20]);
        $this->menu('header', [$this->item('Shop', '/shop', [], ['source' => Section::class, 'source_depth' => 3])]);

        $this->assertSame(['Shop' => ['On' => []]], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_a_section_without_a_page_takes_its_sections_with_it(): void
    {
        $nowhere = Section::query()->create(['name' => 'Nowhere', 'code' => '', 'sort' => 10]);
        Section::query()->create(['name' => 'Below nowhere', 'code' => 'below', 'parent_id' => $nowhere->id]);
        Section::query()->create(['name' => 'Fine', 'code' => 'fine', 'sort' => 20]);
        $this->menu('header', [$this->item('Shop', '/shop', [], ['source' => Section::class, 'source_depth' => 3])]);

        $this->assertSame(['Shop' => ['Fine' => []]], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_an_item_linked_to_a_section_lists_what_is_below_that_section_only(): void
    {
        $top = Section::query()->create(['name' => 'Top', 'code' => 'top']);
        $second = Section::query()->create(['name' => 'Second', 'code' => 'second', 'parent_id' => $top->id]);
        Section::query()->create(['name' => 'Third', 'code' => 'third', 'parent_id' => $second->id]);
        Section::query()->create(['name' => 'Elsewhere', 'code' => 'elsewhere']);
        $this->menu('header', [$this->item('Second', null, [], [
            'link' => ['type' => 'record', 'model' => Section::class, 'id' => $second->id],
            'source' => Section::class,
            'source_depth' => 1,
        ])]);

        $this->assertSame(['Second' => ['Third' => []]], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_an_item_linked_to_a_record_of_another_module_lists_the_top_of_the_tree(): void
    {
        $top = Section::query()->create(['name' => 'Top', 'code' => 'top']);
        Section::query()->create(['name' => 'Second', 'code' => 'second', 'parent_id' => $top->id]);
        $article = Article::query()->create(['name' => 'Delivery', 'code' => 'delivery']);
        $this->menu('header', [$this->item('Delivery', null, [], [
            'link' => ['type' => 'record', 'model' => Article::class, 'id' => $article->id],
            'source' => Section::class,
        ])]);

        $this->assertSame(['Delivery' => ['Top' => []]], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_an_item_linked_to_a_record_of_a_flat_module_lists_all_its_records(): void
    {
        $first = Article::query()->create(['name' => 'First', 'code' => 'first']);
        Article::query()->create(['name' => 'Second', 'code' => 'second']);
        $this->menu('header', [$this->item('First', null, [], [
            'link' => ['type' => 'record', 'model' => Article::class, 'id' => $first->id],
            'source' => Article::class,
        ])]);

        $this->assertSame(['First' => ['First' => [], 'Second' => []]], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_a_flat_module_is_listed_on_one_level_whatever_the_depth(): void
    {
        Article::query()->create(['name' => 'First', 'code' => 'first']);
        Article::query()->create(['name' => 'Second', 'code' => 'second']);
        $this->menu('header', [$this->item('Articles', null, [], ['source' => Article::class, 'source_depth' => 3])]);

        $this->assertSame(['Articles' => ['First' => [], 'Second' => []]], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_the_records_follow_the_children_of_the_item(): void
    {
        Article::query()->create(['name' => 'Record', 'code' => 'record']);
        $this->menu('header', [$this->item('Articles', null, [$this->item('Own', '/own')], ['source' => Article::class])]);

        $this->assertSame(['Articles' => ['Own' => [], 'Record' => []]], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_a_module_that_answers_for_its_own_addresses_is_listed(): void
    {
        Page::query()->create(['name' => 'About', 'code' => 'about', 'sort' => 10]);
        Page::query()->create(['name' => 'Nowhere', 'code' => '', 'sort' => 20]);
        Page::query()->create(['name' => 'Draft', 'code' => 'draft', 'active' => false, 'sort' => 30]);
        $this->menu('header', [$this->item('Pages', null, [], ['source' => Page::class, 'source_depth' => 2])]);

        $children = $this->resolve('header')['items'][0]['children'] ?? [];

        $this->assertSame(['About'], array_column($children, 'title'));
        $this->assertSame('http://localhost/pages/about', $children[0]['url']);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function noSources(): array
    {
        return [
            'a class that is not there' => ['No\\Such\\Model'],
            'a class that is not a model' => [stdClass::class],
            'a model without pages' => [Menu::class],
        ];
    }

    #[DataProvider('noSources')]
    public function test_an_item_whose_source_is_no_module_with_pages_lists_nothing(string $source): void
    {
        config()->set('atrium.public_urls.'.stdClass::class, ['prefix' => 'objects']);
        $this->menu('other', [$this->item('Sidebar', '/side')]);
        $this->menu('header', [$this->item('Things', '/things', [$this->item('Own', '/own')], ['source' => $source])]);

        $this->assertSame(['Things' => ['Own' => []]], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_the_records_of_an_inactive_item_are_not_listed(): void
    {
        Article::query()->create(['name' => 'Record', 'code' => 'record']);
        $this->menu('header', [$this->item('Articles', null, [], ['source' => Article::class, 'active' => false]), $this->item('Home', '/')]);

        $this->assertSame(['Home' => []], $this->titles($this->resolve('header')['items'] ?? []));
    }

    public function test_every_item_unfolds_its_own_source(): void
    {
        Article::query()->create(['name' => 'Record', 'code' => 'record']);
        Section::query()->create(['name' => 'Top', 'code' => 'top']);
        $this->menu('header', [
            $this->item('Articles', null, [], ['source' => Article::class]),
            $this->item('Sections', '/shop', [$this->item('Nested', null, [], ['source' => Article::class])], ['source' => Section::class]),
            $this->item('Plain', '/plain'),
        ]);

        $this->assertSame([
            'Articles' => ['Record' => []],
            'Sections' => ['Nested' => ['Record' => []], 'Top' => []],
            'Plain' => [],
        ], $this->titles($this->resolve('header')['items'] ?? []));
    }
}
