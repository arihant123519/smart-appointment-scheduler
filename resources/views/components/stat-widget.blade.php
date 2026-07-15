@props([
  'icon' => 'fi-rr-stats',
  'label',
  'value',
  'bg' => 'bg-primary-subtle',
  'fg' => 'text-primary',
  'delta' => null,
  'deltaUp' => true,
  'sparkId' => null,
  'sparkColor' => '#5955D1',
  'sparkSeries' => null,
])
<div {{ $attributes->class(['card', 'sas-card-hover', 'h-100']) }} style="position:relative;overflow:hidden">
  <div class="card-body d-flex align-items-center gap-3" style="position:relative;z-index:2">
    <div class="sas-stat__icon {{ $bg }} {{ $fg }}">
      <i class="fi {{ $icon }}"></i>
    </div>
    <div class="flex-grow-1">
      <div class="text-muted small">{{ $label }}</div>
      <div class="d-flex align-items-baseline gap-2">
        <span class="h4 mb-0 fw-bold sas-count" data-count-to="{{ (float) $value }}" data-suffix="{{ str_contains((string) $value, '%') ? '%' : '' }}">0</span>
        @if($delta)
          <span class="sas-stat__delta {{ $deltaUp ? 'text-success' : 'text-danger' }}">
            <i class="fi {{ $deltaUp ? 'fi-rr-arrow-small-up' : 'fi-rr-arrow-small-down' }}"></i>{{ $delta }}
          </span>
        @endif
      </div>
    </div>
  </div>
  @if($sparkId && $sparkSeries)
    <div class="sas-spark" id="{{ $sparkId }}" style="position:absolute;left:0;right:0;bottom:0;opacity:.5"
         data-series='@json($sparkSeries)' data-color="{{ $sparkColor }}"></div>
  @endif
</div>
