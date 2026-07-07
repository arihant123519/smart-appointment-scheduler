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
