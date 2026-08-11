@extends('layouts.app')

@section('title', 'Services')

@php
  $totalServices = $services->count();
  $activeServices = $services->where('is_active', true)->count();
  $telehealthServices = $services->where('telehealth', true)->count();
  $avgDuration = $totalServices > 0 ? (int) round($services->avg('duration')) : 0;
  $avgPrice = $totalServices > 0 ? $services->avg('price') : 0;

  // Deterministic per-specialty color, cycled from a small curated palette —
  // purely presentational grouping, Service has no dedicated specialty-color field.
  $specialtyPalette = ['primary', 'success', 'warning', 'info', 'danger'];
  $specialtyColors = $services->pluck('specialty')->filter()->unique()->values()
    ->mapWithKeys(fn ($spec, $i) => [$spec => $specialtyPalette[$i % count($specialtyPalette)]]);

  $serviceIcon = function (?string $name, ?string $specialty) {
    $n = strtolower(($name ?? '').' '.($specialty ?? ''));
    return match (true) {
      str_contains($n, 'dental') || str_contains($n, 'tooth') => 'fi-rr-tooth',
      str_contains($n, 'therapy') || str_contains($n, 'counsel') || str_contains($n, 'mental') => 'fi-rr-brain',
      str_contains($n, 'pediatric') || str_contains($n, 'child') => 'fi-rr-baby',
      str_contains($n, 'derma') || str_contains($n, 'skin') => 'fi-rr-hand-holding-medical',
      str_contains($n, 'eye') || str_contains($n, 'optic') => 'fi-rr-eye',
      str_contains($n, 'cardio') || str_contains($n, 'heart') => 'fi-rr-heart',
      str_contains($n, 'follow') => 'fi-rr-stethoscope',
      str_contains($n, 'general') || str_contains($n, 'consult') => 'fi-rr-briefcase',
      default => 'fi-rr-symbol',
    };
  };
@endphp

@push('styles')
  <style>
    .sas-svc-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-svc-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-svc-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-svc-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .85rem; padding: var(--sas-space-4) var(--sas-space-5); border-bottom: 1px solid var(--sas-gray-100); }
    .sas-svc-toolbar__length select { border-radius: var(--sas-radius-md); }
    .sas-svc-toolbar__search { flex: 1 1 240px; min-width: 200px; }
    .sas-svc-toolbar__search input { width: 100%; border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); padding: .55rem .9rem; font-size: var(--sas-fs-sm); }
    .sas-svc-toolbar__search input:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }

    .sas-svc-filter-btn {
      border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); font-weight: 600; font-size: var(--sas-fs-sm);
      border-radius: var(--sas-radius-md); padding: .55rem 1rem; display: inline-flex; align-items: center; gap: .4rem; flex-shrink: 0;
    }
    .sas-svc-filter-btn:hover { background: var(--sas-gray-50); }
    .sas-svc-filter-btn.has-active { border-color: var(--sas-primary-400); color: var(--sas-primary-600); background: var(--sas-primary-50); }

    #servicesTable_wrapper > .row:first-child { display: none; }
    #servicesTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }

    #servicesTable .sas-svc-name { display: flex; align-items: center; gap: .65rem; font-weight: 700; color: var(--sas-gray-900); }
    #servicesTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    #servicesTable .btn-icon-square:hover { background: var(--sas-gray-50); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-svc-header__icon"><i class="fi fi-rr-briefcase" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-svc-header__title mb-1">Services</h1>
        <p class="sas-svc-header__subtitle mb-0">Manage all services offered at your clinics.</p>
      </div>
    </div>
    <a href="{{ route('services.create') }}" class="btn btn-primary btn-lg"><i class="fi fi-rr-plus me-1"></i> Add Service</a>
  </div>

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-6 col-xl">
      <x-stat-widget label="Total Services" :value="$totalServices" icon="fi-rr-briefcase" bg="bg-primary-subtle" fg="text-primary" caption="All services" />
    </div>
    <div class="col-6 col-xl">
      <x-stat-widget label="Active Services" :value="$activeServices" icon="fi-rr-check-circle" bg="bg-success-subtle" fg="text-success"
        :caption="$totalServices > 0 ? round($activeServices / $totalServices * 100).'% of total' : '—'" />
    </div>
    <div class="col-6 col-xl">
      <x-stat-widget label="Telehealth Enabled" :value="$telehealthServices" icon="fi-rr-video-camera-alt" bg="bg-warning-subtle" fg="text-warning"
        :caption="$totalServices > 0 ? round($telehealthServices / $totalServices * 100).'% of total' : '—'" />
    </div>
    <div class="col-6 col-xl">
      {{-- Hand-rolled, not <x-stat-widget>: "43 min" would animate-count to
           "43" and silently drop the unit (the shared counter's suffix slot
           only ever adds "%") — same class of bug already caught on the
           Walk-in Queue page's "Avg wait" card. --}}
      <div class="card sas-card sas-card-hover h-100">
        <div class="card-body d-flex align-items-start gap-3">
          <div class="sas-stat__icon bg-info-subtle text-info"><i class="fi fi-rr-time-quarter-past" aria-hidden="true"></i></div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="text-muted small">Avg Duration</div>
            <span class="h4 mb-0 fw-bold d-block">{{ $avgDuration }} min</span>
            <div class="sas-stat__caption">Across all services</div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl">
      {{-- Hand-rolled for the same reason: (float)"₹98.00" casts to 0 in PHP
           since the string starts with a non-numeric currency symbol. --}}
      <div class="card sas-card sas-card-hover h-100">
        <div class="card-body d-flex align-items-start gap-3">
          <div class="sas-stat__icon bg-warning-subtle text-warning"><i class="fi fi-rr-sack-dollar" aria-hidden="true"></i></div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="text-muted small">Avg Price</div>
            <span class="h4 mb-0 fw-bold d-block">₹{{ number_format($avgPrice, 2) }}</span>
            <div class="sas-stat__caption">Across all services</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <x-card bodyClass="p-0">
    <div class="sas-svc-toolbar">
      <span class="sas-svc-toolbar__length" id="servicesLengthSlot"></span>
      <span class="sas-svc-toolbar__search" id="servicesSearchSlot"></span>

      <div class="dropdown ms-auto">
        <button type="button" class="sas-svc-filter-btn" id="serviceFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fi fi-rr-filter" aria-hidden="true"></i> Filters
        </button>
        <ul class="dropdown-menu dropdown-menu-end p-3" style="min-width:220px">
          <div class="sas-bc-label mb-2" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--sas-gray-500)">Status</div>
          <div class="form-check"><input class="form-check-input sas-svc-status-check" type="checkbox" value="active" id="svcFilterActive"><label class="form-check-label small" for="svcFilterActive">Active</label></div>
          <div class="form-check mb-3"><input class="form-check-input sas-svc-status-check" type="checkbox" value="inactive" id="svcFilterInactive"><label class="form-check-label small" for="svcFilterInactive">Inactive</label></div>

          <div class="sas-bc-label mb-2" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--sas-gray-500)">Telehealth</div>
          <div class="form-check"><input class="form-check-input sas-svc-tele-check" type="checkbox" value="1" id="svcFilterTeleYes"><label class="form-check-label small" for="svcFilterTeleYes">Enabled</label></div>
          <div class="form-check mb-3"><input class="form-check-input sas-svc-tele-check" type="checkbox" value="0" id="svcFilterTeleNo"><label class="form-check-label small" for="svcFilterTeleNo">Not enabled</label></div>

          @if ($specialtyColors->isNotEmpty())
            <div class="sas-bc-label mb-2" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--sas-gray-500)">Specialty</div>
            @foreach ($specialtyColors->keys() as $spec)
              <div class="form-check"><input class="form-check-input sas-svc-spec-check" type="checkbox" value="{{ $spec }}" id="svcFilterSpec{{ $loop->index }}"><label class="form-check-label small" for="svcFilterSpec{{ $loop->index }}">{{ $spec }}</label></div>
            @endforeach
          @endif
        </ul>
      </div>
    </div>

    <div class="table-responsive">
      <table id="servicesTable" class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Service</th><th>Specialty</th><th>Duration</th><th>Buffer</th><th>Price</th><th>Telehealth</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          @forelse ($services as $s)
            <tr data-status="{{ $s->is_active ? 'active' : 'inactive' }}" data-telehealth="{{ $s->telehealth ? '1' : '0' }}" data-specialty="{{ $s->specialty }}">
              <td>
                <span class="sas-svc-name">
                  <span class="sas-icon-tile" style="width:32px;height:32px;font-size:.9rem;background:{{ $s->color }}22;color:{{ $s->color }}">
                    <i class="fi {{ $serviceIcon($s->name, $s->specialty) }}" aria-hidden="true"></i>
                  </span>
                  {{ $s->name }}
                </span>
              </td>
              <td>
                @if ($s->specialty)
                  <x-badge-status :color="$specialtyColors[$s->specialty] ?? 'secondary'" :label="$s->specialty" />
                @else
                  <span class="text-muted small">—</span>
                @endif
              </td>
              <td data-order="{{ $s->duration }}">{{ $s->duration }} min</td>
              <td data-order="{{ $s->buffer }}">{{ $s->buffer }} min</td>
              <td data-order="{{ $s->price }}">₹{{ number_format($s->price, 2) }}</td>
              <td>
                <x-badge-status :color="$s->telehealth ? 'success' : 'danger'" :label="$s->telehealth ? 'Yes' : 'No'" :icon="$s->telehealth ? 'fi-rr-video-camera-alt' : 'fi-rr-cross-circle'" />
              </td>
              <td><x-badge-status :color="$s->is_active ? 'success' : 'secondary'" :label="$s->is_active ? 'Active' : 'Inactive'" :icon="$s->is_active ? 'fi-rr-check-circle' : 'fi-rr-minus-circle'" /></td>
              <td class="text-end">
                <div class="dropdown sas-dropdown-actions">
                  <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $s->name }}">
                    <i class="fi fi-rr-menu-dots-vertical"></i>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('services.edit', $s) }}"><i class="fi fi-rr-edit"></i> Edit service</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <form method="POST" action="{{ route('services.destroy', $s) }}" data-sas-confirm="Delete this service?" data-sas-confirm-label="Delete">
                        @csrf @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Delete</button>
                      </form>
                    </li>
                  </ul>
                </div>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="8" icon="fi-rr-briefcase" title="No services available yet." description="Add your first service to start taking bookings.">
              <a href="{{ route('services.create') }}" class="btn btn-sm btn-primary"><i class="fi fi-rr-plus me-1"></i> Add Service</a>
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
        if (!jQuery.fn.DataTable.isDataTable('#servicesTable')) return;
        clearInterval(waitForTable);

        const table = jQuery('#servicesTable').DataTable();

        // Move DataTables' own real length + search controls into the
        // unified toolbar row instead of rebuilding fakes.
        const lengthWrap = document.querySelector('#servicesTable_wrapper .dataTables_length');
        const lengthSlot = document.getElementById('servicesLengthSlot');
        if (lengthWrap && lengthSlot) lengthSlot.appendChild(lengthWrap);
        const filterWrap = document.querySelector('#servicesTable_wrapper .dataTables_filter');
        const searchSlot = document.getElementById('servicesSearchSlot');
        if (filterWrap && searchSlot) {
          const input = filterWrap.querySelector('input');
          if (input) input.placeholder = 'Search services…';
          searchSlot.appendChild(filterWrap);
        }

        let statusSet = new Set();
        let teleSet = new Set();
        let specSet = new Set();

        jQuery.fn.dataTable.ext.search.push(function (settings, data, rowIdx) {
          if (settings.nTable.id !== 'servicesTable') return true;
          const row = table.row(rowIdx).node();
          if (!row) return true;
          if (statusSet.size && !statusSet.has(row.getAttribute('data-status'))) return false;
          if (teleSet.size && !teleSet.has(row.getAttribute('data-telehealth'))) return false;
          if (specSet.size && !specSet.has(row.getAttribute('data-specialty'))) return false;
          return true;
        });

        const filterBtnEl = document.getElementById('serviceFilterBtn');
        const statusChecks = document.querySelectorAll('.sas-svc-status-check');
        const teleChecks = document.querySelectorAll('.sas-svc-tele-check');
        const specChecks = document.querySelectorAll('.sas-svc-spec-check');
        function sync() {
          statusSet = new Set(Array.from(statusChecks).filter(c => c.checked).map(c => c.value));
          teleSet = new Set(Array.from(teleChecks).filter(c => c.checked).map(c => c.value));
          specSet = new Set(Array.from(specChecks).filter(c => c.checked).map(c => c.value));
          filterBtnEl.classList.toggle('has-active', statusSet.size + teleSet.size + specSet.size > 0);
          table.draw();
        }
        [...statusChecks, ...teleChecks, ...specChecks].forEach(c => c.addEventListener('change', sync));
      }, 50);
    })();
  </script>
@endpush
