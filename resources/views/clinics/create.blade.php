@extends('layouts.app')

@section('title', 'Add Clinic')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8"><div class="card"><div class="card-body">
    <form method="POST" action="{{ route('clinics.store') }}">
      @csrf
      @include('clinics._form')
      <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Create Clinic</button><a href="{{ route('clinics.index') }}" class="btn btn-light">Cancel</a></div>
    </form>
  </div></div></div></div>
@endsection
