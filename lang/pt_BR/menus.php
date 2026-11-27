<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Menus',
    'create' => 'Adicionar menu',
    'code_taken' => 'Já existe um menu com este código para este idioma.',
    'fields' => [
        'code_hint' => 'O tema pede o menu por ele: header, footer.',
        'language' => 'Idioma',
        'all_languages' => 'Todos os idiomas',
        'items' => 'Itens',
        'add_item' => 'Adicionar item',
        'title' => 'Título',
        'link' => 'Link',
        'target' => 'Abre em',
        'same_window' => 'A mesma janela',
        'new_window' => 'Uma nova janela',
        'icon' => 'Ícone',
        'icon_hint' => 'Classes CSS de um ícone, por exemplo “icon icon-phone”.',
        'source' => 'Expandir abaixo',
        'source_hint' => 'Os registros do módulo aparecem sob o item e se atualizam sozinhos.',
        'source_none' => 'Nada',
        'source_depth' => 'Níveis a expandir',
    ],
    'actions' => [
        'add_to_header' => 'Adicionar ao menu do topo',
        'no_header' => 'O site não tem menu do topo para todos os idiomas. Crie um menu com o código “header”.',
        'already' => '“:title” já está no menu do topo.',
        'added' => '“:title” foi adicionado ao fim do menu do topo.',
    ],
    'builder' => [
        'label' => 'Menu',
    ],
];
