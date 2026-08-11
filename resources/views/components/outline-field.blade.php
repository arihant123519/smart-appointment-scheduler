@props([
    'name' => null,
    'id' => null,
    'label' => null,
    'type' => 'text',
    'help' => null,
    'required' => false,
    'value' => null,
    'textarea' => false,
    'rows' => 4,
])
@php
    $fieldId = $id ?? $name;
    $hasError = $name && $errors->has($name);
    $placeholder = $attributes->get('placeholder') ?: ' ';
@endphp
<div class="sas-outline-field @error($name) is-invalid @enderror">
  @if($textarea)
    <textarea
      id="{{ $fieldId }}"
      @if($name) name="{{ $name }}" @endif
      rows="{{ $rows }}"
      placeholder="{{ $placeholder }}"
      @if($required) required @endif
      {{ $attributes->except('placeholder')->class(['sas-outline-field__input']) }}
    >{{ $value ?? $slot }}</textarea>
  @else
    <input
      type="{{ $type }}"
      id="{{ $fieldId }}"
      @if($name) name="{{ $name }}" @endif
      value="{{ $value }}"
      placeholder="{{ $placeholder }}"
      @if($required) required @endif
      {{ $attributes->except('placeholder')->class(['sas-outline-field__input']) }}
    >
  @endif
  <fieldset class="sas-outline-field__fieldset" aria-hidden="true">
    <legend><span>{{ $label }}</span></legend>
  </fieldset>
  <label for="{{ $fieldId }}" class="sas-outline-field__label">{{ $label }}: @if($required)<span class="text-danger">*</span>@endif</label>
</div>
@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
@if($help)<div class="form-text">{{ $help }}</div>@endif
