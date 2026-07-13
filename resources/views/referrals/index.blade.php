@extends('layouts.app')

@section('title', 'Referrals')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body">
      <div class="text-muted small">Total referrals</div><div class="h3 fw-bold">{{ $stats['total'] }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
      <div class="text-muted small">Converted to a booking</div><div class="h3 fw-bold text-success">{{ $stats['booked'] }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
      <div class="text-muted small">Conversion rate</div><div class="h3 fw-bold text-primary">{{ $stats['conversion_rate'] }}%</div>
    </div></div></div>
  </div>

  @if ($topReferrers->isNotEmpty())
    <div class="card mb-3">
      <div class="card-header"><h6 class="mb-0">Top referrers</h6></div>
      <div class="card-body p-0">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th>Patient</th><th>Total referrals</th><th>Booked</th></tr></thead>
          <tbody>
            @foreach ($topReferrers as $row)
              <tr>
                <td>{{ $row['patient']->name ?? '—' }}</td>
                <td>{{ $row['total'] }}</td>
                <td>{{ $row['booked'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h6 class="mb-0">Referrals</h6>
      <span class="badge bg-primary-subtle text-primary">{{ $referrals->count() }}</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0 datatable">
          <thead class="table-light">
            <tr>
              <th>Referred by</th>
              <th>Referred contact</th>
              <th>Status</th>
              <th>Booked as</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($referrals as $ref)
              <tr>
                <td>
                  @if ($ref->referrerPatient)
                    {{ $ref->referrerPatient->name }} <span class="text-muted small">(patient)</span>
                  @elseif ($ref->referrerProvider)
                    {{ $ref->referrerProvider->user->name ?? 'Provider' }} <span class="text-muted small">(provider)</span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>
                  {{ $ref->referred_name ?? '—' }}
                  @if ($ref->referred_phone)<br><span class="text-muted small">{{ $ref->referred_phone }}</span>@endif
                </td>
                <td>
                  @php $badge = ['pending' => 'warning', 'booked' => 'success', 'expired' => 'secondary'][$ref->status] ?? 'secondary'; @endphp
                  <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }}">{{ ucfirst($ref->status) }}</span>
                </td>
                <td>
                  @if ($ref->appointment)
                    <a href="{{ route('appointments.show', $ref->appointment) }}">{{ $ref->appointment->patient->name ?? 'View' }}</a>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>{{ $ref->created_at->format('M j, Y') }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-4">No referrals yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
