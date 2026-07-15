@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
  <x-card bodyClass="p-0">
    <div class="table-responsive p-3">
      <table class="table align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
        <tbody>
          @forelse ($logs as $log)
            <tr>
              <td>{{ $log->created_at->format('M j, Y g:i A') }}</td>
              <td>{{ $log->user->name ?? 'System' }}</td>
              <td><x-badge-status color="secondary" :label="ucwords(str_replace('_',' ',$log->action))" /></td>
              <td>{{ $log->entity }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
              <td class="text-muted small">{{ $log->ip }}</td>
            </tr>
          @empty
            <x-empty-state colspan="5" icon="fi-rr-list-check" title="No audit entries yet" description="Sensitive actions will show up here as they happen." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
