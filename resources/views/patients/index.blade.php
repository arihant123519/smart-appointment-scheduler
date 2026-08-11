@extends('layouts.app')

@section('title', 'Patients')

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }

    .sas-pat-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-pat-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-pat-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-pat-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .85rem; padding: var(--sas-space-4) var(--sas-space-5); border-bottom: 1px solid var(--sas-gray-100); }
    .sas-pat-toolbar__count { font-weight: 700; font-size: var(--sas-fs-sm); color: var(--sas-gray-800); white-space: nowrap; }
    .sas-pat-toolbar__length select { border-radius: var(--sas-radius-md); }
    .sas-pat-search { position: relative; flex: 1 1 240px; min-width: 200px; }
    .sas-pat-search i { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--sas-gray-400); }
    .sas-pat-search input {
      width: 100%; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); background: var(--sas-gray-50);
      padding: .55rem .9rem .55rem 2.4rem; font-size: var(--sas-fs-sm); transition: border-color .15s var(--sas-ease), background-color .15s var(--sas-ease);
    }
    .sas-pat-search input:focus { outline: none; background: #fff; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    .sas-pat-search__clear { position: absolute; right: .6rem; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: var(--sas-gray-400); }
    .sas-pat-search__clear:hover { color: var(--sas-gray-700); }

    .sas-pat-filter-btn {
      border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); font-weight: 600; font-size: var(--sas-fs-sm);
      border-radius: var(--sas-radius-md); padding: .55rem 1rem; display: inline-flex; align-items: center; gap: .4rem; flex-shrink: 0;
    }
    .sas-pat-filter-btn:hover { background: var(--sas-gray-50); }
    .sas-pat-filter-btn.has-active { border-color: var(--sas-primary-400); color: var(--sas-primary-600); background: var(--sas-primary-50); }

    #patientsTable_wrapper > .row:first-child { display: none; } {{-- DataTables' own length+filter row: superseded by .sas-pat-toolbar above --}}
    #patientsTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }

    #patientsTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    #patientsTable .btn-icon-square:hover { background: var(--sas-gray-50); }
  </style>
@endpush

@section('content')
  @php
    $totalPatients = $patients->count();
    $activePatients = $patients->where('is_active', true)->count();
    $inactivePatients = $totalPatients - $activePatients;
    $newThisWeek = $patients->filter(fn ($p) => $p->created_at && $p->created_at->gte(now()->subDays(7)))->count();
    $newLastWeek = $patients->filter(fn ($p) => $p->created_at && $p->created_at->between(now()->subDays(14), now()->subDays(7)))->count();
    $newWeekCaption = $newLastWeek > 0
      ? ($newThisWeek >= $newLastWeek ? '+' : '').round(($newThisWeek - $newLastWeek) / $newLastWeek * 100).'% vs last week'
      : ($newThisWeek > 0 ? 'New this week' : '+0% vs last week');
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-pat-header__icon"><i class="fi fi-rr-users" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-pat-header__title mb-1">Patients</h1>
        <p class="sas-pat-header__subtitle mb-0">Manage and view all patient records.</p>
      </div>
    </div>
    <a href="{{ route('patients.create') }}" class="btn btn-primary btn-lg"><i class="fi fi-rr-plus me-1"></i> Add Patient</a>
  </div>

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-6 col-xl-3">
      <x-stat-widget label="Total patients" :value="$totalPatients" icon="fi-rr-users" bg="bg-primary-subtle" fg="text-primary" caption="All time" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget label="Active" :value="$activePatients" icon="fi-rr-user-check" bg="bg-success-subtle" fg="text-success"
        :caption="$totalPatients > 0 ? round($activePatients / $totalPatients * 100).'% of total' : '—'" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget label="Inactive" :value="$inactivePatients" icon="fi-rr-user-minus" bg="bg-danger-subtle" fg="text-danger"
        :caption="$totalPatients > 0 ? round($inactivePatients / $totalPatients * 100).'% of total' : '—'" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget label="New this week" :value="$newThisWeek" icon="fi-rr-arrow-trend-up" bg="bg-info-subtle" fg="text-info" :caption="$newWeekCaption" />
    </div>
  </div>

  <x-card bodyClass="p-0">
    <div class="sas-pat-toolbar">
      <span class="sas-pat-toolbar__count">{{ $totalPatients }} patient{{ $totalPatients === 1 ? '' : 's' }}</span>
      <span class="sas-pat-toolbar__length" id="patientsLengthSlot"></span>

      <form method="GET" class="sas-pat-search">
        <i class="fi fi-rr-search" aria-hidden="true"></i>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, email or phone…" aria-label="Search patients">
        @if (request('q'))
          <a href="{{ route('patients.index') }}" class="sas-pat-search__clear" aria-label="Clear search"><i class="fi fi-rr-cross" aria-hidden="true"></i></a>
        @endif
      </form>

      <div class="dropdown">
        <button type="button" class="sas-pat-filter-btn" id="patientStatusFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fi fi-rr-filter" aria-hidden="true"></i> Filters
        </button>
        <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:180px">
          <li><label class="dropdown-item d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input mt-0 sas-pat-status-check" value="active"> Active</label></li>
          <li><label class="dropdown-item d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input mt-0 sas-pat-status-check" value="inactive"> Inactive</label></li>
        </ul>
      </div>
    </div>

    @if (request('q'))
      <div class="px-4 pt-3 text-muted small">
        {{ $totalPatients }} patient{{ $totalPatients === 1 ? '' : 's' }} matching &ldquo;{{ request('q') }}&rdquo;
      </div>
    @endif

    <div class="table-responsive">
      <table id="patientsTable" class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Phone</th><th>Visits</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          @forelse ($patients as $p)
            <tr data-status="{{ $p->is_active ? 'active' : 'inactive' }}">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <img src="{{ $p->avatar_url }}" class="sas-avatar sas-avatar-sm" alt="">
                  <a href="{{ route('patients.show', $p) }}" class="fw-semibold text-body text-decoration-none">{{ $p->name }}</a>
                </div>
              </td>
              <td class="text-muted">{{ $p->email }}</td>
              <td class="text-muted">{{ $p->phone ?? '—' }}</td>
              <td>{{ $p->appointments_count }}</td>
              <td>
                <x-badge-status :color="$p->is_active ? 'success' : 'secondary'" :label="$p->is_active ? 'Active' : 'Inactive'" :icon="$p->is_active ? 'fi-rr-check' : 'fi-rr-minus'" />
              </td>
              <td class="text-end">
                <div class="dropdown sas-dropdown-actions">
                  <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions for {{ $p->name }}">
                    <i class="fi fi-rr-menu-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('patients.show', $p) }}"><i class="fi fi-rr-eye"></i> View profile</a></li>
                    <li><a class="dropdown-item" href="{{ route('patients.edit', $p) }}"><i class="fi fi-rr-edit"></i> Edit details</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <form method="POST" action="{{ route('patients.destroy', $p) }}" data-sas-confirm="Remove {{ $p->name }} from your patient list? This can't be undone." data-sas-confirm-label="Remove patient">
                        @csrf @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Remove patient</button>
                      </form>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="6" icon="fi-rr-users" title="No patients found" :description="request('q') ? 'No one matches your search — try a different name, email or phone.' : 'Add your first patient to start scheduling appointments.'">
              <a href="{{ route('patients.create') }}" class="btn btn-sm btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Patient</a>
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
      const waitForTable = setInterval(function () {
        if (!jQuery.fn.DataTable.isDataTable('#patientsTable')) return;
        clearInterval(waitForTable);

        const table = jQuery('#patientsTable').DataTable();

        // Move DataTables' own (real, functional) length control into our
        // custom toolbar row instead of rebuilding a fake one.
        const lengthWrap = document.querySelector('#patientsTable_wrapper .dataTables_length');
        const slot = document.getElementById('patientsLengthSlot');
        if (lengthWrap && slot) slot.appendChild(lengthWrap);

        let statusSet = new Set();
        jQuery.fn.dataTable.ext.search.push(function (settings, data, rowIdx) {
          if (settings.nTable.id !== 'patientsTable') return true;
          if (!statusSet.size) return true;
          const row = table.row(rowIdx).node();
          return row ? statusSet.has(row.getAttribute('data-status')) : true;
        });

        const filterBtnEl = document.getElementById('patientStatusFilterBtn');
        const checks = document.querySelectorAll('.sas-pat-status-check');
        checks.forEach(c => c.addEventListener('change', function () {
          statusSet = new Set(Array.from(checks).filter(x => x.checked).map(x => x.value));
          filterBtnEl.classList.toggle('has-active', statusSet.size > 0);
          table.draw();
        }));
      }, 50);
    })();
  </script>
@endpush
