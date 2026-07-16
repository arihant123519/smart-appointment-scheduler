@extends('layouts.app')

@section('title', 'Reports & Analytics')

@section('content')
  @if ($clinics->isNotEmpty())
    <form method="GET" class="mb-3" style="max-width:280px">
      <select name="clinic_id" class="form-select" onchange="this.form.submit()">
        <option value="">All clinics</option>
        @foreach ($clinics as $c)
          <option value="{{ $c->id }}" @selected($selectedClinicId == $c->id)>{{ $c->name }}</option>
        @endforeach
      </select>
    </form>
  @endif

  {{-- Natural-language report query (PRD §5.2) --}}
  <x-card bodyClass="pb-3" class="mb-3 border-primary-subtle">
    <label class="form-label mb-1 fw-semibold"><i class="fi fi-rr-search text-primary me-1"></i> Ask the data</label>
    <div class="input-group">
      <input type="text" id="askInput" class="form-control" placeholder='e.g. "which provider had the worst no-show rate last month?"'>
      <button class="btn btn-primary" id="askGo">Ask</button>
    </div>
    <div id="askResult" class="mt-2 small"></div>
  </x-card>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <x-stat-widget label="No-show rate (30d)" :value="$noShowRate.'%'" icon="fi-rr-chart-line-up" bg="bg-danger-subtle" fg="text-danger" />
    </div>
    <div class="col-md-4">
      <x-stat-widget label="No-shows (30d)" :value="$noShows" icon="fi-rr-calendar-xmark" bg="bg-warning-subtle" fg="text-warning" />
    </div>
    <div class="col-md-4">
      <x-stat-widget label="Completed + no-show (30d)" :value="$finished" icon="fi-rr-check-circle" bg="bg-info-subtle" fg="text-info" />
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-6">
      <x-card title="No-show rate by provider" bodyClass="p-0" class="h-100">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr><th>Provider</th><th>Total</th><th>No-shows</th><th>Rate</th></tr></thead>
            <tbody>
              @forelse ($byProvider as $row)
                @php $rc = $row['rate'] > 20 ? 'danger' : ($row['rate'] > 10 ? 'warning' : 'success'); @endphp
                <tr>
                  <td>{{ $row['name'] }}</td>
                  <td>{{ $row['total'] }}</td>
                  <td>{{ $row['no_shows'] }}</td>
                  <td><span class="badge badge-light-{{ $rc }}">{{ $row['rate'] }}%</span></td>
                </tr>
              @empty
                <x-empty-state colspan="4" icon="fi-rr-chart-line-up" title="No provider data yet" />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>
    <div class="col-xl-6">
      <x-card title="Booking channels" class="h-100">
        <div id="channelChart" class="sas-skeleton" style="min-height:300px"></div>
      </x-card>
    </div>
    <div class="col-12">
      <x-card title="Provider utilization (30d)">
        <div id="utilChart" class="sas-skeleton" style="min-height:320px"></div>
      </x-card>
    </div>
  </div>

  {{-- Conversion engine: WhatsApp flow performance + appointment funnel --}}
  <div class="row g-3 mt-1">
    <div class="col-12"><h6 class="text-muted mb-0 mt-2"><i class="fi fi-rr-arrows-split-up-and-left me-1"></i> Conversion engine (30d)</h6></div>
    <div class="col-md-2 col-6">
      <x-stat-widget label="Flows started" :value="$flow['started']" icon="fi-rr-comment-alt" bg="bg-primary-subtle" fg="text-primary" />
    </div>
    <div class="col-md-2 col-6">
      <x-stat-widget label="Completed" :value="$flow['completed']" icon="fi-rr-check-circle" bg="bg-success-subtle" fg="text-success" />
    </div>
    <div class="col-md-2 col-6">
      <x-stat-widget label="Timed out" :value="$flow['timed_out']" icon="fi-rr-clock" bg="bg-warning-subtle" fg="text-warning" />
    </div>
    <div class="col-md-2 col-6">
      <x-stat-widget label="Escalated to staff" :value="$flow['escalated']" icon="fi-rr-exclamation" bg="bg-danger-subtle" fg="text-danger" />
    </div>
    <div class="col-md-2 col-6">
      <x-stat-widget label="Appointments rescued" :value="$flow['rescued']" icon="fi-rr-shield-check" bg="bg-success-subtle" fg="text-success" />
    </div>
    <div class="col-md-2 col-6">
      <x-stat-widget label="Lost anyway" :value="$flow['lost']" icon="fi-rr-calendar-xmark" bg="bg-danger-subtle" fg="text-danger" />
    </div>

    <div class="col-12">
      <x-card title="Appointment funnel (30d)" subtitle="Booked → completed vs. lost">
        <div id="funnelChart" class="sas-skeleton" style="min-height:260px"></div>
      </x-card>
    </div>
  </div>

  {{-- Revenue & patient intelligence: flags, benchmarking, fine-grained utilization --}}
  <div class="row g-3 mt-1">
    <div class="col-12"><h6 class="text-muted mb-0 mt-2"><i class="fi fi-rr-usd-circle me-1"></i> Revenue &amp; patient intelligence</h6></div>

    <div class="col-xl-7">
      <x-card class="h-100">
        <x-slot:title>Flagged patterns (30d)</x-slot:title>
        <x-slot:toolbar>
          <span class="text-muted small">For review — nothing here changes automatically</span>
        </x-slot:toolbar>
        @forelse ($revenueLeaks as $flag)
          <div class="alert alert-warning-subtle border-warning-subtle mb-2">
            <div class="fw-semibold small">{{ $flag['title'] }}</div>
            <div class="text-muted small mb-0">{{ $flag['detail'] }}</div>
          </div>
        @empty
          <x-empty-state icon="fi-rr-shield-check" title="No notable patterns flagged this month" />
        @endforelse
      </x-card>
    </div>

    <div class="col-xl-5">
      <x-card title="How you compare to similar clinics (30d)" class="h-100">
        @if ($benchmark && $benchmark['available'])
          <div class="row text-center g-2">
            <div class="col-6">
              <div class="text-muted small">Your no-show rate</div>
              <div class="h4 fw-bold">{{ $benchmark['clinic']['no_show_rate'] }}%</div>
              <div class="text-muted small">vs. {{ $benchmark['others']['no_show_rate'] }}% average</div>
            </div>
            <div class="col-6">
              <div class="text-muted small">Your completion rate</div>
              <div class="h4 fw-bold">{{ $benchmark['clinic']['completion_rate'] }}%</div>
              <div class="text-muted small">vs. {{ $benchmark['others']['completion_rate'] }}% average</div>
            </div>
          </div>
        @else
          <p class="text-muted small mb-0">{{ $benchmark['reason'] ?? 'Benchmarking is unavailable right now.' }}</p>
        @endif
      </x-card>
    </div>

    <div class="col-12">
      <x-card title="Provider utilization by day &amp; hour (30d)">
        @if (empty($heatmap['providers']))
          <p class="text-muted small mb-0">No active providers to show.</p>
        @else
          <div class="table-responsive">
            <table class="table table-sm text-center align-middle mb-0" style="min-width: 900px;">
              <thead>
                <tr>
                  <th class="text-start">Provider</th>
                  @foreach ($heatmap['hours'] as $h)
                    <th class="small text-muted fw-normal">{{ \Carbon\Carbon::createFromTime($h)->format('gA') }}</th>
                  @endforeach
                </tr>
              </thead>
              <tbody>
                @foreach ($heatmap['providers'] as $providerId => $providerName)
                  <tr>
                    <td class="text-start small fw-semibold">{{ $providerName }}</td>
                    @foreach ($heatmap['hours'] as $h)
                      @php
                        $total = 0;
                        foreach (range(0, 6) as $dow) { $total += $heatmap['cells']["{$providerId}-{$dow}-{$h}"] ?? 0; }
                        $intensity = min(1, $total / 8);
                      @endphp
                      <td style="background-color: rgba(89, 85, 209, {{ $intensity }}); color: {{ $intensity > 0.5 ? '#fff' : 'inherit' }};" title="{{ $total }} appointment(s) over 30 days">
                        {{ $total > 0 ? $total : '' }}
                      </td>
                    @endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="text-muted small mt-2">Darker = busier. Summed across all weekdays in the last 30 days.</div>
        @endif
      </x-card>
    </div>

    @if (!empty($experiments))
      <div class="col-12">
        <x-card title="A/B tests">
          @foreach ($experiments as $exp)
            <div class="mb-3 {{ !$loop->last ? 'pb-3 border-bottom' : '' }}">
              <div class="fw-semibold small mb-2">{{ $exp['experiment']->name }}</div>
              <div class="row g-2">
                @foreach ($exp['variants'] as $v)
                  <div class="col-md-3 col-6">
                    <div class="border rounded-3 p-2">
                      <div class="text-muted small text-uppercase">{{ $v['variant'] }}</div>
                      <div class="h5 fw-bold mb-0">{{ $v['rate'] }}%</div>
                      <div class="text-muted small">{{ $v['converted'] }}/{{ $v['assigned'] }} booked</div>
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
          <div class="text-muted small mb-0">Never switches automatically — review the numbers and adopt a winner yourself.</div>
        </x-card>
      </div>
    @endif
  </div>
@endsection

@push('scripts')
  <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
  <script>
    document.getElementById('askGo').addEventListener('click', function () {
      const q = document.getElementById('askInput').value.trim();
      const box = document.getElementById('askResult');
      if (!q) return;
      box.innerHTML = '<span class="text-muted">Analyzing…</span>';
      fetch('{{ route('reports.ask') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ question: q }),
      }).then(r => r.json())
        .then(d => box.innerHTML = '<div class="alert alert-light border mb-0">' + (d.answer || '').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])) + '</div>')
        .catch(() => box.innerHTML = '<span class="text-danger">Could not analyze. Try again.</span>');
    });
  </script>
  <script>
    // Each chart container carries .sas-skeleton (shimmer placeholder) until
    // ApexCharts finishes its first render, matching the loading-state
    // pattern used elsewhere in the design system.
    function sasUnskeleton(selector) {
      const el = document.querySelector(selector);
      if (el) el.classList.remove('sas-skeleton');
    }

    new ApexCharts(document.querySelector('#channelChart'), {
      chart: { type: 'donut', height: 300, fontFamily: 'Inter, sans-serif' },
      series: @json(array_values($byChannel)),
      labels: @json(array_map('ucfirst', array_keys($byChannel))),
      colors: ['#5955D1', '#17c653', '#7239ea', '#f6b100', '#f1416c'],
      legend: { position: 'bottom' },
    }).render().then(() => sasUnskeleton('#channelChart'));

    new ApexCharts(document.querySelector('#utilChart'), {
      chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
      series: [{ name: 'Appointments', data: @json($utilization->pluck('count')) }],
      xaxis: { categories: @json($utilization->pluck('name')) },
      colors: ['#5955D1'],
      plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
      dataLabels: { enabled: false },
      grid: { borderColor: '#eef0f4', strokeDashArray: 4 },
    }).render().then(() => sasUnskeleton('#utilChart'));

    new ApexCharts(document.querySelector('#funnelChart'), {
      chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
      series: [{ name: 'Appointments', data: @json(array_values($funnel)) }],
      xaxis: { categories: @json(array_keys($funnel)) },
      plotOptions: { bar: { horizontal: true, borderRadius: 6, distributed: true } },
      legend: { show: false },
      colors: ['#5955D1', '#7239ea', '#9b7be0', '#17c653', '#9aa1ad', '#f1416c'],
      dataLabels: { enabled: true },
      grid: { borderColor: '#eef0f4', strokeDashArray: 4 },
    }).render().then(() => sasUnskeleton('#funnelChart'));
  </script>
@endpush
