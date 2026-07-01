@extends('layouts.app')

@section('title', 'Appointment #'.$appointment->id)

@section('page_actions')
  @can('manage appointments')
    <a href="{{ route('appointments.edit', $appointment) }}" class="btn btn-light"><i class="fi fi-rr-pencil me-1"></i> Edit</a>
    <form method="POST" action="{{ route('appointments.destroy', $appointment) }}" class="d-inline" onsubmit="return confirm('Delete this appointment?')">
      @csrf @method('DELETE')
      <button class="btn btn-outline-danger">Delete</button>
    </form>
  @endcan
@endsection

@push('styles')
  <style>
    .sas-card-soft { border: 0; border-radius: 1rem; box-shadow: 0 .25rem .9rem rgba(31, 33, 64, .06); }
    .sas-appt-hero { background: linear-gradient(120deg, #5955D1 0%, #7b78e0 60%, #9b7be0 100%); color: #fff;
      border: 0; border-radius: 1.1rem; position: relative; overflow: hidden; }
    .sas-appt-hero::after { content: ''; position: absolute; right: -40px; top: -50px; width: 200px; height: 200px;
      border-radius: 50%; background: rgba(255,255,255,.12); }
    .sas-appt-avatar { width: 64px; height: 64px; border-radius: 1rem; background: rgba(255,255,255,.2); color: #fff;
      display: grid; place-items: center; font-size: 1.6rem; font-weight: 700; flex: 0 0 64px; }
    .sas-appt-meta { display: flex; flex-wrap: wrap; gap: .4rem; }
    .sas-appt-meta .chip { background: rgba(255,255,255,.18); border-radius: 999px; padding: .3rem .8rem;
      font-size: .82rem; display: inline-flex; align-items: center; gap: .35rem; }
    .sas-dl dt { font-weight: 500; }
    .sas-dl dd { font-weight: 600; color: #2a2c45; }
    .sas-rem-item { display: flex; align-items: center; gap: .75rem; padding: .8rem 1rem; border-bottom: 1px solid #f0f0f5; }
    .sas-rem-item:last-child { border-bottom: 0; }
    .sas-rem-ico { width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; flex: 0 0 36px; }
  </style>
@endpush

@section('content')
  @php
    $rc = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'][$appointment->risk_level];
    $initials = collect(explode(' ', $appointment->patient->name))->take(2)->map(fn($w) => mb_substr($w, 0, 1))->implode('');
  @endphp

  {{-- Hero header --}}
  <div class="card sas-appt-hero mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div class="sas-appt-avatar">{{ strtoupper($initials) ?: '?' }}</div>
      <div class="flex-grow-1">
        <h4 class="mb-1 text-white fw-bold">{{ $appointment->patient->name }}</h4>
        <div class="sas-appt-meta">
          <span class="chip"><i class="fi fi-rr-calendar"></i> {{ $appointment->start_at->format('D, M j') }}</span>
          <span class="chip"><i class="fi fi-rr-clock"></i> {{ $appointment->start_at->format('g:i A') }} – {{ $appointment->end_at->format('g:i A') }}</span>
          <span class="chip"><i class="fi fi-rr-user-md"></i> {{ $appointment->provider->name }}</span>
          @if ($appointment->is_telehealth)<span class="chip"><i class="fi fi-rr-video-camera"></i> Telehealth</span>@endif
        </div>
      </div>
      <span class="badge bg-white text-{{ $appointment->status_color }} fs-6 px-3 py-2">{{ $appointment->status_label }}</span>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card sas-card-soft mb-3">
        <div class="card-header bg-transparent border-0 pt-3">
          <h6 class="mb-0"><i class="fi fi-rr-info text-primary me-1"></i> Appointment details</h6>
        </div>
        <div class="card-body">
          <dl class="row mb-0 sas-dl">
            <dt class="col-sm-4 text-muted">Patient</dt><dd class="col-sm-8">{{ $appointment->patient->name }} <small class="text-muted fw-normal">({{ $appointment->patient->email }})</small></dd>
            <dt class="col-sm-4 text-muted">Provider</dt><dd class="col-sm-8">{{ $appointment->provider->name }} — {{ $appointment->provider->specialty }}</dd>
            <dt class="col-sm-4 text-muted">Service</dt><dd class="col-sm-8">{{ $appointment->service->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">When</dt><dd class="col-sm-8">{{ $appointment->start_at->format('l, F j, Y') }} · {{ $appointment->start_at->format('g:i A') }} – {{ $appointment->end_at->format('g:i A') }}</dd>
            <dt class="col-sm-4 text-muted">Clinic</dt><dd class="col-sm-8">{{ $appointment->clinic->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Room/Resource</dt><dd class="col-sm-8">{{ $appointment->resource->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Channel</dt><dd class="col-sm-8">{{ ucfirst($appointment->channel) }}</dd>
            <dt class="col-sm-4 text-muted">Telehealth</dt>
            <dd class="col-sm-8">
              @if ($appointment->is_telehealth && $appointment->telehealth_link)
                <a href="{{ $appointment->telehealth_link }}" target="_blank" class="btn btn-sm btn-success"><i class="fi fi-rr-video-camera me-1"></i> Join video visit</a>
              @else
                {{ $appointment->is_telehealth ? 'Yes' : 'No' }}
              @endif
            </dd>
            @if ($appointment->intakeForm?->ai_summary)
              <dt class="col-sm-4 text-muted">Intake summary</dt>
              <dd class="col-sm-8"><pre class="small bg-light p-2 rounded mb-0">{{ $appointment->intakeForm->ai_summary }}</pre></dd>
            @endif
            <dt class="col-sm-4 text-muted">Reason</dt><dd class="col-sm-8">{{ $appointment->reason ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Notes</dt><dd class="col-sm-8">{{ $appointment->notes ?? '—' }}</dd>
          </dl>
        </div>
      </div>

      <div class="card sas-card-soft">
        <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0"><i class="fi fi-rr-bell text-primary me-1"></i> Reminders</h6></div>
        <div class="card-body p-0">
          @forelse ($appointment->reminders as $rem)
            @php $rs = ['sent' => 'success', 'pending' => 'warning', 'failed' => 'danger'][$rem->status] ?? 'secondary'; @endphp
            <div class="sas-rem-item">
              <span class="sas-rem-ico bg-{{ $rs }}-subtle text-{{ $rs }}"><i class="fi fi-rr-{{ $rem->channel === 'sms' ? 'comment-sms' : ($rem->channel === 'email' ? 'envelope' : 'bell') }}"></i></span>
              <div class="flex-grow-1">
                <div class="fw-semibold">{{ ucfirst($rem->type) }} <span class="text-muted fw-normal small">· {{ ucfirst($rem->channel) }}</span></div>
                <small class="text-muted">{{ $rem->scheduled_at->format('M j, g:i A') }}</small>
              </div>
              <span class="badge bg-{{ $rs }}-subtle text-{{ $rs }}">{{ ucfirst($rem->status) }}</span>
            </div>
          @empty
            <div class="text-center text-muted py-4">No reminders scheduled.</div>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card sas-card-soft mb-3">
        <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0"><i class="fi fi-rr-shield-check text-{{ $rc }} me-1"></i> No-show risk</h6></div>
        <div class="card-body text-center">
          <div id="riskGauge"></div>
          <div class="badge bg-{{ $rc }}-subtle text-{{ $rc }} px-3 py-2">{{ ucfirst($appointment->risk_level) }} risk</div>
        </div>
      </div>

      @can('manage appointments')
        <div class="card sas-card-soft mb-3">
          <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0"><i class="fi fi-rr-refresh text-primary me-1"></i> Update status</h6></div>
          <div class="card-body">
            <form method="POST" action="{{ route('appointments.status', $appointment) }}">
              @csrf @method('PATCH')
              <select name="status" class="form-select mb-2">
                @foreach (\App\Models\Appointment::STATUSES as $key => $label)
                  <option value="{{ $key }}" @selected($appointment->status === $key)>{{ $label }}</option>
                @endforeach
              </select>
              <input type="text" name="cancellation_reason" class="form-control mb-2" placeholder="Reason (if cancelling)">
              <button class="btn btn-primary w-100">Update</button>
            </form>
          </div>
        </div>
      @endcan

      <div class="card sas-card-soft">
        <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0"><i class="fi fi-rr-bolt text-primary me-1"></i> Quick actions</h6></div>
        <div class="card-body d-grid gap-2">
          <a href="{{ route('intake.edit', $appointment) }}" class="btn btn-light">
            <i class="fi fi-rr-document me-1"></i> Intake form
            @if ($appointment->intakeForm?->status === 'completed')<span class="badge bg-success-subtle text-success ms-1">Done</span>@endif
          </a>
          @if ($appointment->status !== \App\Models\Appointment::STATUS_CHECKED_IN)
            <form method="POST" action="{{ route('intake.checkin', $appointment) }}">
              @csrf @method('PATCH')
              <button class="btn btn-light w-100"><i class="fi fi-rr-marker me-1"></i> Digital check-in</button>
            </form>
          @endif

          @can('manage appointments')
            <hr class="my-1">
            <form method="POST" action="{{ route('payments.charge', $appointment) }}" class="row g-1">
              @csrf
              <div class="col-5"><input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="Amt" value="{{ $appointment->service?->price }}"></div>
              <div class="col-4">
                <select name="type" class="form-select form-select-sm">
                  <option value="copay">Copay</option><option value="fee">Fee</option><option value="no_show_fee">No-show</option>
                </select>
              </div>
              <div class="col-3"><button class="btn btn-sm btn-primary w-100">Charge</button></div>
              <input type="hidden" name="method" value="cash">
            </form>
          @endcan
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
  <script>
    (function () {
      const el = document.getElementById('riskGauge');
      if (!el || typeof ApexCharts === 'undefined') return;
      const score = {{ (int) $appointment->no_show_score }};
      const colors = { high: '#dc3545', medium: '#ffc107', low: '#198754' };
      const color = colors['{{ $appointment->risk_level }}'] || '#5955D1';
      new ApexCharts(el, {
        chart: { type: 'radialBar', height: 230, fontFamily: 'Instrument Sans, sans-serif' },
        series: [score],
        colors: [color],
        plotOptions: { radialBar: {
          hollow: { size: '62%' },
          track: { background: '#eef0f4' },
          dataLabels: {
            name: { offsetY: 22, color: '#6c757d', fontSize: '13px' },
            value: { offsetY: -10, fontSize: '30px', fontWeight: 700, color: color, formatter: (v) => v + '%' },
          },
        } },
        fill: { type: 'gradient', gradient: { shade: 'light', gradientToColors: [color], stops: [0, 100] } },
        labels: ['No-show chance'],
        stroke: { lineCap: 'round' },
      }).render();
    })();
  </script>
@endpush
