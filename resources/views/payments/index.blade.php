@extends('layouts.app')

@section('title', 'Billing & Payments')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-md-4"><div class="card"><div class="card-body">
      <div class="text-muted small">Collected</div><div class="h3 fw-bold text-success">${{ number_format($totals['collected'], 2) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
      <div class="text-muted small">Pending</div><div class="h3 fw-bold text-warning">${{ number_format($totals['pending'], 2) }}</div>
    </div></div></div>
    <div class="col-md-4"><div class="card"><div class="card-body">
      <div class="text-muted small">Refunded</div><div class="h3 fw-bold text-danger">${{ number_format($totals['refunded'], 2) }}</div>
    </div></div></div>
  </div>

  <div class="card">
    <div class="card-header"><h6 class="mb-0">Transactions</h6></div>
    <div class="card-body p-0">
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
                <td>${{ number_format($pay->amount, 2) }}</td>
                <td><span class="badge bg-{{ $pay->status === 'paid' ? 'success' : ($pay->status === 'pending' ? 'warning' : 'secondary') }}-subtle text-{{ $pay->status === 'paid' ? 'success' : ($pay->status === 'pending' ? 'warning' : 'secondary') }}">{{ ucfirst($pay->status) }}</span></td>
                <td class="text-end">
                  @if ($pay->status === 'paid' && $pay->type !== 'refund')
                    <form method="POST" action="{{ route('payments.refund', $pay) }}" onsubmit="return confirm('Issue a refund?')">
                      @csrf
                      <button class="btn btn-sm btn-outline-danger">Refund</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">No transactions yet. Collect a copay from an appointment's Quick actions panel. Driver: <strong>{{ $driver }}</strong>.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
