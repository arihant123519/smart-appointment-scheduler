@extends('layouts.app')

@section('title', 'Calendar')

@php
  // Read-only, presentational only (mirrors the toast-payload query pattern
  // already used in layouts/app.blade.php) — feeds the KPI sparklines with a
  // real last-7-days trend instead of a fabricated one. Same clinic/provider
  // scoping CalendarController::events() already applies.
  $calSparkStart = now()->subDays(6)->startOfDay();
  $calSparkQuery = \App\Models\Appointment::forCurrentClinic()->active()
    ->where('start_at', '>=', $calSparkStart);
  if (auth()->user()->hasRole('provider') && auth()->user()->provider) {
    $calSparkQuery->where('provider_id', auth()->user()->provider->id);
  }
  $calSparkRows = $calSparkQuery->selectRaw(
    "DATE(start_at) as d, COUNT(*) as total,
     SUM(CASE WHEN status IN ('checked_in','completed') THEN 1 ELSE 0 END) as done,
     SUM(CASE WHEN status IN ('booked','confirmed') THEN 1 ELSE 0 END) as upcoming,
     SUM(CASE WHEN no_show_score >= 70 THEN 1 ELSE 0 END) as risk"
  )->groupBy('d')->orderBy('d')->get()->keyBy('d');

  $calSpark = ['total' => [], 'done' => [], 'upcoming' => [], 'risk' => []];
  foreach (range(0, 6) as $i) {
    $row = $calSparkRows->get(now()->subDays(6 - $i)->toDateString());
    $calSpark['total'][] = (int) ($row->total ?? 0);
    $calSpark['done'][] = (int) ($row->done ?? 0);
    $calSpark['upcoming'][] = (int) ($row->upcoming ?? 0);
    $calSpark['risk'][] = (int) ($row->risk ?? 0);
  }
@endphp

@push('styles')
  <style>
    /* This page builds its own hero header (title + subtitle + New
       Appointment) below, so the generic layout toolbar is redundant here.
       Scoped to this page only — see the AI Assistant page for the same pattern. */
    .sas-page-toolbar { display: none; }

    .sas-cal-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-cal-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    /* ---------------------------------------------------------------------
       Custom toolbar (replaces FullCalendar's default headerToolbar chrome)
       ------------------------------------------------------------------ */
    .sas-cal-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; padding: var(--sas-space-4) var(--sas-space-5); }
    #providerFilter {
      appearance: none; -webkit-appearance: none; border: 1px solid var(--sas-gray-200); border-radius: 999px;
      background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%2394A3B8' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") no-repeat right .9rem center;
      font-size: var(--sas-fs-sm); font-weight: 600; color: var(--sas-gray-700); padding: .5rem 2.1rem .5rem 2.4rem;
      min-width: 190px; position: relative;
    }
    .sas-cal-filter { position: relative; display: inline-flex; align-items: center; }
    .sas-cal-filter i { position: absolute; left: .85rem; color: var(--sas-gray-400); font-size: .9rem; pointer-events: none; }
    .sas-cal-nav { display: inline-flex; align-items: center; gap: .35rem; }
    .sas-cal-nav__arrow {
      width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600);
      display: inline-flex; align-items: center; justify-content: center; transition: background-color .15s var(--sas-ease), border-color .15s var(--sas-ease);
    }
    .sas-cal-nav__arrow:hover { background: var(--sas-gray-50); border-color: var(--sas-gray-300); }
    .sas-cal-nav__today {
      border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-700); font-weight: 600; font-size: var(--sas-fs-sm);
      border-radius: var(--sas-radius-md); padding: .45rem .9rem;
    }
    .sas-cal-nav__today:hover { background: var(--sas-gray-50); }
    .sas-cal-range {
      border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-900); font-weight: 700; font-size: var(--sas-fs-sm);
      border-radius: var(--sas-radius-md); padding: .45rem .9rem; display: inline-flex; align-items: center; gap: .4rem;
    }
    .sas-cal-range:hover { background: var(--sas-gray-50); }
    .sas-cal-views { display: inline-flex; align-items: center; gap: .15rem; background: var(--sas-gray-50); border: 1px solid var(--sas-gray-100); border-radius: var(--sas-radius-md); padding: .2rem; }
    .sas-cal-views button {
      border: 0; background: transparent; color: var(--sas-gray-600); font-weight: 600; font-size: var(--sas-fs-sm);
      padding: .4rem .8rem; border-radius: var(--sas-radius-sm); transition: background-color .15s var(--sas-ease), color .15s var(--sas-ease);
    }
    .sas-cal-views button:hover { color: var(--sas-gray-900); }
    .sas-cal-views button.active { background: #fff; color: var(--sas-primary-700); box-shadow: var(--sas-shadow-xs); }
    .sas-cal-gear {
      width: 34px; height: 34px; border-radius: 50%; border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600);
      display: inline-flex; align-items: center; justify-content: center; margin-left: auto;
    }
    .sas-cal-gear:hover { background: var(--sas-gray-50); }

    .sas-cal-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; padding: 0 var(--sas-space-5) var(--sas-space-4); }
    .sas-cal-legend span { display: inline-flex; align-items: center; gap: .4rem; font-size: var(--sas-fs-xs); font-weight: 600; color: var(--sas-gray-500); }
    .sas-cal-legend i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

    /* FullCalendar theming — colors/geometry only; structural/drag classes untouched. */
    #calendar { --fc-border-color: var(--sas-gray-100); --fc-today-bg-color: var(--sas-primary-25, #f5f9ff); --fc-now-indicator-color: var(--sas-danger); padding: 0 var(--sas-space-3) var(--sas-space-3); }
    #calendar .fc-col-header-cell-cushion { color: var(--sas-gray-500); font-weight: 700; text-decoration: none; padding: .6rem; font-size: var(--sas-fs-xs); text-transform: uppercase; letter-spacing: .04em; }
    #calendar .fc-timegrid-slot-label-cushion { color: var(--sas-gray-400); font-size: var(--sas-fs-xs); }
    #calendar .fc-event { border: 0; border-radius: var(--sas-radius-sm); padding: 1px 5px; font-weight: 600; font-size: .76rem;
      box-shadow: 0 1px 3px rgba(15,23,42,.15); cursor: pointer; transition: transform .1s var(--sas-ease); }
    #calendar .fc-event:hover { transform: translateY(-1px); filter: brightness(1.04); }
    #calendar .fc-timegrid-now-indicator-line { border-width: 2px; }
    #calendar .fc-day-today { background: var(--fc-today-bg-color) !important; }

    /* Right rail */
    .sas-cal-agenda-row { display: flex; align-items: center; gap: .65rem; padding: .55rem 0; border-bottom: 1px solid var(--sas-gray-100); text-decoration: none; color: inherit; }
    .sas-cal-agenda-row:last-child { border-bottom: 0; }
    .sas-cal-agenda-row:hover .sas-cal-agenda-row__title { color: var(--sas-primary-700); }
    .sas-cal-agenda-row__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .sas-cal-agenda-row__title { font-weight: 600; font-size: var(--sas-fs-sm); color: var(--sas-gray-900); transition: color .15s var(--sas-ease); }
    .sas-cal-agenda-row__meta { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }
    .sas-cal-help-row { display: flex; align-items: flex-start; gap: .65rem; }
    .sas-cal-help-row + .sas-cal-help-row { margin-top: 1rem; }
    .sas-cal-help-row__title { font-weight: 700; font-size: var(--sas-fs-sm); color: var(--sas-gray-900); }
    .sas-cal-help-row__text { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h1 class="sas-cal-header__title mb-1">Calendar</h1>
      <p class="sas-cal-header__subtitle mb-0">View and manage all appointments</p>
    </div>
    @can('manage appointments')
      <a href="{{ route('appointments.create') }}" class="btn btn-primary btn-lg"><i class="fi fi-rr-plus me-1"></i> New Appointment</a>
    @endcan
  </div>

  <div class="row g-3 mb-3" id="calStats">
    <div class="col-6 col-xl-3">
      <x-stat-widget label="In view" value="0" icon="fi-rr-eye" bg="bg-primary-subtle" fg="text-primary" class="cal-stat-total"
        caption="Today" sparkId="calSparkTotal" sparkColor="#2563EB" :sparkSeries="$calSpark['total']" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget label="Checked in / completed" value="0" icon="fi-rr-check-circle" bg="bg-success-subtle" fg="text-success" class="cal-stat-done"
        caption="Today" sparkId="calSparkDone" sparkColor="#22C55E" :sparkSeries="$calSpark['done']" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget label="Upcoming" value="0" icon="fi-rr-calendar-clock" bg="bg-info-subtle" fg="text-info" class="cal-stat-upcoming"
        caption="Next 7 days" sparkId="calSparkUpcoming" sparkColor="#7C3AED" :sparkSeries="$calSpark['upcoming']" />
    </div>
    <div class="col-6 col-xl-3">
      <x-stat-widget label="High no-show risk" value="0" icon="fi-rr-triangle-warning" bg="bg-danger-subtle" fg="text-danger" class="cal-stat-risk"
        caption="Next 7 days" sparkId="calSparkRisk" sparkColor="#EF4444" :sparkSeries="$calSpark['risk']" />
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-9">
      <div class="card">
        <div class="sas-cal-toolbar">
          @if ($providers->count() > 1 && auth()->user()->hasAnyRole(['system_admin', 'front_desk', 'clinic_admin']))
            <div class="sas-cal-filter">
              <i class="fi fi-rr-user" aria-hidden="true"></i>
              <select id="providerFilter" aria-label="Filter by provider">
                <option value="">All providers</option>
                @foreach ($providers as $p)
                  <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->specialty }})</option>
                @endforeach
              </select>
            </div>
          @endif

          <div class="sas-cal-nav">
            <button type="button" class="sas-cal-nav__arrow" id="calPrev" aria-label="Previous period"><i class="fi fi-rr-angle-small-left" aria-hidden="true"></i></button>
            <button type="button" class="sas-cal-nav__today" id="calToday">Today</button>
            <button type="button" class="sas-cal-nav__arrow" id="calNext" aria-label="Next period"><i class="fi fi-rr-angle-small-right" aria-hidden="true"></i></button>
          </div>

          <div class="position-relative d-inline-block">
            <button type="button" class="sas-cal-range" id="calRangeLabel" aria-label="Jump to date">
              <span id="calRangeText">—</span>
              <i class="fi fi-rr-angle-small-down" aria-hidden="true"></i>
            </button>
            <input type="text" id="calDatePicker" class="visually-hidden" aria-hidden="true" tabindex="-1">
          </div>

          <div class="sas-cal-views" role="group" aria-label="Calendar view">
            <button type="button" data-view="timeGridDay">Day</button>
            <button type="button" data-view="timeGridWeek" class="active">Week</button>
            <button type="button" data-view="dayGridMonth">Month</button>
            <div class="dropdown d-inline-block">
              <button type="button" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">More</button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><button type="button" class="dropdown-item" data-view="listWeek"><i class="fi fi-rr-list me-2" aria-hidden="true"></i>Agenda list</button></li>
              </ul>
            </div>
          </div>

          <div class="dropdown">
            <button type="button" class="sas-cal-gear" id="calSettingsBtn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Calendar settings" title="Calendar settings">
              <i class="fi fi-rr-settings" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width:220px">
              <li>
                <label class="dropdown-item d-flex align-items-center gap-2 mb-0" style="cursor:pointer">
                  <input type="checkbox" class="form-check-input mt-0" id="calToggleWeekends" checked>
                  Show weekends
                </label>
              </li>
            </ul>
          </div>
        </div>

        <div class="sas-cal-legend">
          <span><i style="background:#f6b100"></i> Booked</span>
          <span><i style="background:#7239ea"></i> Confirmed</span>
          <span><i style="background:#2563EB"></i> Checked in</span>
          <span><i style="background:#17c653"></i> Completed</span>
          <span><i style="background:#f1416c"></i> No-show</span>
          <span><i style="background:#adb5bd"></i> Cancelled</span>
        </div>

        <div id="calendar"></div>
      </div>
    </div>

    <div class="col-lg-3">
      <div class="card mb-3">
        <div class="card-body">
          <div id="calAgendaEmpty">
            <x-empty-state icon="fi-rr-calendar" title="No appointments" description="There are no appointments in this view.">
              @can('manage appointments')
                <a href="{{ route('appointments.create') }}" class="btn btn-primary btn-sm"><i class="fi fi-rr-plus me-1"></i> Book an appointment</a>
              @endcan
            </x-empty-state>
          </div>
          <div id="calAgendaList" class="d-none" aria-live="polite"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <div class="sas-cal-help-row">
            <span class="sas-icon-tile bg-primary-subtle text-primary"><i class="fi fi-rr-user" aria-hidden="true"></i></span>
            <div>
              <div class="sas-cal-help-row__title">Provider</div>
              <div class="sas-cal-help-row__text">The assigned provider</div>
            </div>
          </div>
          <div class="sas-cal-help-row">
            <span class="sas-icon-tile bg-success-subtle text-success"><i class="fi fi-rr-checkbox" aria-hidden="true"></i></span>
            <div>
              <div class="sas-cal-help-row__title">Status</div>
              <div class="sas-cal-help-row__text">Current appointment status</div>
            </div>
          </div>
          <div class="sas-cal-help-row">
            <span class="sas-icon-tile bg-danger-subtle text-danger"><i class="fi fi-rr-triangle-warning" aria-hidden="true"></i></span>
            <div>
              <div class="sas-cal-help-row__title">No-show risk</div>
              <div class="sas-cal-help-row__text">AI predicted risk level</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Hover quick-preview — populated from the already-fetched event's extendedProps, no extra request --}}
  <div class="sas-event-popover" id="calEventPopover">
    <div class="sas-event-popover__title" id="calEventPopoverTitle"></div>
    <div class="sas-event-popover__time" id="calEventPopoverTime"><i class="fi fi-rr-clock"></i> <span></span></div>
    <div class="sas-event-popover__row"><span>Provider</span><span id="calEventPopoverProvider"></span></div>
    <div class="sas-event-popover__row"><span>Status</span><span id="calEventPopoverStatus"></span></div>
    <div class="sas-event-popover__row"><span>No-show risk</span><span id="calEventPopoverRisk"></span></div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('assets/libs/fullcalendar/index.global.min.js') }}"></script>
  <script src="{{ asset('assets/libs/flatpickr/flatpickr.min.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const el = document.getElementById('calendar');
      const filter = document.getElementById('providerFilter');

      const canEdit = @json(auth()->user()->can('manage calendar'));
      const canBook = @json(auth()->user()->can('manage appointments'));
      const csrf = document.querySelector('meta[name=csrf-token]').content;
      const esc = s => (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

      function persistMove(arg) {
        fetch('{{ url('calendar') }}/' + arg.event.id + '/reschedule', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-HTTP-Method-Override': 'PATCH' },
          body: JSON.stringify({ start: arg.event.start.toISOString(), end: arg.event.end ? arg.event.end.toISOString() : null }),
        }).then(r => r.json()).then(d => {
          if (!d.ok) { alert(d.message || 'Could not reschedule.'); arg.revert(); }
        }).catch(() => arg.revert());
      }

      // --- Hover quick-preview -------------------------------------------
      const popover = document.getElementById('calEventPopover');
      const pvTitle = document.getElementById('calEventPopoverTitle');
      const pvTime = document.querySelector('#calEventPopoverTime span');
      const pvProvider = document.getElementById('calEventPopoverProvider');
      const pvStatus = document.getElementById('calEventPopoverStatus');
      const pvRisk = document.getElementById('calEventPopoverRisk');
      const riskColor = { high: 'var(--sas-danger)', medium: 'var(--sas-warning)', low: 'var(--sas-success)' };

      function showPopover(info) {
        const e = info.event;
        const props = e.extendedProps || {};
        pvTitle.textContent = e.title;
        const fmt = (d) => d ? d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '';
        pvTime.textContent = fmt(e.start) + (e.end ? ' – ' + fmt(e.end) : '');
        pvProvider.textContent = props.provider || '—';
        pvStatus.textContent = props.status || '—';
        pvRisk.textContent = props.risk ? props.risk.charAt(0).toUpperCase() + props.risk.slice(1) : '—';
        pvRisk.style.color = riskColor[props.risk] || 'var(--sas-gray-700)';

        const rect = info.el.getBoundingClientRect();
        const popW = 260, margin = 10;
        let left = rect.right + margin;
        if (left + popW > window.innerWidth) left = Math.max(margin, rect.left - popW - margin);
        popover.style.left = left + 'px';
        popover.style.top = Math.max(margin, rect.top) + 'px';
        popover.classList.add('show');
      }
      function hidePopover() { popover.classList.remove('show'); }

      // --- Right-rail agenda ------------------------------------------------
      const agendaEmpty = document.getElementById('calAgendaEmpty');
      const agendaList = document.getElementById('calAgendaList');
      const statusDotColor = { 'Booked': '#f6b100', 'Confirmed': '#7239ea', 'Checked In': '#2563EB', 'Completed': '#17c653', 'No Show': '#f1416c', 'Cancelled': '#adb5bd' };
      function renderAgenda(events) {
        if (!events.length) {
          agendaEmpty.classList.remove('d-none');
          agendaList.classList.add('d-none');
          agendaList.innerHTML = '';
          return;
        }
        agendaEmpty.classList.add('d-none');
        agendaList.classList.remove('d-none');
        const sorted = events.slice().sort((a, b) => (a.start || 0) - (b.start || 0)).slice(0, 25);
        agendaList.innerHTML = sorted.map(e => {
          const props = e.extendedProps || {};
          const time = e.start ? e.start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) : '';
          const dot = statusDotColor[props.status] || '#adb5bd';
          const href = e.url || '#';
          return '<a class="sas-cal-agenda-row" href="' + esc(href) + '">' +
            '<span class="sas-cal-agenda-row__dot" style="background:' + dot + '"></span>' +
            '<span class="flex-grow-1" style="min-width:0">' +
              '<span class="sas-cal-agenda-row__title d-block text-truncate">' + esc(e.title) + '</span>' +
              '<span class="sas-cal-agenda-row__meta">' + esc(time) + (props.provider ? ' · ' + esc(props.provider) : '') + '</span>' +
            '</span>' +
          '</a>';
        }).join('');
      }

      // --- Custom toolbar ----------------------------------------------------
      const rangeText = document.getElementById('calRangeText');
      const viewButtons = document.querySelectorAll('.sas-cal-views [data-view]');
      function setActiveView(viewName) {
        viewButtons.forEach(b => b.classList.toggle('active', b.dataset.view === viewName));
      }

      const calendar = new FullCalendar.Calendar(el, {
        initialView: 'timeGridWeek',
        headerToolbar: false,
        weekends: true,
        nowIndicator: true,
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        slotLabelFormat: { hour: 'numeric', meridiem: 'lowercase', omitZeroMinute: true },
        height: 'auto',
        expandRows: true,
        dayMaxEvents: true,
        editable: canEdit,
        eventDrop: persistMove,
        eventResize: persistMove,
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
        events: function (info, success, failure) {
          const params = new URLSearchParams({ start: info.startStr, end: info.endStr });
          if (filter && filter.value) params.append('provider_id', filter.value);
          fetch('{{ route('calendar.events') }}?' + params.toString())
            .then(r => r.json()).then(success).catch(failure);
        },
        eventClick: function (arg) {
          if (arg.event.url) { arg.jsEvent.preventDefault(); window.location.href = arg.event.url; }
        },
        eventDidMount: function (info) {
          info.el.addEventListener('mouseenter', () => showPopover(info));
          info.el.addEventListener('mouseleave', hidePopover);
        },
        datesSet: function (info) {
          rangeText.textContent = info.view.title;
          setActiveView(info.view.type);
        },
        eventsSet: function (events) {
          const total = events.length;
          const done = events.filter(e => ['Checked In', 'Completed'].includes(e.extendedProps.status)).length;
          const upcoming = events.filter(e => ['Booked', 'Confirmed'].includes(e.extendedProps.status)).length;
          const risk = events.filter(e => e.extendedProps.risk === 'high').length;
          const set = (cls, val) => { const n = document.querySelector('.' + cls + ' .sas-count'); if (n) n.textContent = val; };
          set('cal-stat-total', total);
          set('cal-stat-done', done);
          set('cal-stat-upcoming', upcoming);
          set('cal-stat-risk', risk);
          renderAgenda(events);
        },
      });
      calendar.render();
      if (filter) filter.addEventListener('change', () => calendar.refetchEvents());
      el.addEventListener('scroll', hidePopover, true);

      document.getElementById('calPrev').addEventListener('click', () => calendar.prev());
      document.getElementById('calNext').addEventListener('click', () => calendar.next());
      document.getElementById('calToday').addEventListener('click', () => calendar.today());
      viewButtons.forEach(btn => btn.addEventListener('click', () => calendar.changeView(btn.dataset.view)));

      const weekendsToggle = document.getElementById('calToggleWeekends');
      if (weekendsToggle) {
        weekendsToggle.addEventListener('change', () => calendar.setOption('weekends', weekendsToggle.checked));
      }

      // Jump-to-date via flatpickr, anchored to the range label button
      if (window.flatpickr) {
        const rangeBtn = document.getElementById('calRangeLabel');
        const picker = flatpickr('#calDatePicker', {
          positionElement: rangeBtn,
          onChange: dates => { if (dates[0]) calendar.gotoDate(dates[0]); },
        });
        rangeBtn.addEventListener('click', () => picker.open());
      }
    });
  </script>
@endpush
