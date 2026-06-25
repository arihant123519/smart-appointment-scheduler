@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-7">
    <div class="card"><div class="card-body">
      <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf @method('PUT')
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone) }}"></div>
          <div class="col-md-6"><label class="form-label">Role</label>
            <select name="role" class="form-select" required>
              @foreach ($roles as $role)<option value="{{ $role->name }}" @selected($user->hasRole($role->name))>{{ ucwords(str_replace('_',' ',$role->name)) }}</option>@endforeach
            </select></div>
          <div class="col-md-6"><label class="form-label">New password <small class="text-muted">(leave blank to keep)</small></label><input type="password" name="password" class="form-control"></div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="uact" @checked($user->is_active)><label class="form-check-label" for="uact">Active</label></div>
          </div>
        </div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Save changes</button><a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a></div>
      </form>
    </div></div>
  </div></div>
@endsection
