@extends('layouts.app')

@section('title', 'Users & Roles')

@section('page_actions')
  <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Add User</a>
@endsection

@section('content')
  <div class="card mb-3"><div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4"><label class="form-label small text-muted">Search</label>
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name or email"></div>
      <div class="col-md-4"><label class="form-label small text-muted">Role</label>
        <select name="role" class="form-select"><option value="">All</option>
          @foreach ($roles as $role)<option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ ucwords(str_replace('_',' ',$role->name)) }}</option>@endforeach
        </select></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
    </form>
  </div></div>

  <div class="card"><div class="card-body p-0">
    <div class="table-responsive p-3">
      <table class="table table-hover align-middle mb-0 datatable">
        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Roles</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @forelse ($users as $u)
            <tr>
              <td class="d-flex align-items-center gap-2"><img src="{{ $u->avatar_url }}" class="rounded-circle" width="32" height="32"><span class="fw-semibold">{{ $u->name }}</span></td>
              <td>{{ $u->email }}</td>
              <td>@foreach ($u->roles as $role)<span class="badge bg-primary-subtle text-primary">{{ ucwords(str_replace('_',' ',$role->name)) }}</span> @endforeach</td>
              <td><span class="badge bg-{{ $u->is_active ? 'success' : 'secondary' }}-subtle text-{{ $u->is_active ? 'success' : 'secondary' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
              <td class="text-end">
                <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-light">Edit</a>
                @if ($u->id !== auth()->id())
                  <form method="POST" action="{{ route('users.destroy', $u) }}" class="d-inline" onsubmit="return confirm('Delete user?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-center text-muted py-4">No users found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div></div>
@endsection
