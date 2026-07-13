@extends('layouts.app')

@section('title', 'My Appointments')

@section('page_actions')
  <a href="{{ route('booking.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Book Appointment</a>
@endsection

@push('styles')
  <style>
    .sas-card-soft { border: 0; border-radius: 1rem; box-shadow: 0 .25rem .9rem rgba(31, 33, 64, .06); }
    .sas-phero { background: linear-gradient(120deg, #5955D1 0%, #7b78e0 58%, #9b7be0 100%); color: #fff;
      border: 0; border-radius: 1.1rem; position: relative; overflow: hidden; }
    .sas-phero::after { content: ''; position: absolute; right: -40px; top: -60px; width: 220px; height: 220px;
      border-radius: 50%; background: rgba(255,255,255,.12); }
    .sas-phero__avatar { width: 60px; height: 60px; border-radius: 50%; background: rgba(255,255,255,.22);
      display: grid; place-items: center; font-size: 1.4rem; font-weight: 700; flex: 0 0 60px; }
    .sas-stat-card { border: 0; border-radius: 1rem; transition: transform .18s ease, box-shadow .18s ease; }
    .sas-stat-card:hover { transform: translateY(-4px); box-shadow: 0 .75rem 1.5rem rgba(31, 33, 64, .12); }
    .sas-stat__ico { width: 46px; height: 46px; border-radius: .8rem; display: grid; place-items: center; font-size: 1.2rem; }
    .sas-count { font-variant-numeric: tabular-nums; }
    .sas-appt-row { border: 1px solid #f0f0f5; border-radius: .85rem; transition: box-shadow .15s, border-color .15s; }
    .sas-appt-row:hover { border-color: #d9d8f0; box-shadow: 0 .35rem .9rem rgba(31,33,64,.06); }
    .sas-appt-date { width: 58px; flex: 0 0 58px; text-align: center; }
    .sas-appt-date .d { font-size: 1.3rem; font-weight: 700; line-height: 1; }
    .sas-pulse { animation: sasPulse 1.6s ease-in-out infinite; }
    @keyframes sasPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(220,53,69,.45); } 50% { box-shadow: 0 0 0 .5rem rgba(220,53,69,0); } }
  </style>
@endpush

@section('content')
  @php $initials = collect(explode(' ', $user->name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''); @endphp

  {{-- Welcome hero --}}
  <div class="card sas-phero mb-4">
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
    @php
      $cards = [
        ['label' => 'Upcoming', 'value' => $stats['upcoming'], 'icon' => 'fi-rr-clock', 'bg' => 'bg-primary-subtle', 'fg' => 'text-primary'],
        ['label' => 'Completed', 'value' => $stats['completed'], 'icon' => 'fi-rr-check-circle', 'bg' => 'bg-success-subtle', 'fg' => 'text-success'],
        ['label' => 'Missed', 'value' => $stats['missed'], 'icon' => 'fi-rr-calendar-xmark', 'bg' => 'bg-danger-subtle', 'fg' => 'text-danger'],
        ['label' => 'Total visits', 'value' => $stats['total'], 'icon' => 'fi-rr-heart', 'bg' => 'bg-info-subtle', 'fg' => 'text-info'],
      ];
    @endphp
    @foreach ($cards as $card)
      <div class="col-xl-3 col-sm-6">
        <div class="card sas-stat-card sas-card-soft h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="sas-stat__ico {{ $card['bg'] }} {{ $card['fg'] }}"><i class="fi {{ $card['icon'] }}"></i></div>
            <div>
              <div class="text-muted small">{{ $card['label'] }}</div>
              <div class="h3 mb-0 fw-bold sas-count" data-count-to="{{ (int) $card['value'] }}">0</div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Trend + attendance --}}
  <div class="row g-3 mb-4">
    <div class="col-xl-8">
      <div class="card sas-card-soft h-100">
        <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0">Your visits — last 6 months</h6></div>
        <div class="card-body pt-1"><div id="patientTrend"></div></div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card sas-card-soft h-100">
        <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0">Attendance rate</h6></div>
        <div class="card-body text-center">
          <div id="attendanceGauge"></div>
          <div class="text-muted small">Based on {{ $stats['completed'] + $stats['missed'] }} finished appointment(s)</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Upcoming appointments (card list) --}}
  <div class="card sas-card-soft mb-4">
    <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3">
      <h6 class="mb-0"><i class="fi fi-rr-clock text-primary me-1"></i> Upcoming appointments</h6>
      <span class="badge bg-primary-subtle text-primary">{{ $upcoming->count() }}</span>
    </div>
    <div class="card-body">
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
          <span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }}">{{ $a->status_label }}</span>
          <div class="d-flex gap-1">
            @if ($a->is_telehealth && $a->telehealth_link)
              <a href="{{ $a->telehealth_link }}" target="_blank" class="btn btn-sm btn-success" title="Join video visit"><i class="fi fi-rr-video-camera"></i></a>
            @endif
            <a href="{{ route('intake.edit', $a) }}" class="btn btn-sm btn-light" title="Intake form"><i class="fi fi-rr-document"></i></a>
            <form method="POST" action="{{ route('booking.cancel', $a) }}" onsubmit="return confirm('Cancel this appointment?')">
              @csrf @method('PATCH')
              <button class="btn btn-sm btn-outline-danger">Cancel</button>
            </form>
          </div>
        </div>
      @empty
        <div class="text-center text-muted py-4">No upcoming appointments. <a href="{{ route('booking.create') }}">Book one now</a>.</div>
      @endforelse
    </div>
  </div>

  {{-- Referral link --}}
  <div class="card sas-card-soft mb-4">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div class="sas-stat__ico bg-primary-subtle text-primary"><i class="fi fi-rr-share"></i></div>
      <div class="flex-grow-1">
        <div class="fw-semibold">Know someone who'd like it here?</div>
        <div class="text-muted small">Share your link — when they book their first visit, we'll know it was you.</div>
      </div>
      <div class="input-group" style="max-width: 380px;">
        <input type="text" class="form-control form-control-sm" id="referralLinkInput" value="{{ $referral->share_url }}" readonly>
        <button class="btn btn-sm btn-outline-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('referralLinkInput').value); this.innerText='Copied!'; setTimeout(() => this.innerText='Copy', 1500);">Copy</button>
      </div>
    </div>
  </div>

  {{-- Past visits --}}
  <div class="card sas-card-soft">
    <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0"><i class="fi fi-rr-time-past text-secondary me-1"></i> Past visits</h6></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th>Date</th><th>Provider</th><th>Service</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @forelse ($past as $a)
              <tr>
                <td>{{ $a->start_at->format('M j, Y') }}</td>
                <td>{{ $a->provider->name }}</td>
                <td>{{ $a->service->name ?? '—' }}</td>
                <td><span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }}">{{ $a->status_label }}</span></td>
                <td class="text-end">
                  @if ($a->status === \App\Models\Appointment::STATUS_COMPLETED)
                    <a href="{{ route('reviews.create', $a) }}" class="btn btn-sm btn-light"><i class="fi fi-rr-star me-1"></i> Review</a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-4">No past visits.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ==================== TODAY / MISSED POPUP ==================== --}}
  @if ($todays->count())
    @php $alert = $todaysMissed->count() > 0; @endphp
    <div class="modal fade" id="patientTodayModal" tabindex="-1" aria-hidden="true" data-alert="{{ $alert ? '1' : '0' }}">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1rem;overflow:hidden">
          <div class="modal-header border-0 text-white {{ $alert ? 'bg-danger' : '' }}"
               @if(!$alert) style="background:linear-gradient(120deg,#5955D1,#7b78e0)" @endif>
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
                      <span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }}">{{ $a->status_label }}</span>
                    @endif
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
          <div class="modal-footer border-0">
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

      // Animated counters
      document.querySelectorAll('.sas-count').forEach(function (el) {
        const target = parseInt(el.dataset.countTo, 10) || 0;
        const dur = 800, start = performance.now();
        function step(now) {
          const p = Math.min((now - start) / dur, 1);
          el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
          if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
      });

      // Visits trend
      new ApexCharts(document.querySelector('#patientTrend'), {
        chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Instrument Sans, sans-serif' },
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
        chart: { type: 'radialBar', height: 240, fontFamily: 'Instrument Sans, sans-serif' },
        series: [{{ $attendanceRate }}],
        colors: ['#198754'],
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
