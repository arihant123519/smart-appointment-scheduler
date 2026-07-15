@props(['id', 'title' => null, 'size' => null])
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $id }}Label">
  <div class="modal-dialog modal-dialog-centered {{ $size ? "modal-$size" : '' }}">
    <div class="modal-content">
      @if($title)
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="{{ $id }}Label">{{ $title }}</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
      @endif
      <div class="modal-body">
        {{ $slot }}
      </div>
      @isset($footer)
        <div class="modal-footer">{{ $footer }}</div>
      @endisset
    </div>
  </div>
</div>
