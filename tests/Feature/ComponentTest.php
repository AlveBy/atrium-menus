<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Menus\Tests\Feature;

use Atrium\Admin\Models\Language;
use Atrium\Menus\Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * <x-atrium-menu> and atrium_menu(): what the markup says and what it never
 * lets through.
 */
final class ComponentTest extends TestCase
{
    public function test_the_class_and_the_label_can_be_given(): void
    {
        $this->menu('footer', [$this->item('Privacy', '/privacy')], name: 'Footer links');

        $html = Blade::render('<x-atrium-menu code="footer" class="footer-menu" label="Site map" />');

        $this->assertStringContainsString('<nav class="footer-menu" aria-label="Site map" data-menu="footer">', $html);
    }

    public function test_the_defaults_are_the_menu_class_and_the_name_of_the_menu(): void
    {
        $this->menu('footer', [$this->item('Privacy', '/privacy')], name: 'Footer links');

        $html = Blade::render('<x-atrium-menu code="footer" />');

        $this->assertStringContainsString('<nav class="menu" aria-label="Footer links" data-menu="footer">', $html);
    }

    public function test_extra_attributes_are_added_to_the_nav(): void
    {
        $this->menu('footer', [$this->item('Privacy', '/privacy')]);

        $html = Blade::render('<x-atrium-menu code="footer" id="bottom" class="wide" />');

        $this->assertStringContainsString('id="bottom"', $html);
        $this->assertStringContainsString('class="wide"', $html);
    }

    public function test_the_language_prop_picks_the_menu_of_that_language(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch']);
        $this->menu('footer', [$this->item('Privacy', '/privacy')]);
        $this->menu('footer', [$this->item('Datenschutz', '/datenschutz')], 'de');

        $this->assertStringContainsString('Privacy', Blade::render('<x-atrium-menu code="footer" />'));
        $this->assertStringNotContainsString('Datenschutz', Blade::render('<x-atrium-menu code="footer" />'));

        $html = Blade::render('<x-atrium-menu code="footer" language="de" />');

        $this->assertStringContainsString('Datenschutz', $html);
        $this->assertStringNotContainsString('Privacy', $html);
    }

    public function test_a_menu_without_visible_items_draws_nothing(): void
    {
        $this->menu('empty', []);
        $this->menu('hidden', [$this->item('Secret', '/secret', [], ['active' => false])]);

        $this->assertSame('', trim(Blade::render('<x-atrium-menu code="empty" />')));
        $this->assertSame('', trim(Blade::render('<x-atrium-menu code="hidden" />')));
        $this->assertSame('', trim(Blade::render('<x-atrium-menu code="missing" />')));
    }

    public function test_a_menu_with_a_single_item_is_drawn(): void
    {
        $this->menu('one', [$this->item('Only', '/only')]);

        $this->assertStringContainsString('Only', Blade::render('<x-atrium-menu code="one" />'));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function hostileText(): array
    {
        return [
            'a script tag' => ['<script>alert(1)</script>'],
            'a broken attribute' => ['" onmouseover="alert(1)'],
            'an image with an error handler' => ['<img src=x onerror=alert(1)>'],
        ];
    }

    #[DataProvider('hostileText')]
    public function test_a_title_is_escaped(string $title): void
    {
        $this->menu('header', [$this->item($title, '/a'), $this->item($title)]);

        $html = Blade::render('<x-atrium-menu code="header" />');

        $this->assertStringNotContainsString($title, $html);
        $this->assertStringContainsString(e($title), $html);
    }

    #[DataProvider('hostileText')]
    public function test_a_menu_name_and_a_label_are_escaped(string $text): void
    {
        $this->menu('header', [$this->item('Shop', '/shop')], name: $text);

        $this->assertStringNotContainsString($text, Blade::render('<x-atrium-menu code="header" />'));
        $this->assertStringNotContainsString($text, Blade::render('<x-atrium-menu code="header" :label="$label" />', ['label' => $text]));
        $this->assertStringContainsString(e($text), Blade::render('<x-atrium-menu code="header" :label="$label" />', ['label' => $text]));
    }

    #[DataProvider('hostileText')]
    public function test_an_address_and_an_icon_are_escaped(string $text): void
    {
        $this->menu('header', [$this->item('Shop', '/a?x='.$text, [], ['icon' => $text])]);

        $html = Blade::render('<x-atrium-menu code="header" />');

        $this->assertStringNotContainsString($text, $html);
    }

    public function test_the_helper_gives_the_menu_in_the_language_asked(): void
    {
        Language::query()->create(['code' => 'de', 'name' => 'Deutsch']);
        $this->menu('footer', [$this->item('Privacy', '/privacy')], name: 'Links');
        $this->menu('footer', [$this->item('Datenschutz', '/datenschutz')], 'de', 'Verweise');

        $this->assertSame('Links', atrium_menu('footer')['name'] ?? null);
        $this->assertSame('Verweise', atrium_menu('footer', 'de')['name'] ?? null);
        $this->assertSame(['Datenschutz' => []], $this->titles(atrium_menu('footer', 'de')['items'] ?? []));
        $this->assertNull(atrium_menu('missing'));
    }
}
