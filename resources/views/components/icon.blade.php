@props(['name'])
<svg {{ $attributes->merge(['class' => 'size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('search') <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/> @break
        @case('back') <path d="M15 18l-6-6 6-6"/> @break
        @case('send') <path d="M4 12 20 4l-4 16-4-7-8-1Z" fill="currentColor" stroke-width="1.5"/> @break
        @case('check') <path d="M5 12.5 9.5 17 19 7.5"/> @break
        @case('checks') <path d="M2 12.5 6.5 17 16 7.5"/><path d="M11.5 16 13 17.5l9-10"/> @break
        @case('play') <path d="M7 4.5v15l13-7.5-13-7.5Z" fill="currentColor"/> @break
        @case('pause') <rect x="6" y="4.5" width="4" height="15" rx="1" fill="currentColor"/><rect x="14" y="4.5" width="4" height="15" rx="1" fill="currentColor"/> @break
        @case('mic') <rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/> @break
        @case('image') <rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/> @break
        @case('sparkles') <path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2 2M16 16l2 2M6 18l2-2M16 8l2-2"/> @break
        @case('x') <path d="M6 6l12 12M18 6 6 18"/> @break
        @case('chat') <path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/> @break
        @case('alert') <circle cx="12" cy="12" r="9"/><path d="M12 7.5v5M12 16h.01"/> @break
        @case('lock') <rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/> @break
    @endswitch
</svg>
