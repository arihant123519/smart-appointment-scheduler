@extends('layouts.app')

@section('title', 'Add Provider')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <div class="card"><div class="card-body">
      <form method="POST" action="{{ route('providers.store') }}">
        @csrf
        @include('providers._form')
        <div class="mt-4 d-flex gap-2">
          <button class="btn btn-primary">Create Provider</button>
          <a href="{{ route('providers.index') }}" class="btn btn-light">Cancel</a>
        </div>
      </form>
    </div></div>
  </div></div>
@endsection
