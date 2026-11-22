<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Menús',
    'create' => 'Añadir menú',
    'code_taken' => 'Ya existe un menú con este código para este idioma.',
    'fields' => [
        'code_hint' => 'El tema pide el menú por él: header, footer.',
        'language' => 'Idioma',
        'all_languages' => 'Todos los idiomas',
        'items' => 'Elementos',
        'add_item' => 'Añadir elemento',
        'title' => 'Título',
        'link' => 'Enlace',
        'target' => 'Se abre en',
        'same_window' => 'La misma ventana',
        'new_window' => 'Una ventana nueva',
        'icon' => 'Icono',
        'icon_hint' => 'Clases CSS de un icono, por ejemplo «icon icon-phone».',
        'source' => 'Desplegar debajo',
        'source_hint' => 'Los registros del módulo aparecen bajo el elemento y se actualizan solos.',
        'source_none' => 'Nada',
        'source_depth' => 'Niveles que desplegar',
    ],
    'actions' => [
        'add_to_header' => 'Añadir al menú superior',
        'no_header' => 'El sitio no tiene menú superior para todos los idiomas. Cree un menú con el código «header».',
        'already' => '«:title» ya está en el menú superior.',
        'added' => '«:title» se ha añadido al final del menú superior.',
    ],
];
