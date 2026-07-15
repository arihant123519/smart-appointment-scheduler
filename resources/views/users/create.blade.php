@extends('layouts.app')

@section('title', 'Add User')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-7">
    <x-card>
      <form method="POST" action="{{ route('users.store') }}">
        @csrf
        <div class="row g-3">
          <div class="col-md-6">
            <x-form-field name="name" label="Name" :required="true" :value="old('name')" />
          </div>
          <div class="col-md-6">
            <x-form-field name="email" type="email" label="Email" :required="true" :value="old('email')" />
          </div>
          <div class="col-md-6">
            <x-form-field name="phone" label="Phone" :value="old('phone')" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Role <span class="text-danger">*</span></label>
            <select name="role" class="form-select @error('role') is-invalid @enderror" required>
              <option value="">Select…</option>
              @foreach ($roles as $role)
                <option value="{{ $role->name }}">{{ ucwords(str_replace('_',' ',$role->name)) }}</option>
              @endforeach
            </select>
            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <x-form-field name="password" type="password" label="Password" :required="true" />
          </div>
        </div>
        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Create User</button>
          <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
        </div>
      </form>
    </x-card>
  </div></div>
@endsection
