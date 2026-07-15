@extends('layouts.app')

@section('title', 'Add Clinic')

@section('page_actions')
  <a href="{{ route('clinics.index') }}" class="btn btn-light"><i class="fi fi-rr-arrow-left me-1"></i> Back to Clinics</a>
@endsection

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <x-card>
      <form method="POST" action="{{ route('clinics.store') }}" enctype="multipart/form-data">
        @csrf
        @include('clinics._form')
        <div class="mt-4 d-flex gap-2"><button class="btn btn-primary">Create Clinic</button><a href="{{ route('clinics.index') }}" class="btn btn-light">Cancel</a></div>
      </form>
    </x-card>
  </div></div>
@endsection
