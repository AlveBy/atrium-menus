<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Menüs',
    'create' => 'Menü hinzufügen',
    'code_taken' => 'Für diese Sprache gibt es bereits ein Menü mit diesem Code.',
    'fields' => [
        'code_hint' => 'Damit fragt das Theme das Menü ab: header, footer.',
        'language' => 'Sprache',
        'all_languages' => 'Alle Sprachen',
        'items' => 'Einträge',
        'add_item' => 'Eintrag hinzufügen',
        'title' => 'Titel',
        'link' => 'Link',
        'target' => 'Öffnet sich',
        'same_window' => 'Im selben Fenster',
        'new_window' => 'In einem neuen Fenster',
        'icon' => 'Symbol',
        'icon_hint' => 'CSS-Klassen eines Symbols, zum Beispiel „icon icon-phone“.',
        'source' => 'Darunter aufklappen',
        'source_hint' => 'Die Datensätze des Moduls erscheinen unter dem Eintrag und aktualisieren sich selbst.',
        'source_none' => 'Nichts',
        'source_depth' => 'Aufzuklappende Ebenen',
    ],
    'actions' => [
        'add_to_header' => 'Zum Kopfmenü hinzufügen',
        'no_header' => 'Die Website hat kein Kopfmenü für alle Sprachen. Legen Sie ein Menü mit dem Code „header“ an.',
        'already' => '„:title“ ist bereits im Kopfmenü.',
        'added' => '„:title“ wurde ans Ende des Kopfmenüs gesetzt.',
    ],
    'builder' => [
        'label' => 'Menü',
    ],
];
