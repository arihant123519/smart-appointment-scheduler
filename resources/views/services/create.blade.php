@extends('layouts.app')

@section('title', 'Add Service')

@section('page_actions')
  <a href="{{ route('services.index') }}" class="btn btn-light"><i class="fi fi-rr-arrow-left me-1"></i> Back to Services</a>
@endsection

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <x-card>
      <form method="POST" action="{{ route('services.store') }}">
        @csrf
        @include('services._form')
        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Create Service</button>
          <a href="{{ route('services.index') }}" class="btn btn-light">Cancel</a>
        </div>
      </form>
    </x-card>
  </div></div>
@endsection
