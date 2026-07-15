@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  {{-- Today's appointments highlight --}}
  @if ($todaysAppointments->count())
    <div class="card sas-card-hero mb-4">
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
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Today's Appointments" :value="$stats['today']" icon="fi-rr-calendar-clock"
        bg="bg-primary-subtle" fg="text-primary" sparkId="sparkToday" sparkColor="#5955D1" :sparkSeries="$chartData" />
    </div>
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Upcoming" :value="$stats['upcoming']" icon="fi-rr-clock"
        bg="bg-info-subtle" fg="text-info" sparkId="sparkUpcoming" sparkColor="#7239ea" :sparkSeries="$chartCompleted" />
    </div>
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Patients" :value="$stats['patients']" icon="fi-rr-users"
        bg="bg-success-subtle" fg="text-success" sparkId="sparkPatients" sparkColor="#17c653" :sparkSeries="$chartData" />
    </div>
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="No-show rate (30d)" :value="$noShowRate.'%'" icon="fi-rr-chart-line-up"
        bg="bg-danger-subtle" fg="text-danger" sparkId="sparkNoShow" sparkColor="#f1416c" :sparkSeries="$chartNoShow" />
    </div>
  </div>

  <div class="row g-3">
    {{-- Trend chart --}}
    <div class="col-xl-8">
      <x-card bodyClass="pt-1">
        <x-slot:toolbar>
          <div class="btn-group sas-chart-toggle" role="group" aria-label="Chart type">
            <button type="button" class="btn btn-outline-secondary active" data-chart-type="area">Area</button>
            <button type="button" class="btn btn-outline-secondary" data-chart-type="bar">Bars</button>
          </div>
        </x-slot:toolbar>
        <x-slot:title>Appointments — last 14 days</x-slot:title>
        <x-slot:subtitle>Booked vs. completed vs. no-show</x-slot:subtitle>
        <div id="apptTrendChart"></div>
      </x-card>
    </div>

    {{-- Status breakdown donut --}}
    <div class="col-xl-4">
      <x-card title="Status breakdown">
        @if (array_sum($statusBreakdown) > 0)
          <div id="statusDonut"></div>
        @else
          <p class="text-muted mb-0">No data yet.</p>
        @endif
      </x-card>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- Fill rate gauge --}}
    <div class="col-xl-6">
      <x-card title="Fill rate" subtitle="Last 30 days">
        @if ($fillRate['available_minutes'] > 0)
          <div id="fillRateGauge"></div>
          <div class="text-muted small text-center">{{ number_format($fillRate['booked_minutes']) }} of {{ number_format($fillRate['available_minutes']) }} available minutes booked</div>
        @else
          <p class="text-muted mb-0">Set up provider working hours to see fill rate.</p>
        @endif
      </x-card>
    </div>

    {{-- Channel mix donut --}}
    <div class="col-xl-6">
      <x-card title="Booking channels" subtitle="Last 30 days">
        @if (array_sum($channelMix) > 0)
          <div id="channelMixDonut"></div>
        @else
          <p class="text-muted mb-0">No data yet.</p>
        @endif
      </x-card>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- Busiest hours --}}
    <div class="col-xl-8">
      <x-card title="Busiest hours" subtitle="Last 30 days" bodyClass="pt-1">
        <div id="busyHoursChart"></div>
      </x-card>
    </div>

    {{-- Completion gauge + top services --}}
    <div class="col-xl-4">
      <x-card title="Completion rate (30d)">
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
      </x-card>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- Today's appointments --}}
    <div class="col-xl-8">
      <x-card title="Today's schedule" bodyClass="p-0">
        <x-slot:toolbar>
          <a href="{{ route('calendar') }}" class="btn btn-sm btn-light">Open calendar</a>
        </x-slot:toolbar>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Time</th><th>Patient</th><th>Provider</th><th>Service</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($todaysAppointments as $a)
                <tr onclick="window.location='{{ route('appointments.show', $a) }}'" style="cursor:pointer">
                  <td class="fw-semibold">{{ $a->start_at->format('g:i A') }}</td>
                  <td>{{ $a->patient->name }}</td>
                  <td>{{ $a->provider->name }}</td>
                  <td>{{ $a->service->name ?? '—' }}</td>
                  <td><x-badge-status :color="$a->status_color" :label="$a->status_label" /></td>
                </tr>
              @empty
                <x-empty-state colspan="5" icon="fi-rr-calendar-clock" title="No appointments today" />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>

    {{-- High-risk --}}
    <div class="col-xl-4">
      <x-card>
        <x-slot:title><i class="fi fi-rr-exclamation text-danger me-1"></i> High no-show risk</x-slot:title>
        @forelse ($highRisk as $a)
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <div class="fw-semibold">{{ $a->patient->name }}</div>
              <small class="text-muted">{{ $a->start_at->format('M j, g:i A') }} · {{ $a->provider->name }}</small>
            </div>
            <span class="badge bg-danger">{{ $a->no_show_score }}%</span>
          </div>
        @empty
          <x-empty-state icon="fi-rr-shield-check" title="No high-risk appointments" description="Nice and steady." />
        @endforelse
      </x-card>
    </div>
  </div>

  {{-- Missed appointments (past + not completed) --}}
  <div class="row g-3 mt-1">
    <div class="col-12">
      <x-card bodyClass="p-0">
        <x-slot:title><i class="fi fi-rr-calendar-xmark text-danger me-1"></i> Missed appointments
          @if ($missedCount)<span class="badge bg-danger ms-1">{{ $missedCount }}</span>@endif
        </x-slot:title>
        <x-slot:toolbar>
          <a href="{{ route('appointments.index', ['status' => \App\Models\Appointment::STATUS_NO_SHOW]) }}" class="btn btn-sm btn-light">View all</a>
        </x-slot:toolbar>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>When</th><th>Patient</th><th>Provider</th><th>Service</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($missedAppointments as $a)
                <tr onclick="window.location='{{ route('appointments.show', $a) }}'" style="cursor:pointer">
                  <td class="text-nowrap">{{ $a->start_at->format('M j, Y g:i A') }}</td>
                  <td>{{ $a->patient->name ?? '—' }}</td>
                  <td>{{ $a->provider->name ?? '—' }}</td>
                  <td>{{ $a->service->name ?? '—' }}</td>
                  <td><x-badge-status :color="$a->status_color" :label="$a->status_label" /></td>
                </tr>
              @empty
                <x-empty-state colspan="5" icon="fi-rr-check-circle" title="No missed appointments" />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
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
        <div class="modal-content">
          <div class="modal-header border-0 text-white {{ $alert ? 'bg-danger' : '' }}"
               @if(!$alert) style="background:linear-gradient(120deg,var(--sas-primary-500),#7b78e0)" @endif>
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
                    <x-badge-status :color="$a->status_color" :label="$a->status_label" />
                  </li>
                @endforeach
              </ul>
              @if ($todaysAppointments->count() > 5)
                <small class="text-muted">+{{ $todaysAppointments->count() - 5 }} more on the schedule below.</small>
              @endif
            @endif
          </div>
          <div class="modal-footer">
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
      const purple = '#5955D1', green = '#17c653', red = '#f1416c';
      const labels = @json($chartLabels);
      const totals = @json($chartData);
      const completed = @json($chartCompleted);
      const noShow = @json($chartNoShow);

      // Animated stat counters + mini sparklines are handled once, globally,
      // in layouts/app.blade.php (any page with .sas-count/.sas-spark opts in
      // automatically — see that file for the shared implementation).

      // ---- Main trend chart (toggle area / bars) ----
      let trendType = 'area';
      function trendOptions(type) {
        return {
          chart: { type: type, height: 320, toolbar: { show: false }, fontFamily: 'Inter, sans-serif',
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
        const colorMap = { booked:'#f6b100', confirmed:'#7239ea', checked_in:'#5955D1', completed:'#17c653', cancelled:'#adb5bd', no_show:'#f1416c' };
        const keys = Object.keys(map);
        new ApexCharts(document.querySelector('#statusDonut'), {
          chart: { type: 'donut', height: 280, fontFamily: 'Inter, sans-serif' },
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
        chart: { type: 'radialBar', height: 230, fontFamily: 'Inter, sans-serif' },
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
          chart: { type: 'donut', height: 280, fontFamily: 'Inter, sans-serif' },
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
        chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
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
        chart: { type: 'radialBar', height: 230, fontFamily: 'Inter, sans-serif' },
        series: [{{ $completionRate }}],
        colors: ['#17c653'],
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
