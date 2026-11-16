{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
{{--
    A menu as nested lists. Publish the views (vendor:publish
    --tag=atrium-menus-views) to change the markup.
--}}
<nav {{ $attributes->merge(['class' => $class]) }} aria-label="{{ $label ?? $menu['name'] }}" data-menu="{{ $menu['code'] }}">
    @include('atrium-menus::partials.items', ['items' => $menu['items']])
</nav>
