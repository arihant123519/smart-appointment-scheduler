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
    <div class="col-md-4">
      <x-card bodyClass="text-center" class="sas-card-hover">
        <div class="text-muted small">Average rating</div>
        <div class="display-5 fw-bold text-warning">{{ $average ?: '—' }} <small class="fs-6">/ 5</small></div>
      </x-card>
    </div>
    @foreach (['positive' => 'success', 'neutral' => 'secondary', 'negative' => 'danger'] as $s => $c)
      <div class="col-md-auto">
        <x-card bodyClass="text-center px-4 h-100" class="sas-card-hover h-100">
          <div class="text-muted small text-capitalize">{{ $s }}</div>
          <div class="h3 mb-0 text-{{ $c }}">{{ $sentiment[$s] ?? 0 }}</div>
        </x-card>
      </div>
    @endforeach
  </div>

  @if (!empty($themes['themes']))
    <x-card class="mb-3 border-primary-subtle">
      <div class="d-flex align-items-center gap-2 mb-2">
        <h6 class="mb-0">🧠 AI feedback themes</h6>
      </div>
      <p class="text-muted small mb-2">{{ $themes['summary'] }}</p>
      <div class="d-flex flex-wrap gap-2">
        @foreach ($themes['themes'] as $theme)
          <span class="badge badge-light-primary text-capitalize">{{ $theme }}</span>
        @endforeach
      </div>
    </x-card>
  @endif

  <x-card bodyClass="p-0">
    <div class="table-responsive p-3">
      <table class="table align-middle mb-0">
        <thead class="table-light"><tr><th>Date</th><th>Patient</th><th>Provider</th><th>Rating</th><th>Comment</th><th>Sentiment</th></tr></thead>
        <tbody id="reviewsBody">
          @forelse ($reviews as $r)
            <tr>
              <td>{{ $r->created_at->format('M j, Y') }}</td>
              <td>{{ $r->reviewer_display_name }}</td>
              <td>{{ $r->provider->name ?? '—' }}</td>
              <td class="text-warning">{{ str_repeat('★', $r->rating) }}</td>
              <td>{{ $r->comment ?? '—' }}</td>
              <td>
                @php $c = ['positive'=>'success','negative'=>'danger','neutral'=>'secondary'][$r->sentiment] ?? 'secondary'; @endphp
                @if ($r->sentiment)<x-badge-status :color="$c" :label="ucfirst($r->sentiment)" />@endif
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
        const sentimentColor = { positive: 'success', negative: 'danger', neutral: 'secondary' };

        function esc(str) {
          const d = document.createElement('div');
          d.textContent = str == null ? '' : String(str);
          return d.innerHTML;
        }

        function badge(color, label) {
          return '<span class="badge badge-light-' + color + '">' + esc(label) + '</span>';
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
                const stars = '★'.repeat(r.rating);
                const sentimentHtml = r.sentiment ? badge(sentimentColor[r.sentiment] || 'secondary', r.sentiment.charAt(0).toUpperCase() + r.sentiment.slice(1)) : '';
                const row = '<tr><td>' + esc(r.date) + '</td><td>' + esc(r.patient) + '</td><td>' + esc(r.provider)
                  + '</td><td class="text-warning">' + stars + '</td><td>' + esc(r.comment || '—') + '</td><td>' + sentimentHtml + '</td></tr>';
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
