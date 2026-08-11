@props(['name', 'label' => null, 'type' => 'text', 'help' => null, 'required' => false, 'value' => null])
@php $placeholder = $attributes->get('placeholder') ?: ' '; @endphp
<div class="sas-outline-field @error($name) is-invalid @enderror">
  <input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $name }}"
    value="{{ $value }}"
    placeholder="{{ $placeholder }}"
    @if($required) required @endif
    {{ $attributes->except('placeholder')->class(['sas-outline-field__input']) }}
  >
  @if($label)
    <fieldset class="sas-outline-field__fieldset" aria-hidden="true">
      <legend><span>{{ $label }}</span></legend>
    </fieldset>
    <label for="{{ $name }}" class="sas-outline-field__label">{{ $label }}: @if($required)<span class="text-danger">*</span>@endif</label>
  @endif
</div>
@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
@if($help)<div class="form-text">{{ $help }}</div>@endif
