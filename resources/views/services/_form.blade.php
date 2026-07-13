<div class="row g-3">
  <div class="col-md-8">
    <label class="form-label">Service name <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $service->name ?? '') }}" required>
  </div>
  <div class="col-md-4">
    <label class="form-label">Specialty</label>
    <input type="text" name="specialty" class="form-control" value="{{ old('specialty', $service->specialty ?? '') }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">Duration (min) <span class="text-danger">*</span></label>
    <input type="number" name="duration" class="form-control" value="{{ old('duration', $service->duration ?? 30) }}" min="5" required>
  </div>
  <div class="col-md-3">
    <label class="form-label">Buffer (min)</label>
    <input type="number" name="buffer" class="form-control" value="{{ old('buffer', $service->buffer ?? 0) }}" min="0">
  </div>
  <div class="col-md-3">
    <label class="form-label">Price ($)</label>
    <input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', $service->price ?? 0) }}" min="0">
  </div>
  <div class="col-md-3">
    <label class="form-label">Colour</label>
    <input type="color" name="color" class="form-control form-control-color" value="{{ old('color', $service->color ?? '#5955D1') }}">
  </div>
  <div class="col-md-6">
    <div class="form-check form-switch">
      <input type="checkbox" name="telehealth" value="1" class="form-check-input" id="svcTele" @checked(old('telehealth', $service->telehealth ?? false))>
      <label class="form-check-label" for="svcTele">Telehealth available</label>
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-check form-switch">
      <input type="checkbox" name="is_active" value="1" class="form-check-input" id="svcAct" @checked(old('is_active', $service->is_active ?? true))>
      <label class="form-check-label" for="svcAct">Active</label>
    </div>
  </div>

  <div class="col-12"><hr class="my-1"></div>

  <div class="col-md-6">
    <label class="form-label">Follow-up recall (days after a completed visit)</label>
    <input type="number" name="recall_window_days" class="form-control" min="1" max="365"
           value="{{ old('recall_window_days', $service->recall_window_days ?? '') }}"
           placeholder="Leave blank to disable">
    <div class="form-text">Nudges the patient to book a follow-up if they haven't by then.</div>
  </div>
  <div class="col-md-6">
    <label class="form-label">Care-plan cadence (days between visits)</label>
    <input type="number" name="recall_cadence_days" class="form-control" min="1" max="365"
           value="{{ old('recall_cadence_days', $service->recall_cadence_days ?? '') }}"
           placeholder="Leave blank to disable">
    <div class="form-text">For ongoing treatment plans — checks in if a patient falls behind schedule.</div>
  </div>

  <div class="col-12"><hr class="my-1"></div>

  <div class="col-md-4">
    <div class="form-check form-switch">
      <input type="checkbox" name="deposit_required" value="1" class="form-check-input" id="svcDeposit"
             @checked(old('deposit_required', $service->deposit_required ?? false))
             onchange="document.getElementById('svcDepositFields').classList.toggle('d-none', !this.checked)">
      <label class="form-check-label" for="svcDeposit">Require a deposit at booking</label>
    </div>
  </div>
  <div class="col-md-8">
    <div id="svcDepositFields" class="row g-3 {{ old('deposit_required', $service->deposit_required ?? false) ? '' : 'd-none' }}">
      <div class="col-md-6">
        <label class="form-label">Deposit amount ($)</label>
        <input type="number" step="0.01" name="deposit_amount" class="form-control" min="0.5"
               value="{{ old('deposit_amount', $service->deposit_amount ?? '') }}">
      </div>
      <div class="col-md-6">
        <label class="form-label">Forfeit if cancelled within (hours)</label>
        <input type="number" name="deposit_forfeit_hours" class="form-control" min="0" max="720"
               value="{{ old('deposit_forfeit_hours', $service->deposit_forfeit_hours ?? '') }}"
               placeholder="Leave blank to always refund">
      </div>
    </div>
  </div>

  <div class="col-12"><hr class="my-1"></div>

  <div class="col-md-4">
    <div class="form-check form-switch">
      <input type="checkbox" name="overbooking_enabled" value="1" class="form-check-input" id="svcOverbook"
             @checked(old('overbooking_enabled', $service->overbooking_enabled ?? false))
             onchange="document.getElementById('svcOverbookFields').classList.toggle('d-none', !this.checked)">
      <label class="form-check-label" for="svcOverbook">Allow controlled overbooking</label>
    </div>
  </div>
  <div class="col-md-8">
    <div id="svcOverbookFields" class="row g-3 {{ old('overbooking_enabled', $service->overbooking_enabled ?? false) ? '' : 'd-none' }}">
      <div class="col-md-6">
        <label class="form-label">Extra bookings allowed per slot</label>
        <input type="number" name="overbooking_margin" class="form-control" min="1" max="5"
               value="{{ old('overbooking_margin', $service->overbooking_margin ?? 1) }}">
      </div>
      <div class="col-md-6 d-flex align-items-end">
        <div class="form-text mb-2">Only ever applies to a specific day/hour slot with a demonstrated high no-show rate for this service (≥25% over the last 90 days) — never applied broadly.</div>
      </div>
    </div>
  </div>
</div>
