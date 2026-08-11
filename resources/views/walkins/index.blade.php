@extends('layouts.app')

@section('title', 'Walk-in Queue')

@php
  $walkinLeftCaption = $stats['left_rate'] !== null ? $stats['left_rate']."% of today's total" : null;
@endphp

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }

    .sas-wq-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-wq-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-wq-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    #waitingTable_wrapper > .row:first-child,
    #completedTable_wrapper > .row:first-child { padding: var(--sas-space-3) var(--sas-space-5); margin: 0; align-items: center; border-bottom: 1px solid var(--sas-gray-100); }
    #waitingTable_wrapper .dataTables_length select { border-radius: var(--sas-radius-md); }
    #waitingTable_wrapper .dataTables_filter input,
    #completedTable_wrapper .dataTables_filter input { margin-left: 0 !important; min-width: 200px; }
    #waitingTable_wrapper > .row:last-child,
    #completedTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }

    .sas-wq-filter-btn {
      width: 38px; height: 38px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-500);
      display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background-color .15s var(--sas-ease), color .15s var(--sas-ease);
    }
    .sas-wq-filter-btn:hover { background: var(--sas-gray-50); color: var(--sas-gray-700); }
    .sas-wq-filter-btn.has-active { border-color: var(--sas-primary-400); color: var(--sas-primary-600); background: var(--sas-primary-50); }

    .sas-wq-avatar-initials {
      width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; background: var(--sas-primary-50); color: var(--sas-primary-600);
      display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .7rem;
    }
    #waitingTable .sas-wq-position { font-weight: 700; color: var(--sas-gray-500); }
    #waitingTable .btn-icon-square, #completedTable .btn-icon-square {
      width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600);
    }
    #waitingTable .btn-icon-square:hover { background: var(--sas-gray-50); }

    .sas-wq-form__icon {
      width: 44px; height: 44px; border-radius: var(--sas-radius-md); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.1rem;
    }
    .sas-wq-form label:not(.sas-outline-field__label) { font-size: var(--sas-fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--sas-gray-500); margin-bottom: .4rem; display: block; }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-wq-header__icon"><i class="fi fi-rr-users" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-wq-header__title mb-1">Walk-in Queue</h1>
        <p class="sas-wq-header__subtitle mb-0">Manage patients who walk in without an appointment.</p>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span id="walkinLiveDot" class="d-none align-items-center gap-1 text-success small fw-semibold me-1" style="display:inline-flex">
        <i class="fi fi-rr-circle" style="font-size:.5rem" aria-hidden="true"></i> Live
      </span>
      <button type="button" class="btn btn-light btn-lg" id="walkinRefreshBtn"><i class="fi fi-rr-refresh me-1" aria-hidden="true"></i> Refresh</button>
      <button type="button" class="btn btn-primary btn-lg" id="walkinScrollToForm"><i class="fi fi-rr-plus me-1" aria-hidden="true"></i> Add walk-in</button>
    </div>
  </div>

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-6 col-xl-3">
      <x-stat-widget id="statWaiting" label="Waiting now" :value="$stats['waiting']" icon="fi-rr-hourglass-end" bg="bg-primary-subtle" fg="text-primary" style="color: var(--sas-primary-600) !important;" caption="{{ $stats['waiting'] }} waiting" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget id="statServing" label="Being served" :value="$stats['serving']" icon="fi-rr-user-check" bg="bg-warning-subtle" fg="text-warning" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget id="statDoneToday" label="Done today" :value="$stats['done_today']" icon="fi-rr-check-circle" bg="bg-success-subtle" fg="text-success" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget id="statLeftToday" label="Left / no-show today" :value="$stats['left_today']" icon="fi-rr-time-past" bg="bg-danger-subtle" fg="text-danger"
        :caption="$walkinLeftCaption" />
    </div>
  </div>

  <div class="row g-3 mb-4 sas-stagger">
    <div class="col-md-4">
      {{-- Hand-rolled (not <x-stat-widget>): the value here is "12 min" or
           "—", not a clean number, so it can't run through the shared
           animated-counter (which only knows how to count up to a float
           and append a fixed suffix — it would silently drop " min"/"—"). --}}
      <div class="card sas-card sas-card-hover h-100">
        <div class="card-body d-flex align-items-start gap-3">
          <div class="sas-stat__icon bg-info-subtle text-info"><i class="fi fi-rr-clock" aria-hidden="true"></i></div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="text-muted small">Avg. wait today</div>
            <span id="statAvgWait" class="h4 mb-0 fw-bold d-block">{{ $stats['avg_wait_minutes'] !== null ? $stats['avg_wait_minutes'].' min' : '—' }}</span>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <x-stat-widget id="statDoneWeek" label="Done this week" :value="$stats['done_this_week']" icon="fi-rr-arrow-trend-up" bg="bg-success-subtle" fg="text-success" />
    </div>
    <div class="col-md-4">
      <x-stat-widget id="statDoneMonth" label="Done last 30 days" :value="$stats['done_this_month']" icon="fi-rr-calendar" bg="bg-primary-subtle" fg="text-primary" />
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-9">
      <x-card bodyClass="p-0" class="mb-3">
        <x-slot:title>Waiting</x-slot:title>
        <x-slot:toolbar>
          <span id="waitingCountBadge" class="badge bg-primary-subtle text-primary">{{ $stats['waiting'] }} waiting</span>
        </x-slot:toolbar>
        <div class="table-responsive">
          <table id="waitingTable" class="table align-middle mb-0 datatable">
            <thead class="table-light"><tr><th>#</th><th>Name</th><th>Provider pref.</th><th>Service</th><th>Waiting since</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              @include('walkins.partials.waiting-rows', ['entries' => $entries])
            </tbody>
          </table>
        </div>
      </x-card>

      <div id="walkinModalsContainer">
        @include('walkins.partials.patient-modals', ['entries' => $entries])
      </div>

      <x-card bodyClass="p-0">
        <x-slot:title>Completed today</x-slot:title>
        <x-slot:toolbar>
          <span id="completedCountBadge" class="badge bg-secondary-subtle text-secondary">{{ $completedToday->count() }}</span>
        </x-slot:toolbar>
        <div class="table-responsive">
          <table id="completedTable" class="table align-middle mb-0 datatable">
            <thead class="table-light"><tr><th>Name</th><th>Provider</th><th>Service</th><th>Joined</th><th>Wait time</th><th>Completed</th><th>Status</th></tr></thead>
            <tbody>
              @include('walkins.partials.completed-rows', ['entries' => $completedToday])
            </tbody>
          </table>
        </div>
      </x-card>
    </div>

    <div class="col-xl-3">
      <x-card class="sas-wq-form" id="walkinAddForm">
        <div class="d-flex align-items-center gap-3 mb-4">
          <span class="sas-wq-form__icon"><i class="fi fi-rr-user-add" aria-hidden="true"></i></span>
          <h2 class="mb-0" style="font-size:var(--sas-fs-lg);font-weight:700">Add a walk-in</h2>
        </div>
        <form method="POST" action="{{ route('walkins.store') }}">
          @csrf
          <div class="mb-3">
            <x-form-field name="name" label="Name" :value="old('name')" :required="true" placeholder="Enter patient name" />
          </div>
          <div class="mb-3">
            <x-form-field name="phone" label="Phone" :value="old('phone')" placeholder="Enter phone number" />
          </div>
          <div class="mb-3">
            <label for="wiProvider">Preferred provider</label>
            <select name="provider_id" id="wiProvider" class="form-select">
              <option value="">Any</option>
              @foreach ($providers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
            </select>
          </div>
          <div class="mb-4">
            <label for="wiService">Service</label>
            <select name="service_id" id="wiService" class="form-select">
              <option value="">Any</option>
              @foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
          </div>
          <button class="btn btn-primary btn-lg w-100"><i class="fi fi-rr-user-add me-1" aria-hidden="true"></i> Add to queue</button>
        </form>
      </x-card>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      document.getElementById('walkinScrollToForm').addEventListener('click', function () {
        const form = document.getElementById('walkinAddForm');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        const nameInput = document.getElementById('name');
        if (nameInput) setTimeout(() => nameInput.focus(), 400);
      });

      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
      const waitForTable = setInterval(function () {
        if (!jQuery.fn.DataTable.isDataTable('#waitingTable')) return;
        clearInterval(waitForTable);

        const table = jQuery('#waitingTable').DataTable();
        let statusSet = new Set();

        jQuery.fn.dataTable.ext.search.push(function (settings, data, rowIdx) {
          if (settings.nTable.id !== 'waitingTable') return true;
          if (!statusSet.size) return true;
          const row = table.row(rowIdx).node();
          return row ? statusSet.has(row.getAttribute('data-status')) : true;
        });

        const filterWrap = document.querySelector('#waitingTable_wrapper .dataTables_filter');
        if (!filterWrap) return;

        const wrap = document.createElement('div');
        wrap.className = 'dropdown';
        wrap.innerHTML =
          '<button type="button" class="sas-wq-filter-btn" id="walkinStatusFilterBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Filter by status">' +
            '<i class="fi fi-rr-filter" aria-hidden="true"></i>' +
          '</button>' +
          '<ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:160px">' +
            ['waiting', 'serving'].map(s =>
              '<li><label class="dropdown-item d-flex align-items-center gap-2 mb-0" style="cursor:pointer"><input type="checkbox" class="form-check-input mt-0 sas-wq-status-check" value="' + s + '"> ' + s.charAt(0).toUpperCase() + s.slice(1) + '</label></li>'
            ).join('') +
          '</ul>';
        filterWrap.appendChild(wrap);

        const filterBtnEl = document.getElementById('walkinStatusFilterBtn');
        const checks = wrap.querySelectorAll('.sas-wq-status-check');
        checks.forEach(c => c.addEventListener('change', function () {
          statusSet = new Set(Array.from(checks).filter(x => x.checked).map(x => x.value));
          filterBtnEl.classList.toggle('has-active', statusSet.size > 0);
          table.draw();
        }));
      }, 50);
    })();
  </script>

  {{-- Live updates: a Pusher/Echo ping (no patient data in the payload —
       see WalkInQueueUpdated) tells us something changed, then we pull the
       fresh state from our own authenticated endpoint and splice it in
       without a page reload. --}}
  <script>
    (function () {
      const partialUrl = @json(route('walkins.partial'));
      const $ = window.jQuery;

      function setStat(id, value) {
        const el = document.querySelector('#' + id + ' .sas-count');
        if (!el) return;
        el.setAttribute('data-count-to', value);
        el.textContent = value;
      }

      function applyStats(stats) {
        setStat('statWaiting', stats.waiting);
        setStat('statServing', stats.serving);
        setStat('statDoneToday', stats.done_today);
        setStat('statLeftToday', stats.left_today);
        setStat('statDoneWeek', stats.done_this_week);
        setStat('statDoneMonth', stats.done_this_month);

        const avgWaitEl = document.getElementById('statAvgWait');
        if (avgWaitEl) avgWaitEl.textContent = stats.avg_wait_minutes !== null ? stats.avg_wait_minutes + ' min' : '—';

        const leftCaptionEl = document.querySelector('#statLeftToday .sas-stat__caption');
        if (leftCaptionEl) leftCaptionEl.textContent = stats.left_rate !== null ? stats.left_rate + "% of today's total" : '';
      }

      function isEmptyRowsHtml(html) {
        return /<td[^>]*colspan=/.test(html);
      }

      // Swap a DataTable's rows through its own API (clear + rows.add + draw)
      // so pagination/search/the custom status-filter dropdown on #waitingTable
      // keep working, instead of overwriting the <tbody> directly and leaving
      // DataTables' internal row cache out of sync with the DOM.
      function updateTable(tableId, html) {
        if (!$ || !$.fn || !$.fn.DataTable) return;
        const selector = '#' + tableId;
        const wasDataTable = $.fn.DataTable.isDataTable(selector);
        const empty = isEmptyRowsHtml(html);

        if (wasDataTable && !empty) {
          const dt = $(selector).DataTable();
          dt.clear();
          dt.rows.add($(html).filter('tr'));
          dt.draw(false);
          return;
        }

        // Table is transitioning to/from the empty state, or was never
        // initialised (tables with zero rows on page load are skipped by
        // the global DataTables initialiser) — safe to fully rebuild.
        if (wasDataTable) $(selector).DataTable().destroy();
        $(selector).find('tbody').html(html);
        if (!empty) {
          $(selector).DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [],
            language: { search: '', searchPlaceholder: 'Search…' },
          });
        }
      }

      function applySnapshot(data) {
        applyStats(data.stats);

        const waitingBadge = document.getElementById('waitingCountBadge');
        if (waitingBadge) waitingBadge.textContent = data.waitingCount + ' waiting';

        const completedBadge = document.getElementById('completedCountBadge');
        if (completedBadge) completedBadge.textContent = data.completedCount;

        updateTable('waitingTable', data.waitingHtml);
        updateTable('completedTable', data.completedHtml);

        // Don't yank the modal out from under someone who has it open —
        // catch up on the next update instead.
        const modalsContainer = document.getElementById('walkinModalsContainer');
        if (modalsContainer && !modalsContainer.querySelector('.modal.show')) {
          modalsContainer.innerHTML = data.modalsHtml;
        }
      }

      let refreshing = false;
      function refresh() {
        if (refreshing) return;
        refreshing = true;
        fetch(partialUrl, { headers: { Accept: 'application/json' } })
          .then(r => r.ok ? r.json() : Promise.reject())
          .then(applySnapshot)
          .catch(() => {})
          .finally(() => { refreshing = false; });
      }

      const refreshBtn = document.getElementById('walkinRefreshBtn');
      if (refreshBtn) refreshBtn.addEventListener('click', refresh);

      const liveDot = document.getElementById('walkinLiveDot');
      if (window.Echo && window.SAS_CLINIC_ID) {
        window.Echo.private('clinic.' + window.SAS_CLINIC_ID + '.walkins')
          .listen('.walkin.updated', refresh);

        if (window.Echo.connector && window.Echo.connector.pusher && liveDot) {
          const conn = window.Echo.connector.pusher.connection;
          const sync = () => liveDot.classList.toggle('d-none', conn.state !== 'connected');
          conn.bind('state_change', sync);
          sync();
        }
      }
    })();
  </script>
@endpush
