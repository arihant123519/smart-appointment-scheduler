@extends('layouts.app')

@section('title', 'Billing & Payments')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <x-card class="sas-card-hover">
        <div class="text-muted small">Collected</div><div class="h3 fw-bold text-success">${{ number_format($totals['collected'], 2) }}</div>
      </x-card>
    </div>
    <div class="col-md-4">
      <x-card class="sas-card-hover">
        <div class="text-muted small">Pending</div><div class="h3 fw-bold text-warning">${{ number_format($totals['pending'], 2) }}</div>
      </x-card>
    </div>
    <div class="col-md-4">
      <x-card class="sas-card-hover">
        <div class="text-muted small">Refunded</div><div class="h3 fw-bold text-danger">${{ number_format($totals['refunded'], 2) }}</div>
      </x-card>
    </div>
  </div>

  @if ($pendingDeposits->isNotEmpty())
    <x-card bodyClass="p-0" class="mb-3">
      <x-slot:title>Pending deposits</x-slot:title>
      <x-slot:toolbar>
        <span class="badge badge-light-warning">{{ $pendingDeposits->count() }}</span>
      </x-slot:toolbar>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th>Patient</th><th>Appointment</th><th>Amount</th><th>Expires</th><th></th></tr></thead>
          <tbody>
            @foreach ($pendingDeposits as $dep)
              <tr>
                <td>{{ $dep->patient->name ?? '—' }}</td>
                <td>
                  @if ($dep->appointment)
                    <a href="{{ route('appointments.show', $dep->appointment) }}">{{ $dep->appointment->start_at->format('M j, g:i A') }}</a>
                    with {{ $dep->appointment->provider->name ?? '—' }}
                  @else
                    —
                  @endif
                </td>
                <td>{{ $dep->currency === 'INR' ? '₹' : '$' }}{{ number_format($dep->amount, 2) }}</td>
                <td class="{{ $dep->expires_at && $dep->expires_at->isPast() ? 'text-danger' : 'text-muted' }} small">
                  {{ $dep->expires_at?->diffForHumans() ?? '—' }}
                </td>
                <td class="text-end">
                  <form method="POST" action="{{ route('payments.confirm-deposit', $dep) }}" data-sas-confirm="Confirm this deposit was collected?" data-sas-confirm-label="Confirm">
                    @csrf
                    <button class="btn btn-sm btn-success">Mark collected</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </x-card>
  @endif

  <x-card bodyClass="p-0">
    <x-slot:title>Transactions</x-slot:title>
    <div class="table-responsive p-3">
      <table class="table align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Date</th><th>Patient</th><th>Type</th><th>Method</th><th>Amount</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($payments as $pay)
            <tr>
              <td>{{ $pay->created_at->format('M j, Y') }}</td>
              <td>{{ $pay->patient->name ?? '—' }}</td>
              <td>{{ ucfirst(str_replace('_', ' ', $pay->type)) }}</td>
              <td>{{ ucfirst($pay->method ?? '—') }}</td>
              <td>{{ $pay->currency === 'INR' ? '₹' : '$' }}{{ number_format($pay->amount, 2) }}</td>
              @php
                $badge = match ($pay->status) {
                    'paid' => 'success',
                    'pending' => 'warning',
                    'forfeited', 'failed' => 'danger',
                    default => 'secondary',
                };
              @endphp
              <td><x-badge-status :color="$badge" :label="ucfirst($pay->status)" /></td>
              <td class="text-end">
                @if ($pay->status === 'paid' && $pay->type !== 'refund')
                  <form method="POST" action="{{ route('payments.refund', $pay) }}" data-sas-confirm="Issue a refund?" data-sas-confirm-label="Refund">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger">Refund</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <x-empty-state colspan="7" icon="fi-rr-usd-circle" title="No transactions yet" description="Collect a copay from an appointment's Quick actions panel. Driver: {{ $driver }}." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
