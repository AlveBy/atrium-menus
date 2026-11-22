<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Меню',
    'create' => 'Добавить меню',
    'code_taken' => 'Меню с таким кодом для этого языка уже есть.',
    'fields' => [
        'code_hint' => 'По нему тема запрашивает меню: header, footer.',
        'language' => 'Язык',
        'all_languages' => 'Все языки',
        'items' => 'Пункты',
        'add_item' => 'Добавить пункт',
        'title' => 'Название',
        'link' => 'Ссылка',
        'target' => 'Открывать',
        'same_window' => 'В том же окне',
        'new_window' => 'В новом окне',
        'icon' => 'Иконка',
        'icon_hint' => 'CSS-классы иконки, например «icon icon-phone».',
        'source' => 'Развернуть ниже',
        'source_hint' => 'Записи модуля выводятся под пунктом и обновляются сами.',
        'source_none' => 'Ничего',
        'source_depth' => 'Сколько уровней разворачивать',
    ],
    'actions' => [
        'add_to_header' => 'Добавить в верхнее меню',
        'no_header' => 'На сайте нет верхнего меню для всех языков. Создайте меню с кодом «header».',
        'already' => '«:title» уже есть в верхнем меню.',
        'added' => '«:title» добавлено в конец верхнего меню.',
    ],
];
