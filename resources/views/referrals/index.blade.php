@extends('layouts.app')

@section('title', 'Referrals')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <x-stat-widget label="Total referrals" :value="$stats['total']" icon="fi-rr-share" bg="bg-primary-subtle" fg="text-primary" />
    </div>
    <div class="col-md-4">
      <x-stat-widget label="Converted to a booking" :value="$stats['booked']" icon="fi-rr-check" bg="bg-success-subtle" fg="text-success" />
    </div>
    <div class="col-md-4">
      <x-stat-widget label="Conversion rate" :value="$stats['conversion_rate'].'%'" icon="fi-rr-chart-line-up" bg="bg-info-subtle" fg="text-info" />
    </div>
  </div>

  @if ($topReferrers->isNotEmpty())
    <x-card title="Top referrers" class="mb-3" bodyClass="p-0">
      <div class="table-responsive">
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
    </x-card>
  @endif

  <x-card title="Referrals" bodyClass="p-0">
    <x-slot:toolbar>
      <span class="badge badge-light-primary">{{ $referrals->count() }}</span>
    </x-slot:toolbar>
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
                <x-badge-status :color="$badge" :label="ucfirst($ref->status)" />
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
            <x-empty-state colspan="5" icon="fi-rr-share" title="No referrals yet" description="Patients who refer others will show up here." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
