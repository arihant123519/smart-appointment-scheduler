@extends('layouts.app')

@section('title', 'New Role')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-10">
    <x-card>
      <form method="POST" action="{{ route('roles.store') }}">
        @csrf
        <div class="mb-3 col-md-5">
          <x-form-field name="name" label="Role name" :required="true" :value="old('name')"
            placeholder="e.g. nurse_lead" help="Lowercase letters, numbers and underscores only." />
        </div>

        @include('roles._permissions')

        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Create role</button>
          <a href="{{ route('roles.index') }}" class="btn btn-light">Cancel</a>
        </div>
      </form>
    </x-card>
  </div></div>
@endsection
