@props(['name', 'label' => null, 'type' => 'text', 'help' => null, 'required' => false, 'value' => null])
<div>
  @if($label)
    <label for="{{ $name }}" class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label>
  @endif
  <input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $name }}"
    value="{{ $value }}"
    @if($required) required @endif
    {{ $attributes->merge(['class' => 'form-control'])->class(['is-invalid' => $errors->has($name)]) }}
  >
  @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
  @if($help)<div class="form-text">{{ $help }}</div>@endif
</div>
