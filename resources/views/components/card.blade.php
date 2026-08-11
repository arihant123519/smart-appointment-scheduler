@props(['title' => null, 'subtitle' => null, 'bodyClass' => ''])
<div {{ $attributes->class(['card']) }}>
  @if($title || isset($toolbar))
    <div class="card-header sas-card-toolbar">
      <div>
        @if($title)<h6 class="mb-0 fw-semibold" style="font-size:1.0625rem;letter-spacing:-.01em">{{ $title }}</h6>@endif
        @if($subtitle)<small class="text-muted">{{ $subtitle }}</small>@endif
      </div>
      @isset($toolbar)
        <div class="sas-card-toolbar__actions">{{ $toolbar }}</div>
      @endisset
    </div>
  @endif
  <div class="card-body {{ $bodyClass }}">
    {{ $slot }}
  </div>
  @isset($footer)
    <div class="card-footer">{{ $footer }}</div>
  @endisset
</div>
