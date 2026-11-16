{{--
    This file is part of Atrium (https://github.com/AlveBy/atrium).
    Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
--}}
<ul>
    @foreach ($items as $item)
        @php($classes = trim(($item['active'] ? 'is-active ' : '').($item['children'] !== [] ? 'has-children' : '')))
        <li @if ($classes !== '') class="{{ $classes }}" @endif>
            @if ($item['url'] !== null)
                <a href="{{ $item['url'] }}"@if ($item['target'] === '_blank') target="_blank" rel="noopener"@endif @if ($item['current']) aria-current="page"@endif>@if ($item['icon'])<span class="menu-icon {{ $item['icon'] }}" aria-hidden="true"></span> @endif{{ $item['title'] }}</a>
            @else
                <span>@if ($item['icon'])<span class="menu-icon {{ $item['icon'] }}" aria-hidden="true"></span> @endif{{ $item['title'] }}</span>
            @endif
            @if ($item['children'] !== [])
                @include('atrium-menus::partials.items', ['items' => $item['children']])
            @endif
        </li>
    @endforeach
</ul>
