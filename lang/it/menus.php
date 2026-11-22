<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Menu',
    'create' => 'Aggiungi menu',
    'code_taken' => 'Esiste già un menu con questo codice per questa lingua.',
    'fields' => [
        'code_hint' => 'Il tema chiede il menu con questo codice: header, footer.',
        'language' => 'Lingua',
        'all_languages' => 'Tutte le lingue',
        'items' => 'Voci',
        'add_item' => 'Aggiungi voce',
        'title' => 'Titolo',
        'link' => 'Link',
        'target' => 'Si apre in',
        'same_window' => 'La stessa finestra',
        'new_window' => 'Una nuova finestra',
        'icon' => 'Icona',
        'icon_hint' => 'Classi CSS di un’icona, per esempio «icon icon-phone».',
        'source' => 'Espandi sotto',
        'source_hint' => 'I record del modulo compaiono sotto la voce e si aggiornano da soli.',
        'source_none' => 'Niente',
        'source_depth' => 'Livelli da espandere',
    ],
    'actions' => [
        'add_to_header' => 'Aggiungi al menu principale',
        'no_header' => 'Il sito non ha un menu principale per tutte le lingue. Crea un menu con il codice «header».',
        'already' => '«:title» è già nel menu principale.',
        'added' => '«:title» è stato aggiunto in fondo al menu principale.',
    ],
];
