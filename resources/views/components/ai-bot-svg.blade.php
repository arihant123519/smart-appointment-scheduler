{{--
  Shared AI-assistant mascot glyph — reused at hero size (with the optional
  antenna + feet flourishes) and at chat-avatar size (bare head), so the
  "character" stays visually consistent across the page. Usage:
    <x-ai-bot-svg class="sas-ai-mascot__bot" :detailed="true" />
--}}
@props(['detailed' => false])
@php $sasBotGradId = 'sasBotGrad-' . uniqid(); @endphp
<svg viewBox="0 0 160 160" {{ $attributes->merge(['role' => 'img', 'aria-hidden' => 'true', 'focusable' => 'false']) }}>
  <defs>
    <linearGradient id="{{ $sasBotGradId }}" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#1D4ED8"/>
      <stop offset="100%" stop-color="#3B82F6"/>
    </linearGradient>
  </defs>

  @if($detailed)
    {{-- feet, painted first so the body shell overlaps their top half --}}
    <rect x="48" y="118" width="16" height="18" rx="8" fill="url(#{{ $sasBotGradId }})"/>
    <rect x="96" y="118" width="16" height="18" rx="8" fill="url(#{{ $sasBotGradId }})"/>
  @endif

  {{-- body shell --}}
  <rect x="32" y="28" width="96" height="98" rx="32" fill="#ffffff" stroke="#E2E8F0" stroke-width="2"/>

  @if($detailed)
    {{-- antenna --}}
    <line x1="80" y1="20" x2="80" y2="30" stroke="url(#{{ $sasBotGradId }})" stroke-width="4" stroke-linecap="round"/>
    <circle cx="80" cy="15" r="6" fill="url(#{{ $sasBotGradId }})"/>
  @endif

  {{-- headphone band + ear cups --}}
  <path d="M42 64 a38 38 0 0 1 76 0" fill="none" stroke="url(#{{ $sasBotGradId }})" stroke-width="7" stroke-linecap="round"/>
  <rect x="28" y="58" width="15" height="32" rx="7" fill="url(#{{ $sasBotGradId }})"/>
  <rect x="117" y="58" width="15" height="32" rx="7" fill="url(#{{ $sasBotGradId }})"/>

  {{-- face plate + closed happy eyes --}}
  <rect x="52" y="58" width="56" height="42" rx="18" fill="url(#{{ $sasBotGradId }})"/>
  <path d="M64 76 q6 8 12 0" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round"/>
  <path d="M84 76 q6 8 12 0" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round"/>
</svg>
