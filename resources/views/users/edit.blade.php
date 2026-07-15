@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-7">
    <x-card>
      <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf @method('PUT')
        <div class="row g-3">
          <div class="col-md-6">
            <x-form-field name="name" label="Name" :required="true" :value="old('name', $user->name)" />
          </div>
          <div class="col-md-6">
            <x-form-field name="email" type="email" label="Email" :required="true" :value="old('email', $user->email)" />
          </div>
          <div class="col-md-6">
            <x-form-field name="phone" label="Phone" :value="old('phone', $user->phone)" />
          </div>
          <div class="col-md-6">
            <label class="form-label">Role</label>
            <select name="role" class="form-select @error('role') is-invalid @enderror" required>
              @foreach ($roles as $role)
                <option value="{{ $role->name }}" @selected($user->hasRole($role->name))>{{ ucwords(str_replace('_',' ',$role->name)) }}</option>
              @endforeach
            </select>
            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-6">
            <x-form-field name="password" type="password" label="New password" help="Leave blank to keep the current password." />
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div class="form-check form-switch">
              <input type="checkbox" name="is_active" value="1" class="form-check-input" id="uact" @checked($user->is_active)>
              <label class="form-check-label" for="uact">Active</label>
            </div>
          </div>
        </div>
        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Save changes</button>
          <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
        </div>
      </form>
    </x-card>
  </div></div>
@endsection
