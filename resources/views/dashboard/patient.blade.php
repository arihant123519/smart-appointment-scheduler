@extends('layouts.app')

@section('title', 'My Appointments')

@section('page_actions')
  <a href="{{ route('booking.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Book Appointment</a>
@endsection

@push('styles')
  <style>
    /* Page-specific bits only — cards/hero/hover/pulse/stat-icon now come from
       the shared design system (public/assets/css/sas-ui.css + app-shell.css). */
    .sas-phero__avatar { width: 60px; height: 60px; border-radius: 50%; background: rgba(255,255,255,.22);
      display: grid; place-items: center; font-size: 1.4rem; font-weight: 700; flex: 0 0 60px; }
    .sas-appt-row { border: 1px solid var(--sas-gray-100); border-radius: var(--sas-radius-lg); transition: box-shadow .15s, border-color .15s; }
    .sas-appt-row:hover { border-color: var(--sas-primary-200); box-shadow: var(--sas-shadow-sm); }
    .sas-appt-date { width: 58px; flex: 0 0 58px; text-align: center; }
    .sas-appt-date .d { font-size: 1.3rem; font-weight: 700; line-height: 1; }
  </style>
@endpush

@section('content')
  @php $initials = collect(explode(' ', $user->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''); @endphp

  {{-- Welcome hero --}}
  <div class="card sas-card-hero mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div class="sas-phero__avatar">{{ strtoupper($initials) ?: '?' }}</div>
      <div class="flex-grow-1">
        <h4 class="mb-1 text-white fw-bold">Welcome, {{ $user->name }}</h4>
        <div class="text-white-50">{{ $user->email }}</div>
      </div>
      @if ($stats['upcoming'])
        <div class="text-end">
          <div class="small text-white-50">Next up</div>
          <div class="fw-semibold">{{ $upcoming->first()->start_at->format('M j · g:i A') }}</div>
        </div>
      @endif
      <a href="{{ route('booking.create') }}" class="btn btn-light"><i class="fi fi-rr-plus me-1"></i> Book</a>
    </div>
  </div>

  {{-- Stat cards --}}
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Upcoming" :value="$stats['upcoming']" icon="fi-rr-clock" bg="bg-primary-subtle" fg="text-primary" />
    </div>
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Completed" :value="$stats['completed']" icon="fi-rr-check-circle" bg="bg-success-subtle" fg="text-success" />
    </div>
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Missed" :value="$stats['missed']" icon="fi-rr-calendar-xmark" bg="bg-danger-subtle" fg="text-danger" />
    </div>
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Total visits" :value="$stats['total']" icon="fi-rr-heart" bg="bg-info-subtle" fg="text-info" />
    </div>
  </div>

  {{-- Trend + attendance --}}
  <div class="row g-3 mb-4">
    <div class="col-xl-8">
      <x-card title="Your visits — last 6 months" bodyClass="pt-1" class="h-100">
        <div id="patientTrend"></div>
      </x-card>
    </div>
    <div class="col-xl-4">
      <x-card title="Attendance rate" bodyClass="text-center" class="h-100">
        <div id="attendanceGauge"></div>
        <div class="text-muted small">Based on {{ $stats['completed'] + $stats['missed'] }} finished appointment(s)</div>
      </x-card>
    </div>
  </div>

  {{-- Upcoming appointments (card list) --}}
  <x-card class="mb-4">
    <x-slot:title><i class="fi fi-rr-clock text-primary me-1"></i> Upcoming appointments</x-slot:title>
    <x-slot:toolbar>
      <span class="badge bg-primary-subtle text-primary">{{ $upcoming->count() }}</span>
    </x-slot:toolbar>
    @forelse ($upcoming as $a)
      <div class="sas-appt-row d-flex flex-wrap align-items-center gap-3 p-3 mb-2">
        <div class="sas-appt-date text-primary">
          <div class="small text-uppercase">{{ $a->start_at->format('M') }}</div>
          <div class="d">{{ $a->start_at->format('j') }}</div>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold">{{ $a->service->name ?? 'Visit' }} <span class="text-muted fw-normal">· {{ $a->start_at->format('g:i A') }}</span></div>
          <small class="text-muted"><i class="fi fi-rr-user-md me-1"></i>{{ $a->provider->name }}</small>
        </div>
        <x-badge-status :color="$a->status_color" :label="$a->status_label" />
        <div class="d-flex gap-1">
          @if ($a->is_telehealth && $a->telehealth_link)
            <a href="{{ $a->telehealth_link }}" target="_blank" class="btn btn-sm btn-success" title="Join video visit"><i class="fi fi-rr-video-camera"></i></a>
          @endif
          <a href="{{ route('intake.edit', $a) }}" class="btn btn-sm btn-light" title="Intake form"><i class="fi fi-rr-document"></i></a>
          <form method="POST" action="{{ route('booking.cancel', $a) }}" data-sas-confirm="Cancel this appointment?" data-sas-confirm-label="Cancel appointment">
            @csrf @method('PATCH')
            <button class="btn btn-sm btn-outline-danger">Cancel</button>
          </form>
        </div>
      </div>
    @empty
      <x-empty-state icon="fi-rr-calendar-clock" title="No upcoming appointments">
        <a href="{{ route('booking.create') }}" class="btn btn-sm btn-primary">Book one now</a>
      </x-empty-state>
    @endforelse
  </x-card>

  {{-- Referral link --}}
  <x-card class="mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <div class="sas-stat__icon bg-primary-subtle text-primary"><i class="fi fi-rr-share"></i></div>
      <div class="flex-grow-1">
        <div class="fw-semibold">Know someone who'd like it here?</div>
        <div class="text-muted small">Share your link — when they book their first visit, we'll know it was you.</div>
      </div>
      <div class="input-group" style="max-width: 380px;">
        <input type="text" class="form-control form-control-sm" id="referralLinkInput" value="{{ $referral->share_url }}" readonly>
        <button class="btn btn-sm btn-outline-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('referralLinkInput').value); this.innerText='Copied!'; setTimeout(() => this.innerText='Copy', 1500);">Copy</button>
      </div>
    </div>
  </x-card>

  {{-- Past visits --}}
  <x-card bodyClass="p-0">
    <x-slot:title><i class="fi fi-rr-time-past text-secondary me-1"></i> Past visits</x-slot:title>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Date</th><th>Provider</th><th>Service</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($past as $a)
            <tr>
              <td>{{ $a->start_at->format('M j, Y') }}</td>
              <td>{{ $a->provider->name }}</td>
              <td>{{ $a->service->name ?? '—' }}</td>
              <td><x-badge-status :color="$a->status_color" :label="$a->status_label" /></td>
              <td class="text-end">
                @if ($a->status === \App\Models\Appointment::STATUS_COMPLETED)
                  <a href="{{ route('reviews.create', $a) }}" class="btn btn-sm btn-light"><i class="fi fi-rr-star me-1"></i> Review</a>
                @endif
              </td>
            </tr>
          @empty
            <x-empty-state colspan="5" icon="fi-rr-time-past" title="No past visits" />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>

  {{-- ==================== TODAY / MISSED POPUP ==================== --}}
  @if ($todays->count())
    @php $alert = $todaysMissed->count() > 0; @endphp
    <div class="modal fade" id="patientTodayModal" tabindex="-1" aria-hidden="true" data-alert="{{ $alert ? '1' : '0' }}">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0 text-white {{ $alert ? 'bg-danger' : '' }}"
               @if(!$alert) style="background:linear-gradient(120deg,var(--sas-primary-500),#7b78e0)" @endif>
            <h5 class="modal-title d-flex align-items-center gap-2">
              <i class="fi {{ $alert ? 'fi-rr-bell-ring sas-pulse rounded-circle p-1' : 'fi-rr-calendar-clock' }}"></i>
              {{ $alert ? 'You missed an appointment' : "You have an appointment today" }}
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            @if ($todaysMissed->count())
              <p class="text-danger fw-semibold mb-2">
                <i class="fi fi-rr-exclamation me-1"></i>
                {{ $todaysMissed->count() }} of today's appointment(s) {{ $todaysMissed->count() === 1 ? 'was' : 'were' }} marked missed. Please rebook if you still need care:
              </p>
              <ul class="list-group list-group-flush mb-3">
                @foreach ($todaysMissed as $a)
                  <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span><strong>{{ $a->start_at->format('g:i A') }}</strong> · {{ $a->service->name ?? 'Visit' }}</span>
                    <a href="{{ route('booking.create') }}" class="btn btn-sm btn-outline-danger">Rebook</a>
                  </li>
                @endforeach
              </ul>
            @endif

            @php $active = $todays->whereNotIn('status', [\App\Models\Appointment::STATUS_NO_SHOW]); @endphp
            @if ($active->count())
              <p class="text-muted mb-2">Here's what's on your schedule today:</p>
              <ul class="list-group list-group-flush">
                @foreach ($active as $a)
                  <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span><strong>{{ $a->start_at->format('g:i A') }}</strong> · {{ $a->service->name ?? 'Visit' }}
                      <span class="text-muted">with {{ $a->provider->name }}</span></span>
                    @if ($a->is_telehealth && $a->telehealth_link)
                      <a href="{{ $a->telehealth_link }}" target="_blank" class="btn btn-sm btn-success"><i class="fi fi-rr-video-camera me-1"></i> Join</a>
                    @else
                      <x-badge-status :color="$a->status_color" :label="$a->status_label" />
                    @endif
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Dismiss</button>
            @php $first = $todays->firstWhere('status', '!=', \App\Models\Appointment::STATUS_NO_SHOW); @endphp
            @if ($first)
              <a href="{{ route('intake.edit', $first) }}" class="btn btn-primary">Complete intake</a>
            @endif
          </div>
        </div>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
  <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
  <script>
    (function () {
      const purple = '#5955D1';

      // Animated counters are handled globally in layouts/app.blade.php
      // (any element with class "sas-count"/data-count-to opts in automatically).

      // Visits trend
      new ApexCharts(document.querySelector('#patientTrend'), {
        chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
        series: [{ name: 'Visits', data: @json($trendData) }],
        xaxis: { categories: @json($trendLabels) },
        colors: [purple],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        grid: { borderColor: '#eef0f4', strokeDashArray: 4 },
        yaxis: { labels: { formatter: (v) => Math.round(v) } },
      }).render();

      // Attendance gauge
      new ApexCharts(document.querySelector('#attendanceGauge'), {
        chart: { type: 'radialBar', height: 240, fontFamily: 'Inter, sans-serif' },
        series: [{{ $attendanceRate }}],
        colors: ['#17c653'],
        plotOptions: { radialBar: { hollow: { size: '62%' }, track: { background: '#eef0f4' },
          dataLabels: { name: { offsetY: 22, color: '#6c757d', fontSize: '13px' },
            value: { offsetY: -10, fontSize: '28px', fontWeight: 700, formatter: (v) => v + '%' } } } },
        fill: { type: 'gradient', gradient: { shade: 'light', gradientToColors: ['#5fd39a'], stops: [0, 100] } },
        labels: ['Attended'],
        stroke: { lineCap: 'round' },
      }).render();
    })();
  </script>

  {{-- Today / missed popup — independent so a chart error can't block it. --}}
  <script>
    (function () {
      const modalEl = document.getElementById('patientTodayModal');
      if (!modalEl) return;
      function show() {
        if (window.bootstrap && bootstrap.Modal) {
          bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
          modalEl.classList.add('show'); modalEl.style.display = 'block'; modalEl.removeAttribute('aria-hidden');
        }
      }
      const isAlert = modalEl.dataset.alert === '1';
      const stamp = 'sas_patient_today_' + new Date().toISOString().slice(0, 10);
      if (isAlert || !sessionStorage.getItem(stamp)) {
        if (!isAlert) sessionStorage.setItem(stamp, '1');
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', show);
        else show();
      }
    })();
  </script>
@endpush
