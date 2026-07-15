@extends('layouts.app')

@section('title', 'Conversations — '.$flow->name)

@section('content')
  <div class="row g-3">
    <div class="col-12">
      <x-card bodyClass="p-0">
        <x-slot:title><i class="fi fi-brands-whatsapp me-1"></i> Conversations — {{ $flow->name }}</x-slot:title>
        <x-slot:toolbar>
          <a href="{{ route('flows.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fi fi-rr-arrow-left me-1"></i> Back to flows</a>
        </x-slot:toolbar>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
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
                      <x-badge-status :color="$c->appointment->status_color" :label="$c->appointment->status_label" class="ms-1" />
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td>
                    @php $badge = ['active' => 'primary', 'completed' => 'success', 'timed_out' => 'warning', 'escalated' => 'danger', 'cancelled' => 'secondary'][$c->status] ?? 'secondary'; @endphp
                    <span class="badge badge-light-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $c->status)) }}</span>
                  </td>
                  <td><span class="small text-muted">{{ $c->outcome ? str_replace('_', ' ', $c->outcome) : '—' }}</span></td>
                  <td>{{ $c->last_message_at?->diffForHumans() ?? '—' }}</td>
                </tr>
              @empty
                <x-empty-state colspan="6" icon="fi-brands-whatsapp" title="No conversations yet" description="Activate this flow and trigger it to see them here." />
              @endforelse
            </tbody>
          </table>
        </div>
        @if ($conversations->hasPages())
          <x-slot:footer>{{ $conversations->links() }}</x-slot:footer>
        @endif
      </x-card>
    </div>
  </div>
@endsection
