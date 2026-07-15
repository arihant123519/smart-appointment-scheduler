@extends('layouts.app')

@section('title', 'Edit Appointment')

@section('content')
  <div class="row justify-content-center">
    <div class="col-xl-8">
      <x-card>
        <form method="POST" action="{{ route('appointments.update', $appointment) }}">
          @csrf @method('PUT')
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Patient</label>
              <select name="patient_id" class="form-select" required>
                @foreach ($patients as $p)
                  <option value="{{ $p->id }}" @selected($appointment->patient_id == $p->id)>{{ $p->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Service</label>
              <select name="service_id" class="form-select" required>
                @foreach ($services as $s)
                  <option value="{{ $s->id }}" @selected($appointment->service_id == $s->id)>{{ $s->name }} ({{ $s->duration }} min)</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Provider</label>
              <select name="provider_id" class="form-select" required>
                @foreach ($providers as $p)
                  <option value="{{ $p->id }}" @selected($appointment->provider_id == $p->id)>{{ $p->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <x-form-field name="start_at" label="Start time" type="datetime-local" :value="$appointment->start_at->format('Y-m-d\TH:i')" :required="true" />
            </div>
            <div class="col-md-6">
              <label class="form-label">Resource / Room</label>
              <select name="resource_id" class="form-select">
                <option value="">None</option>
                @foreach ($resources as $r)
                  <option value="{{ $r->id }}" @selected($appointment->resource_id == $r->id)>{{ $r->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <x-form-field name="reason" label="Reason" :value="$appointment->reason" />
            </div>
            <div class="col-12">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="2">{{ $appointment->notes }}</textarea>
            </div>
          </div>
          <div class="mt-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Save changes</button>
            <a href="{{ route('appointments.show', $appointment) }}" class="btn btn-light">Cancel</a>
          </div>
        </form>
      </x-card>
    </div>
  </div>
@endsection
