{{-- أيقونات SVG مدمجة - مفيش مكتبة خارجية عشان الصفحة تفضل خفيفة --}}
@php
    $paths = [
        'home'     => '<path d="M3 10.5 12 3l9 7.5M5.5 9.5V20a1 1 0 0 0 1 1H10v-5h4v5h3.5a1 1 0 0 0 1-1V9.5"/>',
        'inbox'    => '<path d="M3 13h5l1.5 2.5h5L16 13h5M3 13l2.5-7A1.5 1.5 0 0 1 7 5h10a1.5 1.5 0 0 1 1.5 1L21 13v5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18z"/>',
        'doc'      => '<path d="M14 3H7a1.5 1.5 0 0 0-1.5 1.5v15A1.5 1.5 0 0 0 7 21h10a1.5 1.5 0 0 0 1.5-1.5V7.5zM14 3v4.5h4.5M8.5 13h7M8.5 16.5h5"/>',
        'truck'    => '<path d="M3 16V7a1 1 0 0 1 1-1h9v10M13 10h4l3 3.5V16M3 16h1.5M20 16h1M9 16h4"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
        'ban'      => '<circle cx="12" cy="12" r="8.5"/><path d="m6 6 12 12"/>',
        'car'      => '<path d="M3 13.5 4.6 8.4A2 2 0 0 1 6.5 7h11a2 2 0 0 1 1.9 1.4L21 13.5M3 13.5h18M3 13.5v4h2v-1h14v1h2v-4"/><circle cx="7" cy="16" r="1"/><circle cx="17" cy="16" r="1"/>',
        'tag'      => '<path d="M3.5 11V4.5A1 1 0 0 1 4.5 3.5H11l9 9-7.5 7.5z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
        'building' => '<path d="M4 21V5.5A1.5 1.5 0 0 1 5.5 4h7A1.5 1.5 0 0 1 14 5.5V21M14 10h4.5A1.5 1.5 0 0 1 20 11.5V21M3 21h18M7.5 8h3M7.5 12h3M7.5 16h3M17 14h0M17 17.5h0"/>',
        'cash'     => '<rect x="3" y="6" width="18" height="12" rx="1.5"/><circle cx="12" cy="12" r="2.5"/><path d="M6.5 12h.01M17.5 12h.01"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="1.5"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
        'bank'     => '<path d="M3 9.5 12 4l9 5.5M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 21h18"/>',
        'receipt'  => '<path d="M6 3h12v18l-3-1.5L12 21l-3-1.5L6 21z"/><path d="M9.5 8h5M9.5 12h5"/>',
        'users'    => '<circle cx="9" cy="8.5" r="3"/><path d="M3.5 19.5a5.5 5.5 0 0 1 11 0M16 6.2a3 3 0 0 1 0 5.6M17.5 19.5a5 5 0 0 0-2-4"/>',
        'key'      => '<circle cx="8" cy="12" r="4"/><path d="M12 12h9M18 12v3M15.5 12v2"/>',
        'chart'    => '<path d="M4 20V4M4 20h16M8 16.5V11M12.5 16.5V7M17 16.5v-4"/>',
        'cog'      => '<circle cx="12" cy="12" r="3"/><path d="M12 3v2.5M12 18.5V21M21 12h-2.5M5.5 12H3M18.4 5.6l-1.8 1.8M7.4 16.6l-1.8 1.8M18.4 18.4l-1.8-1.8M7.4 7.4 5.6 5.6"/>',
    ];
@endphp

<svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
    {!! $paths[$name] ?? $paths['doc'] !!}
</svg>
