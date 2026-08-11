@extends('layouts.app')

@section('title', 'Roles & Permissions')

@php
  // Read-only, presentational computation — same pattern as every other
  // page in this pass. $roles already carries permissions_count/users_count
  // (withCount, from the controller); permissions themselves aren't
  // eager-loaded, so a real (not fabricated) description needs one extra
  // load — safe, read-only, no business logic touched.
  $roles->load('permissions');

  $rolTotalRoles = $roles->count();
  $rolTotalUsers = (int) $roles->sum('users_count');
  $rolTotalPermissionGrants = (int) $roles->sum('permissions_count'); // grants, not unique permission types
  $rolProtectedCount = $roles->where('name', 'system_admin')->count();
  $rolCustomCount = $rolTotalRoles - $rolProtectedCount;
  $rolLastUpdated = $roles->max('updated_at');

  // Real audit trail for "last updated by" — RoleController::update()/store()
  // calls AuditLog::record() on every change, so this reflects genuine
  // history. Roles seeded before the app was ever used through the UI have
  // no entry, and that's shown honestly (no attribution) rather than guessed.
  $rolAuditByRole = \App\Models\AuditLog::where('entity', 'Role')
    ->whereIn('entity_id', $roles->pluck('id'))
    ->with('user')->orderByDesc('created_at')->get()
    ->groupBy('entity_id')->map(fn ($logs) => $logs->first());

  $rolIconMap = [
    'system_admin' => ['icon' => 'fi-rr-crown', 'color' => 'danger'],
    'clinic_admin' => ['icon' => 'fi-rr-building', 'color' => 'primary'],
    'provider' => ['icon' => 'fi-rr-stethoscope', 'color' => 'success'],
    'front_desk' => ['icon' => 'fi-rr-user-headset', 'color' => 'warning'],
    'billing' => ['icon' => 'fi-rr-file-invoice', 'color' => 'success'],
    'patient' => ['icon' => 'fi-rr-user', 'color' => 'info'],
  ];
  $rolIconFor = fn ($name) => $rolIconMap[$name] ?? ['icon' => 'fi-rr-shield', 'color' => 'secondary'];

  $rolDescriptionFor = function ($role) {
    if ($role->name === 'system_admin') {
      return 'Full system access with all administrative privileges.';
    }
    if ($role->permissions_count === 0) {
      return 'Access own appointments, profile and basic information — no administrative permissions.';
    }
    $groups = $role->permissions->pluck('name')->map(function ($name) {
      $parts = explode(' ', $name, 2);
      return \Illuminate\Support\Str::headline($parts[1] ?? $parts[0]);
    })->unique()->values();
    $shown = $groups->take(4);
    $rest = $groups->count() - $shown->count();
    return 'Manage '.$shown->implode(', ').($rest > 0 ? ', and '.$rest.' more area'.($rest === 1 ? '' : 's').'.' : '.');
  };
@endphp

@push('styles')
  <style>
    .sas-role-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-role-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-role-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-role-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .85rem; padding: var(--sas-space-4) var(--sas-space-5); border-bottom: 1px solid var(--sas-gray-100); }
    .sas-role-toolbar__length select { border-radius: var(--sas-radius-md); }
    .sas-role-toolbar__search { margin-left: auto; }
    .sas-role-toolbar__search input { border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); padding: .55rem .9rem; font-size: var(--sas-fs-sm); min-width: 220px; }
    .sas-role-toolbar__search input:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    #rolesTable_wrapper > .row:first-child { display: none; }
    #rolesTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }

    .sas-role-filter-btn {
      border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); font-weight: 600; font-size: var(--sas-fs-sm);
      border-radius: var(--sas-radius-md); padding: .55rem 1rem; display: inline-flex; align-items: center; gap: .4rem; flex-shrink: 0;
    }
    .sas-role-filter-btn:hover { background: var(--sas-gray-50); }
    .sas-role-filter-btn.has-active { border-color: var(--sas-primary-400); color: var(--sas-primary-600); background: var(--sas-primary-50); }

    #rolesTable .sas-role-name { display: flex; align-items: center; gap: .65rem; font-weight: 700; color: var(--sas-gray-900); }
    #rolesTable .sas-role-desc { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); max-width: 320px; }
    #rolesTable .sas-role-updated__date { font-size: var(--sas-fs-sm); color: var(--sas-gray-800); }
    #rolesTable .sas-role-updated__meta { font-size: var(--sas-fs-xs); color: var(--sas-gray-400); }
    #rolesTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    #rolesTable .btn-icon-square:hover { background: var(--sas-gray-50); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-role-header__icon"><i class="fi fi-rr-shield-check" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-role-header__title mb-1">Roles &amp; Permissions</h1>
        <p class="sas-role-header__subtitle mb-0">Define what each role can do. Permissions are checked on every sensitive action and audit-logged.</p>
      </div>
    </div>
    <a href="{{ route('roles.create') }}" class="btn btn-primary btn-lg"><i class="fi fi-rr-plus me-1" aria-hidden="true"></i> Create Role</a>
  </div>

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-6 col-xl">
      <x-stat-widget label="Total Roles" :value="$rolTotalRoles" icon="fi-rr-users-alt" bg="bg-primary-subtle" fg="text-primary" caption="All system roles" />
    </div>
    <div class="col-6 col-xl">
      <x-stat-widget label="Total Users" :value="$rolTotalUsers" icon="fi-rr-users-alt" bg="bg-success-subtle" fg="text-success" caption="Across all roles" />
    </div>
    <div class="col-6 col-xl">
      <x-stat-widget label="Total Permissions" :value="$rolTotalPermissionGrants" icon="fi-rr-shield-check" bg="bg-info-subtle" fg="text-info" caption="All role permissions" />
    </div>
    <div class="col-6 col-xl">
      <x-stat-widget label="Custom Roles" :value="$rolCustomCount" icon="fi-rr-file-edit" bg="bg-warning-subtle" fg="text-warning" caption="Not system-protected" />
    </div>
    <div class="col-6 col-xl">
      {{-- Hand-rolled: a formatted date isn't a number the shared animated
           counter can meaningfully count up to. --}}
      <div class="card sas-card sas-card-hover h-100">
        <div class="card-body d-flex align-items-start gap-3">
          <div class="sas-stat__icon bg-primary-subtle text-primary"><i class="fi fi-rr-arrow-trend-up" aria-hidden="true"></i></div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="text-muted small">Last Updated</div>
            <span class="h5 mb-0 fw-bold d-block">{{ $rolLastUpdated ? $rolLastUpdated->format('M j, Y') : '—' }}</span>
            <div class="sas-stat__caption">Most recent change</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <x-card bodyClass="p-0">
    <div class="sas-role-toolbar">
      <span class="sas-role-toolbar__length" id="rolesLengthSlot"></span>
      <span class="sas-role-toolbar__search" id="rolesSearchSlot"></span>
      <div class="dropdown">
        <button type="button" class="sas-role-filter-btn" id="roleFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fi fi-rr-filter" aria-hidden="true"></i> Filters
        </button>
        <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:180px">
          <li><label class="dropdown-item d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input mt-0 sas-role-protected-check" value="protected"> Protected</label></li>
          <li><label class="dropdown-item d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input mt-0 sas-role-protected-check" value="custom"> Custom</label></li>
        </ul>
      </div>
    </div>

    <div class="table-responsive">
      <table id="rolesTable" class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Role</th><th>Permissions</th><th>Users</th><th>Description</th><th>Last Updated</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          @forelse ($roles as $role)
            @php $meta = $rolIconFor($role->name); @endphp
            <tr data-protected="{{ $role->name === 'system_admin' ? 'protected' : 'custom' }}">
              <td>
                <div class="sas-role-name">
                  <span class="sas-icon-tile bg-{{ $meta['color'] }}-subtle text-{{ $meta['color'] }}" style="width:34px;height:34px;font-size:.95rem"><i class="fi {{ $meta['icon'] }}" aria-hidden="true"></i></span>
                  {{ ucwords(str_replace('_', ' ', $role->name)) }}
                  @if ($role->name === 'system_admin')
                    <x-badge-status color="dark" label="Protected" class="ms-1" />
                  @endif
                </div>
              </td>
              <td data-order="{{ $role->permissions_count }}"><x-badge-status color="primary" :label="$role->permissions_count.' permission(s)'" /></td>
              <td data-order="{{ $role->users_count }}"><x-badge-status color="secondary" :label="$role->users_count.' user(s)'" /></td>
              <td class="sas-role-desc">{{ $rolDescriptionFor($role) }}</td>
              <td>
                @php $log = $rolAuditByRole->get($role->id); @endphp
                <div class="sas-role-updated__date">{{ $role->updated_at?->format('M j, Y') ?? '—' }}</div>
                <div class="sas-role-updated__meta">
                  {{ ($log?->created_at ?? $role->updated_at)?->format('g:i A') }}
                  @if ($log?->user)
                    by {{ $log->user->name }}
                  @endif
                </div>
              </td>
              <td class="text-end">
                <div class="dropdown sas-dropdown-actions">
                  <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $role->name }}">
                    <i class="fi fi-rr-menu-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('roles.edit', $role) }}"><i class="fi fi-rr-edit"></i> Edit permissions</a></li>
                    @if ($role->name !== 'system_admin' && $role->users_count === 0)
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form method="POST" action="{{ route('roles.destroy', $role) }}" data-sas-confirm="Delete this role?" data-sas-confirm-label="Delete">
                          @csrf @method('DELETE')
                          <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Delete role</button>
                        </form>
                      </li>
                    @endif
                  </ul>
                </div>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="6" icon="fi-rr-shield-check" title="No roles found" description="Create your first permission role to start managing secure access.">
              <a href="{{ route('roles.create') }}" class="btn btn-sm btn-primary"><i class="fi fi-rr-plus me-1"></i> Create Role</a>
            </x-empty-state>
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection

@push('scripts')
  <script>
    (function () {
      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
      const el = document.getElementById('rolesTable');
      if (!el || el.querySelector('tbody td[colspan]')) return;

      const table = jQuery(el).DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [],
        language: { search: '', searchPlaceholder: 'Search roles…' },
      });

      const lengthWrap = document.querySelector('#rolesTable_wrapper .dataTables_length');
      const lengthSlot = document.getElementById('rolesLengthSlot');
      if (lengthWrap && lengthSlot) lengthSlot.appendChild(lengthWrap);
      const filterWrap = document.querySelector('#rolesTable_wrapper .dataTables_filter');
      const searchSlot = document.getElementById('rolesSearchSlot');
      if (filterWrap && searchSlot) searchSlot.appendChild(filterWrap);

      let protectedSet = new Set();
      jQuery.fn.dataTable.ext.search.push(function (settings, data, rowIdx) {
        if (settings.nTable.id !== 'rolesTable') return true;
        if (!protectedSet.size) return true;
        const row = table.row(rowIdx).node();
        return row ? protectedSet.has(row.getAttribute('data-protected')) : true;
      });

      const filterBtnEl = document.getElementById('roleFilterBtn');
      const checks = document.querySelectorAll('.sas-role-protected-check');
      checks.forEach(c => c.addEventListener('change', function () {
        protectedSet = new Set(Array.from(checks).filter(x => x.checked).map(x => x.value));
        filterBtnEl.classList.toggle('has-active', protectedSet.size > 0);
        table.draw();
      }));
    })();
  </script>
@endpush
