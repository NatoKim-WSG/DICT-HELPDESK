@props([
    'href' => null,
    'label' => '',
    'value' => null,
    'valueClass' => 'text-slate-900',
    'labelClass' => 'text-slate-500',
    'contentClass' => 'text-xs text-slate-500',
    'cardClass' => '',
])

@php
    $hasContent = trim((string) $slot) !== '';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([$cardClass, 'stat-card', 'block']) }}>
        @if(isset($icon))
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider {{ $labelClass }}">{{ $label }}</p>
                    <p class="mt-2 font-display text-3xl font-semibold {{ $valueClass }}">{{ $value }}</p>
                    @if($hasContent)
                        <div class="mt-2 {{ $contentClass }}">{{ $slot }}</div>
                    @endif
                </div>
                {{ $icon }}
            </div>
        @else
            <p class="text-xs font-semibold uppercase tracking-wider {{ $labelClass }}">{{ $label }}</p>
            <p class="mt-2 font-display text-3xl font-semibold {{ $valueClass }}">{{ $value }}</p>
            @if($hasContent)
                <div class="mt-2 {{ $contentClass }}">{{ $slot }}</div>
            @endif
        @endif
    </a>
@else
    <div {{ $attributes->class([$cardClass, 'stat-card']) }}>
        @if(isset($icon))
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider {{ $labelClass }}">{{ $label }}</p>
                    <p class="mt-2 font-display text-3xl font-semibold {{ $valueClass }}">{{ $value }}</p>
                    @if($hasContent)
                        <div class="mt-2 {{ $contentClass }}">{{ $slot }}</div>
                    @endif
                </div>
                {{ $icon }}
            </div>
        @else
            <p class="text-xs font-semibold uppercase tracking-wider {{ $labelClass }}">{{ $label }}</p>
            <p class="mt-2 font-display text-3xl font-semibold {{ $valueClass }}">{{ $value }}</p>
            @if($hasContent)
                <div class="mt-2 {{ $contentClass }}">{{ $slot }}</div>
            @endif
        @endif
    </div>
@endif
