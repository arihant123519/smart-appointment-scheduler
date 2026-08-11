@extends('layouts.app')

@section('title', 'Billing & Payments')

@php
  // Read-only, presentational-only computation in the view — same pattern
  // as every other page in this pass. PaymentController::index() doesn't
  // accept a clinic filter at all (always scoped to the current user's own
  // clinic via forCurrentClinic()), so there's no clinic switcher here,
  // unlike Reports/Reviews.
  $scopeToClinic = fn ($q) => $q->whereHas('patient', fn ($qq) => $qq->forCurrentClinic());
  $billWindow = now()->subDays(30);
  $billPrevStart = now()->subDays(60);
  $billPrevEnd = now()->subDays(30);

  $billPrevCollected = (float) \App\Models\Payment::where('status', 'paid')->whereIn('type', ['copay', 'fee', 'deposit', 'no_show_fee'])
    ->tap($scopeToClinic)->whereBetween('paid_at', [$billPrevStart, $billPrevEnd])->sum('amount');
  $billPrevPending = (float) \App\Models\Payment::where('status', 'pending')->tap($scopeToClinic)
    ->whereBetween('created_at', [$billPrevStart, $billPrevEnd])->sum('amount');
  $billPrevRefunded = (float) \App\Models\Payment::where('type', 'refund')->tap($scopeToClinic)
    ->whereBetween('created_at', [$billPrevStart, $billPrevEnd])->sum('amount');

  $billPctDelta = fn ($now, $prev) => $prev > 0 ? round(($now - $prev) / $prev * 100, 1) : ($now > 0 ? 100.0 : 0.0);
  $billCollectedDelta = $billPctDelta((float) $totals['collected'], $billPrevCollected);
  $billPendingDelta = $billPctDelta((float) $totals['pending'], $billPrevPending);
  $billRefundedDelta = $billPctDelta((float) $totals['refunded'], $billPrevRefunded);

  // 7-day trailing series for the sparklines — genuine daily sums, not fabricated.
  $billTrailStart = now()->subDays(6)->startOfDay();
  $billTrailRows = \App\Models\Payment::tap($scopeToClinic)->where('created_at', '>=', $billTrailStart)
    ->selectRaw(
      "DATE(created_at) as d,
       SUM(CASE WHEN status = 'paid' AND type IN ('copay','fee','deposit','no_show_fee') THEN amount ELSE 0 END) as collected,
       SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending,
       SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END) as refunded"
    )->groupBy('d')->orderBy('d')->get()->keyBy('d');
  $billSpark = ['collected' => [], 'pending' => [], 'refunded' => []];
  foreach (range(0, 6) as $i) {
    $row = $billTrailRows->get(now()->subDays(6 - $i)->toDateString());
    $billSpark['collected'][] = (float) ($row->collected ?? 0);
    $billSpark['pending'][] = (float) ($row->pending ?? 0);
    $billSpark['refunded'][] = (float) ($row->refunded ?? 0);
  }

  // "Total volume" = collected + pending (money moved or expected — refunds
  // are money going back out, not new volume). This is the one definition
  // that actually reconciles collected/pending/refunded as independent
  // shares of a single denominator, each computed the same way below.
  $billVolume = (float) $totals['collected'] + (float) $totals['pending'];
  $billAvgTransaction = $payments->count() > 0 ? $billVolume / $payments->count() : 0;
  $billShareOfVolume = fn ($amount) => $billVolume > 0 ? round($amount / $billVolume * 100, 1) : 0;

  $billStatusColor = ['paid' => 'success', 'pending' => 'warning', 'forfeited' => 'danger', 'failed' => 'danger', 'abandoned' => 'secondary', 'refunded' => 'info'];
@endphp

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }
    .sas-bill-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-bill-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-bill-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-bill-kpi__caption { font-size: var(--sas-fs-xs); margin-top: .3rem; }
    .sas-bill-kpi__link { font-size: var(--sas-fs-xs); font-weight: 700; text-decoration: none; margin-top: .5rem; display: inline-block; }

    .sas-bill-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .85rem; padding: var(--sas-space-4) var(--sas-space-5); border-bottom: 1px solid var(--sas-gray-100); }
    .sas-bill-toolbar__length select { border-radius: var(--sas-radius-md); }
    .sas-bill-toolbar__search { margin-left: auto; }
    .sas-bill-toolbar__search input { border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); padding: .55rem .9rem; font-size: var(--sas-fs-sm); min-width: 220px; }
    .sas-bill-toolbar__search input:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    #transactionsTable_wrapper > .row:first-child { display: none; }
    #transactionsTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }
    #transactionsTable .btn-icon-square { width: 34px; height: 34px; border-radius: var(--sas-radius-md); border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); }
    #transactionsTable .btn-icon-square:hover { background: var(--sas-gray-50); }

    .sas-bill-revenue__legend-row { display: flex; align-items: center; gap: .5rem; padding: .4rem 0; }
    .sas-bill-revenue__dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .sas-bill-revenue__stat { text-align: center; }
    .sas-bill-revenue__stat-value { font-size: 1.35rem; font-weight: 800; color: var(--sas-gray-900); }
    .sas-bill-revenue__stat-label { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-bill-header__icon"><i class="fi fi-rr-usd-circle" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-bill-header__title mb-1">Billing &amp; Payments</h1>
        <p class="sas-bill-header__subtitle mb-0">Track deposits, payments, refunds and revenue across your clinic.</p>
      </div>
    </div>
    {{-- Payments are always charged from a specific appointment (there's no
         standalone "create payment" form) — this sends staff to where that
         real flow actually starts, rather than a form that doesn't exist. --}}
    <a href="{{ route('appointments.index') }}" class="btn btn-primary btn-lg"><i class="fi fi-rr-plus me-1" aria-hidden="true"></i> New Payment</a>
  </div>

  <div class="row g-3 mb-3 sas-stagger">
    {{-- The first three are hand-rolled, not <x-stat-widget>: the shared
         animated counter does (float) $value for its data-count-to, and
         (float) on a string starting with "₹" evaluates to 0 in PHP — the
         same class of bug already caught on the Walk-in Queue and Services
         pages. The markup below (including the .sas-spark div) matches the
         component's own output exactly, so the shared sparkline-render
         script in layouts/app.blade.php still picks it up. --}}
    <div class="col-md-3">
      <div class="card sas-card sas-card-hover h-100" style="position:relative;overflow:hidden">
        <div class="card-body d-flex align-items-start gap-3" style="position:relative;z-index:2">
          <div class="sas-stat__icon bg-success-subtle text-success"><i class="fi fi-rr-wallet" aria-hidden="true"></i></div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="text-muted small">Collected</div>
            <span class="h4 mb-0 fw-bold d-block">₹{{ number_format($totals['collected'], 2) }}</span>
          </div>
        </div>
        <div class="sas-spark" id="billSparkCollected" style="position:absolute;left:0;right:0;bottom:0;opacity:.5" data-series='@json($billSpark['collected'])' data-color="#22C55E"></div>
      </div>
      <div class="sas-bill-kpi__caption {{ $billCollectedDelta >= 0 ? 'text-success' : 'text-danger' }}">
        <i class="fi {{ $billCollectedDelta >= 0 ? 'fi-rr-arrow-small-up' : 'fi-rr-arrow-small-down' }}" aria-hidden="true"></i>
        {{ number_format(abs($billCollectedDelta), 1) }}% vs last 30 days
      </div>
    </div>
    <div class="col-md-3">
      <div class="card sas-card sas-card-hover h-100" style="position:relative;overflow:hidden">
        <div class="card-body d-flex align-items-start gap-3" style="position:relative;z-index:2">
          <div class="sas-stat__icon bg-warning-subtle text-warning"><i class="fi fi-rr-clock" aria-hidden="true"></i></div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="text-muted small">Pending</div>
            <span class="h4 mb-0 fw-bold d-block">₹{{ number_format($totals['pending'], 2) }}</span>
          </div>
        </div>
        <div class="sas-spark" id="billSparkPending" style="position:absolute;left:0;right:0;bottom:0;opacity:.5" data-series='@json($billSpark['pending'])' data-color="#F59E0B"></div>
      </div>
      <div class="sas-bill-kpi__caption {{ $billPendingDelta >= 0 ? 'text-warning' : 'text-success' }}">
        <i class="fi {{ $billPendingDelta >= 0 ? 'fi-rr-arrow-small-up' : 'fi-rr-arrow-small-down' }}" aria-hidden="true"></i>
        {{ number_format(abs($billPendingDelta), 1) }}% vs last 30 days
      </div>
    </div>
    <div class="col-md-3">
      <div class="card sas-card sas-card-hover h-100" style="position:relative;overflow:hidden">
        <div class="card-body d-flex align-items-start gap-3" style="position:relative;z-index:2">
          <div class="sas-stat__icon bg-danger-subtle text-danger"><i class="fi fi-rr-undo" aria-hidden="true"></i></div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="text-muted small">Refunded</div>
            <span class="h4 mb-0 fw-bold d-block">₹{{ number_format($totals['refunded'], 2) }}</span>
          </div>
        </div>
        <div class="sas-spark" id="billSparkRefunded" style="position:absolute;left:0;right:0;bottom:0;opacity:.5" data-series='@json($billSpark['refunded'])' data-color="#EF4444"></div>
      </div>
      <div class="sas-bill-kpi__caption {{ $billRefundedDelta <= 0 ? 'text-success' : 'text-danger' }}">
        <i class="fi {{ $billRefundedDelta >= 0 ? 'fi-rr-arrow-small-up' : 'fi-rr-arrow-small-down' }}" aria-hidden="true"></i>
        {{ number_format(abs($billRefundedDelta), 1) }}% vs last 30 days
      </div>
    </div>
    <div class="col-md-3">
      <x-stat-widget label="Pending deposits" :value="$pendingDeposits->count()" icon="fi-rr-file-invoice" bg="bg-info-subtle" fg="text-info" />
      <a href="#pendingDepositsCard" class="sas-bill-kpi__link">View all pending &rarr;</a>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-7" id="pendingDepositsCard">
      <x-card bodyClass="p-0" class="h-100">
        <x-slot:title>Pending deposits</x-slot:title>
        <x-slot:toolbar>
          <span class="badge badge-light-warning">{{ $pendingDeposits->count() }}</span>
        </x-slot:toolbar>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>Patient</th><th>Appointment</th><th>Amount</th><th>Expires</th><th></th></tr></thead>
            <tbody>
              @forelse ($pendingDeposits as $dep)
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <div class="sas-avatar sas-avatar-sm">{{ strtoupper(substr($dep->patient->name ?? '?', 0, 1)) }}</div>
                      <span class="fw-semibold">{{ $dep->patient->name ?? '—' }}</span>
                    </div>
                  </td>
                  <td>
                    @if ($dep->appointment)
                      <a href="{{ route('appointments.show', $dep->appointment) }}" class="text-decoration-none">{{ $dep->appointment->start_at->format('M j, g:i A') }}</a>
                      <div class="text-muted small">with {{ $dep->appointment->provider->name ?? '—' }}</div>
                    @else
                      —
                    @endif
                  </td>
                  <td>₹{{ number_format($dep->amount, 2) }}</td>
                  <td class="{{ $dep->expires_at && $dep->expires_at->isPast() ? 'text-danger fw-semibold' : 'text-muted' }} small">
                    {{ $dep->expires_at?->diffForHumans() ?? '—' }}
                  </td>
                  <td class="text-end">
                    <form method="POST" action="{{ route('payments.confirm-deposit', $dep) }}" data-sas-confirm="Confirm this deposit was collected?" data-sas-confirm-label="Confirm">
                      @csrf
                      <button class="btn btn-sm btn-success">Mark collected</button>
                    </form>
                  </td>
                </tr>
              @empty
                <x-empty-state colspan="5" icon="fi-rr-file-invoice" title="No pending deposits" description="Deposits awaiting collection will show up here." />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>
    <div class="col-xl-5">
      <x-card class="h-100">
        <x-slot:title>Revenue overview <span class="fw-normal text-muted small">(Last 30 days)</span></x-slot:title>
        @if ($billVolume > 0 || $totals['refunded'] > 0)
          <div class="row align-items-center g-3 mb-3">
            <div class="col-6">
              <div id="revenueChart" class="sas-skeleton" style="min-height:180px"></div>
            </div>
            <div class="col-6">
              <div class="sas-bill-revenue__legend-row"><span class="sas-bill-revenue__dot" style="background:#22C55E"></span><span class="flex-grow-1 small fw-semibold">Collected</span><span class="small">₹{{ number_format($totals['collected'], 2) }} ({{ $billShareOfVolume($totals['collected']) }}%)</span></div>
              <div class="sas-bill-revenue__legend-row"><span class="sas-bill-revenue__dot" style="background:#F59E0B"></span><span class="flex-grow-1 small fw-semibold">Pending</span><span class="small">₹{{ number_format($totals['pending'], 2) }} ({{ $billShareOfVolume($totals['pending']) }}%)</span></div>
              <div class="sas-bill-revenue__legend-row"><span class="sas-bill-revenue__dot" style="background:#EF4444"></span><span class="flex-grow-1 small fw-semibold">Refunded</span><span class="small">₹{{ number_format($totals['refunded'], 2) }} ({{ $billShareOfVolume($totals['refunded']) }}%)</span></div>
            </div>
          </div>
          <div class="row text-center g-2 pt-2 border-top">
            <div class="col-4 sas-bill-revenue__stat">
              <div class="sas-bill-revenue__stat-value">{{ $payments->count() }}</div>
              <div class="sas-bill-revenue__stat-label">Total transactions</div>
            </div>
            <div class="col-4 sas-bill-revenue__stat">
              <div class="sas-bill-revenue__stat-value">₹{{ number_format($billAvgTransaction, 2) }}</div>
              <div class="sas-bill-revenue__stat-label">Average transaction</div>
            </div>
            <div class="col-4 sas-bill-revenue__stat">
              <div class="sas-bill-revenue__stat-value">₹{{ number_format($billVolume, 2) }}</div>
              <div class="sas-bill-revenue__stat-label">Total volume</div>
            </div>
          </div>
        @else
          <p class="text-muted small mb-0">No revenue recorded yet.</p>
        @endif
      </x-card>
    </div>
  </div>

  <x-card bodyClass="p-0">
    <x-slot:title>Transactions</x-slot:title>
    <div class="sas-bill-toolbar">
      <span class="sas-bill-toolbar__length" id="transactionsLengthSlot"></span>
      <span class="sas-bill-toolbar__search" id="transactionsSearchSlot"></span>
    </div>
    <div class="table-responsive">
      <table id="transactionsTable" class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Patient</th><th>Type</th><th>Method</th><th>Amount</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
          @forelse ($payments as $pay)
            <tr>
              <td data-order="{{ $pay->created_at->timestamp }}">{{ $pay->created_at->format('M j, Y') }}</td>
              <td>{{ $pay->patient->name ?? '—' }}</td>
              <td>{{ ucfirst(str_replace('_', ' ', $pay->type)) }}</td>
              <td>{{ ucfirst($pay->method ?? '—') }}</td>
              <td data-order="{{ $pay->amount }}">₹{{ number_format($pay->amount, 2) }}</td>
              <td><x-badge-status :color="$billStatusColor[$pay->status] ?? 'secondary'" :label="ucfirst($pay->status)" /></td>
              <td class="text-end">
                @if ($pay->status === 'paid' && $pay->type !== 'refund')
                  <div class="dropdown sas-dropdown-actions">
                    <button type="button" class="btn btn-sm btn-icon-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for this transaction">
                      <i class="fi fi-rr-menu-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li>
                        <form method="POST" action="{{ route('payments.refund', $pay) }}" data-sas-confirm="Issue a refund?" data-sas-confirm-label="Refund">
                          @csrf
                          <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-undo"></i> Refund</button>
                        </form>
                      </li>
                    </ul>
                  </div>
                @endif
              </td>
            </tr>
          @empty
            <x-empty-state colspan="7" icon="fi-rr-usd-circle" title="No transactions yet" description="Payments and deposits will automatically appear here as appointments are booked and completed. Driver: {{ $driver }}.">
              <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-primary"><i class="fi fi-rr-plus me-1"></i> Create First Payment</a>
            </x-empty-state>
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection

@push('scripts')
  <script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
  <script>
    function sasUnskeleton(selector) {
      const el = document.querySelector(selector);
      if (el) el.classList.remove('sas-skeleton');
    }
    if (document.querySelector('#revenueChart')) {
      new ApexCharts(document.querySelector('#revenueChart'), {
        chart: { type: 'donut', height: 180, fontFamily: 'Inter, sans-serif' },
        series: [{{ $totals['collected'] }}, {{ $totals['pending'] }}, {{ $totals['refunded'] }}],
        labels: ['Collected', 'Pending', 'Refunded'],
        colors: ['#22C55E', '#F59E0B', '#EF4444'],
        legend: { show: false },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total', formatter: () => '₹{{ number_format($billVolume, 0) }}' } } } } },
      }).render().then(() => sasUnskeleton('#revenueChart'));
    }
  </script>
  <script>
    (function () {
      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;
      const el = document.getElementById('transactionsTable');
      if (!el || el.querySelector('tbody td[colspan]')) return;

      jQuery(el).DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'desc']],
        language: { search: '', searchPlaceholder: 'Search transactions…' },
      });

      const lengthWrap = document.querySelector('#transactionsTable_wrapper .dataTables_length');
      const lengthSlot = document.getElementById('transactionsLengthSlot');
      if (lengthWrap && lengthSlot) lengthSlot.appendChild(lengthWrap);
      const filterWrap = document.querySelector('#transactionsTable_wrapper .dataTables_filter');
      const searchSlot = document.getElementById('transactionsSearchSlot');
      if (filterWrap && searchSlot) searchSlot.appendChild(filterWrap);
    })();
  </script>
@endpush
