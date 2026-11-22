<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Menüler',
    'create' => 'Menü ekle',
    'code_taken' => 'Bu dil için bu kodla bir menü zaten var.',
    'fields' => [
        'code_hint' => 'Tema menüyü bu kodla ister: header, footer.',
        'language' => 'Dil',
        'all_languages' => 'Tüm diller',
        'items' => 'Öğeler',
        'add_item' => 'Öğe ekle',
        'title' => 'Başlık',
        'link' => 'Bağlantı',
        'target' => 'Açılacağı yer',
        'same_window' => 'Aynı pencere',
        'new_window' => 'Yeni pencere',
        'icon' => 'Simge',
        'icon_hint' => 'Bir simgenin CSS sınıfları, örneğin “icon icon-phone”.',
        'source' => 'Altında aç',
        'source_hint' => 'Modülün kayıtları öğenin altında listelenir ve kendiliğinden güncellenir.',
        'source_none' => 'Hiçbiri',
        'source_depth' => 'Açılacak düzey sayısı',
    ],
    'actions' => [
        'add_to_header' => 'Üst menüye ekle',
        'no_header' => 'Sitede tüm diller için bir üst menü yok. “header” kodlu bir menü oluşturun.',
        'already' => '“:title” zaten üst menüde.',
        'added' => '“:title” üst menünün sonuna eklendi.',
    ],
];
