<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Full name <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $patient->name ?? '') }}" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Email <span class="text-danger">*</span></label>
    <input type="email" name="email" class="form-control" value="{{ old('email', $patient->email ?? '') }}" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Phone</label>
    <input type="text" name="phone" class="form-control" value="{{ old('phone', $patient->phone ?? '') }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">Date of birth</label>
    <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', isset($patient) && $patient->date_of_birth ? $patient->date_of_birth->format('Y-m-d') : '') }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">Gender</label>
    <select name="gender" class="form-select">
      <option value="">—</option>
      @foreach (['male', 'female', 'other'] as $g)
        <option value="{{ $g }}" @selected(old('gender', $patient->gender ?? '') === $g)>{{ ucfirst($g) }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-12">
    <label class="form-label">Address</label>
    <input type="text" name="address" class="form-control" value="{{ old('address', $patient->address ?? '') }}">
  </div>
  @isset($patient)
    <div class="col-12">
      <div class="form-check form-switch">
        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="isActive" @checked($patient->is_active)>
        <label class="form-check-label" for="isActive">Active</label>
      </div>
    </div>
  @endisset
</div>
