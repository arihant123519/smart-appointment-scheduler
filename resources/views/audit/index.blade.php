@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
  <div class="card"><div class="card-body p-0">
    <div class="table-responsive p-3">
      <table class="table align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
        <tbody>
          @forelse ($logs as $log)
            <tr>
              <td>{{ $log->created_at->format('M j, Y g:i A') }}</td>
              <td>{{ $log->user->name ?? 'System' }}</td>
              <td><span class="badge bg-light text-dark">{{ ucwords(str_replace('_',' ',$log->action)) }}</span></td>
              <td>{{ $log->entity }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
              <td class="text-muted small">{{ $log->ip }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No audit entries yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div></div>
@endsection
