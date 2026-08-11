@extends('layouts.app')

@section('title', 'Clinics')

@php
  // Read-only, presentational computation — same pattern as every other
  // page in this pass. ClinicController::index() takes no request params at
  // all, so search/status/location/timezone filtering below is genuine
  // client-side JS, not a server round-trip that doesn't exist.
  $clnTotal = $clinics->count();
  $clnActive = $clinics->where('is_active', true)->count();
  $clnTotalProviders = (int) $clinics->sum('providers_count');
  $clnTotalServices = (int) $clinics->sum('services_count');
  $clnTotalAppointments = (int) $clinics->sum('appointments_count');
  $clnActivePct = $clnTotal > 0 ? round($clnActive / $clnTotal * 100) : 0;

  // Clinic has no is_primary column — "Primary" here is a presentational
  // convention (the first/oldest clinic by id), not a real flag.
  $clnPrimaryId = $clinics->min('id');

  $clnOffsetFor = function (?string $tz) {
    try {
      $offset = (new \DateTimeZone($tz ?: 'UTC'))->getOffset(new \DateTime()) / 3600;
    } catch (\Throwable $e) {
      return null;
    }
    $sign = $offset == 0 ? '±' : ($offset > 0 ? '+' : '-');
    $abs = abs($offset);
    return sprintf('UTC %s%02d:%02d', $sign, floor($abs), ($abs - floor($abs)) * 60);
  };

  $clnTopByAppointments = $clinics->sortByDesc('appointments_count')->first();
  $clnMostProviders = $clinics->sortByDesc('providers_count')->first();
  $clnTodayAppointments = \App\Models\Appointment::whereDate('start_at', today())->active()->count();
  $clnTimezoneDist = $clinics->countBy('timezone');
@endphp

@push('styles')
  <style>
    .sas-cln-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-cln-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-cln-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-cln-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .7rem; padding: var(--sas-space-4) var(--sas-space-5); border-bottom: 1px solid var(--sas-gray-100); }
    .sas-cln-search { position: relative; flex: 1 1 220px; min-width: 200px; }
    .sas-cln-search i { position: absolute; left: .9rem; top: 50%; transform: translateY(-50%); color: var(--sas-gray-400); }
    .sas-cln-search input { width: 100%; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); padding: .55rem .9rem .55rem 2.4rem; font-size: var(--sas-fs-sm); }
    .sas-cln-search input:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    .sas-cln-select { border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); padding: .55rem .9rem; font-size: var(--sas-fs-sm); font-weight: 600; color: var(--sas-gray-700); min-width: 150px; }
    .sas-cln-select:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    .sas-cln-clear { margin-left: auto; border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); font-weight: 600; font-size: var(--sas-fs-sm); border-radius: var(--sas-radius-md); padding: .55rem 1rem; display: inline-flex; align-items: center; gap: .4rem; }
    .sas-cln-clear:hover { background: var(--sas-gray-50); }

    #clinicsTable_wrapper > .row:first-child { display: none; }
    #clinicsTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }
    #clinicsTable .sas-cln-logo { width: 56px; height: 56px; border-radius: var(--sas-radius-md); object-fit: cover; flex-shrink: 0; }
    #clinicsTable .sas-cln-logo-placeholder { width: 56px; height: 56px; border-radius: var(--sas-radius-md); background: var(--sas-gray-100); color: var(--sas-gray-400); display: grid; place-items: center; font-size: 1.3rem; flex-shrink: 0; }
    #clinicsTable .sas-cln-name { font-weight: 700; color: var(--sas-gray-900); }
    #clinicsTable .sas-cln-subtitle { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }
    #clinicsTable .sas-cln-count-chip { display: inline-flex; align-items: center; gap: .35rem; background: var(--sas-gray-50); border: 1px solid var(--sas-gray-100); border-radius: var(--sas-radius-sm); padding: .25rem .6rem; font-weight: 700; font-size: var(--sas-fs-sm); }
    #clinicsTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    #clinicsTable .btn-icon-square:hover { background: var(--sas-gray-50); }

    .sas-cln-bottom-card { display: flex; align-items: center; gap: .9rem; }
    .sas-cln-bottom-card__icon { width: 44px; height: 44px; border-radius: var(--sas-radius-md); display: grid; place-items: center; font-size: 1.1rem; flex-shrink: 0; }
    .sas-cln-bottom-card__label { font-size: var(--sas-fs-xs); font-weight: 700; color: var(--sas-gray-500); text-transform: uppercase; letter-spacing: .03em; margin-bottom: .35rem; }
    .sas-cln-bottom-card__value { font-size: 1.4rem; font-weight: 800; color: var(--sas-gray-900); line-height: 1.1; }
    .sas-cln-bottom-card__caption { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }
    .sas-cln-tz-legend-row { display: flex; align-items: center; gap: .5rem; padding: .3rem 0; font-size: var(--sas-fs-sm); }
    .sas-cln-tz-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-cln-header__icon"><i class="fi fi-rr-building" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-cln-header__title mb-1">Clinics</h1>
        <p class="sas-cln-header__subtitle mb-0">Manage all clinics, their settings, providers, services and appointment activity.</p>
      </div>
    </div>
    <a href="{{ route('clinics.create') }}" class="btn btn-primary btn-lg"><i class="fi fi-rr-plus me-1" aria-hidden="true"></i> Add Clinic</a>
  </div>

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-6 col-xl">
      <x-stat-widget label="Total Clinics" :value="$clnTotal" icon="fi-rr-building" bg="bg-primary-subtle" fg="text-primary" caption="All clinics" />
    </div>
    <div class="col-6 col-xl">
      <x-stat-widget label="Total Providers" :value="$clnTotalProviders" icon="fi-rr-users-alt" bg="bg-success-subtle" fg="text-success" caption="Across all clinics" />
    </div>
    <div class="col-6 col-xl">
      <x-stat-widget label="Total Services" :value="$clnTotalServices" icon="fi-rr-grid" bg="bg-info-subtle" fg="text-info" caption="Across all clinics" />
    </div>
    <div class="col-6 col-xl">
      <x-stat-widget label="Total Appointments" :value="$clnTotalAppointments" icon="fi-rr-calendar" bg="bg-warning-subtle" fg="text-warning" caption="All time" />
    </div>
    <div class="col-6 col-xl">
      <x-stat-widget label="Active Clinics" :value="$clnActive" icon="fi-rr-signal-alt" bg="bg-success-subtle" fg="text-success" :caption="$clnActivePct.'% active'" />
    </div>
  </div>

  <x-card bodyClass="p-0" class="mb-3">
    <div class="sas-cln-toolbar">
      <div class="sas-cln-search">
        <i class="fi fi-rr-search" aria-hidden="true"></i>
        <input type="text" id="clnSearch" placeholder="Search clinics…" aria-label="Search clinics">
      </div>
      <select id="clnStatusFilter" class="sas-cln-select" aria-label="Filter by status">
        <option value="">All Status</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
      <select id="clnLocationFilter" class="sas-cln-select" aria-label="Filter by location">
        <option value="">All Locations</option>
        @foreach ($clinics->pluck('city')->filter()->unique()->sort() as $city)
          <option value="{{ $city }}">{{ $city }}</option>
        @endforeach
      </select>
      <select id="clnTimezoneFilter" class="sas-cln-select" aria-label="Filter by timezone">
        <option value="">All Timezones</option>
        @foreach ($clinics->pluck('timezone')->filter()->unique()->sort() as $tz)
          <option value="{{ $tz }}">{{ $tz }}</option>
        @endforeach
      </select>
      <button type="button" class="sas-cln-clear" id="clnClearFilters"><i class="fi fi-rr-refresh" aria-hidden="true"></i> Clear Filters</button>
    </div>

    <div class="table-responsive">
      <table id="clinicsTable" class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Clinic</th><th>Location</th><th>Timezone</th><th>Providers</th><th>Services</th><th>Appointments</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          @forelse ($clinics as $c)
            <tr data-status="{{ $c->is_active ? 'active' : 'inactive' }}" data-city="{{ $c->city }}" data-timezone="{{ $c->timezone }}" data-search="{{ strtolower($c->name.' '.$c->city.' '.$c->state.' '.$c->country) }}">
              <td>
                <div class="d-flex align-items-center gap-3">
                  @if ($c->logo_url)
                    <img src="{{ $c->logo_url }}" class="sas-cln-logo" alt="">
                  @else
                    <span class="sas-cln-logo-placeholder"><i class="fi fi-rr-building" aria-hidden="true"></i></span>
                  @endif
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <span class="sas-cln-name">{{ $c->name }}</span>
                      @if ($c->id === $clnPrimaryId)
                        <x-badge-status color="primary" label="Primary" />
                      @endif
                    </div>
                    <div class="sas-cln-subtitle">{{ $c->id === $clnPrimaryId ? 'Main clinic' : 'Clinic location' }}</div>
                  </div>
                </div>
              </td>
              <td>
                @if ($c->city || $c->state || $c->country)
                  <div><i class="fi fi-rr-marker text-muted me-1" aria-hidden="true"></i>{{ collect([$c->city, $c->state, $c->country])->filter()->join(', ') }}</div>
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>
                <div><i class="fi fi-rr-clock text-muted me-1" aria-hidden="true"></i>{{ $c->timezone }}</div>
                <span class="badge badge-light-secondary mt-1">{{ $clnOffsetFor($c->timezone) }}</span>
              </td>
              <td data-order="{{ $c->providers_count }}"><span class="sas-cln-count-chip text-primary"><i class="fi fi-rr-users-alt" aria-hidden="true"></i>{{ $c->providers_count }}</span></td>
              <td data-order="{{ $c->services_count }}"><span class="sas-cln-count-chip text-info"><i class="fi fi-rr-grid" aria-hidden="true"></i>{{ $c->services_count }}</span></td>
              <td data-order="{{ $c->appointments_count }}"><span class="sas-cln-count-chip text-warning"><i class="fi fi-rr-calendar" aria-hidden="true"></i>{{ $c->appointments_count }}</span></td>
              <td><x-badge-status :color="$c->is_active ? 'success' : 'secondary'" :label="$c->is_active ? 'Active' : 'Inactive'" :icon="$c->is_active ? 'fi-rr-check' : 'fi-rr-minus'" /></td>
              <td class="text-end">
                <div class="dropdown sas-dropdown-actions">
                  <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $c->name }}">
                    <i class="fi fi-rr-menu-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('clinics.edit', $c) }}"><i class="fi fi-rr-edit"></i> Edit clinic</a></li>
                  </ul>
                </div>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="8" icon="fi-rr-building" title="No Clinics Yet" description="Create your first clinic to begin managing appointments, providers and services.">
              <a href="{{ route('clinics.create') }}" class="btn btn-sm btn-primary"><i class="fi fi-rr-plus me-1"></i> Create Clinic</a>
            </x-empty-state>
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>

  @if ($clinics->isNotEmpty())
    <div class="row g-3">
      <div class="col-md-3">
        <x-card class="h-100">
          <div class="sas-cln-bottom-card">
            <span class="sas-cln-bottom-card__icon bg-warning-subtle text-warning"><i class="fi fi-rr-trophy" aria-hidden="true"></i></span>
            <div>
              <div class="sas-cln-bottom-card__label">Top Clinic by Appointments</div>
              <div class="text-truncate fw-semibold mb-1" style="max-width:150px">{{ $clnTopByAppointments->name }}</div>
              <div class="sas-cln-bottom-card__value">{{ $clnTopByAppointments->appointments_count }}</div>
              <div class="sas-cln-bottom-card__caption">Total appointments</div>
            </div>
          </div>
        </x-card>
      </div>
      <div class="col-md-3">
        <x-card class="h-100">
          <div class="sas-cln-bottom-card">
            <span class="sas-cln-bottom-card__icon bg-success-subtle text-success"><i class="fi fi-rr-calendar-check" aria-hidden="true"></i></span>
            <div>
              <div class="sas-cln-bottom-card__label">Upcoming Appointments <span class="fw-normal text-muted">(Today)</span></div>
              <div class="sas-cln-bottom-card__value">{{ $clnTodayAppointments }}</div>
              <div class="sas-cln-bottom-card__caption">Across all clinics</div>
            </div>
          </div>
        </x-card>
      </div>
      <div class="col-md-3">
        <x-card class="h-100">
          <div class="sas-cln-bottom-card">
            <span class="sas-cln-bottom-card__icon bg-primary-subtle text-primary"><i class="fi fi-rr-users-alt" aria-hidden="true"></i></span>
            <div>
              <div class="sas-cln-bottom-card__label">Most Providers</div>
              <div class="text-truncate fw-semibold mb-1" style="max-width:150px">{{ $clnMostProviders->name }}</div>
              <div class="sas-cln-bottom-card__value">{{ $clnMostProviders->providers_count }}</div>
              <div class="sas-cln-bottom-card__caption">Providers</div>
            </div>
          </div>
        </x-card>
      </div>
      <div class="col-md-3">
        <x-card class="h-100">
          <div class="sas-cln-bottom-card__label mb-2">Timezone Distribution</div>
          <div class="d-flex align-items-center gap-3">
            <div id="clnTzChart" style="width:90px;height:90px;flex-shrink:0"></div>
            <div class="flex-grow-1" style="min-width:0">
              @foreach ($clnTimezoneDist as $tz => $count)
                <div class="sas-cln-tz-legend-row">
                  <span class="sas-cln-tz-dot" style="background:{{ ['#2563EB', '#94A3B8', '#22C55E', '#F59E0B'][$loop->index % 4] }}"></span>
                  <span class="flex-grow-1 text-truncate">{{ $tz }}</span>
                  <span class="text-muted small">{{ round($count / $clnTotal * 100) }}% ({{ $count }})</span>
                </div>
              @endforeach
            </div>
          </div>
        </x-card>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
  <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
  @if ($clinics->isNotEmpty())
    <script>
      new ApexCharts(document.querySelector('#clnTzChart'), {
        chart: { type: 'donut', height: 90, sparkline: { enabled: true } },
        series: @json($clnTimezoneDist->values()),
        colors: ['#2563EB', '#94A3B8', '#22C55E', '#F59E0B'],
        stroke: { width: 0 },
        tooltip: { enabled: true },
      }).render();
    </script>
  @endif
  <script>
    (function () {
      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
      const el = document.getElementById('clinicsTable');
      if (!el || el.querySelector('tbody td[colspan]')) return;

      const table = jQuery(el).DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [],
        language: { search: '', searchPlaceholder: 'Search clinics…' },
      });

      const search = document.getElementById('clnSearch');
      const statusFilter = document.getElementById('clnStatusFilter');
      const locationFilter = document.getElementById('clnLocationFilter');
      const timezoneFilter = document.getElementById('clnTimezoneFilter');
      const clearBtn = document.getElementById('clnClearFilters');

      jQuery.fn.dataTable.ext.search.push(function (settings, data, rowIdx) {
        if (settings.nTable.id !== 'clinicsTable') return true;
        const row = table.row(rowIdx).node();
        if (!row) return true;
        const q = search.value.trim().toLowerCase();
        if (q && !(row.getAttribute('data-search') || '').includes(q)) return false;
        if (statusFilter.value && row.getAttribute('data-status') !== statusFilter.value) return false;
        if (locationFilter.value && row.getAttribute('data-city') !== locationFilter.value) return false;
        if (timezoneFilter.value && row.getAttribute('data-timezone') !== timezoneFilter.value) return false;
        return true;
      });

      [search, statusFilter, locationFilter, timezoneFilter].forEach(el => {
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', () => table.draw());
      });
      clearBtn.addEventListener('click', () => {
        search.value = '';
        statusFilter.value = '';
        locationFilter.value = '';
        timezoneFilter.value = '';
        table.draw();
      });
    })();
  </script>
@endpush
