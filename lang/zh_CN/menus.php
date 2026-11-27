<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => '菜单',
    'create' => '添加菜单',
    'code_taken' => '该语言已有使用此代码的菜单。',
    'fields' => [
        'code_hint' => '主题按此代码调用菜单：header、footer。',
        'language' => '语言',
        'all_languages' => '所有语言',
        'items' => '菜单项',
        'add_item' => '添加菜单项',
        'title' => '标题',
        'link' => '链接',
        'target' => '打开方式',
        'same_window' => '同一窗口',
        'new_window' => '新窗口',
        'icon' => '图标',
        'icon_hint' => '图标的 CSS 类，例如“icon icon-phone”。',
        'source' => '在下方展开',
        'source_hint' => '模块的记录会列在此项下方，并自动更新。',
        'source_none' => '不展开',
        'source_depth' => '展开层数',
    ],
    'actions' => [
        'add_to_header' => '添加到顶部菜单',
        'no_header' => '网站没有适用于所有语言的顶部菜单。请创建代码为“header”的菜单。',
        'already' => '“:title”已在顶部菜单中。',
        'added' => '已将“:title”添加到顶部菜单末尾。',
    ],
    'builder' => [
        'label' => '菜单',
    ],
];
