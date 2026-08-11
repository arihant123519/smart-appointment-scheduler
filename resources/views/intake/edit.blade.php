@extends('layouts.app')

@section('title', 'Intake Form')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <x-card>
      <p class="text-muted">Pre-visit questionnaire for your appointment with {{ $appointment->provider->name }} on
        {{ $appointment->start_at->format('M j, Y g:i A') }}.</p>
      <form method="POST" action="{{ route('intake.update', $appointment) }}">
        @csrf @method('PUT')
        @foreach ($schema as $key => $label)
          <div class="mb-3">
            <x-outline-field name="responses[{{ $key }}]" id="resp_{{ $key }}" label="{{ $label }}" textarea rows="2"
              :value="old('responses.'.$key, $intake->responses[$key] ?? '')" />
          </div>
        @endforeach
        <hr>
        <x-form-field name="signature_name" label="Electronic signature (type your full name)" :value="old('signature_name', $intake->signature_name ?? '')" :required="true"
          help="By typing your name you consent to treatment and confirm the information is accurate." />
        <button class="btn btn-primary mt-3"><i class="fi fi-rr-check me-1"></i> Submit &amp; Sign</button>
        <a href="{{ url()->previous() }}" class="btn btn-light mt-3">Back</a>
      </form>
    </x-card>
  </div></div>
@endsection
