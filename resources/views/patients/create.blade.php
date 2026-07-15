@extends('layouts.app')

@section('title', 'Add Patient')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <x-card>
      <form method="POST" action="{{ route('patients.store') }}">
        @csrf
        @include('patients._form')
        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Create Patient</button>
          <a href="{{ route('patients.index') }}" class="btn btn-light">Cancel</a>
        </div>
        <p class="text-muted small mt-2 mb-0">Default password is <code>password</code>; the patient can change it after first login.</p>
      </form>
    </x-card>
  </div></div>
@endsection
