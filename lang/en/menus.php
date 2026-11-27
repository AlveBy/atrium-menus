<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Menus',
    'create' => 'Add menu',
    'code_taken' => 'A menu with this code already exists for this language.',
    'fields' => [
        'code_hint' => 'The theme asks for the menu by it: header, footer.',
        'language' => 'Language',
        'all_languages' => 'All languages',
        'items' => 'Items',
        'add_item' => 'Add item',
        'title' => 'Title',
        'link' => 'Link',
        'target' => 'Opens in',
        'same_window' => 'The same window',
        'new_window' => 'A new window',
        'icon' => 'Icon',
        'icon_hint' => 'CSS classes of an icon, for example “icon icon-phone”.',
        'source' => 'Unfold below',
        'source_hint' => 'The module’s records are listed under the item and refresh by themselves.',
        'source_none' => 'Nothing',
        'source_depth' => 'Levels to unfold',
    ],
    'actions' => [
        'add_to_header' => 'Add to header menu',
        'no_header' => 'The site has no header menu for all languages. Create a menu with the code “header”.',
        'already' => '“:title” is already in the header menu.',
        'added' => '“:title” is added to the end of the header menu.',
    ],
    'builder' => [
        'label' => 'Menu',
    ],
];
