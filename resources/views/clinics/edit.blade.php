@extends('layouts.app')

@section('title', 'Edit Clinic')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8"><div class="card"><div class="card-body">
    <form method="POST" action="{{ route('clinics.update', $clinic) }}" enctype="multipart/form-data">
      @csrf @method('PUT')
      @include('clinics._form')
      <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Save changes</button><a href="{{ route('clinics.index') }}" class="btn btn-light">Cancel</a></div>
    </form>
  </div></div></div></div>
@endsection
