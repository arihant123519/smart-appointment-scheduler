@extends('layouts.app')

@section('title', 'Book an Appointment')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-8">
    <div class="card"><div class="card-body">
      <p class="text-muted">Choose a service, provider and an available time. You'll get a confirmation right away.</p>
      <form method="POST" action="{{ route('booking.store') }}">
        @csrf
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Service <span class="text-danger">*</span></label>
            <select name="service_id" id="serviceSelect" class="form-select" required>
              <option value="">Select service…</option>
              @foreach ($services as $s)<option value="{{ $s->id }}" data-duration="{{ $s->duration }}">{{ $s->name }} ({{ $s->duration }} min · ${{ number_format($s->price,2) }})</option>@endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Provider <span class="text-danger">*</span></label>
            <select name="provider_id" id="providerSelect" class="form-select" required>
              <option value="">Select provider…</option>
              @foreach ($providers as $p)<option value="{{ $p->id }}">{{ $p->name }} — {{ $p->specialty }}</option>@endforeach
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Date <span class="text-danger">*</span></label>
            <input type="date" id="dateSelect" class="form-control" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
          </div>
          <div class="col-12">
            <label class="form-label">Available times <span class="text-danger">*</span></label>
            <div id="slotsContainer" class="d-flex flex-wrap gap-2"><span class="text-muted small">Select service, provider and date.</span></div>
            <input type="hidden" name="start_at" id="startAt" required>
          </div>
          <div class="col-12">
            <label class="form-label">Reason for visit</label>
            <input type="text" name="reason" class="form-control" value="{{ old('reason') }}">
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
            btn.addEventListener('click', function () {
              container.querySelectorAll('.btn').forEach(b => b.classList.replace('btn-primary','btn-outline-primary'));
              btn.classList.replace('btn-outline-primary','btn-primary'); startAt.value = slot.start;
            });
            container.appendChild(btn);
          });
        });
    }
    [provider, service, date].forEach(el => el.addEventListener('change', loadSlots));
  })();
</script>
@endpush
