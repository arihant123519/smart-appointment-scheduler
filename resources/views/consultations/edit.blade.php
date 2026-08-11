@extends('layouts.app')

@section('title', 'Consultation — Appointment #'.$appointment->id)

@section('content')
  <div class="row justify-content-center"><div class="col-xl-9">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h5 class="mb-0 fw-bold">Consultation</h5>
        <small class="text-muted">{{ $appointment->patient->name }} with {{ $appointment->provider->name }} · {{ $appointment->start_at->format('M j, Y g:i A') }}</small>
      </div>
      @if ($consultation->is_finalized)
        <span class="badge bg-success-subtle text-success px-3 py-2">Finalized {{ $consultation->finalized_at->diffForHumans() }}</span>
      @else
        <span class="badge bg-warning-subtle text-warning px-3 py-2">Draft</span>
      @endif
    </div>

    @if (! $editable)
      <div class="alert alert-info small">You're viewing this consultation in read-only mode.</div>
    @endif

    <form id="encounterForm" method="POST" action="{{ route('consultations.update', $appointment) }}">
      @csrf
      <input type="hidden" name="_method" id="formMethod" value="PUT">

      <x-card class="mb-3">
        <x-slot:title><i class="fi fi-rr-stethoscope text-primary me-1"></i> Encounter</x-slot:title>

        <div class="mb-3">
          <x-outline-field name="chief_complaint" label="Chief complaint" textarea rows="2" :value="old('chief_complaint', $consultation->chief_complaint)" @disabled(!$editable) />
        </div>

        <label class="form-label">Vitals</label>
        <div class="row g-2 mb-3">
          <div class="col-6 col-md-2">
            <input type="text" name="vitals[bp]" class="form-control form-control-sm" placeholder="BP" value="{{ old('vitals.bp', $consultation->vitals['bp'] ?? '') }}" @disabled(!$editable)>
          </div>
          <div class="col-6 col-md-2">
            <input type="text" name="vitals[pulse]" class="form-control form-control-sm" placeholder="Pulse" value="{{ old('vitals.pulse', $consultation->vitals['pulse'] ?? '') }}" @disabled(!$editable)>
          </div>
          <div class="col-6 col-md-2">
            <input type="text" name="vitals[temp]" class="form-control form-control-sm" placeholder="Temp" value="{{ old('vitals.temp', $consultation->vitals['temp'] ?? '') }}" @disabled(!$editable)>
          </div>
          <div class="col-6 col-md-2">
            <input type="text" name="vitals[weight]" class="form-control form-control-sm" placeholder="Weight" value="{{ old('vitals.weight', $consultation->vitals['weight'] ?? '') }}" @disabled(!$editable)>
          </div>
          <div class="col-6 col-md-2">
            <input type="text" name="vitals[spo2]" class="form-control form-control-sm" placeholder="SpO2" value="{{ old('vitals.spo2', $consultation->vitals['spo2'] ?? '') }}" @disabled(!$editable)>
          </div>
        </div>

        <div class="mb-3">
          <x-outline-field name="examination_notes" label="Examination notes" textarea rows="3" :value="old('examination_notes', $consultation->examination_notes)" @disabled(!$editable) />
        </div>

        <div class="mb-3">
          <x-outline-field name="diagnosis" label="Diagnosis" textarea rows="2" :value="old('diagnosis', $consultation->diagnosis)" @disabled(!$editable) />
        </div>

        <div class="row g-2">
          <div class="col-md-4">
            <x-outline-field name="follow_up_date" type="date" label="Follow-up date" :value="old('follow_up_date', optional($consultation->follow_up_date)->format('Y-m-d'))" @disabled(!$editable) />
          </div>
          <div class="col-md-8">
            <x-outline-field name="follow_up_instructions" label="Follow-up instructions" :value="old('follow_up_instructions', $consultation->follow_up_instructions)" @disabled(!$editable) />
          </div>
        </div>
      </x-card>

      <x-card class="mb-3">
        <x-slot:title><i class="fi fi-rr-prescription text-primary me-1"></i> Prescription</x-slot:title>

        <div id="itemsWrap">
          @forelse ($items as $i => $item)
            <div class="row g-2 mb-2 rx-item align-items-center">
              <div class="col-md-3"><input type="text" name="items[{{ $i }}][medicine_name]" class="form-control form-control-sm" placeholder="Medicine" value="{{ $item->medicine_name }}" @disabled(!$editable)></div>
              <div class="col-md-2"><input type="text" name="items[{{ $i }}][dosage]" class="form-control form-control-sm" placeholder="Dosage" value="{{ $item->dosage }}" @disabled(!$editable)></div>
              <div class="col-md-2"><input type="text" name="items[{{ $i }}][frequency]" class="form-control form-control-sm" placeholder="Frequency" value="{{ $item->frequency }}" @disabled(!$editable)></div>
              <div class="col-md-2"><input type="text" name="items[{{ $i }}][duration]" class="form-control form-control-sm" placeholder="Duration" value="{{ $item->duration }}" @disabled(!$editable)></div>
              <div class="col-md-2"><input type="text" name="items[{{ $i }}][instructions]" class="form-control form-control-sm" placeholder="Instructions" value="{{ $item->instructions }}" @disabled(!$editable)></div>
              @if ($editable)
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.rx-item').remove()">&times;</button></div>
              @endif
            </div>
          @empty
            @unless ($editable)
              <p class="text-muted small mb-0">No medicines prescribed.</p>
            @endunless
          @endforelse
        </div>

        @if ($editable)
          <button type="button" class="btn btn-sm btn-light mb-3" onclick="addRxRow()"><i class="fi fi-rr-plus me-1"></i> Add medicine</button>
        @endif

        <div class="mb-0">
          <x-outline-field name="prescription_notes" label="General advice / notes" textarea rows="2" :value="old('prescription_notes', $prescription->notes ?? '')" @disabled(!$editable) />
        </div>
      </x-card>

      <div class="d-flex gap-2">
        @if ($editable)
          <button type="button" class="btn btn-light" onclick="submitEncounter('{{ route('consultations.update', $appointment) }}', 'PUT')">Save Draft</button>
          <button type="button" class="btn btn-primary" onclick="submitEncounter('{{ route('consultations.finalize', $appointment) }}', 'PATCH', 'Finalize this consultation? The appointment will be marked Completed.')">
            {{ $consultation->is_finalized ? 'Save changes' : 'Finalize Consultation' }}
          </button>
        @endif
        @if ($prescription?->exists)
          <a href="{{ route('prescriptions.pdf', $appointment) }}" target="_blank" class="btn btn-outline-primary">
            <i class="fi fi-rr-print me-1"></i> Print / Download Prescription
          </a>
        @endif
        <a href="{{ route('appointments.show', $appointment) }}" class="btn btn-outline-secondary">Back</a>
      </div>
    </form>

  </div></div>

  @if ($editable)
    <template id="rxRowTemplate">
      <div class="row g-2 mb-2 rx-item align-items-center">
        <div class="col-md-3"><input type="text" name="items[__INDEX__][medicine_name]" class="form-control form-control-sm" placeholder="Medicine"></div>
        <div class="col-md-2"><input type="text" name="items[__INDEX__][dosage]" class="form-control form-control-sm" placeholder="Dosage"></div>
        <div class="col-md-2"><input type="text" name="items[__INDEX__][frequency]" class="form-control form-control-sm" placeholder="Frequency"></div>
        <div class="col-md-2"><input type="text" name="items[__INDEX__][duration]" class="form-control form-control-sm" placeholder="Duration"></div>
        <div class="col-md-2"><input type="text" name="items[__INDEX__][instructions]" class="form-control form-control-sm" placeholder="Instructions"></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.rx-item').remove()">&times;</button></div>
      </div>
    </template>
  @endif
@endsection

@push('scripts')
  <script>
    var rxIndex = {{ $items->count() }};

    function addRxRow() {
      var tpl = document.getElementById('rxRowTemplate').innerHTML.replaceAll('__INDEX__', rxIndex++);
      document.getElementById('itemsWrap').insertAdjacentHTML('beforeend', tpl);
    }

    function submitEncounter(url, method, confirmMsg) {
      if (confirmMsg && !confirm(confirmMsg)) return;
      var form = document.getElementById('encounterForm');
      form.action = url;
      document.getElementById('formMethod').value = method;
      form.submit();
    }
  </script>
@endpush
