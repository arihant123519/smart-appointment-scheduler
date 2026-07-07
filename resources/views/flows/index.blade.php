@extends('layouts.app')

@section('title', 'WhatsApp Flows')

@section('content')
  <div class="row g-3">
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="fi fi-brands-whatsapp me-1"></i> WhatsApp conversation flows</h6>
          <a href="{{ route('flows.create') }}" class="btn btn-primary btn-sm"><i class="fi fi-rr-plus me-1"></i> New flow</a>
        </div>
        <div class="card-body p-0">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr><th>Name</th><th>Trigger</th><th>Status</th><th>Conversations</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
              @forelse ($flows as $flow)
                <tr>
                  <td>{{ $flow->name }}</td>
                  <td>
                    @if ($flow->trigger_event)
                      <span class="badge bg-light text-dark border">{{ $events[$flow->trigger_event] ?? $flow->trigger_event }}</span>
                    @else
                      <span class="text-muted small">Not set</span>
                    @endif
                  </td>
                  <td>
                    @php $badge = ['draft' => 'secondary', 'active' => 'success', 'archived' => 'dark'][$flow->status] ?? 'secondary'; @endphp
                    <span class="badge bg-{{ $badge }}">{{ ucfirst($flow->status) }}</span>
                  </td>
                  <td>
                    <a href="{{ route('flows.conversations', $flow) }}" class="text-decoration-none">{{ $flow->conversations_count }}</a>
                  </td>
                  <td class="text-end text-nowrap">
                    @if ($flow->status !== 'active')
                      <form method="POST" action="{{ route('flows.activate', $flow) }}" class="d-inline">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-success" title="Activate"><i class="fi fi-rr-check-circle"></i> Activate</button>
                      </form>
                    @endif
                    <a href="{{ route('flows.edit', $flow) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fi fi-rr-edit"></i></a>
                    <form method="POST" action="{{ route('flows.destroy', $flow) }}" class="d-inline" onsubmit="return confirm('Delete this flow? Its past conversations stay on record.');">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fi fi-rr-trash"></i></button>
                    </form>
                  </td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No flows yet — build one to automate WhatsApp conversations like reschedule confirmations.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
