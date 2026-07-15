@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-10">
    <x-card>
      <div class="mb-3">
        <span class="text-muted small">Role</span>
        <h5 class="mb-0 text-capitalize">{{ ucwords(str_replace('_',' ',$role->name)) }}</h5>
      </div>

      <form method="POST" action="{{ route('roles.update', $role) }}">
        @csrf @method('PUT')

        @include('roles._permissions')

        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Save permissions</button>
          <a href="{{ route('roles.index') }}" class="btn btn-light">Cancel</a>
        </div>
      </form>
    </x-card>
  </div></div>
@endsection
