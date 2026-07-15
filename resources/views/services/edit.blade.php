@extends('layouts.app')

@section('title', 'Edit Service')

@section('page_actions')
  <a href="{{ route('services.index') }}" class="btn btn-light"><i class="fi fi-rr-arrow-left me-1"></i> Back to Services</a>
@endsection

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <x-card>
      <form method="POST" action="{{ route('services.update', $service) }}">
        @csrf @method('PUT')
        @include('services._form')
        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Save changes</button>
          <a href="{{ route('services.index') }}" class="btn btn-light">Cancel</a>
        </div>
      </form>
    </x-card>
  </div></div>
@endsection
