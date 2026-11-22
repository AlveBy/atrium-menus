<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

return [
    'module' => 'Menus',
    'create' => 'Ajouter un menu',
    'code_taken' => 'Un menu avec ce code existe déjà pour cette langue.',
    'fields' => [
        'code_hint' => 'Le thème demande le menu par ce code : header, footer.',
        'language' => 'Langue',
        'all_languages' => 'Toutes les langues',
        'items' => 'Éléments',
        'add_item' => 'Ajouter un élément',
        'title' => 'Titre',
        'link' => 'Lien',
        'target' => 'S’ouvre dans',
        'same_window' => 'La même fenêtre',
        'new_window' => 'Une nouvelle fenêtre',
        'icon' => 'Icône',
        'icon_hint' => 'Classes CSS d’une icône, par exemple « icon icon-phone ».',
        'source' => 'Déplier en dessous',
        'source_hint' => 'Les enregistrements du module s’affichent sous l’élément et se mettent à jour seuls.',
        'source_none' => 'Rien',
        'source_depth' => 'Niveaux à déplier',
    ],
    'actions' => [
        'add_to_header' => 'Ajouter au menu d’en-tête',
        'no_header' => 'Le site n’a pas de menu d’en-tête pour toutes les langues. Créez un menu avec le code « header ».',
        'already' => '« :title » est déjà dans le menu d’en-tête.',
        'added' => '« :title » est ajouté à la fin du menu d’en-tête.',
    ],
];
