@extends('layouts.app')

@section('title', 'Reviews & Feedback')

@section('page_actions')
  @can('manage services')
    <a href="{{ route('reviewqrcodes.index') }}" class="btn btn-light"><i class="fi fi-rr-qrcode me-1"></i> Review QR codes</a>
  @endcan
@endsection

@section('content')
  @if ($clinics->isNotEmpty())
    <form method="GET" class="mb-3" style="max-width:280px">
      <select name="clinic_id" class="form-select" onchange="this.form.submit()">
        <option value="">All clinics</option>
        @foreach ($clinics as $c)
          <option value="{{ $c->id }}" @selected($clinicId == $c->id)>{{ $c->name }}</option>
        @endforeach
      </select>
    </form>
  @endif

  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
      <x-stat-widget icon="fi-rr-star" label="Average rating" :value="$average ?: 0" bg="bg-warning-subtle" fg="text-warning" />
    </div>
    <div class="col-sm-6 col-xl-3">
      <x-stat-widget icon="fi-rr-check-circle" label="Positive" :value="$sentiment['positive'] ?? 0" bg="bg-success-subtle" fg="text-success" />
    </div>
    <div class="col-sm-6 col-xl-3">
      <x-stat-widget icon="fi-rr-minus" label="Neutral" :value="$sentiment['neutral'] ?? 0" bg="bg-secondary-subtle" fg="text-secondary" />
    </div>
    <div class="col-sm-6 col-xl-3">
      <x-stat-widget icon="fi-rr-exclamation" label="Negative" :value="$sentiment['negative'] ?? 0" bg="bg-danger-subtle" fg="text-danger" />
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-xl-5">
      <x-card title="Rating breakdown">
        @if ($reviews->isEmpty())
          <p class="text-muted small mb-0">No ratings yet.</p>
        @else
          @foreach ($byRating as $stars => $count)
            @php $pct = $reviews->count() ? round($count / $reviews->count() * 100) : 0; @endphp
            <div class="d-flex align-items-center gap-2 {{ !$loop->last ? 'mb-2' : '' }}">
              <div class="text-nowrap small fw-semibold" style="width:44px">{{ $stars }} <i class="fi fi-rr-star text-warning" style="font-size:.75em"></i></div>
              <div class="sas-rating-bar__track flex-grow-1">
                <div class="sas-rating-bar__fill" style="width:{{ $pct }}%"></div>
              </div>
              <div class="text-muted small text-end" style="width:28px">{{ $count }}</div>
            </div>
          @endforeach
        @endif
      </x-card>
    </div>

    @if (!empty($themes['themes']))
      <div class="col-xl-7">
        <x-card class="h-100">
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
      </div>
    @endif
  </div>

  <x-card bodyClass="p-0">
    <div class="table-responsive p-3">
      <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Patient</th><th>Provider</th><th>Rating</th><th>Comment</th><th>Sentiment</th></tr></thead>
        <tbody id="reviewsBody">
          @forelse ($reviews as $r)
            <tr>
              <td class="text-nowrap">{{ $r->created_at->format('M j, Y') }}</td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="sas-avatar sas-avatar-sm">{{ strtoupper(substr($r->reviewer_display_name, 0, 1)) }}</div>
                  <span class="fw-semibold">{{ $r->reviewer_display_name }}</span>
                </div>
              </td>
              <td>{{ $r->provider->name ?? '—' }}</td>
              <td class="text-warning text-nowrap">{{ str_repeat('★', $r->rating) }}<span class="text-muted">{{ str_repeat('★', 5 - $r->rating) }}</span></td>
              <td class="text-truncate" style="max-width:260px" title="{{ $r->comment }}">{{ $r->comment ?? '—' }}</td>
              <td>
                @php $c = ['positive'=>'success','negative'=>'danger','neutral'=>'secondary'][$r->sentiment] ?? 'secondary'; @endphp
                @php $ic = ['positive'=>'fi-rr-check-circle','negative'=>'fi-rr-exclamation','neutral'=>'fi-rr-minus'][$r->sentiment] ?? null; @endphp
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

  @push('scripts')
    <script>
      (function () {
        const tbody = document.getElementById('reviewsBody');
        if (!tbody) return;
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

        function initials(name) {
          return esc((name || '?').charAt(0).toUpperCase());
        }

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
              const empty = tbody.querySelector('.sas-empty-state');
              if (empty) empty.closest('tr').remove();
              data.items.slice().reverse().forEach(function (r) {
                lastId = Math.max(lastId, r.id);
                const filled = '★'.repeat(r.rating);
                const unfilled = '★'.repeat(5 - r.rating);
                const sentimentHtml = r.sentiment ? badge(r.sentiment, r.sentiment.charAt(0).toUpperCase() + r.sentiment.slice(1)) : '';
                const row = '<tr><td class="text-nowrap">' + esc(r.date) + '</td>'
                  + '<td><div class="d-flex align-items-center gap-2"><div class="sas-avatar sas-avatar-sm">' + initials(r.patient) + '</div><span class="fw-semibold">' + esc(r.patient) + '</span></div></td>'
                  + '<td>' + esc(r.provider) + '</td>'
                  + '<td class="text-warning text-nowrap">' + filled + '<span class="text-muted">' + unfilled + '</span></td>'
                  + '<td class="text-truncate" style="max-width:260px" title="' + esc(r.comment || '') + '">' + esc(r.comment || '—') + '</td>'
                  + '<td>' + sentimentHtml + '</td></tr>';
                tbody.insertAdjacentHTML('afterbegin', row);
              });
            })
            .catch(() => { /* transient/offline — ignore */ });
        }

        setInterval(poll, 15000);
      })();
    </script>
  @endpush
@endsection
