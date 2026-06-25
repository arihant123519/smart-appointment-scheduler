<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Full name <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control" value="{{ old('name', $provider->user->name ?? '') }}" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Email <span class="text-danger">*</span></label>
    <input type="email" name="email" class="form-control" value="{{ old('email', $provider->user->email ?? '') }}" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Phone</label>
    <input type="text" name="phone" class="form-control" value="{{ old('phone', $provider->user->phone ?? '') }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">Specialty</label>
    <input type="text" name="specialty" class="form-control" value="{{ old('specialty', $provider->specialty ?? '') }}">
  </div>
  <div class="col-md-3">
    <label class="form-label">Credentials</label>
    <input type="text" name="credentials" class="form-control" value="{{ old('credentials', $provider->credentials ?? '') }}" placeholder="MD, DDS…">
  </div>
  <div class="col-12">
    <label class="form-label">Bio</label>
    <textarea name="bio" class="form-control" rows="2">{{ old('bio', $provider->bio ?? '') }}</textarea>
  </div>
  <div class="col-12">
    <label class="form-label">Services offered</label>
    <div class="d-flex flex-wrap gap-3">
      @foreach ($services as $s)
        <div class="form-check">
          <input type="checkbox" name="services[]" value="{{ $s->id }}" class="form-check-input" id="svc{{ $s->id }}"
            @checked(in_array($s->id, old('services', isset($provider) ? $provider->services->pluck('id')->toArray() : [])))>
          <label class="form-check-label" for="svc{{ $s->id }}">{{ $s->name }}</label>
        </div>
      @endforeach
    </div>
  </div>
  <div class="col-md-6">
    <div class="form-check form-switch">
      <input type="checkbox" name="accepts_telehealth" value="1" class="form-check-input" id="teleh" @checked(old('accepts_telehealth', $provider->accepts_telehealth ?? false))>
      <label class="form-check-label" for="teleh">Accepts telehealth</label>
    </div>
  </div>
  @isset($provider)
    <div class="col-md-6">
      <div class="form-check form-switch">
        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="provActive" @checked($provider->is_active)>
        <label class="form-check-label" for="provActive">Active</label>
      </div>
    </div>
  @endisset
</div>
