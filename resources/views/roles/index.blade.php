@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('page_actions')
  <a href="{{ route('roles.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> New Role</a>
@endsection

@section('content')
  <p class="text-muted">Define what each role can do. Permissions are checked on every sensitive action and audit-logged (PRD §4).</p>

  <x-card bodyClass="p-0">
    <div class="table-responsive p-3">
      <table class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Role</th><th>Permissions</th><th>Users</th><th></th></tr></thead>
        <tbody>
          @forelse ($roles as $role)
            <tr>
              <td class="fw-semibold text-capitalize">
                {{ ucwords(str_replace('_',' ',$role->name)) }}
                @if ($role->name === 'system_admin')
                  <x-badge-status color="dark" label="protected" class="ms-1" />
                @endif
              </td>
              <td><x-badge-status color="primary" :label="$role->permissions_count.' permission(s)'" /></td>
              <td><x-badge-status color="secondary" :label="$role->users_count.' user(s)'" /></td>
              <td class="text-end">
                <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-light">Edit permissions</a>
                @if ($role->name !== 'system_admin' && $role->users_count === 0)
                  <form method="POST" action="{{ route('roles.destroy', $role) }}" class="d-inline" data-sas-confirm="Delete this role?" data-sas-confirm-label="Delete">
                    @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <x-empty-state colspan="4" icon="fi-rr-shield-check" title="No roles defined" description="Create a role to control what staff can access." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
