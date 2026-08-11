@extends('layouts.app')

@section('title', 'Users & Roles')

@php
  // Read-only, presentational-only computation — same pattern used
  // throughout this redesign pass. All counts come from $users, which the
  // controller already loads in full (no new queries needed for the KPI
  // totals themselves).
  $usrTotal = $users->count();
  $usrActive = $users->where('is_active', true)->count();
  $usrByRole = fn (string $role) => $users->filter(fn ($u) => $u->roles->contains('name', $role))->count();
  $usrProviders = $usrByRole('provider');
  $usrPatients = $usrByRole('patient');
  $usrClinicAdmins = $usrByRole('clinic_admin');
  $usrBilling = $usrByRole('billing');

  $usrMonthStart = now()->startOfMonth();
  $usrThisMonth = fn ($collection) => $collection->filter(fn ($u) => $u->created_at && $u->created_at->gte($usrMonthStart))->count();

  // 7-day trailing signup counts, split by the same categories as the KPI
  // cards — genuine daily counts from $users (already loaded), not fabricated.
  $usrTrailDays = collect(range(0, 6))->map(fn ($i) => now()->subDays(6 - $i)->toDateString());
  $usrSparkFor = function ($filtered) use ($usrTrailDays) {
    $byDay = $filtered->groupBy(fn ($u) => $u->created_at?->toDateString());
    return $usrTrailDays->map(fn ($d) => $byDay->get($d, collect())->count())->all();
  };
  $usrSpark = [
    'total' => $usrSparkFor($users),
    'active' => $usrSparkFor($users->where('is_active', true)),
    'providers' => $usrSparkFor($users->filter(fn ($u) => $u->roles->contains('name', 'provider'))),
    'patients' => $usrSparkFor($users->filter(fn ($u) => $u->roles->contains('name', 'patient'))),
    'clinic_admins' => $usrSparkFor($users->filter(fn ($u) => $u->roles->contains('name', 'clinic_admin'))),
    'billing' => $usrSparkFor($users->filter(fn ($u) => $u->roles->contains('name', 'billing'))),
  ];

  $usrRolePalette = ['primary', 'success', 'warning', 'info', 'danger', 'secondary'];
  $usrRoleColors = $roles->pluck('name')->values()->mapWithKeys(fn ($name, $i) => [$name => $usrRolePalette[$i % count($usrRolePalette)]]);
@endphp

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }
    .sas-usr-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-usr-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-usr-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }
    .sas-usr-kpi__caption { font-size: var(--sas-fs-xs); margin-top: .3rem; }

    .sas-usr-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .85rem; padding: var(--sas-space-4) var(--sas-space-5); border-bottom: 1px solid var(--sas-gray-100); }
    .sas-usr-toolbar__length select { border-radius: var(--sas-radius-md); }
    .sas-usr-toolbar__search { margin-left: auto; }
    .sas-usr-toolbar__search input { border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); padding: .55rem .9rem; font-size: var(--sas-fs-sm); min-width: 220px; }
    .sas-usr-toolbar__search input:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    #usersTable_wrapper > .row:first-child { display: none; }
    #usersTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }
    #usersTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    #usersTable .btn-icon-square:hover { background: var(--sas-gray-50); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-usr-header__icon"><i class="fi fi-rr-users-alt" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-usr-header__title mb-1">Users &amp; Roles</h1>
        <p class="sas-usr-header__subtitle mb-0">Manage system users, their roles, and access permissions.</p>
      </div>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary btn-lg"><i class="fi fi-rr-plus me-1" aria-hidden="true"></i> Add User</a>
  </div>

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Total Users" :value="$usrTotal" icon="fi-rr-users-alt" bg="bg-primary-subtle" fg="text-primary"
        sparkId="usrSparkTotal" sparkColor="#2563EB" :sparkSeries="$usrSpark['total']" />
      <div class="sas-usr-kpi__caption text-muted">{{ $usrThisMonth($users) }} this month</div>
    </div>
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Active Users" :value="$usrActive" icon="fi-rr-shield-check" bg="bg-info-subtle" fg="text-info"
        sparkId="usrSparkActive" sparkColor="#2563EB" :sparkSeries="$usrSpark['active']" />
      <div class="sas-usr-kpi__caption text-muted">{{ $usrThisMonth($users->where('is_active', true)) }} this month</div>
    </div>
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Providers" :value="$usrProviders" icon="fi-rr-user-md" bg="bg-success-subtle" fg="text-success"
        sparkId="usrSparkProviders" sparkColor="#22C55E" :sparkSeries="$usrSpark['providers']" />
      <div class="sas-usr-kpi__caption text-muted">{{ $usrThisMonth($users->filter(fn ($u) => $u->roles->contains('name', 'provider'))) }} this month</div>
    </div>
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Patients" :value="$usrPatients" icon="fi-rr-user" bg="bg-warning-subtle" fg="text-warning"
        sparkId="usrSparkPatients" sparkColor="#F59E0B" :sparkSeries="$usrSpark['patients']" />
      <div class="sas-usr-kpi__caption text-muted">{{ $usrThisMonth($users->filter(fn ($u) => $u->roles->contains('name', 'patient'))) }} this month</div>
    </div>
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Clinic Admins" :value="$usrClinicAdmins" icon="fi-rr-shield" bg="bg-danger-subtle" fg="text-danger"
        sparkId="usrSparkClinicAdmins" sparkColor="#EF4444" :sparkSeries="$usrSpark['clinic_admins']" />
      <div class="sas-usr-kpi__caption text-muted">{{ $usrThisMonth($users->filter(fn ($u) => $u->roles->contains('name', 'clinic_admin'))) }} this month</div>
    </div>
    <div class="col-6 col-xl-2">
      <x-stat-widget label="Billing Users" :value="$usrBilling" icon="fi-rr-file-invoice" bg="bg-info-subtle" fg="text-info"
        sparkId="usrSparkBilling" sparkColor="#06B6D4" :sparkSeries="$usrSpark['billing']" />
      <div class="sas-usr-kpi__caption text-muted">{{ $usrThisMonth($users->filter(fn ($u) => $u->roles->contains('name', 'billing'))) }} this month</div>
    </div>
  </div>

  <x-card bodyClass="p-0">
    <form method="GET" class="sas-usr-toolbar">
      <div>
        <label class="form-label small text-muted mb-1" for="usrSearch">Search</label>
        <input type="text" name="q" id="usrSearch" value="{{ request('q') }}" class="form-control" placeholder="Name or email…" style="min-width:220px">
      </div>
      <div>
        <label class="form-label small text-muted mb-1" for="usrRole">Role</label>
        <select name="role" id="usrRole" class="form-select" onchange="this.form.submit()">
          <option value="">All</option>
          @foreach ($roles as $role)
            <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ ucwords(str_replace('_', ' ', $role->name)) }}</option>
          @endforeach
        </select>
      </div>
      <button class="btn btn-primary btn-sm align-self-end">Filter</button>
      @if (request('q') || request('role'))
        <a href="{{ route('users.index') }}" class="btn btn-light btn-sm align-self-end">Reset</a>
      @endif
    </form>

    <div class="sas-usr-toolbar">
      <span class="sas-usr-toolbar__length" id="usersLengthSlot"></span>
      <span class="sas-usr-toolbar__search" id="usersSearchSlot"></span>
    </div>

    <div class="table-responsive">
      <table id="usersTable" class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Roles</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          @forelse ($users as $u)
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="{{ $u->avatar_url }}" class="sas-avatar sas-avatar-sm" width="32" height="32" alt="">
                  <span class="fw-semibold">{{ $u->name }}</span>
                </div>
              </td>
              <td class="text-muted">{{ $u->email }}</td>
              <td>
                @foreach ($u->roles as $role)
                  <x-badge-status :color="$usrRoleColors[$role->name] ?? 'secondary'" :label="ucwords(str_replace('_', ' ', $role->name))" />
                @endforeach
              </td>
              <td>
                <x-badge-status :color="$u->is_active ? 'success' : 'secondary'" :label="$u->is_active ? 'Active' : 'Inactive'" :icon="$u->is_active ? 'fi-rr-check' : 'fi-rr-minus'" />
              </td>
              <td class="text-end">
                <div class="dropdown sas-dropdown-actions">
                  <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $u->name }}">
                    <i class="fi fi-rr-menu-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('users.edit', $u) }}"><i class="fi fi-rr-edit"></i> Edit user</a></li>
                    @if ($u->id !== auth()->id())
                      <li><hr class="dropdown-divider"></li>
                      <li>
                        <form method="POST" action="{{ route('users.destroy', $u) }}" data-sas-confirm="Delete this user?" data-sas-confirm-label="Delete">
                          @csrf @method('DELETE')
                          <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Delete user</button>
                        </form>
                      </li>
                    @endif
                  </ul>
                </div>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="5" icon="fi-rr-users-alt" title="No users found" :description="request('q') || request('role') ? 'Try a different search or filter.' : 'Invite team members to start managing appointments and clinic operations.'">
              <a href="{{ route('users.create') }}" class="btn btn-sm btn-primary"><i class="fi fi-rr-plus me-1"></i> Add User</a>
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
      const el = document.getElementById('usersTable');
      if (!el || el.querySelector('tbody td[colspan]')) return;

      jQuery(el).DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [],
        language: { search: '', searchPlaceholder: 'Search…' },
      });

      const lengthWrap = document.querySelector('#usersTable_wrapper .dataTables_length');
      const lengthSlot = document.getElementById('usersLengthSlot');
      if (lengthWrap && lengthSlot) lengthSlot.appendChild(lengthWrap);
      const filterWrap = document.querySelector('#usersTable_wrapper .dataTables_filter');
      const searchSlot = document.getElementById('usersSearchSlot');
      if (filterWrap && searchSlot) searchSlot.appendChild(filterWrap);
    })();
  </script>
@endpush
