@props(['color' => 'secondary', 'label', 'icon' => null])
<span {{ $attributes->class(["badge bg-$color-subtle text-$color", 'sas-badge-icon' => $icon]) }}>
  @if($icon)<i class="fi {{ $icon }}"></i>@endif{{ $label }}
</span>
