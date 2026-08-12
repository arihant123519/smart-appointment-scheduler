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
    'icon' => null,
])
@php
    $fieldId = $id ?? $name;
    $hasError = $name && $errors->has($name);
    $placeholder = $attributes->get('placeholder') ?: ' ';
    $isPassword = $type === 'password';
@endphp
<div class="sas-outline-field @error($name) is-invalid @enderror @if($icon || $isPassword) has-trailing-icon @endif">
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

  @if($isPassword)
    {{-- Client-side only: toggles the input's own type attribute, no data leaves the browser. --}}
    <button type="button" class="sas-outline-field__icon-btn" data-sas-toggle-password="{{ $fieldId }}" aria-label="Show password" tabindex="-1">
      <i class="fi fi-rr-eye" aria-hidden="true"></i>
    </button>
  @elseif($icon)
    <i class="fi {{ $icon }} sas-outline-field__icon" aria-hidden="true"></i>
  @endif
</div>
@error($name)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
@if($help)<div class="form-text">{{ $help }}</div>@endif

@once
  <script>
    // Self-contained so it works regardless of which layout renders this
    // component (the authenticated app shell and the guest auth pages don't
    // share a script bundle). Purely client-side — flips the input's own
    // type attribute, nothing is sent anywhere.
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-sas-toggle-password]');
      if (!btn) return;
      const input = document.getElementById(btn.dataset.sasTogglePassword);
      if (!input) return;
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.querySelector('i').className = showing ? 'fi fi-rr-eye' : 'fi fi-rr-eye-crossed';
      btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
  </script>
@endonce
