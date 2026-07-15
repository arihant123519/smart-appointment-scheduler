@extends('layouts.app')

@section('title', 'Edit Patient')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <x-card>
      <form method="POST" action="{{ route('patients.update', $patient) }}">
        @csrf @method('PUT')
        @include('patients._form')
        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Save changes</button>
          <a href="{{ route('patients.show', $patient) }}" class="btn btn-light">Cancel</a>
        </div>
      </form>
    </x-card>
  </div></div>
@endsection
