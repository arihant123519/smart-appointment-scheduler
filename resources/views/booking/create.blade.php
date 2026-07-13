@extends('layouts.app')

@section('title', 'Book an Appointment')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <div class="card"><div class="card-body">
      @if (($introVariant ?? 'control') === 'reassuring')
        <p class="text-muted">Pick what works for you — providers, dates and times below are all real, live availability, so whatever you choose is yours the moment you confirm it.</p>
      @else
        <p class="text-muted">Choose a service, provider and an available time. You'll get a confirmation right away.</p>
      @endif
      <form method="POST" action="{{ route('booking.store') }}">
        @csrf
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="serviceSelect">Service <span class="text-danger">*</span></label>
            <select name="service_id" id="serviceSelect" class="form-select" required>
              <option value="">Select service…</option>
              @foreach ($services as $s)<option value="{{ $s->id }}" data-duration="{{ $s->duration }}" @selected(old('service_id', $preselectedServiceId ?? null) == $s->id)>{{ $s->name }} ({{ $s->duration }} min · ${{ number_format($s->price,2) }})</option>@endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="providerSelect">Provider <span class="text-danger">*</span></label>
            <select name="provider_id" id="providerSelect" class="form-select" required>
              <option value="">Select provider…</option>
              @foreach ($providers as $p)<option value="{{ $p->id }}">{{ $p->name }} — {{ $p->specialty }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="dateSelect">Date <span class="text-danger">*</span></label>
            <input type="date" id="dateSelect" class="form-control" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
          </div>
          <div class="col-12">
            <span class="form-label" id="slotsLabel">Available times <span class="text-danger">*</span></span>
            <div id="slotsContainer" class="d-flex flex-wrap gap-2" role="group" aria-labelledby="slotsLabel"><span class="text-muted small">Select service, provider and date.</span></div>
            <input type="hidden" name="start_at" id="startAt" required>
          </div>
          <div class="col-12">
            <label class="form-label" for="reasonInput">Reason for visit</label>
            <input type="text" name="reason" id="reasonInput" class="form-control" value="{{ old('reason') }}">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="bookForSomeoneElse" @checked(old('booked_for_name'))>
              <label class="form-check-label" for="bookForSomeoneElse">Booking for someone else?</label>
            </div>
            <div id="bookForSomeoneElseFields" class="row g-3 mt-1 {{ old('booked_for_name') ? '' : 'd-none' }}">
              <div class="col-md-6">
                <label class="form-label" for="bookedForName">Their name <span class="text-danger">*</span></label>
                <input type="text" name="booked_for_name" id="bookedForName" class="form-control" value="{{ old('booked_for_name') }}">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="bookedForRelationship">Relationship to you <span class="text-danger">*</span></label>
                <input type="text" name="booked_for_relationship" id="bookedForRelationship" class="form-control" placeholder="e.g. Father, Child, Spouse" value="{{ old('booked_for_relationship') }}">
              </div>
              <div class="col-12 text-muted small">You'll keep receiving the reminders and confirmations for this visit — we'll just note it's for {{ old('booked_for_name') ?: 'them' }}.</div>
            </div>
          </div>
        </div>
        <div class="mt-4"><button class="btn btn-primary">Confirm Booking</button></div>
      </form>
    </div></div>
  </div></div>
@endsection

@push('scripts')
<script>
  (function () {
    const provider = document.getElementById('providerSelect');
    const service = document.getElementById('serviceSelect');
    const date = document.getElementById('dateSelect');
    const container = document.getElementById('slotsContainer');
    const startAt = document.getElementById('startAt');
    function loadSlots() {
      if (!provider.value || !service.value || !date.value) return;
      container.innerHTML = '<span class="text-muted small">Loading…</span>';
      const params = new URLSearchParams({ provider_id: provider.value, service_id: service.value, date: date.value });
      fetch('{{ route('appointments.slots') }}?' + params.toString())
        .then(r => r.json()).then(data => {
          if (!data.slots.length) { container.innerHTML = '<span class="text-danger small">No available times this day.</span>'; return; }
          container.innerHTML = '';
          data.slots.forEach(slot => {
            const btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'btn btn-outline-primary btn-sm'; btn.textContent = slot.label;
            btn.setAttribute('aria-pressed', 'false');
            btn.addEventListener('click', function () {
              container.querySelectorAll('.btn').forEach(b => { b.classList.replace('btn-primary','btn-outline-primary'); b.setAttribute('aria-pressed', 'false'); });
              btn.classList.replace('btn-outline-primary','btn-primary'); btn.setAttribute('aria-pressed', 'true'); startAt.value = slot.start;
            });
            container.appendChild(btn);
          });
        });
    }
    [provider, service, date].forEach(el => el.addEventListener('change', loadSlots));

    const toggle = document.getElementById('bookForSomeoneElse');
    const fields = document.getElementById('bookForSomeoneElseFields');
    const nameInput = document.getElementById('bookedForName');
    const relInput = document.getElementById('bookedForRelationship');
    function syncFamilyFields() {
      const on = toggle.checked;
      fields.classList.toggle('d-none', !on);
      nameInput.required = on;
      relInput.required = on;
      if (!on) { nameInput.value = ''; relInput.value = ''; }
    }
    toggle.addEventListener('change', syncFamilyFields);
    syncFamilyFields();
  })();
</script>
@endpush
