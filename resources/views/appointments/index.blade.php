@extends('layouts.app')

@section('title', 'Appointments')

@php
  // Read-only, presentational only (same pattern as the Calendar page) — real
  // last-7-day trend series for the KPI sparklines. Scoping mirrors
  // AppointmentController::index()'s own provider-role restriction exactly.
  $apptScope = function () {
    $q = \App\Models\Appointment::forCurrentClinic();
    $u = auth()->user();
    if ($u->hasRole('provider') && ! $u->hasAnyRole(['clinic_admin', 'system_admin']) && $u->provider) {
      $q->where('provider_id', $u->provider->id);
    }

    return $q;
  };

  $apptTrailStart = now()->subDays(6)->startOfDay();
  $apptTrailRows = $apptScope()->where('start_at', '>=', $apptTrailStart)
    ->selectRaw(
      "DATE(start_at) as d, COUNT(*) as total,
       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
       SUM(CASE WHEN status IN ('cancelled','no_show') THEN 1 ELSE 0 END) as cancelled"
    )->groupBy('d')->orderBy('d')->get()->keyBy('d');

  $apptFwdEnd = now()->addDays(6)->endOfDay();
  $apptFwdRows = $apptScope()->whereBetween('start_at', [now()->startOfDay(), $apptFwdEnd])
    ->whereIn('status', ['booked', 'confirmed'])
    ->selectRaw('DATE(start_at) as d, COUNT(*) as upcoming')
    ->groupBy('d')->orderBy('d')->get()->keyBy('d');

  $apptSpark = ['total' => [], 'completed' => [], 'upcoming' => [], 'cancelled' => []];
  foreach (range(0, 6) as $i) {
    $tr = $apptTrailRows->get(now()->subDays(6 - $i)->toDateString());
    $apptSpark['total'][] = (int) ($tr->total ?? 0);
    $apptSpark['completed'][] = (int) ($tr->completed ?? 0);
    $apptSpark['cancelled'][] = (int) ($tr->cancelled ?? 0);
    $fw = $apptFwdRows->get(now()->addDays($i)->toDateString());
    $apptSpark['upcoming'][] = (int) ($fw->upcoming ?? 0);
  }

  // Same keyword → icon heuristic used only for a decorative service-type
  // glyph; falls back to a neutral icon since Service has no category field.
  $apptServiceIcon = function (?string $name) {
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

    .sas-appt-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-appt-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-appt-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    /* Filter bar */
    .sas-appt-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; }
    .sas-appt-search { position: relative; flex: 1 1 260px; min-width: 220px; }
    .sas-appt-search i { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--sas-gray-400); }
    .sas-appt-search input {
      width: 100%; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); background: var(--sas-gray-50);
      padding: .6rem .9rem .6rem 2.4rem; font-size: var(--sas-fs-sm); transition: border-color .15s var(--sas-ease), background-color .15s var(--sas-ease);
    }
    .sas-appt-search input:focus { outline: none; background: #fff; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    .sas-appt-pillfield { display: inline-flex; align-items: center; gap: .5rem; }
    .sas-appt-pillfield label { font-size: var(--sas-fs-xs); font-weight: 700; color: var(--sas-gray-500); white-space: nowrap; }
    .sas-appt-pillfield select, .sas-appt-pillfield input[type=date] {
      border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); background: #fff; color: var(--sas-gray-800);
      font-size: var(--sas-fs-sm); font-weight: 600; padding: .5rem .7rem;
    }
    .sas-appt-pillfield select:focus, .sas-appt-pillfield input[type=date]:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    .sas-appt-more-btn {
      border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-primary-700); font-weight: 600; font-size: var(--sas-fs-sm);
      border-radius: var(--sas-radius-md); padding: .55rem 1rem; display: inline-flex; align-items: center; gap: .4rem;
    }
    .sas-appt-more-btn:hover { background: var(--sas-primary-50); }
    .sas-appt-more-btn.has-active { border-color: var(--sas-primary-400); background: var(--sas-primary-50); }

    /* Quick pill rows */
    .sas-appt-pillrow { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .sas-appt-pillrow__label { font-size: var(--sas-fs-xs); font-weight: 700; color: var(--sas-gray-500); margin-right: .3rem; }
    .sas-pill {
      display: inline-flex; align-items: center; gap: .4rem; border: 1px solid var(--sas-gray-200); background: #fff;
      color: var(--sas-gray-600); font-size: var(--sas-fs-xs); font-weight: 600; border-radius: 999px; padding: .35rem .8rem;
      transition: border-color .15s var(--sas-ease), background-color .15s var(--sas-ease), color .15s var(--sas-ease);
    }
    .sas-pill:hover { border-color: var(--sas-gray-300); color: var(--sas-gray-900); }
    .sas-pill.active { background: var(--sas-primary-50); border-color: var(--sas-primary-300); color: var(--sas-primary-700); }
    .sas-pill__dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .sas-pill-icon-only {
      width: 30px; height: 30px; padding: 0; justify-content: center; border-radius: 50%;
    }

    /* Table */
    #apptTable thead th { position: sticky; top: 0; z-index: 1; background: var(--sas-gray-25); }
    #apptTable .sas-appt-person { display: flex; align-items: center; gap: .65rem; min-width: 0; }
    #apptTable .sas-appt-person__name { font-weight: 700; color: var(--sas-gray-900); font-size: var(--sas-fs-sm); }
    #apptTable .sas-appt-person__meta { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }
    #apptTable .sas-appt-when__date { font-weight: 700; color: var(--sas-gray-900); font-size: var(--sas-fs-sm); }
    #apptTable .sas-appt-when__time { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }
    #apptTable .sas-appt-service { display: flex; align-items: center; gap: .55rem; font-weight: 600; font-size: var(--sas-fs-sm); color: var(--sas-gray-800); }
    .sas-appt-risk { display: inline-flex; align-items: center; gap: .4rem; font-weight: 700; font-size: var(--sas-fs-sm); }
    .sas-appt-actions { display: flex; align-items: center; justify-content: flex-end; gap: .3rem; }
    .sas-appt-actions .btn-icon { width: 32px; height: 32px; }
  </style>
@endpush

@section('content')
  @php
    $statusCounts = $appointments->countBy('status');
    $activeFilters = collect(request()->only(['q', 'status', 'provider_id', 'date']))->filter(fn ($v) => filled($v));
    $filterLabels = [
      'q' => fn ($v) => 'Search: "'.$v.'"',
      'status' => fn ($v) => 'Status: '.($statuses[$v] ?? $v),
      'provider_id' => fn ($v) => 'Provider: '.optional($providers->firstWhere('id', (int) $v))->name,
      'date' => fn ($v) => \Illuminate\Support\Carbon::parse($v)->format('M j, Y'),
    ];
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-appt-header__icon"><i class="fi fi-rr-calendar" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-appt-header__title mb-1">Appointments</h1>
        <p class="sas-appt-header__subtitle mb-0">View and manage all appointments</p>
      </div>
    </div>
    @can('manage appointments')
      <div class="d-flex align-items-center gap-2">
        <a href="{{ route('appointments.create') }}" class="btn btn-primary btn-lg"><i class="fi fi-rr-plus me-1"></i> New Appointment</a>
        <div class="dropdown">
          <button type="button" class="btn btn-light btn-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
            <i class="fi fi-rr-menu-dots-vertical" aria-hidden="true"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="{{ route('appointments.notifications.edit') }}"><i class="fi fi-rr-bell me-2" aria-hidden="true"></i>Notification settings</a></li>
            @can('export patient data')
              <li><a class="dropdown-item" href="{{ route('appointments.export') }}"><i class="fi fi-rr-download me-2" aria-hidden="true"></i>Export CSV</a></li>
            @endcan
          </ul>
        </div>
      </div>
    @endcan
  </div>

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-6 col-xl-3">
      <x-stat-widget label="Showing" :value="$appointments->count()" icon="fi-rr-list-check" bg="bg-primary-subtle" fg="text-primary"
        caption="Total appointments" sparkId="apptSparkTotal" sparkColor="#2563EB" :sparkSeries="$apptSpark['total']" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget label="Completed" :value="$statusCounts->get('completed', 0)" icon="fi-rr-check" bg="bg-success-subtle" fg="text-success"
        caption="In current view" sparkId="apptSparkCompleted" sparkColor="#22C55E" :sparkSeries="$apptSpark['completed']" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget label="Upcoming" :value="$statusCounts->get('booked', 0) + $statusCounts->get('confirmed', 0)" icon="fi-rr-clock" bg="bg-info-subtle" fg="text-info"
        caption="In current view" sparkId="apptSparkUpcoming" sparkColor="#7C3AED" :sparkSeries="$apptSpark['upcoming']" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget label="Cancelled / no-show" :value="$statusCounts->get('cancelled', 0) + $statusCounts->get('no_show', 0)" icon="fi-rr-calendar-xmark" bg="bg-danger-subtle" fg="text-danger"
        caption="In current view" sparkId="apptSparkCancelled" sparkColor="#EF4444" :sparkSeries="$apptSpark['cancelled']" />
    </div>
  </div>

  <x-card class="mb-3">
    <form method="GET" class="sas-appt-toolbar">
      <div class="sas-appt-search">
        <i class="fi fi-rr-search" aria-hidden="true"></i>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search patient name, phone, email…" aria-label="Search patient name, phone, email">
      </div>

      <div class="sas-appt-pillfield">
        <label for="apptStatusSelect">Status</label>
        <select name="status" id="apptStatusSelect">
          <option value="">All</option>
          @foreach ($statuses as $key => $label)
            <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>

      @if (auth()->user()->hasAnyRole(['system_admin', 'front_desk', 'clinic_admin']))
        <div class="sas-appt-pillfield">
          <label for="apptProviderSelect">Provider</label>
          <select name="provider_id" id="apptProviderSelect">
            <option value="">All</option>
            @foreach ($providers as $p)
              <option value="{{ $p->id }}" @selected(request('provider_id') == $p->id)>{{ $p->name }}</option>
            @endforeach
          </select>
        </div>
      @endif

      <div class="sas-appt-pillfield">
        <label for="apptDateFilter">Date</label>
        <input type="date" name="date" value="{{ request('date') }}" id="apptDateFilter">
      </div>

      <button class="btn btn-primary btn-sm">Filter</button>

      <div class="dropdown ms-auto">
        <button type="button" class="sas-appt-more-btn" id="apptMoreFiltersBtn" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fi fi-rr-filter" aria-hidden="true"></i> More Filters
        </button>
        <div class="dropdown-menu dropdown-menu-end p-3" style="min-width:240px">
          <div class="sas-section-label mb-2" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--sas-gray-500)">No-show risk</div>
          <div class="form-check">
            <input class="form-check-input sas-appt-risk-check" type="checkbox" value="high" id="apptRiskHigh">
            <label class="form-check-label small" for="apptRiskHigh">High risk</label>
          </div>
          <div class="form-check">
            <input class="form-check-input sas-appt-risk-check" type="checkbox" value="medium" id="apptRiskMedium">
            <label class="form-check-label small" for="apptRiskMedium">Medium risk</label>
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input sas-appt-risk-check" type="checkbox" value="low" id="apptRiskLow">
            <label class="form-check-label small" for="apptRiskLow">Low risk</label>
          </div>
          <div class="form-text mb-2">Filters the rows currently loaded below — doesn't change search results.</div>
          <button type="button" class="btn btn-light btn-sm w-100" id="apptRiskClear">Clear</button>
        </div>
      </div>
    </form>

    <div class="sas-appt-pillrow mt-3">
      <span class="sas-appt-pillrow__label">Status:</span>
      @php $qsBase = request()->except('status'); @endphp
      <a href="{{ route('appointments.index', $qsBase) }}" class="sas-pill {{ request('status') ? '' : 'active' }}">All</a>
      @foreach ($statuses as $key => $label)
        <a href="{{ route('appointments.index', array_merge($qsBase, ['status' => $key])) }}" class="sas-pill {{ request('status') === $key ? 'active' : '' }}">
          <span class="sas-pill__dot" style="background:{{ ['booked' => '#f6b100', 'confirmed' => '#7239ea', 'checked_in' => '#2563EB', 'completed' => '#17c653', 'no_show' => '#f1416c', 'cancelled' => '#adb5bd'][$key] ?? '#adb5bd' }}"></span>
          {{ $label }}
        </a>
      @endforeach

      <span class="sas-appt-pillrow__label ms-3">Quick date:</span>
      <button type="button" class="sas-pill" data-sas-date-preset="0">Today</button>
      <button type="button" class="sas-pill" data-sas-date-preset="1">Tomorrow</button>
      <button type="button" class="sas-pill" id="apptQuickWeek" data-range="week">This Week</button>
      <button type="button" class="sas-pill" id="apptQuickMonth" data-range="month">This Month</button>
      <button type="button" class="sas-pill sas-pill-icon-only" id="apptOpenDatePicker" aria-label="Pick a specific date" title="Pick a specific date"><i class="fi fi-rr-calendar" aria-hidden="true"></i></button>
      @if (request('date'))
        <a href="{{ route('appointments.index', request()->except('date')) }}" class="sas-pill">Clear date <i class="fi fi-rr-cross" aria-hidden="true"></i></a>
      @endif
    </div>

    @if ($activeFilters->isNotEmpty())
      <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-3 border-top">
        <span class="text-muted small me-1">Active filters:</span>
        @foreach ($activeFilters as $key => $value)
          <a href="{{ route('appointments.index', request()->except($key)) }}" class="sas-filter-chip">
            {{ $filterLabels[$key]($value) }} <i class="fi fi-rr-cross"></i>
          </a>
        @endforeach
        <a href="{{ route('appointments.index') }}" class="text-muted small text-decoration-none ms-1">Clear all</a>
      </div>
    @endif
  </x-card>

  <x-card bodyClass="p-0">
    <div class="table-responsive">
      <table id="apptTable" class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light">
          <tr>
            <th>When</th>
            <th>Patient</th>
            <th>Provider</th>
            <th>Service</th>
            <th>Status</th>
            <th>Risk <i class="fi fi-rr-info" style="font-size:.75rem;color:var(--sas-gray-400);cursor:help" data-bs-toggle="tooltip" title="AI-predicted likelihood the patient won't show up"></i></th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($appointments as $a)
            @php $rc = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'][$a->risk_level]; @endphp
            <tr data-date="{{ $a->start_at->toDateString() }}" data-risk="{{ $a->risk_level }}">
              <td data-order="{{ $a->start_at->timestamp }}">
                <div class="sas-appt-when__date">{{ $a->start_at->format('M j, Y') }}</div>
                <div class="sas-appt-when__time">{{ $a->start_at->format('g:i A') }}</div>
              </td>
              <td>
                <a href="{{ route('patients.show', $a->patient) }}" class="sas-appt-person text-decoration-none">
                  <img src="{{ $a->patient->avatar_url }}" class="sas-avatar sas-avatar-sm" alt="">
                  <span style="min-width:0">
                    <span class="sas-appt-person__name d-block">{{ $a->patient->name }}</span>
                    <span class="sas-appt-person__meta d-block">{{ $a->patient->phone ?? $a->patient->email }}</span>
                  </span>
                </a>
              </td>
              <td>
                <span class="sas-appt-person">
                  <img src="{{ $a->provider->user->avatar_url }}" class="sas-avatar sas-avatar-sm" alt="">
                  <span style="min-width:0">
                    <span class="sas-appt-person__name d-block">{{ $a->provider->name }}</span>
                    <span class="sas-appt-person__meta d-block">{{ $a->provider->specialty ?? '—' }}</span>
                  </span>
                </span>
              </td>
              <td>
                <span class="sas-appt-service">
                  <span class="sas-icon-tile bg-primary-subtle text-primary" style="width:28px;height:28px;font-size:.8rem"><i class="fi {{ $apptServiceIcon($a->service->name ?? null) }}" aria-hidden="true"></i></span>
                  {{ $a->service->name ?? '—' }}
                </span>
              </td>
              <td><x-badge-status :color="$a->status_color" :label="$a->status_label" /></td>
              <td data-order="{{ $a->no_show_score }}">
                <span class="sas-appt-risk text-{{ $rc }}">
                  <span class="sas-pill__dot" style="background:currentColor"></span>{{ $a->no_show_score }}%
                </span>
              </td>
              <td class="text-end">
                <div class="sas-appt-actions">
                  <a href="{{ route('appointments.show', $a) }}" class="btn btn-sm btn-light btn-icon" aria-label="View details" title="View details"><i class="fi fi-rr-eye" aria-hidden="true"></i></a>
                  @can('manage appointments')
                    <a href="{{ route('appointments.edit', $a) }}" class="btn btn-sm btn-light btn-icon" aria-label="Edit appointment" title="Edit appointment"><i class="fi fi-rr-pencil" aria-hidden="true"></i></a>
                  @endcan
                  @can('manage appointments')
                  @php $nextStatuses = collect($a->availableStatusOptions())->except($a->status); @endphp
                    @if ($nextStatuses->isNotEmpty())
                      @foreach ($nextStatuses as $key => $label)
                        <li>
                          <form method="POST" action="{{ route('appointments.status', $a) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $key }}">
                            <button type="submit" class="dropdown-item{{ in_array($key, ['cancelled', 'no_show']) ? ' text-danger' : '' }}">
                              <i class="fi {{ ['confirmed' => 'fi-rr-check', 'checked_in' => 'fi-rr-door-open', 'completed' => 'fi-rr-check-circle', 'cancelled' => 'fi-rr-cross-circle', 'no_show' => 'fi-rr-exclamation'][$key] ?? 'fi-rr-refresh' }}"></i>
                              Mark {{ $label }}
                            </button>
                          </form>
                        </li>
                      @endforeach
                    @endif
                  @endcan
                </div>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="7" icon="fi-rr-calendar" title="No appointments found" :description="$activeFilters->isNotEmpty() ? 'Try adjusting or clearing your filters.' : 'Appointments you schedule will show up here.'">
              @can('manage appointments')
                <a href="{{ route('appointments.create') }}" class="btn btn-sm btn-primary"><i class="fi fi-rr-plus me-1"></i> New Appointment</a>
              @endcan
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
      // --- Existing quick-date presets (server round-trip, unchanged) --------
      const dateInput = document.getElementById('apptDateFilter');
      document.querySelectorAll('[data-sas-date-preset]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const days = parseInt(btn.dataset.sasDatePreset, 10) || 0;
          const d = new Date();
          d.setDate(d.getDate() + days);
          dateInput.value = d.toISOString().slice(0, 10);
          dateInput.form.requestSubmit();
        });
      });
      const openPicker = document.getElementById('apptOpenDatePicker');
      if (openPicker && dateInput) {
        openPicker.addEventListener('click', function () {
          if (dateInput.showPicker) { dateInput.showPicker(); } else { dateInput.focus(); }
        });
      }

      // --- Tooltips ------------------------------------------------------------
      if (window.bootstrap && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
      }

      // --- Client-side refinements on top of the already-loaded rows ---------
      // ("This Week" / "This Month" and the risk filter never touch the server —
      // they only narrow what's visible among rows AppointmentController already
      // returned, exactly like DataTables' own built-in search does.)
      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
      const $table = jQuery('#apptTable');
      const waitForTable = setInterval(function () {
        if (!jQuery.fn.DataTable.isDataTable('#apptTable')) return;
        clearInterval(waitForTable);
        const table = $table.DataTable();

        let dateRange = null;
        let riskSet = new Set();

        jQuery.fn.dataTable.ext.search.push(function (settings, data, rowIdx) {
          if (settings.nTable.id !== 'apptTable') return true;
          const row = table.row(rowIdx).node();
          if (!row) return true;

          if (dateRange) {
            const raw = row.getAttribute('data-date');
            if (!raw) return false;
            const d = new Date(raw + 'T00:00:00');
            const now = new Date(); now.setHours(0, 0, 0, 0);
            if (dateRange === 'week') {
              const start = new Date(now); start.setDate(now.getDate() - now.getDay());
              const end = new Date(start); end.setDate(start.getDate() + 6);
              if (d < start || d > end) return false;
            } else if (dateRange === 'month') {
              if (d.getMonth() !== now.getMonth() || d.getFullYear() !== now.getFullYear()) return false;
            }
          }

          if (riskSet.size) {
            const risk = row.getAttribute('data-risk');
            if (!riskSet.has(risk)) return false;
          }

          return true;
        });

        const weekBtn = document.getElementById('apptQuickWeek');
        const monthBtn = document.getElementById('apptQuickMonth');
        [weekBtn, monthBtn].forEach(btn => {
          btn.addEventListener('click', function () {
            const next = dateRange === btn.dataset.range ? null : btn.dataset.range;
            dateRange = next;
            [weekBtn, monthBtn].forEach(b => b.classList.toggle('active', b === btn && next !== null));
            table.draw();
          });
        });

        const riskChecks = document.querySelectorAll('.sas-appt-risk-check');
        const moreBtn = document.getElementById('apptMoreFiltersBtn');
        function syncRisk() {
          riskSet = new Set(Array.from(riskChecks).filter(c => c.checked).map(c => c.value));
          moreBtn.classList.toggle('has-active', riskSet.size > 0);
          table.draw();
        }
        riskChecks.forEach(c => c.addEventListener('change', syncRisk));
        document.getElementById('apptRiskClear').addEventListener('click', function () {
          riskChecks.forEach(c => { c.checked = false; });
          syncRisk();
        });
      }, 50);
    })();
  </script>
@endpush
