@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
  {{-- Today's appointments highlight --}}
  @if ($todaysAppointments->count())
    <div class="card border-0 mb-4" style="background:linear-gradient(135deg,#5955D1,#7b78e0);color:#fff">
      <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="sas-stat__icon bg-white text-primary"><i class="fi fi-rr-calendar-clock"></i></div>
        <div class="flex-grow-1">
          <h5 class="mb-1 text-white">{{ $todaysAppointments->count() }} appointment{{ $todaysAppointments->count() === 1 ? '' : 's' }} today</h5>
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

  {{-- Stat cards --}}
  <div class="row g-3 mb-4">
    @php
      $cards = [
        ['label' => "Today's Appointments", 'value' => $stats['today'], 'icon' => 'fi-rr-calendar-clock', 'bg' => 'bg-primary-subtle', 'fg' => 'text-primary'],
        ['label' => 'Upcoming', 'value' => $stats['upcoming'], 'icon' => 'fi-rr-clock', 'bg' => 'bg-info-subtle', 'fg' => 'text-info'],
        ['label' => 'Patients', 'value' => $stats['patients'], 'icon' => 'fi-rr-users', 'bg' => 'bg-success-subtle', 'fg' => 'text-success'],
        ['label' => 'No-show rate (30d)', 'value' => $noShowRate.'%', 'icon' => 'fi-rr-chart-line-up', 'bg' => 'bg-danger-subtle', 'fg' => 'text-danger'],
      ];
    @endphp
    @foreach ($cards as $card)
      <div class="col-xl-3 col-sm-6">
        <div class="card h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="sas-stat__icon {{ $card['bg'] }} {{ $card['fg'] }}">
              <i class="fi {{ $card['icon'] }}"></i>
            </div>
            <div>
              <div class="text-muted small">{{ $card['label'] }}</div>
              <div class="h3 mb-0 fw-bold">{{ $card['value'] }}</div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="row g-3">
    {{-- Trend chart --}}
    <div class="col-xl-8">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Appointments — last 14 days</h6>
        </div>
        <div class="card-body">
          <div id="apptTrendChart"></div>
        </div>
      </div>
    </div>

    {{-- Status breakdown --}}
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header"><h6 class="mb-0">Status breakdown</h6></div>
        <div class="card-body">
          @forelse ($statusBreakdown as $status => $count)
            @php $label = \App\Models\Appointment::STATUSES[$status] ?? ucfirst($status); @endphp
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span>{{ $label }}</span>
              <span class="badge bg-light text-dark">{{ $count }}</span>
            </div>
          @empty
            <p class="text-muted mb-0">No data yet.</p>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-1">
    {{-- Today's appointments --}}
    <div class="col-xl-8">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
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
      <div class="card h-100">
        <div class="card-header"><h6 class="mb-0"><i class="fi fi-rr-exclamation text-danger me-1"></i> High no-show risk</h6></div>
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
@endsection

@push('scripts')
  <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
  <script>
    new ApexCharts(document.querySelector('#apptTrendChart'), {
      chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'Instrument Sans, sans-serif' },
      series: [{ name: 'Appointments', data: @json($chartData) }],
      xaxis: { categories: @json($chartLabels) },
      colors: ['#5955D1'],
      dataLabels: { enabled: false },
      stroke: { curve: 'smooth', width: 3 },
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
      grid: { borderColor: '#eef0f4' },
    }).render();
  </script>
@endpush
