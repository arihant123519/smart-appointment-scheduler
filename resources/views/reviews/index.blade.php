@extends('layouts.app')

@section('title', 'Reviews & Feedback')

@php
  $revTotal = $reviews->count();
  $revPct = fn ($n) => $revTotal > 0 ? round($n / $revTotal * 100) : 0;
  $revPositive = $sentiment['positive'] ?? 0;
  $revNeutral = $sentiment['neutral'] ?? 0;
  $revNegative = $sentiment['negative'] ?? 0;
@endphp

@push('styles')
  <style>
    .sas-page-toolbar { display: none; }
    .sas-rev-header__icon {
      width: 52px; height: 52px; border-radius: var(--sas-radius-lg); flex-shrink: 0;
      background: var(--sas-primary-50); color: var(--sas-primary-700);
      display: grid; place-items: center; font-size: 1.4rem;
    }
    .sas-rev-header__title { font-size: 2.25rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }
    .sas-rev-header__subtitle { font-size: var(--sas-fs-base); color: var(--sas-gray-500); }

    .sas-rev-clinic-pill {
      display: inline-flex; align-items: center; gap: .4rem; border: 1px solid var(--sas-gray-200); background: #fff;
      color: var(--sas-gray-700); font-weight: 600; font-size: var(--sas-fs-sm); border-radius: var(--sas-radius-md); padding: .5rem .9rem;
      text-decoration: none; transition: border-color .15s var(--sas-ease), background-color .15s var(--sas-ease);
    }
    .sas-rev-clinic-pill:hover { background: var(--sas-gray-50); color: var(--sas-gray-900); }
    .sas-rev-clinic-pill.active { border-color: var(--sas-primary-400); color: var(--sas-primary-700); background: var(--sas-primary-50); }

    .sas-rev-stars i { font-size: .85rem; color: var(--sas-gray-200); }
    .sas-rev-stars i.is-filled { color: var(--sas-warning); }
    .sas-rev-kpi__caption { font-size: var(--sas-fs-xs); color: var(--sas-gray-500); margin-top: .35rem; }
    .sas-rev-kpi__bar { height: 4px; border-radius: 2px; background: var(--sas-gray-100); margin-top: .5rem; overflow: hidden; }
    .sas-rev-kpi__bar span { display: block; height: 100%; border-radius: 2px; }

    .sas-rev-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .85rem; padding: var(--sas-space-4) var(--sas-space-5); border-bottom: 1px solid var(--sas-gray-100); }
    .sas-rev-toolbar__length select { border-radius: var(--sas-radius-md); }
    .sas-rev-toolbar__search { margin-left: auto; }
    .sas-rev-toolbar__search input { border: 1px solid var(--sas-gray-200); border-radius: var(--sas-radius-md); padding: .55rem .9rem; font-size: var(--sas-fs-sm); min-width: 220px; }
    .sas-rev-toolbar__search input:focus { outline: none; border-color: var(--sas-primary-400); box-shadow: 0 0 0 .2rem var(--sas-primary-100); }
    #reviewsTable_wrapper > .row:first-child { display: none; }
    #reviewsTable_wrapper > .row:last-child { padding: var(--sas-space-4) var(--sas-space-5); margin: 0; align-items: center; }

    .sas-rev-filter-btn {
      border: 1px solid var(--sas-gray-200); background: #fff; color: var(--sas-gray-600); font-weight: 600; font-size: var(--sas-fs-sm);
      border-radius: var(--sas-radius-md); padding: .55rem 1rem; display: inline-flex; align-items: center; gap: .4rem; flex-shrink: 0;
    }
    .sas-rev-filter-btn:hover { background: var(--sas-gray-50); }
    .sas-rev-filter-btn.has-active { border-color: var(--sas-primary-400); color: var(--sas-primary-600); background: var(--sas-primary-50); }
  </style>
@endpush

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div class="d-flex align-items-start gap-3">
      <span class="sas-rev-header__icon"><i class="fi fi-rr-star" aria-hidden="true"></i></span>
      <div>
        <h1 class="sas-rev-header__title mb-1">Reviews &amp; Feedback</h1>
        <p class="sas-rev-header__subtitle mb-0">Monitor patient reviews, ratings and sentiment across all clinics.</p>
      </div>
    </div>
    @can('manage services')
      <a href="{{ route('reviewqrcodes.index') }}" class="btn btn-light btn-lg"><i class="fi fi-rr-qrcode me-1"></i> Review QR codes</a>
    @endcan
  </div>

  @if ($clinics->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 mb-3">
      <a href="{{ route('reviews.index') }}" class="sas-rev-clinic-pill {{ ! $clinicId ? 'active' : '' }}">All clinics <i class="fi fi-rr-angle-small-down" aria-hidden="true"></i></a>
      @foreach ($clinics as $c)
        <a href="{{ route('reviews.index', ['clinic_id' => $c->id]) }}" class="sas-rev-clinic-pill {{ (int) $clinicId === $c->id ? 'active' : '' }}">{{ $c->name }} <i class="fi fi-rr-angle-small-down" aria-hidden="true"></i></a>
      @endforeach
    </div>
  @endif

  <div class="row g-3 mb-3 sas-stagger">
    <div class="col-sm-6 col-xl-3">
      <x-stat-widget icon="fi-rr-star" label="Average rating" :value="$average ?: 0" bg="bg-warning-subtle" fg="text-warning" />
      <div class="sas-rev-kpi__caption">
        <span class="sas-rev-stars">
          @for ($i = 1; $i <= 5; $i++)
            <i class="fi fi-rr-star {{ $i <= round($average) ? 'is-filled' : '' }}"></i>
          @endfor
        </span>
        {{ $revTotal ? $revTotal.' review'.($revTotal === 1 ? '' : 's') : 'No reviews yet' }}
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <x-stat-widget icon="fi-rr-check-circle" label="Positive" :value="$revPositive" bg="bg-success-subtle" fg="text-success" />
      <div class="sas-rev-kpi__caption">{{ $revPct($revPositive) }}% of total</div>
      <div class="sas-rev-kpi__bar"><span style="width:{{ $revPct($revPositive) }}%;background:var(--sas-success)"></span></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <x-stat-widget icon="fi-rr-minus" label="Neutral" :value="$revNeutral" bg="bg-secondary-subtle" fg="text-secondary" />
      <div class="sas-rev-kpi__caption">{{ $revPct($revNeutral) }}% of total</div>
      <div class="sas-rev-kpi__bar"><span style="width:{{ $revPct($revNeutral) }}%;background:var(--sas-warning)"></span></div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <x-stat-widget icon="fi-rr-exclamation" label="Negative" :value="$revNegative" bg="bg-danger-subtle" fg="text-danger" />
      <div class="sas-rev-kpi__caption">{{ $revPct($revNegative) }}% of total</div>
      <div class="sas-rev-kpi__bar"><span style="width:{{ $revPct($revNegative) }}%;background:var(--sas-danger)"></span></div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-6">
      <x-card title="Rating breakdown" class="h-100">
        @if ($reviews->isEmpty())
          <p class="text-muted small mb-0">No ratings yet.</p>
        @else
          @foreach ($byRating as $stars => $count)
            @php $pct = $revPct($count); @endphp
            <div class="d-flex align-items-center gap-2 {{ ! $loop->last ? 'mb-2' : '' }}">
              <div class="text-nowrap small fw-semibold" style="width:44px">{{ $stars }} <i class="fi fi-rr-star text-warning" style="font-size:.75em"></i></div>
              <div class="sas-rating-bar__track flex-grow-1">
                <div class="sas-rating-bar__fill" style="width:{{ $pct }}%"></div>
              </div>
              <div class="text-muted small text-end" style="width:60px">{{ $count }} ({{ $pct }}%)</div>
            </div>
          @endforeach
        @endif
      </x-card>
    </div>
    <div class="col-xl-6">
      <x-card title="Sentiment overview" class="h-100">
        <div id="sentimentChart" class="sas-skeleton" style="min-height:220px"></div>
      </x-card>
    </div>
  </div>

  @if (! empty($themes['themes']))
    <x-card class="mb-3">
      <div class="d-flex align-items-center gap-3 mb-2">
        <div class="sas-stat__icon bg-primary-subtle text-primary" style="width:40px;height:40px;font-size:1.1rem"><i class="fi fi-rr-sparkles"></i></div>
        <h6 class="mb-0 fw-bold">AI feedback themes</h6>
      </div>
      <p class="text-muted small mb-2">{{ $themes['summary'] }}</p>
      <div class="d-flex flex-wrap gap-2">
        @foreach ($themes['themes'] as $theme)
          <span class="badge badge-light-primary text-capitalize">{{ $theme }}</span>
        @endforeach
      </div>
    </x-card>
  @endif

  <x-card bodyClass="p-0" title="All reviews">
    <div class="sas-rev-toolbar">
      <span class="sas-rev-toolbar__length" id="reviewsLengthSlot"></span>
      <span class="sas-rev-toolbar__search" id="reviewsSearchSlot"></span>
      <div class="dropdown">
        <button type="button" class="sas-rev-filter-btn" id="reviewFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fi fi-rr-filter" aria-hidden="true"></i> Filters
        </button>
        <ul class="dropdown-menu dropdown-menu-end p-3" style="min-width:200px">
          <div class="mb-2" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--sas-gray-500)">Sentiment</div>
          @foreach (['positive' => 'Positive', 'neutral' => 'Neutral', 'negative' => 'Negative'] as $sv => $sl)
            <div class="form-check"><input class="form-check-input sas-rev-sentiment-check" type="checkbox" value="{{ $sv }}" id="revFilterSent{{ $sv }}"><label class="form-check-label small" for="revFilterSent{{ $sv }}">{{ $sl }}</label></div>
          @endforeach
          <div class="mb-2 mt-3" style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--sas-gray-500)">Rating</div>
          @foreach ([5, 4, 3, 2, 1] as $rv)
            <div class="form-check"><input class="form-check-input sas-rev-rating-check" type="checkbox" value="{{ $rv }}" id="revFilterRating{{ $rv }}"><label class="form-check-label small" for="revFilterRating{{ $rv }}">{{ $rv }} star{{ $rv === 1 ? '' : 's' }}</label></div>
          @endforeach
        </ul>
      </div>
    </div>
    <div class="table-responsive">
      <table id="reviewsTable" class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Patient</th><th>Provider</th><th>Rating</th><th>Comment</th><th>Sentiment</th></tr></thead>
        <tbody id="reviewsBody">
          @forelse ($reviews as $r)
            <tr data-sentiment="{{ $r->sentiment }}" data-rating="{{ $r->rating }}">
              <td class="text-nowrap" data-order="{{ $r->created_at->timestamp }}">{{ $r->created_at->format('M j, Y') }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="sas-avatar sas-avatar-sm">{{ strtoupper(substr($r->reviewer_display_name, 0, 1)) }}</div>
                  <span class="fw-semibold">{{ $r->reviewer_display_name }}</span>
                </div>
              </td>
              <td>{{ $r->provider->name ?? '—' }}</td>
              <td class="text-warning text-nowrap" data-order="{{ $r->rating }}">{{ str_repeat('★', $r->rating) }}<span class="text-muted">{{ str_repeat('★', 5 - $r->rating) }}</span></td>
              <td class="text-truncate" style="max-width:260px" title="{{ $r->comment }}">{{ $r->comment ?? '—' }}</td>
              <td>
                @php $c = ['positive' => 'success', 'negative' => 'danger', 'neutral' => 'secondary'][$r->sentiment] ?? 'secondary'; @endphp
                @php $ic = ['positive' => 'fi-rr-check-circle', 'negative' => 'fi-rr-exclamation', 'neutral' => 'fi-rr-minus'][$r->sentiment] ?? null; @endphp
                @if ($r->sentiment)<x-badge-status :color="$c" :icon="$ic" :label="ucfirst($r->sentiment)" />@endif
              </td>
            </tr>
          @empty
            <x-empty-state colspan="6" icon="fi-rr-star" title="No reviews yet" description="Patient feedback will appear here after their visits." />
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

    const sentimentCounts = { positive: {{ $revPositive }}, neutral: {{ $revNeutral }}, negative: {{ $revNegative }} };
    const sentimentColors = { positive: '#22C55E', neutral: '#F59E0B', negative: '#EF4444' };
    const sentimentTotal = Object.values(sentimentCounts).reduce((a, b) => a + b, 0);
    new ApexCharts(document.querySelector('#sentimentChart'), {
      chart: { type: 'donut', height: 260, fontFamily: 'Inter, sans-serif' },
      series: Object.values(sentimentCounts),
      labels: ['Positive', 'Neutral', 'Negative'],
      colors: Object.values(sentimentColors),
      legend: { position: 'right', fontSize: '12px', formatter: (label, opts) => {
        const v = opts.w.globals.series[opts.seriesIndex];
        const pct = sentimentTotal > 0 ? Math.round(v / sentimentTotal * 100) : 0;
        return label + '  ' + v + ' (' + pct + '%)';
      } },
      plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total', formatter: () => sentimentTotal } } } } },
      noData: { text: 'No reviews yet' },
    }).render().then(() => sasUnskeleton('#sentimentChart'));
  </script>
  <script>
    (function () {
      if (typeof window.jQuery === 'undefined' || !jQuery.fn.DataTable) return;

      // This table intentionally skips the shared table.datatable auto-init
      // (see layouts/app.blade.php) so it can (a) sort by date descending by
      // default and (b) stay reachable from the live-poll below, which needs
      // to add rows through DataTables' own API rather than raw DOM inserts.
      const el = document.getElementById('reviewsTable');
      if (!el || el.querySelector('tbody td[colspan]')) return; // empty state — leave the illustration alone

      const table = jQuery(el).DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, 'desc']],
        language: { search: '', searchPlaceholder: 'Search reviews…' },
      });

      const lengthWrap = document.querySelector('#reviewsTable_wrapper .dataTables_length');
      const lengthSlot = document.getElementById('reviewsLengthSlot');
      if (lengthWrap && lengthSlot) lengthSlot.appendChild(lengthWrap);
      const filterWrap = document.querySelector('#reviewsTable_wrapper .dataTables_filter');
      const searchSlot = document.getElementById('reviewsSearchSlot');
      if (filterWrap && searchSlot) searchSlot.appendChild(filterWrap);

      let sentimentSet = new Set();
      let ratingSet = new Set();
      jQuery.fn.dataTable.ext.search.push(function (settings, data, rowIdx) {
        if (settings.nTable.id !== 'reviewsTable') return true;
        const row = table.row(rowIdx).node();
        if (!row) return true;
        if (sentimentSet.size && !sentimentSet.has(row.getAttribute('data-sentiment'))) return false;
        if (ratingSet.size && !ratingSet.has(row.getAttribute('data-rating'))) return false;
        return true;
      });

      const filterBtnEl = document.getElementById('reviewFilterBtn');
      const sentChecks = document.querySelectorAll('.sas-rev-sentiment-check');
      const ratingChecks = document.querySelectorAll('.sas-rev-rating-check');
      function syncFilters() {
        sentimentSet = new Set(Array.from(sentChecks).filter(c => c.checked).map(c => c.value));
        ratingSet = new Set(Array.from(ratingChecks).filter(c => c.checked).map(c => c.value));
        filterBtnEl.classList.toggle('has-active', sentimentSet.size + ratingSet.size > 0);
        table.draw();
      }
      [...sentChecks, ...ratingChecks].forEach(c => c.addEventListener('change', syncFilters));

      // --- Live-updating feed (unchanged polling behaviour, now added
      //     through DataTables' row API so new reviews sort/filter/paginate
      //     correctly instead of being silently invisible to the table). ---
      let lastId = {{ $reviews->max('id') ?? 0 }};
      const feedUrl = '{{ route('reviews.feed') }}' + '{{ $clinicId ? '?clinic_id='.$clinicId : '' }}';
      const sentimentMeta = {
        positive: { color: 'success', icon: 'fi-rr-check-circle' },
        negative: { color: 'danger', icon: 'fi-rr-exclamation' },
        neutral: { color: 'secondary', icon: 'fi-rr-minus' },
      };

      function esc(str) {
        const d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
      }
      function initials(name) { return esc((name || '?').charAt(0).toUpperCase()); }
      function badge(sentiment, label) {
        const meta = sentimentMeta[sentiment] || sentimentMeta.neutral;
        return '<span class="badge bg-' + meta.color + '-subtle text-' + meta.color + ' sas-badge-icon"><i class="fi ' + meta.icon + '"></i>' + esc(label) + '</span>';
      }

      function poll() {
        const url = feedUrl + (feedUrl.includes('?') ? '&' : '?') + 'after_id=' + lastId;
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(r => r.ok ? r.json() : null)
          .then(data => {
            if (!data || !data.items || !data.items.length) return;
            data.items.forEach(function (r) {
              lastId = Math.max(lastId, r.id);
              const filled = '★'.repeat(r.rating);
              const unfilled = '★'.repeat(5 - r.rating);
              const sentimentHtml = r.sentiment ? badge(r.sentiment, r.sentiment.charAt(0).toUpperCase() + r.sentiment.slice(1)) : '';
              const rowHtml = '<tr data-sentiment="' + esc(r.sentiment || '') + '" data-rating="' + r.rating + '">'
                + '<td class="text-nowrap" data-order="' + Math.floor(Date.now() / 1000) + '">' + esc(r.date) + '</td>'
                + '<td><div class="d-flex align-items-center gap-2"><div class="sas-avatar sas-avatar-sm">' + initials(r.patient) + '</div><span class="fw-semibold">' + esc(r.patient) + '</span></div></td>'
                + '<td>' + esc(r.provider) + '</td>'
                + '<td class="text-warning text-nowrap" data-order="' + r.rating + '">' + filled + '<span class="text-muted">' + unfilled + '</span></td>'
                + '<td class="text-truncate" style="max-width:260px" title="' + esc(r.comment || '') + '">' + esc(r.comment || '—') + '</td>'
                + '<td>' + sentimentHtml + '</td></tr>';
              table.row.add(jQuery(rowHtml)).draw(false);
            });
          })
          .catch(() => { /* transient/offline — ignore */ });
      }

      setInterval(poll, 15000);
    })();
  </script>
@endpush
