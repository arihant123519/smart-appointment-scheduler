@extends('layouts.app')

@section('title', 'WhatsApp Flows')

@section('content')
  <div class="row g-3">
    <div class="col-12">
      <x-card bodyClass="p-0">
        <x-slot:title><i class="fi fi-brands-whatsapp me-1"></i> WhatsApp conversation flows</x-slot:title>
        <x-slot:toolbar>
          <a href="{{ route('flows.create') }}" class="btn btn-primary btn-sm"><i class="fi fi-rr-plus me-1"></i> New flow</a>
        </x-slot:toolbar>
        <div class="table-responsive">
          <table class="table align-middle mb-0 datatable">
            <thead>
              <tr><th>Name</th><th>Trigger</th><th>Status</th><th>Conversations</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
              @forelse ($flows as $flow)
                <tr>
                  <td>{{ $flow->name }}</td>
                  <td>
                    @if ($flow->trigger_event)
                      <span class="badge badge-light-secondary">{{ $events[$flow->trigger_event] ?? $flow->trigger_event }}</span>
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
                    <form method="POST" action="{{ route('flows.destroy', $flow) }}" class="d-inline" data-sas-confirm="Delete this flow? Its past conversations stay on record." data-sas-confirm-label="Delete">
                      @csrf @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger" title="Delete"><i class="fi fi-rr-trash"></i></button>
                    </form>
                  </td>
                </tr>
              @empty
                <x-empty-state colspan="5" icon="fi-rr-comment-alt" title="No flows yet" description="Build one to automate WhatsApp conversations like reschedule confirmations." />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>
  </div>
@endsection
