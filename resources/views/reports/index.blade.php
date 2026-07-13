@extends('layouts.app')

@section('title', 'Reports & Analytics')

@section('content')
  {{-- Natural-language report query (PRD §5.2) --}}
  <div class="card mb-3 border-primary-subtle">
    <div class="card-body">
      <label class="form-label mb-1 fw-semibold">🔎 Ask the data</label>
      <div class="input-group">
        <input type="text" id="askInput" class="form-control" placeholder='e.g. "which provider had the worst no-show rate last month?"'>
        <button class="btn btn-primary" id="askGo">Ask</button>
      </div>
      <div id="askResult" class="mt-2 small"></div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body text-center">
      <div class="text-muted small">No-show rate (30d)</div>
      <div class="display-5 fw-bold text-danger">{{ $noShowRate }}%</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center">
      <div class="text-muted small">No-shows (30d)</div>
      <div class="display-5 fw-bold">{{ $noShows }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body text-center">
      <div class="text-muted small">Completed + no-show (30d)</div>
      <div class="display-5 fw-bold">{{ $finished }}</div>
    </div></div></div>
  </div>

  <div class="row g-3">
    <div class="col-xl-6">
      <div class="card h-100">
        <div class="card-header"><h6 class="mb-0">No-show rate by provider</h6></div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <thead class="table-light"><tr><th>Provider</th><th>Total</th><th>No-shows</th><th>Rate</th></tr></thead>
            <tbody>
              @foreach ($byProvider as $row)
                @php $rc = $row['rate'] > 20 ? 'danger' : ($row['rate'] > 10 ? 'warning' : 'success'); @endphp
                <tr><td>{{ $row['name'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['no_shows'] }}</td>
                  <td><span class="badge bg-{{ $rc }}-subtle text-{{ $rc }} fw-semibold">{{ $row['rate'] }}%</span></td></tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="card h-100">
        <div class="card-header"><h6 class="mb-0">Booking channels</h6></div>
        <div class="card-body"><div id="channelChart"></div></div>
      </div>
    </div>
    <div class="col-12">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Provider utilization (30d)</h6></div>
        <div class="card-body"><div id="utilChart"></div></div>
      </div>
    </div>
  </div>

  {{-- Conversion engine: WhatsApp flow performance + appointment funnel --}}
  <div class="row g-3 mt-1">
    <div class="col-12"><h6 class="text-muted mb-0 mt-2">🔁 Conversion engine (30d)</h6></div>
    <div class="col-md-2 col-6"><div class="card"><div class="card-body text-center">
      <div class="text-muted small">Flows started</div>
      <div class="fs-3 fw-bold">{{ $flow['started'] }}</div>
    </div></div></div>
    <div class="col-md-2 col-6"><div class="card"><div class="card-body text-center">
      <div class="text-muted small">Completed</div>
      <div class="fs-3 fw-bold text-success">{{ $flow['completed'] }}</div>
    </div></div></div>
    <div class="col-md-2 col-6"><div class="card"><div class="card-body text-center">
      <div class="text-muted small">Timed out</div>
      <div class="fs-3 fw-bold text-warning">{{ $flow['timed_out'] }}</div>
    </div></div></div>
    <div class="col-md-2 col-6"><div class="card"><div class="card-body text-center">
      <div class="text-muted small">Escalated to staff</div>
      <div class="fs-3 fw-bold text-danger">{{ $flow['escalated'] }}</div>
    </div></div></div>
    <div class="col-md-2 col-6"><div class="card"><div class="card-body text-center">
      <div class="text-muted small">Appointments rescued</div>
      <div class="fs-3 fw-bold text-success">{{ $flow['rescued'] }}</div>
    </div></div></div>
    <div class="col-md-2 col-6"><div class="card"><div class="card-body text-center">
      <div class="text-muted small">Lost anyway</div>
      <div class="fs-3 fw-bold text-danger">{{ $flow['lost'] }}</div>
    </div></div></div>

    <div class="col-12">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Appointment funnel (30d) — booked → completed vs. lost</h6></div>
        <div class="card-body"><div id="funnelChart"></div></div>
      </div>
    </div>
  </div>

  {{-- Revenue & patient intelligence: flags, benchmarking, fine-grained utilization --}}
  <div class="row g-3 mt-1">
    <div class="col-12"><h6 class="text-muted mb-0 mt-2">💡 Revenue &amp; patient intelligence</h6></div>

    <div class="col-xl-7">
      <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Flagged patterns (30d)</h6>
          <span class="text-muted small">For review — nothing here changes automatically</span>
        </div>
        <div class="card-body">
          @forelse ($revenueLeaks as $flag)
            <div class="alert alert-warning-subtle border-warning-subtle mb-2">
              <div class="fw-semibold small">{{ $flag['title'] }}</div>
              <div class="text-muted small mb-0">{{ $flag['detail'] }}</div>
            </div>
          @empty
            <p class="text-muted small mb-0">No notable patterns flagged this month.</p>
          @endforelse
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card h-100">
        <div class="card-header"><h6 class="mb-0">How you compare to similar clinics (30d)</h6></div>
        <div class="card-body">
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
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Provider utilization by day &amp; hour (30d)</h6></div>
        <div class="card-body">
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
        </div>
      </div>
    </div>

    @if (!empty($experiments))
      <div class="col-12">
        <div class="card">
          <div class="card-header"><h6 class="mb-0">A/B tests</h6></div>
          <div class="card-body">
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
          </div>
        </div>
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
    new ApexCharts(document.querySelector('#channelChart'), {
      chart: { type: 'donut', height: 300, fontFamily: 'Instrument Sans' },
      series: @json(array_values($byChannel)),
      labels: @json(array_map('ucfirst', array_keys($byChannel))),
      colors: ['#5955D1', '#22C55E', '#0EA5E9', '#F59E0B', '#EF4444'],
      legend: { position: 'bottom' },
    }).render();

    new ApexCharts(document.querySelector('#utilChart'), {
      chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'Instrument Sans' },
      series: [{ name: 'Appointments', data: @json($utilization->pluck('count')) }],
      xaxis: { categories: @json($utilization->pluck('name')) },
      colors: ['#5955D1'],
      plotOptions: { bar: { borderRadius: 6, columnWidth: '45%' } },
      dataLabels: { enabled: false },
    }).render();

    new ApexCharts(document.querySelector('#funnelChart'), {
      chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Instrument Sans' },
      series: [{ name: 'Appointments', data: @json(array_values($funnel)) }],
      xaxis: { categories: @json(array_keys($funnel)) },
      plotOptions: { bar: { horizontal: true, borderRadius: 6, distributed: true } },
      legend: { show: false },
      colors: ['#5955D1', '#0EA5E9', '#8B5CF6', '#22C55E', '#94A3B8', '#EF4444'],
      dataLabels: { enabled: true },
    }).render();
  </script>
@endpush
