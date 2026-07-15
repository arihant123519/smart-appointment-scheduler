@extends('layouts.app')

@section('title', 'Reviews & Feedback')

@section('content')
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
      <table class="table align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Date</th><th>Patient</th><th>Provider</th><th>Rating</th><th>Comment</th><th>Sentiment</th></tr></thead>
        <tbody>
          @forelse ($reviews as $r)
            <tr>
              <td>{{ $r->created_at->format('M j, Y') }}</td>
              <td>{{ $r->patient->name ?? '—' }}</td>
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
@endsection
