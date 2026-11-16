{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{--
    The theme's header menu while this module is installed: the site's
    "header" menu, or the theme's own while there is none.
--}}
@if (atrium_menu('header') !== null)
    <x-atrium-menu code="header" :label="__('atrium-theme::theme.menu')" />
@else
    @include('atrium-theme::partials.menu')
@endif
