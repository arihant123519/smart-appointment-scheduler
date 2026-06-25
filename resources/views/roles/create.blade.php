@extends('layouts.app')

@section('title', 'New Role')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-10">
    <div class="card"><div class="card-body">
      <form method="POST" action="{{ route('roles.store') }}">
        @csrf
        <div class="mb-3 col-md-5">
          <label class="form-label">Role name</label>
          <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                 placeholder="e.g. nurse_lead" required>
          <div class="form-text">Lowercase letters, numbers and underscores only.</div>
        </div>

        @include('roles._permissions')

        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Create role</button>
          <a href="{{ route('roles.index') }}" class="btn btn-light">Cancel</a>
        </div>
      </form>
    </div></div>
  </div></div>
@endsection
