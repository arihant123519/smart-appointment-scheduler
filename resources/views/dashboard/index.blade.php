@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
  <style>
    /* ---- Dashboard polish ------------------------------------------------ */
    .sas-hero {
      background: linear-gradient(120deg, #5955D1 0%, #7b78e0 55%, #9b7be0 100%);
      color: #fff; border: 0; position: relative; overflow: hidden;
    }
    .sas-hero::after {
      content: ''; position: absolute; right: -40px; top: -40px;
      width: 220px; height: 220px; border-radius: 50%;
      background: rgba(255, 255, 255, .12);
    }
    .sas-hero::before {
      content: ''; position: absolute; right: 80px; bottom: -60px;
      width: 160px; height: 160px; border-radius: 50%;
      background: rgba(255, 255, 255, .08);
    }
    .sas-stat-card {
      border: 0; border-radius: 1rem; overflow: hidden; position: relative;
      transition: transform .18s ease, box-shadow .18s ease;
    }
    .sas-stat-card:hover { transform: translateY(-4px); box-shadow: 0 .75rem 1.5rem rgba(31, 33, 64, .12); }
    .sas-stat-card .sas-spark { position: absolute; left: 0; right: 0; bottom: 0; opacity: .55; }
    .sas-stat-card .card-body { position: relative; z-index: 2; }
    .sas-stat__icon { width: 48px; height: 48px; border-radius: .85rem; display: grid; place-items: center; font-size: 1.2rem; }
    .sas-stat__delta { font-size: .72rem; font-weight: 600; }
    .sas-count { font-variant-numeric: tabular-nums; }
    .sas-card-soft { border: 0; border-radius: 1rem; box-shadow: 0 .25rem .75rem rgba(31, 33, 64, .05); }
    .sas-chart-toggle .btn { --bs-btn-padding-y: .15rem; --bs-btn-padding-x: .55rem; font-size: .78rem; }
    .sas-legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .sas-pulse { animation: sasPulse 1.6s ease-in-out infinite; }
    @keyframes sasPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(220,53,69,.45); } 50% { box-shadow: 0 0 0 .5rem rgba(220,53,69,0); } }
  </style>
@endpush

@section('content')
  {{-- Today's appointments highlight --}}
  @if ($todaysAppointments->count())
    <div class="card sas-hero mb-4">
      <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="sas-stat__icon bg-white text-primary"><i class="fi fi-rr-calendar-clock"></i></div>
        <div class="flex-grow-1">
          <h5 class="mb-1 text-white">{{ $todaysAppointments->count() }} appointment{{ $todaysAppointments->count() === 1 ? '' : 's' }} today
            @if ($todaysMissed->count())
              <span class="badge bg-warning text-dark ms-2">{{ $todaysMissed->count() }} missed</span>
            @endif
          </h5>
          <div class="d-flex flex-wrap gap-2 mt-2">
            @foreach ($todaysAppointments->take(6) as $a)
              <a href="{{ route('appointments.show', $a) }}" class="badge bg-white text-primary text-decoration-none p-2">
                {{ $a->start_at->format('g:i A') }} · {{ $a->patient->name }}
              </a>
            @endforeach
            @if ($todaysAppointments->count() > 6)
              <span class="badge bg-white text-primary p-2">+{{ $todaysAppointments->count() - 6 }} more</span>
            @endif
          </div>
        </div>
        <a href="{{ route('calendar') }}" class="btn btn-light">Open calendar</a>
      </div>
    </div>
  @endif

  {{-- Stat cards with mini sparklines --}}
  <div class="row g-3 mb-4">
    @php
      $cards = [
        ['label' => "Today's Appointments", 'value' => $stats['today'], 'icon' => 'fi-rr-calendar-clock', 'bg' => 'bg-primary-subtle', 'fg' => 'text-primary', 'spark' => 'sparkToday', 'color' => '#5955D1', 'series' => $chartData],
        ['label' => 'Upcoming', 'value' => $stats['upcoming'], 'icon' => 'fi-rr-clock', 'bg' => 'bg-info-subtle', 'fg' => 'text-info', 'spark' => 'sparkUpcoming', 'color' => '#0dcaf0', 'series' => $chartCompleted],
        ['label' => 'Patients', 'value' => $stats['patients'], 'icon' => 'fi-rr-users', 'bg' => 'bg-success-subtle', 'fg' => 'text-success', 'spark' => 'sparkPatients', 'color' => '#198754', 'series' => $chartData],
        ['label' => 'No-show rate (30d)', 'value' => $noShowRate.'%', 'icon' => 'fi-rr-chart-line-up', 'bg' => 'bg-danger-subtle', 'fg' => 'text-danger', 'spark' => 'sparkNoShow', 'color' => '#dc3545', 'series' => $chartNoShow],
      ];
    @endphp
    @foreach ($cards as $card)
      <div class="col-xl-3 col-sm-6">
        <div class="card sas-stat-card h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="sas-stat__icon {{ $card['bg'] }} {{ $card['fg'] }}">
              <i class="fi {{ $card['icon'] }}"></i>
            </div>
            <div>
              <div class="text-muted small">{{ $card['label'] }}</div>
              <div class="h3 mb-0 fw-bold sas-count" data-count-to="{{ (float) $card['value'] }}" data-suffix="{{ \Illuminate\Support\Str::contains($card['value'], '%') ? '%' : '' }}">0</div>
            </div>
          </div>
          <div class="sas-spark" id="{{ $card['spark'] }}"
               data-series='@json($card['series'])' data-color="{{ $card['color'] }}"></div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-3">
    {{-- Trend chart --}}
    <div class="col-xl-8">
      <div class="card sas-card-soft h-100">
        <div class="card-header bg-transparent border-0 d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
          <div>
            <h6 class="mb-0">Appointments — last 14 days</h6>
            <small class="text-muted">Booked vs. completed vs. no-show</small>
          </div>
          <div class="btn-group sas-chart-toggle" role="group" aria-label="Chart type">
            <button type="button" class="btn btn-outline-secondary active" data-chart-type="area">Area</button>
            <button type="button" class="btn btn-outline-secondary" data-chart-type="bar">Bars</button>
          </div>
        </div>
        <div class="card-body pt-1">
          <div id="apptTrendChart"></div>
        </div>
      </div>
    </div>

    {{-- Status breakdown donut --}}
    <div class="col-xl-4">
      <div class="card sas-card-soft h-100">
        <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0">Status breakdown</h6></div>
        <div class="card-body">
          @if (array_sum($statusBreakdown) > 0)
            <div id="statusDonut"></div>
          @else
            <p class="text-muted mb-0">No data yet.</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- Fill rate gauge --}}
    <div class="col-xl-6">
      <div class="card sas-card-soft h-100">
        <div class="card-header bg-transparent border-0 pt-3">
          <h6 class="mb-0">Fill rate <small class="text-muted fw-normal">(last 30 days)</small></h6>
        </div>
        <div class="card-body">
          @if ($fillRate['available_minutes'] > 0)
            <div id="fillRateGauge"></div>
            <div class="text-muted small text-center">{{ number_format($fillRate['booked_minutes']) }} of {{ number_format($fillRate['available_minutes']) }} available minutes booked</div>
          @else
            <p class="text-muted mb-0">Set up provider working hours to see fill rate.</p>
          @endif
        </div>
      </div>
    </div>

    {{-- Channel mix donut --}}
    <div class="col-xl-6">
      <div class="card sas-card-soft h-100">
        <div class="card-header bg-transparent border-0 pt-3">
          <h6 class="mb-0">Booking channels <small class="text-muted fw-normal">(last 30 days)</small></h6>
        </div>
        <div class="card-body">
          @if (array_sum($channelMix) > 0)
            <div id="channelMixDonut"></div>
          @else
            <p class="text-muted mb-0">No data yet.</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- Busiest hours --}}
    <div class="col-xl-8">
      <div class="card sas-card-soft">
        <div class="card-header bg-transparent border-0 pt-3">
          <h6 class="mb-0">Busiest hours <small class="text-muted fw-normal">(last 30 days)</small></h6>
        </div>
        <div class="card-body pt-1">
          <div id="busyHoursChart"></div>
        </div>
      </div>
    </div>

    {{-- Completion gauge + top services --}}
    <div class="col-xl-4">
      <div class="card sas-card-soft h-100">
        <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0">Completion rate (30d)</h6></div>
        <div class="card-body">
          <div id="completionGauge"></div>
          <hr class="my-2">
          <div class="text-muted small mb-2">Top services</div>
          @forelse ($topServices as $name => $count)
            @php $max = max($topServices); $pct = $max ? round($count / $max * 100) : 0; @endphp
            <div class="mb-2">
              <div class="d-flex justify-content-between small">
                <span class="text-truncate" style="max-width:70%">{{ $name }}</span>
                <span class="fw-semibold">{{ $count }}</span>
              </div>
              <div class="progress" style="height:6px"><div class="progress-bar bg-primary" style="width:{{ $pct }}%"></div></div>
            </div>
          @empty
            <p class="text-muted small mb-0">No service data yet.</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- Today's appointments --}}
    <div class="col-xl-8">
      <div class="card sas-card-soft h-100">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3">
          <h6 class="mb-0">Today's schedule</h6>
          <a href="{{ route('calendar') }}" class="btn btn-sm btn-light">Open calendar</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>Time</th><th>Patient</th><th>Provider</th><th>Service</th><th>Status</th></tr>
              </thead>
              <tbody>
                @forelse ($todaysAppointments as $a)
                  <tr onclick="window.location='{{ route('appointments.show', $a) }}'" style="cursor:pointer">
                    <td class="fw-semibold">{{ $a->start_at->format('g:i A') }}</td>
                    <td>{{ $a->patient->name }}</td>
                    <td>{{ $a->provider->name }}</td>
                    <td>{{ $a->service->name ?? '—' }}</td>
                    <td><span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }}">{{ $a->status_label }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted py-4">No appointments today.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    {{-- High-risk --}}
    <div class="col-xl-4">
      <div class="card sas-card-soft h-100">
        <div class="card-header bg-transparent border-0 pt-3"><h6 class="mb-0"><i class="fi fi-rr-exclamation text-danger me-1"></i> High no-show risk</h6></div>
        <div class="card-body">
          @forelse ($highRisk as $a)
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <div class="fw-semibold">{{ $a->patient->name }}</div>
                <small class="text-muted">{{ $a->start_at->format('M j, g:i A') }} · {{ $a->provider->name }}</small>
              </div>
              <span class="badge bg-danger">{{ $a->no_show_score }}%</span>
            </div>
          @empty
            <p class="text-muted mb-0">No high-risk appointments. 🎉</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  {{-- Missed appointments (past + not completed) --}}
  <div class="row g-3 mt-1">
    <div class="col-12">
      <div class="card sas-card-soft">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3">
          <h6 class="mb-0"><i class="fi fi-rr-calendar-xmark text-danger me-1"></i> Missed appointments
            @if ($missedCount)<span class="badge bg-danger ms-1">{{ $missedCount }}</span>@endif
          </h6>
          <a href="{{ route('appointments.index', ['status' => \App\Models\Appointment::STATUS_NO_SHOW]) }}" class="btn btn-sm btn-light">View all</a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr><th>When</th><th>Patient</th><th>Provider</th><th>Service</th><th>Status</th></tr>
              </thead>
              <tbody>
                @forelse ($missedAppointments as $a)
                  <tr onclick="window.location='{{ route('appointments.show', $a) }}'" style="cursor:pointer">
                    <td class="text-nowrap">{{ $a->start_at->format('M j, Y g:i A') }}</td>
                    <td>{{ $a->patient->name ?? '—' }}</td>
                    <td>{{ $a->provider->name ?? '—' }}</td>
                    <td>{{ $a->service->name ?? '—' }}</td>
                    <td><span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }}">{{ $a->status_label }}</span></td>
                  </tr>
                @empty
                  <tr><td colspan="5" class="text-center text-muted py-4">No missed appointments. 🎉</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ====================  TODAY / MISSED POPUP  ==================== --}}
  @if ($todaysAppointments->count() || $todaysMissed->count())
    @php
      $alert = $todaysMissed->count() > 0;
      // Front desk always sees this popup on every dashboard load.
      $alwaysShow = auth()->user()->hasRole('front_desk');
    @endphp
    <div class="modal fade" id="todayModal" tabindex="-1" aria-hidden="true"
         data-alert="{{ $alert ? '1' : '0' }}" data-always="{{ $alwaysShow ? '1' : '0' }}">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:1rem;overflow:hidden">
          <div class="modal-header border-0 text-white {{ $alert ? 'bg-danger' : '' }}"
               @if(!$alert) style="background:linear-gradient(120deg,#5955D1,#7b78e0)" @endif>
            <h5 class="modal-title d-flex align-items-center gap-2">
              <i class="fi {{ $alert ? 'fi-rr-bell-ring sas-pulse rounded-circle p-1' : 'fi-rr-calendar-clock' }}"></i>
              {{ $alert ? 'Missed appointment alert' : "Today's appointments" }}
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            @if ($todaysMissed->count())
              <p class="text-danger fw-semibold mb-2">
                <i class="fi fi-rr-exclamation me-1"></i>
                {{ $todaysMissed->count() }} appointment{{ $todaysMissed->count() === 1 ? '' : 's' }} today {{ $todaysMissed->count() === 1 ? 'was' : 'were' }} missed and need follow-up:
              </p>
              <ul class="list-group list-group-flush mb-3">
                @foreach ($todaysMissed as $a)
                  <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span><strong>{{ $a->start_at->format('g:i A') }}</strong> · {{ $a->patient->name ?? '—' }}</span>
                    <a href="{{ route('appointments.show', $a) }}" class="btn btn-sm btn-outline-danger">Follow up</a>
                  </li>
                @endforeach
              </ul>
            @endif

            @if ($todaysAppointments->count())
              <p class="text-muted mb-2">
                You have <strong>{{ $todaysAppointments->count() }}</strong> appointment{{ $todaysAppointments->count() === 1 ? '' : 's' }} scheduled today.
              </p>
              <ul class="list-group list-group-flush">
                @foreach ($todaysAppointments->take(5) as $a)
                  <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span><strong>{{ $a->start_at->format('g:i A') }}</strong> · {{ $a->patient->name }}</span>
                    <span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }}">{{ $a->status_label }}</span>
                  </li>
                @endforeach
              </ul>
              @if ($todaysAppointments->count() > 5)
                <small class="text-muted">+{{ $todaysAppointments->count() - 5 }} more on the schedule below.</small>
              @endif
            @endif
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Dismiss</button>
            <a href="{{ route('calendar') }}" class="btn btn-primary">Open calendar</a>
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
      const purple = '#5955D1', green = '#198754', red = '#dc3545';
      const labels = @json($chartLabels);
      const totals = @json($chartData);
      const completed = @json($chartCompleted);
      const noShow = @json($chartNoShow);

      // ---- Animated stat counters ----
      document.querySelectorAll('.sas-count').forEach(function (el) {
        const target = parseFloat(el.dataset.countTo) || 0;
        const suffix = el.dataset.suffix || '';
        const isFloat = target % 1 !== 0;
        const dur = 900, start = performance.now();
        function step(now) {
          const p = Math.min((now - start) / dur, 1);
          const eased = 1 - Math.pow(1 - p, 3);
          const val = target * eased;
          el.textContent = (isFloat ? val.toFixed(1) : Math.round(val)) + suffix;
          if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
      });

      // ---- Mini sparklines in stat cards ----
      document.querySelectorAll('.sas-spark').forEach(function (el) {
        let data;
        try { data = JSON.parse(el.dataset.series || '[]'); } catch (e) { data = []; }
        if (!data.length) return;
        new ApexCharts(el, {
          chart: { type: 'area', height: 50, sparkline: { enabled: true } },
          series: [{ data: data }],
          stroke: { curve: 'smooth', width: 2 },
          fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0 } },
          colors: [el.dataset.color || purple],
          tooltip: { enabled: false },
        }).render();
      });

      // ---- Main trend chart (toggle area / bars) ----
      let trendType = 'area';
      function trendOptions(type) {
        return {
          chart: { type: type, height: 320, toolbar: { show: false }, fontFamily: 'Instrument Sans, sans-serif',
                   animations: { enabled: true, easing: 'easeinout', speed: 600 } },
          series: [
            { name: 'Booked', data: totals },
            { name: 'Completed', data: completed },
            { name: 'No-show', data: noShow },
          ],
          xaxis: { categories: labels },
          colors: [purple, green, red],
          dataLabels: { enabled: false },
          stroke: { curve: 'smooth', width: type === 'area' ? 3 : 0 },
          fill: type === 'area'
            ? { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.03 } }
            : { type: 'solid', opacity: 0.9 },
          plotOptions: { bar: { columnWidth: '55%', borderRadius: 4 } },
          legend: { position: 'top', horizontalAlign: 'right' },
          grid: { borderColor: '#eef0f4', strokeDashArray: 4 },
          markers: { size: 0, hover: { size: 5 } },
          tooltip: { shared: true, intersect: false },
        };
      }
      const trendChart = new ApexCharts(document.querySelector('#apptTrendChart'), trendOptions('area'));
      trendChart.render();
      document.querySelectorAll('[data-chart-type]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          document.querySelectorAll('[data-chart-type]').forEach(b => b.classList.remove('active'));
          btn.classList.add('active');
          trendType = btn.dataset.chartType;
          trendChart.updateOptions(trendOptions(trendType));
        });
      });

      // ---- Status donut ----
      @if (array_sum($statusBreakdown) > 0)
      (function () {
        const map = @json($statusBreakdown);
        const labelMap = @json(\App\Models\Appointment::STATUSES);
        const colorMap = { booked:'#ffc107', confirmed:'#0dcaf0', checked_in:'#5955D1', completed:'#198754', cancelled:'#adb5bd', no_show:'#dc3545' };
        const keys = Object.keys(map);
        new ApexCharts(document.querySelector('#statusDonut'), {
          chart: { type: 'donut', height: 280, fontFamily: 'Instrument Sans, sans-serif' },
          series: keys.map(k => map[k]),
          labels: keys.map(k => labelMap[k] || k),
          colors: keys.map(k => colorMap[k] || '#888'),
          legend: { position: 'bottom' },
          dataLabels: { enabled: true, formatter: (v) => Math.round(v) + '%' },
          plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total',
            formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0) } } } } },
          stroke: { width: 2 },
        }).render();
      })();
      @endif

      // ---- Fill rate gauge ----
      @if ($fillRate['available_minutes'] > 0)
      new ApexCharts(document.querySelector('#fillRateGauge'), {
        chart: { type: 'radialBar', height: 230, fontFamily: 'Instrument Sans, sans-serif' },
        series: [{{ $fillRate['rate'] }}],
        colors: [purple],
        plotOptions: { radialBar: {
          hollow: { size: '62%' },
          track: { background: '#eef0f4' },
          dataLabels: { name: { offsetY: 22, color: '#6c757d', fontSize: '13px' },
            value: { offsetY: -12, fontSize: '28px', fontWeight: 700, formatter: (v) => v + '%' } } } },
        fill: { type: 'gradient', gradient: { shade: 'light', gradientToColors: ['#8f8cf0'], stops: [0, 100] } },
        labels: ['Filled'],
        stroke: { lineCap: 'round' },
      }).render();
      @endif

      // ---- Channel mix donut ----
      @if (array_sum($channelMix) > 0)
      (function () {
        const map = @json($channelMix);
        const labelMap = { web: 'Website', app: 'App', phone: 'Phone', walk_in: 'Walk-in', ai: 'AI Assistant', sms: 'SMS', whatsapp: 'WhatsApp' };
        const keys = Object.keys(map);
        new ApexCharts(document.querySelector('#channelMixDonut'), {
          chart: { type: 'donut', height: 280, fontFamily: 'Instrument Sans, sans-serif' },
          series: keys.map(k => map[k]),
          labels: keys.map(k => labelMap[k] || k),
          legend: { position: 'bottom' },
          dataLabels: { enabled: true, formatter: (v) => Math.round(v) + '%' },
          plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, label: 'Total',
            formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0) } } } } },
          stroke: { width: 2 },
        }).render();
      })();
      @endif

      // ---- Busiest hours bar ----
      new ApexCharts(document.querySelector('#busyHoursChart'), {
        chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Instrument Sans, sans-serif' },
        series: [{ name: 'Appointments', data: @json($hourData) }],
        xaxis: { categories: @json($hourLabels) },
        colors: [purple],
        dataLabels: { enabled: false },
        plotOptions: { bar: { columnWidth: '50%', borderRadius: 5, distributed: false,
          colors: { ranges: [{ from: 0, to: 0, color: '#e9ecf5' }] } } },
        grid: { borderColor: '#eef0f4', strokeDashArray: 4 },
        tooltip: { y: { formatter: (v) => v + ' appt' + (v === 1 ? '' : 's') } },
      }).render();

      // ---- Completion gauge ----
      new ApexCharts(document.querySelector('#completionGauge'), {
        chart: { type: 'radialBar', height: 230, fontFamily: 'Instrument Sans, sans-serif' },
        series: [{{ $completionRate }}],
        colors: ['#198754'],
        plotOptions: { radialBar: {
          hollow: { size: '62%' },
          track: { background: '#eef0f4' },
          dataLabels: { name: { offsetY: 22, color: '#6c757d', fontSize: '13px' },
            value: { offsetY: -12, fontSize: '28px', fontWeight: 700, formatter: (v) => v + '%' } } } },
        fill: { type: 'gradient', gradient: { shade: 'light', gradientToColors: ['#5fd39a'], stops: [0, 100] } },
        labels: ['Completed'],
        stroke: { lineCap: 'round' },
      }).render();
    })();
  </script>

  {{-- Today / missed popup — kept in its OWN script so a charting error above
       can never prevent the alert from showing. --}}
  <script>
    (function () {
      const modalEl = document.getElementById('todayModal');
      if (!modalEl) return;
      function showModal() {
        if (window.bootstrap && bootstrap.Modal) {
          bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
          // Fallback if Bootstrap JS hasn't loaded for some reason.
          modalEl.classList.add('show');
          modalEl.style.display = 'block';
          modalEl.removeAttribute('aria-hidden');
        }
      }
      // Front desk always sees the popup. Otherwise: missed/non-completed
      // appointments are urgent and always alert, while the purely informational
      // "today's appointments" shows once per day/session.
      const always = modalEl.dataset.always === '1';
      const isAlert = modalEl.dataset.alert === '1';
      const stamp = 'sas_today_modal_' + new Date().toISOString().slice(0, 10);
      const shouldShow = always || isAlert || !sessionStorage.getItem(stamp);
      if (shouldShow) {
        if (!always && !isAlert) sessionStorage.setItem(stamp, '1');
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', showModal);
        } else {
          showModal();
        }
      }
    })();
  </script>
@endpush
