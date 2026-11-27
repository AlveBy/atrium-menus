<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Builder\BlockType;
use Atrium\Admin\Builder\BlockTypes;
use Atrium\Admin\Facades\Hook;
use Atrium\Admin\Fields\Field;
use Atrium\Menus\Builder\MenuBlock;
use Atrium\Menus\Tests\TestCase;
use Atrium\Theme\Builder\LayoutRenderer;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A menu as a block of a page made with the site builder.
 */
final class SiteBlockTest extends TestCase
{
    private function type(): BlockType
    {
        $type = $this->app->make(BlockTypes::class)->get('menu');
        $this->assertInstanceOf(MenuBlock::class, $type);

        return $type;
    }

    private function accepts(mixed $menu): bool
    {
        return Validator::make(['menu' => $menu], ['menu' => $this->type()->fields()[0]->getValidationRules()])->passes();
    }

    public function test_the_module_offers_its_block_type_at_its_priority(): void
    {
        $probe = new class extends BlockType
        {
            public function label(): string
            {
                return 'Probe';
            }

            public function view(): string
            {
                return 'probe';
            }
        };

        Hook::register(BlockTypes::REGISTRY, 'menu', $probe, 79);
        $this->type();

        Hook::register(BlockTypes::REGISTRY, 'menu', $probe, 80);
        $this->assertSame($probe, $this->app->make(BlockTypes::class)->get('menu'));
    }

    public function test_the_editor_picks_a_menu_by_its_name_and_the_page_keeps_its_code(): void
    {
        $this->menu('zeta', [], name: 'Zeta');
        $this->menu('alpha', [], name: 'Alpha');

        $this->assertSame('Menu', $this->type()->label());
        $this->assertSame([], $this->type()->translatable());

        $fields = array_map(static fn (Field $field): array => $field->toArray(), $this->type()->fields());
        $this->assertCount(1, $fields);
        $this->assertSame(['menu', 'select', 'Menu', true], [$fields[0]['name'], $fields[0]['type'], $fields[0]['label'], $fields[0]['required']]);
        $this->assertSame([['alpha', 'Alpha'], ['zeta', 'Zeta']], array_map(static fn (array $option): array => [$option['value'], $option['label']], $fields[0]['options']));

        $this->assertTrue($this->accepts('alpha'));
        $this->assertFalse($this->accepts('gone'));
        $this->assertFalse($this->accepts(null));
    }

    public function test_a_menu_is_named_by_text_even_when_there_is_none_to_pick(): void
    {
        $this->assertTrue($this->accepts('header'));
        $this->assertFalse($this->accepts(3));
    }

    public function test_a_block_with_a_menu_draws_it(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')], name: 'Main');

        $this->assertSame(['code' => 'header'], $this->type()->data(['menu' => 'header']));

        $page = [['id' => 's1', 'columns' => [['id' => 'c1', 'blocks' => [['id' => 'b1', 'type' => 'menu', 'settings' => ['menu' => 'header']]]]]]];
        $html = $this->app->make(LayoutRenderer::class)->render($page)->toHtml();

        $this->assertStringContainsString('<nav class="menu b-menu" aria-label="Main" data-menu="header">', $html);
        $this->assertStringContainsString('Shop', $html);
    }

    /**
     * @return array<string,array{mixed}>
     */
    public static function noMenus(): array
    {
        return [
            'nothing chosen' => [null],
            'a number' => [7],
            'a list' => [['header']],
            'a menu that is gone' => ['gone'],
            'a menu with no items' => ['empty'],
        ];
    }

    #[DataProvider('noMenus')]
    public function test_a_block_without_a_menu_to_show_is_not_drawn(mixed $menu): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')]);
        $this->menu('empty', []);

        $this->assertNull($this->type()->data(['menu' => $menu]));
        $this->assertNull($this->type()->data([]));
    }

    public function test_a_switched_off_menu_is_not_drawn(): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')])->update(['active' => false]);

        $this->assertNull($this->type()->data(['menu' => 'header']));
    }
}
