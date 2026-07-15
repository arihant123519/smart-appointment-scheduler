@extends('layouts.app')

@section('title', 'Users & Roles')

@section('page_actions')
  <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add User</a>
@endsection

@section('content')
  <x-card bodyClass="pb-2" class="mb-3">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <x-form-field name="q" label="Search" :value="request('q')" placeholder="Name or email" />
      </div>
      <div class="col-md-4">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <option value="">All</option>
          @foreach ($roles as $role)
            <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ ucwords(str_replace('_',' ',$role->name)) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </form>
  </x-card>

  <x-card bodyClass="p-0">
    <div class="table-responsive p-3">
      <table class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Roles</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($users as $u)
            <tr>
              <td class="d-flex align-items-center gap-2">
                <img src="{{ $u->avatar_url }}" class="sas-avatar sas-avatar-sm" width="32" height="32">
                <span class="fw-semibold">{{ $u->name }}</span>
              </td>
              <td>{{ $u->email }}</td>
              <td>
                @foreach ($u->roles as $role)
                  <x-badge-status color="primary" :label="ucwords(str_replace('_',' ',$role->name))" />
                @endforeach
              </td>
              <td>
                <x-badge-status :color="$u->is_active ? 'success' : 'secondary'" :label="$u->is_active ? 'Active' : 'Inactive'" />
              </td>
              <td class="text-end">
                <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-light">Edit</a>
                @if ($u->id !== auth()->id())
                  <form method="POST" action="{{ route('users.destroy', $u) }}" class="d-inline" data-sas-confirm="Delete this user?" data-sas-confirm-label="Delete">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <x-empty-state colspan="5" icon="fi-rr-users-alt" title="No users found" description="Try a different search or add a new user." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
