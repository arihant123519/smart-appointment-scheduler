@extends('layouts.app')

@section('title', 'Waitlist')

@php
  // Everything below is computed from $entries, which the controller already
  // loads in full (no new queries) — same read-only-in-the-view pattern used
  // on the Calendar/Appointments/Patients pages.
  $waitlistWaiting = $entries->where('status', 'waiting');
  $waitlistTotalWaiting = $waitlistWaiting->count();
  // No "high priority" cutoff is defined anywhere in the codebase for this
  // 0-100 score — 40+ is a presentational choice for this badge/KPI only.
  $waitlistHighPriority = $waitlistWaiting->filter(fn ($e) => $e->priority >= 40)->count();
  $waitlistAvgWaitDays = $waitlistWaiting->isNotEmpty()
    ? (int) round($waitlistWaiting->avg(fn ($e) => abs(now()->diffInDays($e->created_at))))
    : 0;
  $waitlistConvertedThisMonth = $entries->filter(fn ($e) => $e->status === 'booked' && $e->updated_at?->isCurrentMonth())->count();

  $waitlistPriorityColor = fn (int $p) => match (true) { $p >= 70 => 'danger', $p >= 40 => 'warning', default => 'secondary' };
  $waitlistStatusColor = fn (string $s) => match ($s) {
    'waiting' => 'warning', 'offered' => 'info', 'booked' => 'success', 'expired', 'cancelled' => 'secondary', default => 'secondary',
  };
  $waitlistTimeIcon = fn (?string $t) => match ($t) {
    'morning' => 'fi-rr-sun', 'afternoon' => 'fi-rr-cloud-sun', 'evening' => 'fi-rr-moon', default => 'fi-rr-circle',
  };
  $waitlistServiceIcon = function (?string $name) {
    $name = strtolower($name ?? '');
    return match (true) {
      str_contains($name, 'dental') || str_contains($name, 'tooth') => 'fi-rr-tooth',
      str_contains($name, 'therapy') || str_contains($name, 'counsel') || str_contains($name, 'mental') => 'fi-rr-brain',
      str_contains($name, 'derma') || str_contains($name, 'skin') => 'fi-rr-hand-holding-medical',
      str_contains($name, 'eye') || str_contains($name, 'optic') => 'fi-rr-eye',
      str_contains($name, 'cardio') || str_contains($name, 'heart') => 'fi-rr-heart',
      default => 'fi-rr-stethoscope',
    };
  };
@endphp

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }

    .sas-wl-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-wl-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-wl-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    #waitlistTable_wrapper > .row:first-child { padding: var(--sas-space-3) var(--sas-space-5); margin: 0; align-items: center; border-bottom: 1px solid var(--sas-gray-100); }
    #waitlistTable_wrapper .dataTables_length select { border-radius: var(--sas-radius-md); }
    #waitlistTable_wrapper .dataTables_filter { display: flex; justify-content: flex-end; align-items: center; gap: .5rem; }
    #waitlistTable_wrapper .dataTables_filter input { margin-left: 0 !important; min-width: 200px; }
    #waitlistTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }

    .sas-wl-filter-btn {
      width: 38px; height: 38px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-500);
      display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background-color .15s var(--sas-ease), color .15s var(--sas-ease);
    }
    .sas-wl-filter-btn:hover { background: var(--sas-gray-50); color: var(--sas-gray-700); }
    .sas-wl-filter-btn.has-active { border-color: var(--sas-primary-400); color: var(--sas-primary-600); background: var(--sas-primary-50); }

    #waitlistTable .sas-appt-person { display: flex; align-items: center; gap: .6rem; min-width: 0; }
    #waitlistTable .sas-appt-person__name { font-weight: 700; color: var(--sas-gray-900); font-size: var(--sas-fs-sm); }
    #waitlistTable .sas-appt-person__meta { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }
    #waitlistTable .sas-appt-service { display: flex; align-items: center; gap: .55rem; font-weight: 600; font-size: var(--sas-fs-sm); color: var(--sas-gray-800); }
    #waitlistTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    #waitlistTable .btn-icon-square:hover { background: var(--sas-gray-50); }

    .sas-wl-form__icon {
      width: 44px; height: 44px; border-radius: var(--sas-radius-md); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.1rem;
    }
    .sas-wl-form label.sas-wl-form__label { font-size: var(--sas-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--sas-gray-500); margin-bottom: .4rem; display: block; }
    .sas-wl-priority-panel { background: var(--sas-gray-25); border: 1px solid var(--sas-gray-100); border-radius: var(--sas-radius-lg); padding: var(--sas-space-5); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-wl-header__icon"><i class="fi fi-rr-users-alt" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-wl-header__title mb-1">Waitlist</h1>
        <p class="sas-wl-header__subtitle mb-0">Manage patients waiting for earlier appointment openings.</p>
      </div>
    </div>
    <button type="button" class="btn btn-primary btn-lg" id="waitlistScrollToForm"><i class="fi fi-rr-plus me-1"></i> Add to waitlist</button>
  </div>

  <x-stat-strip class="mb-3">
    <x-stat-strip-item label="Total waiting" :value="$waitlistTotalWaiting" caption="Patients" icon="fi-rr-users-alt" bg="bg-primary-subtle" fg="text-primary" />
    <x-stat-strip-item label="High priority" :value="$waitlistHighPriority" caption="Patients" icon="fi-rr-flag" bg="bg-danger-subtle" fg="text-danger" />
    <x-stat-strip-item label="Avg. wait time" :value="$waitlistAvgWaitDays" caption="Days" icon="fi-rr-clock" bg="bg-success-subtle" fg="text-success" />
    <x-stat-strip-item label="Converted this month" :value="$waitlistConvertedThisMonth" caption="Patients" icon="fi-rr-calendar-check" bg="bg-info-subtle" fg="text-info" />
  </x-stat-strip>

  <x-card bodyClass="p-0" class="mb-3">
    <div class="table-responsive">
      <table id="waitlistTable" class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light">
          <tr>
            <th>Priority <i class="fi fi-rr-info" style="font-size:.75rem;color:var(--sas-gray-400);cursor:help" data-bs-toggle="tooltip" title="Computed from visit history, attendance, and referrals — hover a badge to see why. Staff can override it when adding a patient."></i></th>
            <th>Patient</th>
            <th>Service</th>
            <th>Provider pref.</th>
            <th>Time pref.</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($entries as $e)
            <tr data-status="{{ $e->status }}">
              <td data-order="{{ $e->priority }}">
                <x-badge-status :color="$waitlistPriorityColor($e->priority)" :label="$e->priority" title="{{ $reasons[$e->id] ?? '' }}" />
              </td>
              <td>
                <span class="sas-appt-person">
                  <img src="{{ $e->patient->avatar_url }}" class="sas-avatar sas-avatar-sm" alt="">
                  <span style="min-width:0">
                    <span class="sas-appt-person__name d-block">{{ $e->patient->name }}</span>
                    <span class="sas-appt-person__meta d-block">{{ $e->patient->phone ?? $e->patient->email }}</span>
                  </span>
                </span>
              </td>
              <td>
                <span class="sas-appt-service">
                  <span class="sas-icon-tile bg-primary-subtle text-primary" style="width:35px;height:35px;font-size:1rem"><i class="fi {{ $waitlistServiceIcon($e->service->name ?? null) }}" aria-hidden="true"></i></span>
                  {{ $e->service->name ?? 'Any' }}
                </span>
              </td>
              <td>
                @if ($e->provider)
                  <span class="sas-appt-person">
                    <img src="{{ $e->provider->user->avatar_url }}" class="sas-avatar sas-avatar-sm" alt="">
                    <span style="min-width:0">
                      <span class="sas-appt-person__name d-block">{{ $e->provider->name }}</span>
                      <span class="sas-appt-person__meta d-block">{{ $e->provider->specialty ?? '—' }}</span>
                    </span>
                  </span>
                @else
                  <span class="text-muted small">Any provider</span>
                @endif
              </td>
              <td>
                <span class="d-inline-flex align-items-center gap-2">
                  <i class="fi {{ $waitlistTimeIcon($e->time_pref) }} text-warning" aria-hidden="true"></i>
                  {{ $e->time_pref ? ucfirst($e->time_pref) : 'Any' }}
                </span>
              </td>
              <td><x-badge-status :color="$waitlistStatusColor($e->status)" :label="ucfirst($e->status)" /></td>
              <td class="text-end">
                <div class="dropdown sas-dropdown-actions">
                  <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $e->patient->name }}">
                    <i class="fi fi-rr-menu-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <form method="POST" action="{{ route('waitlist.destroy', $e) }}" data-sas-confirm="Remove {{ $e->patient->name }} from the waitlist?" data-sas-confirm-label="Remove">
                        @csrf @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Remove from waitlist</button>
                      </form>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="7" icon="fi-rr-list-check" title="Waitlist is empty" description="Add a patient below when a slot is full." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>

  <x-card class="sas-wl-form" id="waitlistAddForm">
    <div class="d-flex align-items-center gap-3 mb-4">
      <span class="sas-wl-form__icon"><i class="fi fi-rr-users-alt" aria-hidden="true"></i></span>
      <h2 class="mb-0" style="font-size:var(--sas-fs-lg);font-weight:700">Add to waitlist</h2>
    </div>
    <form method="POST" action="{{ route('waitlist.store') }}">
      @csrf
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <label class="sas-wl-form__label" for="waitlistPatient">Patient</label>
          <select name="patient_id" id="waitlistPatient" class="form-select" required>
            <option value="">Select…</option>
            @foreach ($patients as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="sas-wl-form__label" for="waitlistService">Service</label>
          <select name="service_id" id="waitlistService" class="form-select">
            <option value="">Any</option>
            @foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="sas-wl-form__label" for="waitlistProvider">Preferred provider</label>
          <select name="provider_id" id="waitlistProvider" class="form-select">
            <option value="">Any</option>
            @foreach ($providers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="sas-wl-form__label" for="waitlistTimePref">Time preference</label>
          <select name="time_pref" id="waitlistTimePref" class="form-select">
            @foreach (['any', 'morning', 'afternoon', 'evening'] as $t)<option value="{{ $t }}">{{ ucfirst($t) }}</option>@endforeach
          </select>
        </div>
      </div>

      <div class="sas-wl-priority-panel mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
          <i class="fi fi-rr-settings-sliders text-primary" aria-hidden="true"></i>
          <span class="fw-bold">Priority override (optional)</span>
        </div>
        <p class="text-muted small mb-3">Defaults to a computed score from the patient's visit history, attendance, and referrals — hover a priority badge above to see why.</p>
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <x-outline-field name="priority" type="number" label="Priority (0–100)" min="0" max="100" placeholder="Leave blank to auto-compute" />
          </div>
          <div class="col-md-8">
            <div class="form-text mb-0">Leave blank to use the system score.</div>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2">
        <button type="reset" class="btn btn-light">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add to waitlist</button>
      </div>
    </form>
  </x-card>
@endsection

@push('scripts')
  <script>
    (function () {
      document.getElementById('waitlistScrollToForm').addEventListener('click', function () {
        const form = document.getElementById('waitlistAddForm');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const patientSelect = document.getElementById('waitlistPatient');
        if (patientSelect) setTimeout(() => patientSelect.focus(), 400);
      });

      if (window.bootstrap && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
      }

      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
      const waitForTable = setInterval(function () {
        if (!jQuery.fn.DataTable.isDataTable('#waitlistTable')) return;
        clearInterval(waitForTable);

        const table = jQuery('#waitlistTable').DataTable();
        let statusSet = new Set();

        jQuery.fn.dataTable.ext.search.push(function (settings, data, rowIdx) {
          if (settings.nTable.id !== 'waitlistTable') return true;
          if (!statusSet.size) return true;
          const row = table.row(rowIdx).node();
          return row ? statusSet.has(row.getAttribute('data-status')) : true;
        });

        const filterWrap = document.querySelector('#waitlistTable_wrapper .dataTables_filter');
        if (!filterWrap) return;

        const wrap = document.createElement('div');
        wrap.className = 'dropdown';
        wrap.innerHTML =
          '<button type="button" class="sas-wl-filter-btn" id="waitlistStatusFilterBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Filter by status">' +
            '<i class="fi fi-rr-filter" aria-hidden="true"></i>' +
          '</button>' +
          '<ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:180px">' +
            ['waiting', 'offered', 'booked', 'expired', 'cancelled'].map(s =>
              '<li><label class="dropdown-item d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input mt-0 sas-wl-status-check" value="' + s + '"> ' + s.charAt(0).toUpperCase() + s.slice(1) + '</label></li>'
            ).join('') +
          '</ul>';
        filterWrap.appendChild(wrap);

        const filterBtnEl = document.getElementById('waitlistStatusFilterBtn');
        const checks = wrap.querySelectorAll('.sas-wl-status-check');
        checks.forEach(c => c.addEventListener('change', function () {
          statusSet = new Set(Array.from(checks).filter(x => x.checked).map(x => x.value));
          filterBtnEl.classList.toggle('has-active', statusSet.size > 0);
          table.draw();
        }));
      }, 50);
    })();
  </script>
@endpush
