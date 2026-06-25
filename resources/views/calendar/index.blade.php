@extends('layouts.app')

@section('title', 'Calendar')

@section('page_actions')
  @can('manage appointments')
    <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> New Appointment</a>
  @endcan
@endsection

@section('content')
  <div class="card">
    <div class="card-body">
      @if ($providers->count() > 1)
        <div class="mb-3" style="max-width:320px">
          <label class="form-label small text-muted">Filter by provider</label>
          <select id="providerFilter" class="form-select">
            <option value="">All providers</option>
            @foreach ($providers as $p)
              <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->specialty }})</option>
            @endforeach
          </select>
        </div>
      @endif
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
        editable: canEdit,
        eventDrop: persistMove,
        eventResize: persistMove,
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
