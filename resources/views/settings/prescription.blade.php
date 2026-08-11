@extends('layouts.app')

@section('title', 'Prescription Letterhead')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <x-card title="Prescription letterhead">
      <p class="text-muted small">
        Clinic name, address, phone and logo (set under Clinics &rarr; Branding) already appear on every
        prescription automatically. Use the fields below to add an optional tagline and footer text.
        Each doctor's own name, qualification, registration number and signature come from their own
        profile and are added per prescription.
      </p>
      <form method="POST" action="{{ route('settings.prescription.update') }}">
        @csrf @method('PUT')

        <div class="mb-3">
          <x-form-field name="prescription_header_note" label="Header tagline (optional)"
            help="Shown under the clinic name, e.g. a specialty tagline."
            :value="old('prescription_header_note', $clinic->prescription_header_note ?? '')" />
        </div>

        <div class="mb-3">
          <x-outline-field name="prescription_footer_text" label="Footer text" textarea rows="3"
            :value="old('prescription_footer_text', $clinic->prescription_footer_text ?? '')"
            help="Printed at the bottom of every prescription (disclaimer, contact info, etc.)." />
        </div>

        <button class="btn btn-primary">Save</button>
      </form>
    </x-card>
  </div></div>
@endsection
