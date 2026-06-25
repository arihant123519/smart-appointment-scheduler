@extends('layouts.app')

@section('title', 'New Appointment')

@section('content')
  <div class="row justify-content-center">
    <div class="col-xl-8">
      <div class="card">
        <div class="card-body">
          <form method="POST" action="{{ route('appointments.store') }}">
            @csrf
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Patient <span class="text-danger">*</span></label>
                <select name="patient_id" class="form-select" required>
                  <option value="">Select patient…</option>
                  @foreach ($patients as $p)
                    <option value="{{ $p->id }}" @selected(old('patient_id') == $p->id)>{{ $p->name }} — {{ $p->email }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Service <span class="text-danger">*</span></label>
                <select name="service_id" id="serviceSelect" class="form-select" required>
                  <option value="">Select service…</option>
                  @foreach ($services as $s)
                    <option value="{{ $s->id }}" data-duration="{{ $s->duration }}" @selected(old('service_id') == $s->id)>{{ $s->name }} ({{ $s->duration }} min)</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Provider <span class="text-danger">*</span></label>
                <select name="provider_id" id="providerSelect" class="form-select" required>
                  <option value="">Select provider…</option>
                  @foreach ($providers as $p)
                    <option value="{{ $p->id }}" @selected(old('provider_id') == $p->id)>{{ $p->name }} — {{ $p->specialty }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Date <span class="text-danger">*</span></label>
                <input type="date" id="dateSelect" class="form-control" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
              </div>
              <div class="col-12">
                <label class="form-label">Available slots <span class="text-danger">*</span></label>
                <div id="slotsContainer" class="d-flex flex-wrap gap-2">
                  <span class="text-muted small">Select a provider, service and date to see slots.</span>
                </div>
                <input type="hidden" name="start_at" id="startAt" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Resource / Room</label>
                <select name="resource_id" class="form-select">
                  <option value="">None</option>
                  @foreach ($resources as $r)
                    <option value="{{ $r->id }}">{{ $r->name }} ({{ ucfirst($r->type) }})</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Reason</label>
                <input type="text" name="reason" value="{{ old('reason') }}" class="form-control" placeholder="e.g. annual checkup">
              </div>
              <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
              </div>

              <div class="col-12">
                <div class="form-check form-switch">
                  <input type="checkbox" name="is_recurring" value="1" class="form-check-input" id="isRecurring">
                  <label class="form-check-label" for="isRecurring">Make this a recurring series</label>
                </div>
              </div>
              <div class="col-12 d-none" id="recurringOptions">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Frequency</label>
                    <select name="frequency" class="form-select">
                      <option value="weekly">Weekly</option>
                      <option value="biweekly">Every 2 weeks</option>
                      <option value="monthly">Monthly</option>
                      <option value="daily">Daily</option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Number of occurrences</label>
                    <input type="number" name="occurrences" class="form-control" value="4" min="2" max="52">
                  </div>
                </div>
              </div>
            </div>
            <div class="mt-4 d-flex gap-2">
              <button type="submit" class="btn btn-primary">Book Appointment</button>
              <a href="{{ route('appointments.index') }}" class="btn btn-light">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
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
        .then(r => r.json())
        .then(data => {
          if (!data.slots.length) {
            container.innerHTML = '<span class="text-danger small">No available slots for this day.</span>';
            return;
          }
          container.innerHTML = '';
          data.slots.forEach(slot => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-primary btn-sm';
            btn.textContent = slot.label;
            btn.dataset.start = slot.start;
            btn.addEventListener('click', function () {
              container.querySelectorAll('.btn').forEach(b => b.classList.replace('btn-primary', 'btn-outline-primary'));
              btn.classList.replace('btn-outline-primary', 'btn-primary');
              startAt.value = slot.start;
            });
            container.appendChild(btn);
          });
        });
    }
    [provider, service, date].forEach(el => el.addEventListener('change', loadSlots));
    loadSlots();

    const recur = document.getElementById('isRecurring');
    const recurOpts = document.getElementById('recurringOptions');
    recur.addEventListener('change', () => recurOpts.classList.toggle('d-none', !recur.checked));
  })();
</script>
@endpush
