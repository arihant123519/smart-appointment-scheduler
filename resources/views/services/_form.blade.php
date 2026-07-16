<div class="row g-3">
  <div class="col-md-8">
    <x-form-field name="name" label="Service name" :required="true" :value="old('name', $service->name ?? '')" />
  </div>
  <div class="col-md-4">
    <x-form-field name="specialty" label="Specialty" :value="old('specialty', $service->specialty ?? '')" />
  </div>
  <div class="col-md-3">
    <x-form-field name="duration" label="Duration (min)" type="number" :required="true" min="5" :value="old('duration', $service->duration ?? 30)" />
  </div>
  <div class="col-md-3">
    <x-form-field name="buffer" label="Buffer (min)" type="number" min="0" :value="old('buffer', $service->buffer ?? 0)" />
  </div>
  <div class="col-md-3">
    <x-form-field name="price" label="Price (₹)" type="number" step="0.01" min="0" :value="old('price', $service->price ?? 0)" />
  </div>
  <div class="col-md-3">
    <label class="form-label">Colour</label>
    <input type="color" name="color" class="form-control form-control-color @error('color') is-invalid @enderror" value="{{ old('color', $service->color ?? '#2563EB') }}">
    @error('color')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
    <x-form-field name="recall_window_days" label="Follow-up recall (days after a completed visit)" type="number" min="1" max="365"
      placeholder="Leave blank to disable" help="Nudges the patient to book a follow-up if they haven't by then."
      :value="old('recall_window_days', $service->recall_window_days ?? '')" />
  </div>
  <div class="col-md-6">
    <x-form-field name="recall_cadence_days" label="Care-plan cadence (days between visits)" type="number" min="1" max="365"
      placeholder="Leave blank to disable" help="For ongoing treatment plans — checks in if a patient falls behind schedule."
      :value="old('recall_cadence_days', $service->recall_cadence_days ?? '')" />
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
        <x-form-field name="deposit_amount" label="Deposit amount (₹)" type="number" step="0.01" min="0.5"
          :value="old('deposit_amount', $service->deposit_amount ?? '')" />
      </div>
      <div class="col-md-6">
        <x-form-field name="deposit_forfeit_hours" label="Forfeit if cancelled within (hours)" type="number" min="0" max="720"
          placeholder="Leave blank to always refund" :value="old('deposit_forfeit_hours', $service->deposit_forfeit_hours ?? '')" />
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
        <x-form-field name="overbooking_margin" label="Extra bookings allowed per slot" type="number" min="1" max="5"
          :value="old('overbooking_margin', $service->overbooking_margin ?? 1)" />
      </div>
      <div class="col-md-6 d-flex align-items-end">
        <div class="form-text mb-2">Only ever applies to a specific day/hour slot with a demonstrated high no-show rate for this service (≥25% over the last 90 days) — never applied broadly.</div>
      </div>
    </div>
  </div>
</div>
