<div class="row g-3">
  <div class="col-md-6">
    <x-form-field name="name" label="Full name" :required="true" :value="old('name', $provider->user->name ?? '')" />
  </div>
  <div class="col-md-6">
    <x-form-field name="email" type="email" label="Email" :required="true" :value="old('email', $provider->user->email ?? '')" />
  </div>
  <div class="col-md-6">
    <x-form-field name="phone" label="Phone" :value="old('phone', $provider->user->phone ?? '')" />
  </div>
  <div class="col-md-3">
    <x-form-field name="specialty" label="Specialty" :value="old('specialty', $provider->specialty ?? '')" />
  </div>
  <div class="col-md-3">
    <x-form-field name="credentials" label="Credentials" placeholder="MD, DDS…" :value="old('credentials', $provider->credentials ?? '')" />
  </div>
  <div class="col-12">
    <x-outline-field name="bio" label="Bio" textarea rows="2" :value="old('bio', $provider->bio ?? '')" />
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
    @error('services')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
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
