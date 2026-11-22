<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'メニュー',
    'create' => 'メニューを追加',
    'code_taken' => 'この言語には同じコードのメニューがすでにあります。',
    'fields' => [
        'code_hint' => 'テーマはこのコードでメニューを呼び出します: header、footer。',
        'language' => '言語',
        'all_languages' => 'すべての言語',
        'items' => '項目',
        'add_item' => '項目を追加',
        'title' => 'タイトル',
        'link' => 'リンク',
        'target' => '開き方',
        'same_window' => '同じウィンドウ',
        'new_window' => '新しいウィンドウ',
        'icon' => 'アイコン',
        'icon_hint' => 'アイコンの CSS クラス（例: “icon icon-phone”）。',
        'source' => '下に展開',
        'source_hint' => 'モジュールのレコードが項目の下に表示され、自動で更新されます。',
        'source_none' => 'なし',
        'source_depth' => '展開する階層数',
    ],
    'actions' => [
        'add_to_header' => 'ヘッダーメニューに追加',
        'no_header' => 'すべての言語向けのヘッダーメニューがありません。コード「header」のメニューを作成してください。',
        'already' => '「:title」はすでにヘッダーメニューにあります。',
        'added' => '「:title」をヘッダーメニューの末尾に追加しました。',
    ],
];
