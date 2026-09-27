@props(['name', 'size' => 16])
{{-- One stroke family (1.6 on a 16 grid) for every glyph in the /budget UI. --}}
<svg {{ $attributes->merge(['class' => 'icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 16 16"
     fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('external')
            <path d="M6 3.5H3.5v9h9V10M9 3h4v4M13 3 7.5 8.5"/>
            @break
        @case('arrow-right')
            <path d="M3 8h10M9 4l4 4-4 4"/>
            @break
        @case('arrow-left')
            <path d="M13 8H3M7 4 3 8l4 4"/>
            @break
        @case('check')
            <path d="m3.5 8.5 3 3 6-7"/>
            @break
        @case('upload')
            <path d="M8 10.5V3M4.5 6.5 8 3l3.5 3.5M3 10.5v2.5h10v-2.5"/>
            @break
        @case('file')
            <path d="M4 2h5l3 3v9H4zM9 2v3h3"/>
            @break
        @case('close')
            <path d="m4.5 4.5 7 7M11.5 4.5l-7 7"/>
            @break
        @case('search')
            <circle cx="7" cy="7" r="4"/><path d="m10 10 3 3"/>
            @break
        @case('alert')
            <path d="M8 2.5 14 13H2zM8 6.5v3M8 11.2v.1"/>
            @break
    @endswitch
</svg>
