@extends('layouts.app')

@section('title', 'Intake Form')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <div class="card"><div class="card-body">
      <p class="text-muted">Pre-visit questionnaire for your appointment with {{ $appointment->provider->name }} on
        {{ $appointment->start_at->format('M j, Y g:i A') }}.</p>
      <form method="POST" action="{{ route('intake.update', $appointment) }}">
        @csrf @method('PUT')
        @foreach ($schema as $key => $label)
          <div class="mb-3">
            <label class="form-label">{{ $label }}</label>
            <textarea name="responses[{{ $key }}]" class="form-control" rows="2">{{ old("responses.$key", $intake->responses[$key] ?? '') }}</textarea>
          </div>
        @endforeach
        <hr>
        <div class="mb-3">
          <label class="form-label">Electronic signature (type your full name) <span class="text-danger">*</span></label>
          <input type="text" name="signature_name" class="form-control" value="{{ old('signature_name', $intake->signature_name ?? '') }}" required>
          <small class="text-muted">By typing your name you consent to treatment and confirm the information is accurate.</small>
        </div>
        <button class="btn btn-primary">Submit &amp; Sign</button>
        <a href="{{ url()->previous() }}" class="btn btn-light">Back</a>
      </form>
    </div></div>
  </div></div>
@endsection
