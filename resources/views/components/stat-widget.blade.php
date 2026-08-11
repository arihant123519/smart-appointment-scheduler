@props([
  'icon' => 'fi-rr-stats',
  'label',
  'value',
  'bg' => 'bg-primary-subtle',
  'fg' => 'text-primary',
  'delta' => null,
  'deltaUp' => true,
  'deltaLabel' => null,
  'caption' => null,
  'sparkId' => null,
  'sparkColor' => '#2563EB',
  'sparkSeries' => null,
])
<div {{ $attributes->class(['card', 'sas-card-hover', 'h-100']) }} style="position:relative;overflow:hidden">
  <div class="card-body d-flex align-items-start gap-3" style="position:relative;z-index:2">
    <div class="sas-stat__icon {{ $bg }} {{ $fg }}">
      <i class="fi {{ $icon }}"></i>
    </div>
    <div class="flex-grow-1" style="min-width:0">
      <div class="text-muted small">{{ $label }}</div>
      <div class="d-flex align-items-baseline gap-1 flex-wrap">
        <span class="h1 mb-0 fw-bold sas-count" data-count-to="{{ (float) $value }}" data-suffix="{{ str_contains((string) $value, '%') ? '%' : '' }}">0</span>
      </div>
      @if($delta)
        <div class="mt-1">
          <span class="sas-stat__delta {{ $deltaUp ? 'text-success' : 'text-danger' }}">
            <i class="fi {{ $deltaUp ? 'fi-rr-arrow-small-up' : 'fi-rr-arrow-small-down' }}"></i>{{ $delta }}
          </span>
          @if($deltaLabel)<span class="sas-stat__caption">{{ $deltaLabel }}</span>@endif
        </div>
      @elseif($caption)
        <div class="sas-stat__caption">{{ $caption }}</div>
      @endif
    </div>
  </div>
  @if($sparkId && $sparkSeries)
    <div class="sas-spark" id="{{ $sparkId }}" style="position:absolute;left:0;right:0;bottom:0;opacity:.5"
         data-series='@json($sparkSeries)' data-color="{{ $sparkColor }}"></div>
  @endif
</div>
