@extends('layouts.app')

@section('title', 'Add User')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-7">
    <div class="card"><div class="card-body">
      <form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ old('name') }}" required></div>
          <div class="col-md-6"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone') }}"></div>
          <div class="col-md-6"><label class="form-label">Role <span class="text-danger">*</span></label>
            <select name="role" class="form-select" required><option value="">Select…</option>
              @foreach ($roles as $role)<option value="{{ $role->name }}">{{ ucwords(str_replace('_',' ',$role->name)) }}</option>@endforeach
            </select></div>
          <div class="col-md-6"><label class="form-label">Password <span class="text-danger">*</span></label><input type="password" name="password" class="form-control" required></div>
        </div>
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Create User</button><a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a></div>
      </form>
    </div></div>
  </div></div>
@endsection
