@extends('layouts.app')

@section('title', 'Calendar')

@section('page_actions')
  @can('manage appointments')
    <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> New Appointment</a>
  @endcan
@endsection

@push('styles')
  <style>
    .sas-card-soft { border: 0; border-radius: 1rem; box-shadow: 0 .25rem .9rem rgba(31, 33, 64, .06); }
    .sas-cal-legend { display: flex; flex-wrap: wrap; gap: .35rem .9rem; }
    .sas-cal-legend span { display: inline-flex; align-items: center; gap: .4rem; font-size: .8rem; color: #5b5b6b; }
    .sas-cal-legend i { width: 11px; height: 11px; border-radius: 3px; display: inline-block; }
    /* FullCalendar theming */
    #calendar { --fc-border-color: #eef0f4; --fc-today-bg-color: #f4f3ff; --fc-now-indicator-color: #dc3545; }
    #calendar .fc-toolbar-title { font-size: 1.25rem; font-weight: 700; color: #1f2140; }
    #calendar .fc-col-header-cell-cushion { color: #6c757d; font-weight: 600; text-decoration: none; padding: .5rem; }
    #calendar .fc-button-primary { background: #fff; border-color: #e3e3ee; color: #5955D1; font-weight: 600;
      text-transform: capitalize; box-shadow: none; }
    #calendar .fc-button-primary:not(:disabled):hover { background: #f1f0fb; border-color: #c9c7ef; color: #5955D1; }
    #calendar .fc-button-primary:not(:disabled).fc-button-active,
    #calendar .fc-button-primary:not(:disabled):active { background: #5955D1; border-color: #5955D1; color: #fff; }
    #calendar .fc-button-primary:disabled { background: #f5f5f9; border-color: #eee; color: #adb5bd; }
    #calendar .fc-event { border: 0; border-radius: 7px; padding: 1px 4px; font-weight: 600; font-size: .76rem;
      box-shadow: 0 1px 3px rgba(31,33,64,.15); cursor: pointer; transition: transform .1s; }
    #calendar .fc-event:hover { transform: translateY(-1px); filter: brightness(1.04); }
    #calendar .fc-timegrid-now-indicator-line { border-width: 2px; }
    #providerFilter { border-radius: .7rem; }
  </style>
@endpush

@section('content')
  <div class="card sas-card-soft">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        @if ($providers->count() > 1)
          <div style="max-width:320px">
            <label class="form-label small text-muted mb-1"><i class="fi fi-rr-user-md me-1"></i> Filter by provider</label>
            <select id="providerFilter" class="form-select">
              <option value="">All providers</option>
              @foreach ($providers as $p)
                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->specialty }})</option>
              @endforeach
            </select>
          </div>
        @endif
        <div class="sas-cal-legend ms-auto">
          <span><i style="background:#ffc107"></i> Booked</span>
          <span><i style="background:#0dcaf0"></i> Confirmed</span>
          <span><i style="background:#5955D1"></i> Checked in</span>
          <span><i style="background:#198754"></i> Completed</span>
          <span><i style="background:#dc3545"></i> No-show</span>
          <span><i style="background:#adb5bd"></i> Cancelled</span>
        </div>
      </div>
      <div id="calendar"></div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('assets/libs/fullcalendar/index.global.min.js') }}"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const el = document.getElementById('calendar');
      const filter = document.getElementById('providerFilter');

      const canEdit = @json(auth()->user()->can('manage calendar'));
      const csrf = document.querySelector('meta[name=csrf-token]').content;

      function persistMove(arg) {
        fetch('{{ url('calendar') }}/' + arg.event.id + '/reschedule', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-HTTP-Method-Override': 'PATCH' },
          body: JSON.stringify({ start: arg.event.start.toISOString(), end: arg.event.end ? arg.event.end.toISOString() : null }),
        }).then(r => r.json()).then(d => {
          if (!d.ok) { alert(d.message || 'Could not reschedule.'); arg.revert(); }
        }).catch(() => arg.revert());
      }

      const calendar = new FullCalendar.Calendar(el, {
        initialView: 'timeGridWeek',
        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
        nowIndicator: true,
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
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
      });
      calendar.render();
      if (filter) filter.addEventListener('change', () => calendar.refetchEvents());
    });
  </script>
@endpush
