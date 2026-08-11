@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
  <style>
    .sas-chmix-legend-row { display: flex; align-items: center; gap: .55rem; padding: .3rem 0; font-size: var(--sas-fs-sm); }
    .sas-chmix-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .sas-chmix-pct { font-weight: 700; color: var(--sas-gray-900); }
    .sas-tsvc-icon { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .9rem; }
    .sas-tsvc-row-border { border-bottom: 1px solid var(--sas-gray-100); }
  </style>
@endpush

@section('content')
  @php
    // Honest, presentation-only deltas derived from the 14-day series already
    // sent to this view (no new queries, no controller changes) — last 7 days
    // vs. the 7 days before that, so the no-show KPI shows real momentum
    // instead of a static number.
    $recentTotal = array_sum(array_slice($chartData, 7, 7));
    $recentNoShow = array_sum(array_slice($chartNoShow, 7, 7));
    $prevTotal = array_sum(array_slice($chartData, 0, 7));
    $prevNoShow = array_sum(array_slice($chartNoShow, 0, 7));
    $recentNoShowRate = $recentTotal > 0 ? round($recentNoShow / $recentTotal * 100, 1) : null;
    $prevNoShowRate = $prevTotal > 0 ? round($prevNoShow / $prevTotal * 100, 1) : null;
    $noShowDelta = ($recentNoShowRate !== null && $prevNoShowRate !== null) ? round($recentNoShowRate - $prevNoShowRate, 1) : null;

    // Channel mix, sorted largest-first with real percentages, for the
    // donut + side legend on the "Booking channels" card below.
    $chMixLabels = ['web' => 'Website', 'app' => 'App', 'phone' => 'Phone', 'walk_in' => 'Walk-in', 'ai' => 'AI Assistant', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'qr' => 'QR Code'];
    $chMixPalette = ['#2563EB', '#17c653', '#f59e0b', '#7239ea', '#f1416c', '#06b6d4', '#94a3b8'];
    $chMixTotal = array_sum($channelMix);
    $chMixRows = collect($channelMix)->sortDesc()->map(function ($count, $key) use ($chMixTotal, $chMixLabels) {
        return [
          'label' => $chMixLabels[$key] ?? ucfirst(str_replace('_', ' ', $key)),
          'count' => $count,
          'pct' => $chMixTotal > 0 ? round($count / $chMixTotal * 100, 1) : 0,
        ];
      })->values();

    // Status breakdown, sorted largest-first with real percentages, for the
    // donut + side legend on the "Status breakdown" card below.
    $statusColors = ['booked' => '#2563EB', 'confirmed' => '#7239ea', 'checked_in' => '#06b6d4', 'completed' => '#17c653', 'cancelled' => '#f59e0b', 'no_show' => '#f1416c'];
    $statusTotal = array_sum($statusBreakdown);
    $statusRows = collect($statusBreakdown)->sortDesc()->map(function ($count, $key) use ($statusTotal, $statusColors) {
        return [
          'label' => \App\Models\Appointment::STATUSES[$key] ?? ucfirst(str_replace('_', ' ', $key)),
          'count' => $count,
          'pct' => $statusTotal > 0 ? round($count / $statusTotal * 100, 1) : 0,
          'color' => $statusColors[$key] ?? '#94a3b8',
        ];
      })->values();

    // Real "X of Y completed" caption for the completion-rate gauge below —
    // same 30-day window + same-provider scoping the controller already uses
    // to compute $completionRate, just the raw counts behind it (not passed
    // to the view, so re-derived here rather than touching the controller).
    $compUser = auth()->user();
    $compOnlyOwnProvider = $compUser->hasRole('provider') && ! $compUser->hasAnyRole(['clinic_admin', 'system_admin', 'front_desk']) && $compUser->provider;
    $compProviderId = $compOnlyOwnProvider ? $compUser->provider->id : null;
    $compWindow = now()->subDays(30);
    $completed30 = \App\Models\Appointment::where('start_at', '>=', $compWindow)
      ->where('status', \App\Models\Appointment::STATUS_COMPLETED)
      ->when($compOnlyOwnProvider, fn ($q) => $q->where('provider_id', $compProviderId))
      ->count();
    $terminal30 = \App\Models\Appointment::where('start_at', '>=', $compWindow)
      ->whereIn('status', [\App\Models\Appointment::STATUS_COMPLETED, \App\Models\Appointment::STATUS_NO_SHOW, \App\Models\Appointment::STATUS_CANCELLED])
      ->when($compOnlyOwnProvider, fn ($q) => $q->where('provider_id', $compProviderId))
      ->count();

    // Presentational icon/color per top service, matched by keyword — purely
    // cosmetic, doesn't touch what services or counts are shown.
    $svcIconFor = function (string $name) {
      $n = strtolower($name);
      return match (true) {
        str_contains($n, 'dental') || str_contains($n, 'tooth') => ['icon' => 'fi-rr-tooth', 'bg' => '#DBEAFE', 'fg' => '#2563EB'],
        str_contains($n, 'pediatric') || str_contains($n, 'child') => ['icon' => 'fi-rr-child', 'bg' => '#DCFCE7', 'fg' => '#17c653'],
        str_contains($n, 'therapy') || str_contains($n, 'counsel') => ['icon' => 'fi-rr-heart', 'bg' => '#FFEDD5', 'fg' => '#f59e0b'],
        str_contains($n, 'consult') => ['icon' => 'fi-rr-stethoscope', 'bg' => '#FEE2E2', 'fg' => '#f1416c'],
        default => ['icon' => 'fi-rr-briefcase', 'bg' => '#F1F5F9', 'fg' => '#64748b'],
      };
    };
  @endphp

  {{-- Today's appointments highlight --}}
  @if ($todaysAppointments->count())
    <div class="card sas-card-hero mb-4">
      <div class="card-body d-flex flex-wrap align-items-center gap-3 py-4">
        <div class="sas-stat__icon bg-white text-primary" style="width:56px;height:56px;font-size:1.6rem">
          <i class="fi fi-rr-calendar-clock"></i>
        </div>
        <div class="flex-grow-1">
          <h5 class="mb-1 text-white fw-bold">{{ $todaysAppointments->count() }} appointment{{ $todaysAppointments->count() === 1 ? '' : 's' }} today
            @if ($todaysMissed->count())
              <span class="badge bg-white text-danger ms-2">{{ $todaysMissed->count() }} missed</span>
            @endif
          </h5>
          <div class="d-flex flex-wrap gap-2 mt-2">
            @foreach ($todaysAppointments->take(6) as $a)
              <a href="{{ route('appointments.show', $a) }}" class="badge bg-white text-primary text-decoration-none p-2">
                {{ $a->start_at->format('g:i A') }} &middot; {{ $a->patient->name }}
              </a>
            @endforeach
            @if ($todaysAppointments->count() > 6)
              <span class="badge bg-white bg-opacity-75 text-primary p-2">+{{ $todaysAppointments->count() - 6 }} more</span>
            @endif
          </div>
        </div>
        <a href="{{ route('calendar') }}" class="btn btn-light fw-semibold">Open calendar</a>
      </div>
    </div>
  @endif

  {{-- Stat cards with mini sparklines --}}
  <div class="row g-3 mb-4">
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Today's Appointments" :value="$stats['today']" icon="fi-rr-calendar-clock"
        bg="bg-primary-subtle" fg="text-primary" sparkId="sparkToday" sparkColor="#2563EB" :sparkSeries="$chartData"
        :caption="$stats['today'] ? 'Scheduled for today' : 'No appointments today'" />
    </div>
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Upcoming" :value="$stats['upcoming']" icon="fi-rr-clock"
        bg="bg-info-subtle" fg="text-info" sparkId="sparkUpcoming" sparkColor="#7239ea" :sparkSeries="$chartCompleted"
        caption="All future appointments" />
    </div>
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="Patients" :value="$stats['patients']" icon="fi-rr-users"
        bg="bg-success-subtle" fg="text-success" sparkId="sparkPatients" sparkColor="#17c653" :sparkSeries="$chartData"
        caption="Total registered" />
    </div>
    <div class="col-xl-3 col-sm-6">
      <x-stat-widget label="No-show rate (30d)" :value="$noShowRate.'%'" icon="fi-rr-chart-line-up"
        bg="bg-danger-subtle" fg="text-danger" sparkId="sparkNoShow" sparkColor="#f1416c" :sparkSeries="$chartNoShow"
        :delta="$noShowDelta !== null ? abs($noShowDelta).'pp' : null" :deltaUp="$noShowDelta !== null && $noShowDelta >= 0"
        deltaLabel="vs previous 7 days" :caption="$noShowDelta === null ? 'Last 30 days' : null" />
    </div>
  </div>

  <div class="sas-section-label">Trends</div>

  <div class="row g-3">
    {{-- Trend chart --}}
    <div class="col-xl-6">
      <x-card bodyClass="pt-1">
        <x-slot:toolbar>
          <div class="btn-group sas-chart-toggle" role="group" aria-label="Chart type">
            <button type="button" class="btn btn-outline-secondary active" data-chart-type="area">Area</button>
            <button type="button" class="btn btn-outline-secondary" data-chart-type="bar">Bars</button>
          </div>
        </x-slot:toolbar>
        <x-slot:title>Appointments &mdash; last 14 days</x-slot:title>
        <x-slot:subtitle>Booked vs. completed vs. no-show</x-slot:subtitle>
        <div id="apptTrendChart" class="sas-chart-slot" style="--sas-chart-h:320px"></div>
      </x-card>
    </div>

    {{-- Status breakdown donut --}}
    <div class="col-xl-6">
      <x-card title="Status breakdown">
        @if ($statusTotal > 0)
          <div class="d-flex align-items-center gap-3 py-1">
            <div id="statusDonut" style="width:250px;height:250px;flex-shrink:0"></div>
            <div class="flex-grow-1 d-flex flex-column gap-1" style="min-width:0">
              @foreach ($statusRows as $row)
                <div class="sas-chmix-legend-row">
                  <span class="sas-chmix-dot" style="background:{{ $row['color'] }}"></span>
                  <span class="flex-grow-1 text-truncate">{{ $row['label'] }}</span>
                  <span class="sas-chmix-pct">{{ $row['count'] }} ({{ number_format($row['pct'], 1) }}%)</span>
                </div>
              @endforeach
            </div>
          </div>
        @else
          <x-empty-state icon="fi-rr-chart-pie-alt" title="No data yet" description="Status breakdown appears once appointments come in." />
        @endif
      </x-card>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- Fill rate gauge --}}
    <div class="col-xl-4">
      <x-card title="Fill rate" subtitle="Last 30 days">
        @if ($fillRate['available_minutes'] > 0)
          <div id="fillRateGauge" class="sas-chart-slot" style="--sas-chart-h:230px"></div>
          <div class="small text-center" style="font-size:1rem;"><span style="font-weight:800;font-size:1.75rem;">{{ number_format($fillRate['booked_minutes']) }} of {{ number_format($fillRate['available_minutes']) }}</span><br>available minutes booked</div>
        @else
          <x-empty-state icon="fi-rr-hourglass-end" title="No capacity data yet" description="Set up provider working hours to see fill rate." />
        @endif
      </x-card>
    </div>

    {{-- Channel mix donut --}}
    <div class="col-xl-5">
      <x-card title="Booking channels" subtitle="Last 30 days">
        @if ($chMixTotal > 0)
          <div class="d-flex align-items-center gap-4 py-1">
            <div id="channelMixDonut" style="width:250px;height:250px;flex-shrink:0"></div>
            <div class="flex-grow-1 d-flex flex-column gap-1" style="min-width:0">
              @foreach ($chMixRows as $i => $row)
                <div class="sas-chmix-legend-row">
                  <span class="sas-chmix-dot" style="background:{{ $chMixPalette[$i % count($chMixPalette)] }}"></span>
                  <span class="flex-grow-1 text-truncate">{{ $row['label'] }}</span>
                  <span class="sas-chmix-pct">{{ number_format($row['pct'], 1) }}%</span>
                </div>
              @endforeach
            </div>
          </div>
        @else
          <x-empty-state icon="fi-rr-share" title="No data yet" description="Channel mix appears once bookings come in." />
        @endif
      </x-card>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- Busiest hours --}}
    <div class="col-xl-6">
      <x-card title="Busiest hours" subtitle="Last 30 days" bodyClass="pt-1">
        <div id="busyHoursChart" class="sas-chart-slot" style="--sas-chart-h:260px"></div>
      </x-card>
    </div>

    {{-- Completion rate + Top services --}}
    <div class="col-xl-6">
      <div class="row g-3 h-100">
        <div class="col-md-6">
          <x-card title="Completion rate" subtitle="Last 30 days" class="h-100">
            @if ($terminal30 > 0)
              <div id="completionGauge" class="sas-chart-slot" style="--sas-chart-h:150px"></div>
              <div class="text-center small text-dark" style="font-size:14px;">{{ $completed30 }} of {{ $terminal30 }} appointment{{ $terminal30 === 1 ? '' : 's' }} completed</div>
            @else
              <x-empty-state icon="fi-rr-chart-pie-alt" title="No data yet" description="Completion rate appears once appointments are finished." />
            @endif
          </x-card>
        </div>
        <div class="col-md-6">
          <x-card title="Top services" class="h-100">
            @forelse ($topServices as $name => $count)
              @php $meta = $svcIconFor($name); @endphp
              <div class="d-flex align-items-center gap-2 py-2 {{ ! $loop->last ? 'sas-tsvc-row-border' : '' }}">
                <div class="sas-tsvc-icon" style="background:{{ $meta['bg'] }};color:{{ $meta['fg'] }}"><i class="fi {{ $meta['icon'] }}"></i></div>
                <div class="flex-grow-1 text-dark small fw-semibold" style="font-size:14px;">{{ $name }}</div>
                <div class="fw-bold">{{ $count }}</div>
              </div>
            @empty
              <p class="text-muted small mb-0">No service data yet.</p>
            @endforelse
            @if (count($topServices))
              <a href="{{ route('services.index') }}" class="d-block text-end small fw-semibold text-decoration-none mt-2">View all services <i class="fi fi-rr-arrow-right"></i></a>
            @endif
          </x-card>
        </div>
      </div>
    </div>
  </div>

  <div class="sas-section-label">Today &amp; risk</div>

  <div class="row g-3">
    {{-- Today's appointments --}}
    <div class="col-xl-8">
      <x-card title="Today's schedule" bodyClass="p-0">
        <x-slot:toolbar>
          <a href="{{ route('calendar') }}" class="btn btn-sm btn-light-primary fw-semibold">Open calendar</a>
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
                <x-empty-state colspan="5" icon="fi-rr-calendar-clock" title="No appointments today" description="Enjoy your day!" />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>

    {{-- High-risk --}}
    <div class="col-xl-4">
      <x-card>
        <x-slot:title>
          <span class="d-inline-flex align-items-center gap-2">
            <span class="sas-icon-tile bg-danger-subtle text-danger" style="width:28px;height:28px;font-size:.85rem"><i class="fi fi-rr-shield-exclamation"></i></span>
            High no-show risk
          </span>
        </x-slot:title>
        @forelse ($highRisk as $a)
          <a href="{{ route('appointments.show', $a) }}" class="sas-row-link text-decoration-none text-body mb-1">
            <span class="sas-icon-tile bg-danger-subtle text-danger"><i class="fi fi-rr-exclamation"></i></span>
            <div class="flex-grow-1" style="min-width:0">
              <div class="fw-semibold text-truncate">{{ $a->patient->name }}</div>
              <small class="text-muted">{{ $a->start_at->format('M j, g:i A') }} &middot; {{ $a->provider->name }}</small>
            </div>
            <span class="badge badge-light-danger">{{ $a->no_show_score }}%</span>
          </a>
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
        <x-slot:title>
          <span class="d-inline-flex align-items-center gap-2">
            <span class="sas-icon-tile bg-danger-subtle text-danger" style="width:28px;height:28px;font-size:.85rem"><i class="fi fi-rr-calendar-xmark"></i></span>
            Missed appointments
            @if ($missedCount)<span class="badge badge-light-danger">{{ $missedCount }}</span>@endif
          </span>
        </x-slot:title>
        <x-slot:toolbar>
          <a href="{{ route('appointments.index', ['status' => \App\Models\Appointment::STATUS_NO_SHOW]) }}" class="btn btn-sm btn-light-primary fw-semibold">View all</a>
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
               @if(!$alert) style="background:var(--sas-gradient-brand)" @endif>
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
                    <span><strong>{{ $a->start_at->format('g:i A') }}</strong> &middot; {{ $a->patient->name ?? '—' }}</span>
                    <a href="{{ route('appointments.show', $a) }}" class="btn btn-sm btn-outline-danger">Follow up</a>
                  </li>
                @endforeach
              </ul>
            @endif

            @php $todaysScheduled = $todaysAppointments->whereNotIn('status', [\App\Models\Appointment::STATUS_NO_SHOW]); @endphp
            @if ($todaysScheduled->count())
              <p class="text-muted mb-2">
                You have <strong>{{ $todaysScheduled->count() }}</strong> appointment{{ $todaysScheduled->count() === 1 ? '' : 's' }} scheduled today.
              </p>
              <ul class="list-group list-group-flush">
                @foreach ($todaysScheduled->take(5) as $a)
                  <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span><strong>{{ $a->start_at->format('g:i A') }}</strong> &middot; {{ $a->patient->name }}</span>
                    <x-badge-status :color="$a->status_color" :label="$a->status_label" />
                  </li>
                @endforeach
              </ul>
              @if ($todaysScheduled->count() > 5)
                <small class="text-muted">+{{ $todaysScheduled->count() - 5 }} more on the schedule below.</small>
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
      const brand = '#2563EB', brandLight = '#60A5FA', green = '#17c653', red = '#f1416c';
      const labels = @json($chartLabels);
      const totals = @json($chartData);
      const completed = @json($chartCompleted);
      const noShow = @json($chartNoShow);
      const gridStyle = { borderColor: '#eef1f6', strokeDashArray: 4 };
      const fontFamily = 'Inter, sans-serif';

      // Animated stat counters + mini sparklines are handled once, globally,
      // in layouts/app.blade.php (any page with .sas-count/.sas-spark opts in
      // automatically — see that file for the shared implementation).

      // ---- Main trend chart (toggle area / bars) ----
      let trendType = 'area';
      function trendOptions(type) {
        return {
          chart: { type: type, height: 320, toolbar: { show: false }, fontFamily,
                   animations: { enabled: true, easing: 'easeinout', speed: 500 } },
          series: [
            { name: 'Booked', data: totals },
            { name: 'Completed', data: completed },
            { name: 'No-show', data: noShow },
          ],
          xaxis: { categories: labels, axisBorder: { show: false }, axisTicks: { show: false },
                    labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
          yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
          colors: [brand, green, red],
          dataLabels: { enabled: false },
          stroke: { curve: 'smooth', width: type === 'area' ? 2.5 : 0 },
          fill: type === 'area'
            ? { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.28, opacityTo: 0.02 } }
            : { type: 'solid', opacity: 0.9 },
          plotOptions: { bar: { columnWidth: '55%', borderRadius: 5 } },
          legend: { position: 'top', horizontalAlign: 'right', fontSize: '12.5px', fontWeight: 600, markers: { size: 6 } },
          grid: gridStyle,
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

      // ---- Status donut (legend rendered server-side, next to it) ----
      @if ($statusTotal > 0)
      (function () {
        const rows = @json($statusRows);
        new ApexCharts(document.querySelector('#statusDonut'), {
          chart: { type: 'donut', height: 250, fontFamily },
          series: rows.map(r => r.count),
          labels: rows.map(r => r.label),
          colors: rows.map(r => r.color),
          legend: { show: false },
          dataLabels: { enabled: false },
          plotOptions: { pie: { donut: { size: '50%', labels: { show: true,
            value: { fontSize: '20px', fontWeight: 700, color: '#0f172a' },
            total: { show: true, label: 'Total', fontSize: '14px', color: '#64748b',
              formatter: (w) => w.globals.seriesTotals.reduce((a, b) => a + b, 0) } } } } },
          stroke: { width: 3, colors: ['#fff'] },
          tooltip: { y: { formatter: (v, opts) => v + ' (' + rows[opts.seriesIndex].pct + '%)' } },
        }).render();
      })();
      @endif

      // ---- Fill rate gauge ----
      @if ($fillRate['available_minutes'] > 0)
      new ApexCharts(document.querySelector('#fillRateGauge'), {
        chart: { type: 'radialBar', height: 230, fontFamily },
        series: [{{ $fillRate['rate'] }}],
        colors: [brand],
        plotOptions: { radialBar: {
          hollow: { size: '50%' },
          track: { background: '#eef1f6', strokeWidth: '100%' },
          dataLabels: { name: { offsetY: 22, color: '#94a3b8', fontSize: '12.5px', fontWeight: 600 },
            value: { offsetY: -12, fontSize: '30px', fontWeight: 800, color: '#0f172a', formatter: (v) => v + '%' } } } },
        fill: { type: 'gradient', gradient: { shade: 'light', type: 'horizontal', gradientToColors: [brandLight], stops: [0, 100] } },
        labels: ['Filled'],
        stroke: { lineCap: 'round' },
      }).render();
      @endif

      // ---- Channel mix donut (legend rendered server-side, next to it) ----
      @if ($chMixTotal > 0)
      (function () {
        const rows = @json($chMixRows);
        const palette = @json($chMixPalette);
        new ApexCharts(document.querySelector('#channelMixDonut'), {
          chart: { type: 'donut', height: 250, fontFamily },
          series: rows.map(r => r.count),
          labels: rows.map(r => r.label),
          colors: rows.map((r, i) => palette[i % palette.length]),
          legend: { show: false },
          dataLabels: { enabled: false },
          plotOptions: { pie: { donut: { size: '50%', labels: { show: false } } } },
          stroke: { width: 3, colors: ['#fff'] },
          tooltip: { y: { formatter: (v, opts) => v + ' booking' + (v === 1 ? '' : 's') + ' (' + rows[opts.seriesIndex].pct + '%)' } },
        }).render();
      })();
      @endif

      // ---- Busiest hours bar ----
      new ApexCharts(document.querySelector('#busyHoursChart'), {
        chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily },
        series: [{ name: 'Appointments', data: @json($hourData) }],
        xaxis: { categories: @json($hourLabels), axisBorder: { show: false }, axisTicks: { show: false },
                  labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
        yaxis: { labels: { style: { colors: '#94a3b8', fontSize: '11px' } } },
        colors: [brand],
        dataLabels: { enabled: false },
        plotOptions: { bar: { columnWidth: '52%', borderRadius: 6, borderRadiusApplication: 'end' } },
        grid: gridStyle,
        tooltip: { y: { formatter: (v) => v + ' appt' + (v === 1 ? '' : 's') } },
      }).render();

      // ---- Completion gauge (half-circle arc) ----
      @if ($terminal30 > 0)
      new ApexCharts(document.querySelector('#completionGauge'), {
        chart: { type: 'radialBar', height: 300, width: '100%', fontFamily },
        series: [{{ $completionRate }}],
        colors: [green],
        plotOptions: { radialBar: {
          startAngle: -90, endAngle: 90,
          hollow: { size: '50%' },
          track: { background: '#eef1f6', strokeWidth: '100%' },
          dataLabels: { name: { show: false },
            value: { offsetY: -4, fontSize: '24px', fontWeight: 800, color: '#0f172a', formatter: (v) => v + '%' } } } },
        fill: { type: 'gradient', gradient: { shade: 'light', type: 'horizontal', gradientToColors: ['#5fd39a'], stops: [0, 100] } },
        labels: ['Completed'],
        stroke: { lineCap: 'round' },
      }).render();
      @endif
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
