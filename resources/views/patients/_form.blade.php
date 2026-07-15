<div class="row g-3">
  <div class="col-md-6">
    <x-form-field name="name" label="Full name" :required="true" :value="old('name', $patient->name ?? '')" />
  </div>
  <div class="col-md-6">
    <x-form-field name="email" type="email" label="Email" :required="true" :value="old('email', $patient->email ?? '')" />
  </div>
  <div class="col-md-6">
    <x-form-field name="phone" label="Phone" :value="old('phone', $patient->phone ?? '')" />
  </div>
  <div class="col-md-3">
    <x-form-field name="date_of_birth" type="date" label="Date of birth" :value="old('date_of_birth', isset($patient) && $patient->date_of_birth ? $patient->date_of_birth->format('Y-m-d') : '')" />
  </div>
  <div class="col-md-3">
    <label class="form-label">Gender</label>
    <select name="gender" class="form-select @error('gender') is-invalid @enderror">
      <option value="">—</option>
      @foreach (['male', 'female', 'other'] as $g)
        <option value="{{ $g }}" @selected(old('gender', $patient->gender ?? '') === $g)>{{ ucfirst($g) }}</option>
      @endforeach
    </select>
    @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
  </div>
  <div class="col-12">
    <x-form-field name="address" label="Address" :value="old('address', $patient->address ?? '')" />
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
