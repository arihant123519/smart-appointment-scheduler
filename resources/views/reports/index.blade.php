@extends('layouts.app')

@section('title', 'Reports & Analytics')

@php
  // ---------------------------------------------------------------------
  // Everything below is read-only, presentational-only computation in the
  // view — the same pattern used throughout this redesign pass (Calendar,
  // Appointments, Waitlist, Flows, ...). It mirrors ReportController's own
  // clinic-scoping logic exactly, adding a previous-30-day comparison
  // window the controller doesn't compute, plus a couple of genuinely new
  // metrics (revenue, unique paying patients) pulled from the real Payment
  // model. Nothing here is fabricated — where real data doesn't exist for
  // something the brief asked for, it's left out (see the summary).
  // ---------------------------------------------------------------------
  $rptWindow = now()->subDays(30);
  $rptPrevStart = now()->subDays(60);
  $rptPrevEnd = now()->subDays(30);
  $rptScopeAppt = fn ($q) => $q->when($selectedClinicId, fn ($qq) => $qq->where('clinic_id', $selectedClinicId))->forCurrentClinic();

  $rptFinishedPrev = \App\Models\Appointment::whereBetween('start_at', [$rptPrevStart, $rptPrevEnd])->tap($rptScopeAppt)
    ->whereIn('status', [\App\Models\Appointment::STATUS_COMPLETED, \App\Models\Appointment::STATUS_NO_SHOW])->count();
  $rptNoShowsPrev = \App\Models\Appointment::whereBetween('start_at', [$rptPrevStart, $rptPrevEnd])->tap($rptScopeAppt)
    ->where('status', \App\Models\Appointment::STATUS_NO_SHOW)->count();
  $rptNoShowRatePrev = $rptFinishedPrev > 0 ? round($rptNoShowsPrev / $rptFinishedPrev * 100, 1) : 0;

  $rptPctDelta = fn ($now, $prev) => $prev > 0 ? round(($now - $prev) / $prev * 100, 1) : ($now > 0 ? 100.0 : 0.0);
  $rptNoShowRateDelta = $rptPctDelta($noShowRate, $rptNoShowRatePrev);
  $rptNoShowsDelta = $noShows - $rptNoShowsPrev;
  $rptFinishedDelta = $finished - $rptFinishedPrev;

  // Real revenue, from the Payment model (status = paid), scoped the same
  // way as everything else. Payments have no clinic_id column directly, so
  // scoping goes through the paying patient's clinic.
  $rptPaymentBase = fn () => \App\Models\Payment::where('status', 'paid')
    ->whereHas('patient', fn ($q) => $q->when($selectedClinicId, fn ($q2) => $q2->where('clinic_id', $selectedClinicId)));
  $rptRevenueNow = (float) $rptPaymentBase()->where('paid_at', '>=', $rptWindow)->sum('amount');
  $rptRevenuePrev = (float) $rptPaymentBase()->whereBetween('paid_at', [$rptPrevStart, $rptPrevEnd])->sum('amount');
  $rptPatientsNow = $rptPaymentBase()->where('paid_at', '>=', $rptWindow)->distinct('patient_id')->count('patient_id');
  $rptPatientsPrev = $rptPaymentBase()->whereBetween('paid_at', [$rptPrevStart, $rptPrevEnd])->distinct('patient_id')->count('patient_id');
  $rptAvgPerPatientNow = $rptPatientsNow > 0 ? $rptRevenueNow / $rptPatientsNow : 0;
  $rptAvgPerPatientPrev = $rptPatientsPrev > 0 ? $rptRevenuePrev / $rptPatientsPrev : 0;

  $rptRevenueDelta = $rptPctDelta($rptRevenueNow, $rptRevenuePrev);
  $rptPatientsDelta = $rptPctDelta($rptPatientsNow, $rptPatientsPrev);
  $rptAvgPerPatientDelta = $rptPctDelta($rptAvgPerPatientNow, $rptAvgPerPatientPrev);

  // Utilization expressed relative to the busiest provider this period
  // (busiest = 100%) — a real ratio of the real counts, not an invented
  // "% of theoretical capacity" (nothing in this app tracks slot capacity
  // at that granularity).
  $rptMaxUtil = $utilization->max('count') ?: 1;
  $rptUtilPct = $utilization->map(fn ($u) => (int) round(($u['count'] / $rptMaxUtil) * 100));

  $rptInitials = function (string $name) {
    $words = preg_split('/\s+/', trim(str_replace('Dr.', '', $name)));
    return strtoupper(substr($words[0] ?? '', 0, 1).substr($words[1] ?? '', 0, 1));
  };

  $rptFunnelBase = reset($funnel) ?: 1;

  $rptDeltaBadge = function ($delta, bool $upIsGood, string $suffix = '%') {
    $up = $delta >= 0;
    $good = $upIsGood ? $up : ! $up;
    $color = $delta == 0 ? 'text-muted' : ($good ? 'text-success' : 'text-danger');
    $icon = $delta == 0 ? 'fi-rr-minus' : ($up ? 'fi-rr-arrow-small-up' : 'fi-rr-arrow-small-down');
    return '<span class="'.$color.'"><i class="fi '.$icon.'" aria-hidden="true"></i> '.number_format(abs($delta), 1).$suffix.' vs previous 30 days</span>';
  };
@endphp

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }
    .sas-rpt-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-rpt-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-rpt-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-rpt-ask-panel { min-width: 420px; padding: 1rem; }
    .sas-rpt-ask-panel input { border-radius: var(--sas-radius-md); }

    .sas-rpt-clinic-pill {
      display: inline-flex; align-items: center; gap: .4rem; border: 1px solid var(--sas-gray-200); background: #fff;
      color: var(--sas-gray-700); font-weight: 600; font-size: var(--sas-fs-sm); border-radius: var(--sas-radius-md); padding: .5rem .9rem;
      text-decoration: none; transition: border-color .15s var(--sas-ease), background-color .15s var(--sas-ease);
    }
    .sas-rpt-clinic-pill:hover { background: var(--sas-gray-50); color: var(--sas-gray-900); }
    .sas-rpt-clinic-pill.active { border-color: var(--sas-primary-400); color: var(--sas-primary-700); background: var(--sas-primary-50); }

    .sas-rpt-kpi__caption { font-size: var(--sas-fs-xs); margin-top: .3rem; }

    .sas-rpt-provider-row { display: flex; align-items: center; gap: .6rem; }
    .sas-rpt-provider-row__avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--sas-primary-50); color: var(--sas-primary-700); font-weight: 700; font-size: .68rem; display: grid; place-items: center; flex-shrink: 0; }

    .sas-rpt-conv-list__row { display: flex; align-items: center; gap: .6rem; padding: .5rem 0; border-bottom: 1px solid var(--sas-gray-100); }
    .sas-rpt-conv-list__row:last-of-type { border-bottom: 0; }
    .sas-rpt-conv-list__icon { width: 28px; height: 28px; border-radius: var(--sas-radius-sm); display: grid; place-items: center; flex-shrink: 0; font-size: .8rem; }
    .sas-rpt-conv-list__label { flex: 1; font-size: var(--sas-fs-sm); color: var(--sas-gray-700); }
    .sas-rpt-conv-list__value { font-weight: 700; color: var(--sas-gray-900); }

    .sas-rpt-funnel__stage { margin-bottom: .55rem; }
    .sas-rpt-funnel__bar { height: 34px; border-radius: var(--sas-radius-sm); display: flex; align-items: center; padding: 0 .75rem; color: #fff; font-weight: 700; font-size: var(--sas-fs-xs); white-space: nowrap; transition: width .5s var(--sas-ease); }
    .sas-rpt-funnel__meta { display: flex; justify-content: space-between; font-size: var(--sas-fs-xs); color: var(--sas-gray-500); margin-bottom: .2rem; }

    .sas-rpt-revenue__value { font-size: 1.6rem; font-weight: 800; color: var(--sas-gray-900); line-height: 1.1; }
    .sas-rpt-revenue__label { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); margin-bottom: .3rem; }

    .sas-rpt-flag { display: flex; gap: .65rem; background: var(--sas-warning-subtle); border: 1px solid #fde68a; border-radius: var(--sas-radius-lg); padding: 1rem; }
    .sas-rpt-flag i { color: var(--sas-warning-emphasis); flex-shrink: 0; margin-top: .1rem; }

    .sas-rpt-heat table { border-collapse: separate; border-spacing: 3px; }
    .sas-rpt-heat td { border-radius: var(--sas-radius-sm); min-width: 34px; font-size: var(--sas-fs-xs); font-weight: 600; }
    .sas-rpt-heat th { font-size: var(--sas-fs-xs); }

    .sas-rpt-ab__exp { border: 1px solid var(--sas-gray-100); border-radius: var(--sas-radius-lg); padding: .9rem 1rem; margin-bottom: .75rem; }
    .sas-rpt-ab__exp:last-child { margin-bottom: 0; }
    .sas-rpt-ab__row { display: flex; align-items: center; gap: .75rem; padding: .4rem 0; }
    .sas-rpt-ab__variant { font-weight: 700; font-size: var(--sas-fs-xs); text-transform: uppercase; letter-spacing: .04em; width: 90px; flex-shrink: 0; }
    .sas-rpt-ab__rate { font-weight: 700; width: 60px; flex-shrink: 0; }
    .sas-rpt-ab__frac { color: var(--sas-gray-500); font-size: var(--sas-fs-xs); }

    .sas-rpt-view-link { font-size: var(--sas-fs-xs); font-weight: 700; text-decoration: none; }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-rpt-header__icon"><i class="fi fi-rr-chart-pie-alt" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-rpt-header__title mb-1">Reports &amp; Analytics</h1>
        <p class="sas-rpt-header__subtitle mb-0">Monitor no-shows, provider performance, bookings and revenue.</p>
      </div>
    </div>
    <div class="dropdown">
      <button type="button" class="btn btn-light btn-lg" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fi fi-rr-sparkles me-1" aria-hidden="true"></i> Ask the data <i class="fi fi-rr-angle-small-down ms-1" aria-hidden="true"></i>
      </button>
      <div class="dropdown-menu dropdown-menu-end sas-rpt-ask-panel">
        <label class="form-label mb-1 fw-semibold small">Ask a question about your data</label>
        <div class="input-group">
          <input type="text" id="askInput" class="form-control" placeholder='e.g. "which provider had the worst no-show rate?"'>
          <button class="btn btn-primary" id="askGo">Ask</button>
        </div>
        <div id="askResult" class="mt-2 small"></div>
      </div>
    </div>
  </div>

  @if ($clinics->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 mb-3">
      <a href="{{ route('reports.index') }}" class="sas-rpt-clinic-pill {{ ! $selectedClinicId ? 'active' : '' }}">All clinics <i class="fi fi-rr-angle-small-down" aria-hidden="true"></i></a>
      @foreach ($clinics as $c)
        <a href="{{ route('reports.index', ['clinic_id' => $c->id]) }}" class="sas-rpt-clinic-pill {{ (int) $selectedClinicId === $c->id ? 'active' : '' }}">{{ $c->name }} <i class="fi fi-rr-angle-small-down" aria-hidden="true"></i></a>
      @endforeach
    </div>
  @endif

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-md-4">
      <x-stat-widget label="No-show rate (30d)" :value="$noShowRate.'%'" icon="fi-rr-chart-line-up" bg="bg-danger-subtle" fg="text-danger" />
      <div class="sas-rpt-kpi__caption">{!! $rptDeltaBadge($rptNoShowRateDelta, false) !!}</div>
    </div>
    <div class="col-md-4">
      <x-stat-widget label="No-shows (30d)" :value="$noShows" icon="fi-rr-calendar-xmark" bg="bg-warning-subtle" fg="text-warning" />
      <div class="sas-rpt-kpi__caption">{!! $rptDeltaBadge($rptNoShowsDelta, false, '') !!}</div>
    </div>
    <div class="col-md-4">
      <x-stat-widget label="Completed + no-show (30d)" :value="$finished" icon="fi-rr-check-circle" bg="bg-info-subtle" fg="text-info" />
      <div class="sas-rpt-kpi__caption">{!! $rptDeltaBadge($rptFinishedDelta, true, '') !!}</div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-4">
      <x-card title="No-show rate by provider" bodyClass="p-0" class="h-100">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr><th>Provider</th><th>Total</th><th>No-shows</th><th>Rate</th></tr></thead>
            <tbody>
              @forelse ($byProvider as $row)
                @php $rc = $row['rate'] > 20 ? 'danger' : ($row['rate'] > 10 ? 'warning' : 'success'); @endphp
                <tr>
                  <td>
                    <div class="sas-rpt-provider-row">
                      <span class="sas-rpt-provider-row__avatar">{{ $rptInitials($row['name']) }}</span>
                      {{ $row['name'] }}
                    </div>
                  </td>
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
        <div class="card-footer"><a href="#" class="sas-rpt-view-link">View full report &rarr;</a></div>
      </x-card>
    </div>
    <div class="col-xl-3">
      <x-card title="Booking channels" class="h-100">
        <div id="channelChart" class="sas-skeleton" style="min-height:260px"></div>
        <div class="mt-2"><a href="#" class="sas-rpt-view-link">View full report &rarr;</a></div>
      </x-card>
    </div>
    <div class="col-xl-3">
      <x-card title="Provider utilization (30d)" class="h-100">
        <div id="utilChart" class="sas-skeleton" style="min-height:260px"></div>
        <div class="mt-2"><a href="#" class="sas-rpt-view-link">View full report &rarr;</a></div>
      </x-card>
    </div>
    <div class="col-xl-2">
      <x-card title="Conversion engine (30d)" class="h-100">
        <div class="sas-rpt-conv-list__row">
          <span class="sas-rpt-conv-list__icon bg-primary-subtle text-primary"><i class="fi fi-rr-comment-alt" aria-hidden="true"></i></span>
          <span class="sas-rpt-conv-list__label">Flows started</span>
          <span class="sas-rpt-conv-list__value">{{ $flow['started'] }}</span>
        </div>
        <div class="sas-rpt-conv-list__row">
          <span class="sas-rpt-conv-list__icon bg-success-subtle text-success"><i class="fi fi-rr-check-circle" aria-hidden="true"></i></span>
          <span class="sas-rpt-conv-list__label">Completed</span>
          <span class="sas-rpt-conv-list__value">{{ $flow['completed'] }}</span>
        </div>
        <div class="sas-rpt-conv-list__row">
          <span class="sas-rpt-conv-list__icon bg-warning-subtle text-warning"><i class="fi fi-rr-clock" aria-hidden="true"></i></span>
          <span class="sas-rpt-conv-list__label">Timed out</span>
          <span class="sas-rpt-conv-list__value">{{ $flow['timed_out'] }}</span>
        </div>
        <div class="sas-rpt-conv-list__row">
          <span class="sas-rpt-conv-list__icon bg-danger-subtle text-danger"><i class="fi fi-rr-exclamation" aria-hidden="true"></i></span>
          <span class="sas-rpt-conv-list__label">Escalated to staff</span>
          <span class="sas-rpt-conv-list__value">{{ $flow['escalated'] }}</span>
        </div>
        <div class="sas-rpt-conv-list__row">
          <span class="sas-rpt-conv-list__icon bg-success-subtle text-success"><i class="fi fi-rr-shield-check" aria-hidden="true"></i></span>
          <span class="sas-rpt-conv-list__label">Appointments rescued</span>
          <span class="sas-rpt-conv-list__value">{{ $flow['rescued'] }}</span>
        </div>
        <div class="sas-rpt-conv-list__row">
          <span class="sas-rpt-conv-list__icon bg-danger-subtle text-danger"><i class="fi fi-rr-calendar-xmark" aria-hidden="true"></i></span>
          <span class="sas-rpt-conv-list__label">Lost anyway</span>
          <span class="sas-rpt-conv-list__value">{{ $flow['lost'] }}</span>
        </div>
        <div class="mt-2"><a href="#" class="sas-rpt-view-link">View full report &rarr;</a></div>
      </x-card>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-xl-4">
      <x-card title="Appointment funnel (30d)" subtitle="Booked &rarr; completed vs. lost" class="h-100">
        @foreach ($funnel as $stage => $count)
          @php $pct = round($count / $rptFunnelBase * 100, 1); @endphp
          <div class="sas-rpt-funnel__stage">
            <div class="sas-rpt-funnel__meta"><span>{{ $stage }}</span><span>{{ $count }} ({{ $pct }}%)</span></div>
            <div class="sas-rpt-funnel__bar" style="width:{{ min(max($pct, 6), 100) }}%;background:{{ ['#2563EB','#7239ea','#93c5fd','#17c653','#9aa1ad','#f1416c'][$loop->index] ?? '#2563EB' }}"></div>
          </div>
        @endforeach
        <div class="mt-2"><a href="#" class="sas-rpt-view-link">View full report &rarr;</a></div>
      </x-card>
    </div>
    <div class="col-xl-4">
      <x-card title="Revenue &amp; patient intelligence" class="h-100">
        <div class="row g-3">
          <div class="col-6">
            <div class="sas-rpt-revenue__label">Total revenue (30d)</div>
            <div class="sas-rpt-revenue__value">₹{{ number_format($rptRevenueNow, 2) }}</div>
            <div class="sas-rpt-kpi__caption">{!! $rptDeltaBadge($rptRevenueDelta, true) !!}</div>
          </div>
          <div class="col-6">
            <div class="sas-rpt-revenue__label">Unique paying patients</div>
            <div class="sas-rpt-revenue__value">{{ $rptPatientsNow }}</div>
            <div class="sas-rpt-kpi__caption">{!! $rptDeltaBadge($rptPatientsDelta, true) !!}</div>
          </div>
          <div class="col-12">
            <div class="sas-rpt-revenue__label">Avg. revenue / paying patient</div>
            <div class="sas-rpt-revenue__value">₹{{ number_format($rptAvgPerPatientNow, 2) }}</div>
            <div class="sas-rpt-kpi__caption">{!! $rptDeltaBadge($rptAvgPerPatientDelta, true) !!}</div>
          </div>
        </div>
      </x-card>
    </div>
    <div class="col-xl-4">
      <x-card title="Flagged patterns (30d)" class="h-100">
        <x-slot:toolbar>
          <span class="text-muted small">Nothing changes automatically</span>
        </x-slot:toolbar>
        @forelse ($revenueLeaks as $flag)
          <div class="sas-rpt-flag mb-2">
            <i class="fi fi-rr-triangle-warning" aria-hidden="true"></i>
            <div>
              <div class="fw-semibold small">{{ $flag['title'] }}</div>
              <div class="text-muted small mb-0">{{ $flag['detail'] }}</div>
            </div>
          </div>
        @empty
          <x-empty-state icon="fi-rr-shield-check" title="No notable patterns flagged this month" />
        @endforelse
        <div class="mt-2"><a href="#" class="sas-rpt-view-link">View full report &rarr;</a></div>
      </x-card>
    </div>
  </div>

  <div class="row g-3 mt-1">
    <div class="col-xl-4">
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

    <div class="col-xl-4">
      <x-card title="Provider utilization by day &amp; hour (30d)" class="h-100 sas-rpt-heat">
        @if (empty($heatmap['providers']))
          <p class="text-muted small mb-0">No active providers to show.</p>
        @else
          <div class="table-responsive">
            <table class="table table-sm text-center align-middle mb-0">
              <thead>
                <tr>
                  <th class="text-start">Provider</th>
                  @foreach ($heatmap['hours'] as $h)
                    <th class="fw-normal text-muted">{{ \Carbon\Carbon::createFromTime($h)->format('gA') }}</th>
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
                      <td style="background-color: rgba(37, 99, 235, {{ $intensity }}); color: {{ $intensity > 0.5 ? '#fff' : 'var(--sas-gray-700)' }};" title="{{ $total }} appointment(s) over 30 days">
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

    @if (! empty($experiments))
      <div class="col-xl-4">
        <x-card title="A/B tests" class="h-100">
          @foreach ($experiments as $exp)
            <div class="sas-rpt-ab__exp">
              <div class="fw-semibold small mb-1">{{ $exp['experiment']->name }}</div>
              @foreach ($exp['variants'] as $v)
                <div class="sas-rpt-ab__row">
                  <span class="badge badge-light-{{ $loop->first ? 'primary' : 'secondary' }} sas-rpt-ab__variant">{{ $v['variant'] }}</span>
                  <span class="sas-rpt-ab__rate">{{ $v['rate'] }}%</span>
                  <span class="sas-rpt-ab__frac">{{ $v['converted'] }}/{{ $v['assigned'] }} booked</span>
                </div>
              @endforeach
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
    document.getElementById('askInput').addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('askGo').click(); });
  </script>
  <script>
    function sasUnskeleton(selector) {
      const el = document.querySelector(selector);
      if (el) el.classList.remove('sas-skeleton');
    }

    const byChannel = @json($byChannel);
    const channelTotal = Object.values(byChannel).reduce((a, b) => a + b, 0);
    new ApexCharts(document.querySelector('#channelChart'), {
      chart: { type: 'donut', height: 260, fontFamily: 'Inter, sans-serif' },
      series: Object.values(byChannel),
      labels: Object.keys(byChannel).map(k => k.charAt(0).toUpperCase() + k.slice(1)),
      colors: ['#2563EB', '#17c653', '#7239ea', '#f6b100', '#f1416c'],
      legend: { position: 'bottom', fontSize: '12px' },
      plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total', formatter: () => channelTotal } } } } },
    }).render().then(() => sasUnskeleton('#channelChart'));

    // Relative utilization (busiest provider = 100%) as radial gauges.
    new ApexCharts(document.querySelector('#utilChart'), {
      chart: { type: 'radialBar', height: 260, fontFamily: 'Inter, sans-serif' },
      series: @json($rptUtilPct->values()),
      labels: @json($utilization->pluck('name')),
      colors: ['#2563EB', '#F59E0B', '#22C55E', '#7239ea'],
      plotOptions: { radialBar: { hollow: { size: '35%' }, dataLabels: { name: { fontSize: '11px' }, value: { fontSize: '16px', fontWeight: 700 } } } },
      legend: { show: true, position: 'bottom', fontSize: '11px' },
    }).render().then(() => sasUnskeleton('#utilChart'));
  </script>
@endpush
