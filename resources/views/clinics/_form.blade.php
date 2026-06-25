<div class="row g-3">
  <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ old('name', $clinic->name ?? '') }}" required></div>
  <div class="col-md-4"><label class="form-label">Timezone</label><input type="text" name="timezone" class="form-control" value="{{ old('timezone', $clinic->timezone ?? 'UTC') }}"></div>
  <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $clinic->email ?? '') }}"></div>
  <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $clinic->phone ?? '') }}"></div>
  <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="{{ old('address', $clinic->address ?? '') }}"></div>
  <div class="col-md-4"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="{{ old('city', $clinic->city ?? '') }}"></div>
  <div class="col-md-4"><label class="form-label">State</label><input type="text" name="state" class="form-control" value="{{ old('state', $clinic->state ?? '') }}"></div>
  <div class="col-md-4"><label class="form-label">Country</label><input type="text" name="country" class="form-control" value="{{ old('country', $clinic->country ?? '') }}"></div>
  <div class="col-12"><div class="form-check form-switch"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="clAct" @checked(old('is_active', $clinic->is_active ?? true))><label class="form-check-label" for="clAct">Active</label></div></div>
</div>
