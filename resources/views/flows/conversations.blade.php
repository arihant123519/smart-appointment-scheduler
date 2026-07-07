@extends('layouts.app')

@section('title', 'Conversations — '.$flow->name)

@section('content')
  <div class="row g-3">
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="fi fi-brands-whatsapp me-1"></i> Conversations — {{ $flow->name }}</h6>
          <a href="{{ route('flows.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fi fi-rr-arrow-left me-1"></i> Back to flows</a>
        </div>
        <div class="card-body p-0">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr><th>Started</th><th>Patient</th><th>Appointment</th><th>Status</th><th>Outcome</th><th>Last activity</th></tr>
            </thead>
            <tbody>
              @forelse ($conversations as $c)
                <tr>
                  <td>{{ $c->started_at?->format('M j, Y g:i A') }}</td>
                  <td>{{ $c->patient?->name ?? '—' }}</td>
                  <td>
                    @if ($c->appointment)
                      <a href="{{ route('appointments.show', $c->appointment) }}">{{ $c->appointment->start_at?->format('M j, g:i A') }}</a>
                      <span class="badge bg-{{ $c->appointment->status_color }} ms-1">{{ $c->appointment->status_label }}</span>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td>
                    @php $badge = ['active' => 'primary', 'completed' => 'success', 'timed_out' => 'warning', 'escalated' => 'danger', 'cancelled' => 'secondary'][$c->status] ?? 'secondary'; @endphp
                    <span class="badge bg-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $c->status)) }}</span>
                  </td>
                  <td><span class="small text-muted">{{ $c->outcome ? str_replace('_', ' ', $c->outcome) : '—' }}</span></td>
                  <td>{{ $c->last_message_at?->diffForHumans() ?? '—' }}</td>
                </tr>
              @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No conversations yet — activate this flow and trigger it to see them here.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($conversations->hasPages())
          <div class="card-footer">{{ $conversations->links() }}</div>
        @endif
      </div>
    </div>
  </div>
@endsection
