@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('page_actions')
  <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> New Role</a>
@endsection

@section('content')
  <p class="text-muted">Define what each role can do. Permissions are checked on every sensitive action and audit-logged (PRD §4).</p>

  <div class="card"><div class="card-body p-0">
    <div class="table-responsive p-3">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>Role</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
        <tbody>
          @forelse ($roles as $role)
            <tr>
              <td class="fw-semibold text-capitalize">
                {{ ucwords(str_replace('_',' ',$role->name)) }}
                @if ($role->name === 'system_admin')<span class="badge bg-dark-subtle text-dark ms-1">protected</span>@endif
              </td>
              <td><span class="badge bg-primary-subtle text-primary">{{ $role->permissions_count }} permission(s)</span></td>
              <td><span class="badge bg-secondary-subtle text-secondary">{{ $role->users_count }} user(s)</span></td>
              <td class="text-end">
                <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-light">Edit permissions</a>
                @if ($role->name !== 'system_admin' && $role->users_count === 0)
                  <form method="POST" action="{{ route('roles.destroy', $role) }}" class="d-inline" onsubmit="return confirm('Delete this role?')">
                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No roles defined.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div></div>
@endsection
