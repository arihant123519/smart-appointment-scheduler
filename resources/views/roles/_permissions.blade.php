{{-- Grouped permission checkboxes. Expects $groups (group => [Permission]) and $assigned (array of names). --}}
@php $isProtected = ($role->name ?? null) === 'system_admin'; @endphp

@if ($isProtected)
  <div class="alert alert-secondary small d-flex align-items-center">
    <i class="fi fi-rr-lock me-2"></i>
    <div>The <strong>system_admin</strong> role always holds every permission and cannot be changed.</div>
  </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
  <label class="form-label fw-semibold mb-0">Permissions</label>
  @unless ($isProtected)
    <div class="small">
      <a href="#" class="link-primary" data-perm-all="1">Select all</a> ·
      <a href="#" class="link-secondary" data-perm-none="1">Clear</a>
    </div>
  @endunless
</div>

<div class="row g-3">
  @foreach ($groups as $group => $permissions)
    <div class="col-md-6 col-xl-4">
      <div class="card shadow-none border h-100">
        <div class="card-body py-3">
          <div class="fw-semibold small text-uppercase text-muted mb-2">{{ $group }}</div>
          @foreach ($permissions as $permission)
            <div class="form-check mb-1">
              <input class="form-check-input" type="checkbox" name="permissions[]"
                     value="{{ $permission->name }}" id="perm_{{ $permission->id }}"
                     @checked($isProtected || in_array($permission->name, old('permissions', $assigned), true))
                     @disabled($isProtected)>
              <label class="form-check-label text-capitalize" for="perm_{{ $permission->id }}">
                {{ str_replace(['manage ','view ','use '], ['Manage ','View ','Use '], $permission->name) }}
              </label>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  @endforeach
</div>

@push('scripts')
<script>
  document.querySelectorAll('[data-perm-all]').forEach(a => a.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('input[name="permissions[]"]:not(:disabled)').forEach(c => c.checked = true);
  }));
  document.querySelectorAll('[data-perm-none]').forEach(a => a.addEventListener('click', e => {
    e.preventDefault();
    document.querySelectorAll('input[name="permissions[]"]:not(:disabled)').forEach(c => c.checked = false);
  }));
</script>
@endpush
