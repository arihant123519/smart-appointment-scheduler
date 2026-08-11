@props([
  'label',
  'value',
  'caption' => null,
  'icon' => 'fi-rr-stats',
  'bg' => 'bg-primary-subtle',
  'fg' => 'text-primary',
])
<div class="sas-stat-strip__item">
  <div style="min-width:0">
    <div class="text-muted small">{{ $label }}</div>
    <div class="sas-stat-strip__value sas-count" data-count-to="{{ (float) $value }}">0</div>
    @if ($caption)<div class="text-muted small">{{ $caption }}</div>@endif
  </div>
  <span class="sas-icon-tile sas-stat-strip__icon {{ $bg }} {{ $fg }}"><i class="fi {{ $icon }}"></i></span>
</div>
