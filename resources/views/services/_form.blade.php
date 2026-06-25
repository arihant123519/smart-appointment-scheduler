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
</div>
