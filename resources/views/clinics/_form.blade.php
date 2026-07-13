<div class="row g-3">
  <div class="col-md-8"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ old('name', $clinic->name ?? '') }}" required></div>
  <div class="col-md-4"><label class="form-label">Timezone</label><input type="text" name="timezone" class="form-control" value="{{ old('timezone', $clinic->timezone ?? 'UTC') }}"></div>
  <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $clinic->email ?? '') }}"></div>
  <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="{{ old('phone', $clinic->phone ?? '') }}"></div>
  <div class="col-12"><label class="form-label">Address</label><input type="text" name="address" class="form-control" value="{{ old('address', $clinic->address ?? '') }}"></div>
  <div class="col-md-4"><label class="form-label">City</label><input type="text" name="city" class="form-control" value="{{ old('city', $clinic->city ?? '') }}"></div>
  <div class="col-md-4"><label class="form-label">State</label><input type="text" name="state" class="form-control" value="{{ old('state', $clinic->state ?? '') }}"></div>
  <div class="col-md-4"><label class="form-label">Country</label><input type="text" name="country" class="form-control" value="{{ old('country', $clinic->country ?? '') }}"></div>

  <div class="col-12"><hr class="my-1"></div>

  <div class="col-12"><h6 class="mb-2">Branding</h6></div>
  <div class="col-md-6">
    <label class="form-label">Logo</label>
    <input type="file" name="logo" class="form-control" accept="image/*">
    @if (($clinic->logo_path ?? null))
      <div class="form-text">
        <img src="{{ $clinic->logo_url }}" alt="Current logo" style="height:28px" class="mt-1 rounded border p-1">
        Current logo — choose a file to replace it.
      </div>
    @else
      <div class="form-text">Shown in place of the default logo wherever this clinic's staff/patients are signed in.</div>
    @endif
  </div>
  <div class="col-md-6">
    <label class="form-label">Primary color</label>
    <input type="color" name="primary_color" class="form-control form-control-color" value="{{ old('primary_color', $clinic->primary_color ?? '#5955d1') }}">
    <div class="form-text">Best-effort accent color for buttons/links on this clinic's pages.</div>
  </div>

  <div class="col-12"><hr class="my-1"></div>

  <div class="col-md-6">
    <label class="form-label">ABDM health ID <span class="text-muted small">(India, optional)</span></label>
    <input type="text" name="abdm_health_id" class="form-control" value="{{ old('abdm_health_id', $clinic->abdm_health_id ?? '') }}" placeholder="Set once the clinic has its own ABDM registration">
    <div class="form-text">This system connects to it once you've registered directly with the government framework — it doesn't register the clinic for you.</div>
  </div>
  <div class="col-md-6 d-flex align-items-end">
    <div class="form-check form-switch">
      @php $signed = old('compliance_agreements_signed', $clinic->compliance_agreements_signed_at ?? null); @endphp
      <input type="checkbox" name="compliance_agreements_signed" value="1" class="form-check-input" id="clCompliance" @checked($signed)>
      <label class="form-check-label" for="clCompliance">Compliance agreements signed (HIPAA / DPDP)</label>
      @if (($clinic->compliance_agreements_signed_at ?? null) && ! old('compliance_agreements_signed'))
        <div class="form-text">Signed {{ $clinic->compliance_agreements_signed_at->format('M j, Y') }}.</div>
      @endif
    </div>
  </div>

  <div class="col-12">
    <div class="form-check form-switch">
      <input type="checkbox" name="is_active" value="1" class="form-check-input" id="clAct" @checked(old('is_active', $clinic->is_active ?? true))>
      <label class="form-check-label" for="clAct">Active</label>
      <div class="form-text">A clinic can't be activated until compliance agreements above are marked as signed.</div>
    </div>
  </div>

  <div class="col-12"><hr class="my-1"></div>

  <div class="col-12">
    <h6 class="mb-2">Clinic Admin</h6>
    @isset($clinic)
      @if (($clinicAdmins ?? collect())->isNotEmpty())
        <div class="table-responsive mb-3">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Email</th><th class="text-end"></th></tr></thead>
            <tbody>
              @foreach ($clinicAdmins as $admin)
                <tr>
                  <td>{{ $admin->name }}</td>
                  <td>{{ $admin->email }}</td>
                  <td class="text-end"><a href="{{ route('users.edit', $admin) }}" class="btn btn-sm btn-outline-secondary">Manage / reset password</a></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <p class="text-muted small">Fill the fields below to add another clinic admin — optional, leave blank to make no change.</p>
      @else
        <div class="alert alert-warning small">This clinic has no admin account yet — add one below so someone can log in and manage it.</div>
      @endif
    @else
      <p class="text-muted small">This login is how the clinic will sign in to manage its own settings, staff, and integrations — required to create the clinic.</p>
    @endisset
  </div>

  <div class="col-md-4">
    <label class="form-label">Admin name @unless(isset($clinic))<span class="text-danger">*</span>@endunless</label>
    <input type="text" name="admin_name" class="form-control" value="{{ old('admin_name') }}" @unless(isset($clinic)) required @endunless>
  </div>
  <div class="col-md-4">
    <label class="form-label">Admin email @unless(isset($clinic))<span class="text-danger">*</span>@endunless</label>
    <input type="email" name="admin_email" class="form-control" value="{{ old('admin_email') }}" @unless(isset($clinic)) required @endunless>
  </div>
  <div class="col-md-4">
    <label class="form-label">Admin password @unless(isset($clinic))<span class="text-danger">*</span>@endunless</label>
    <input type="password" name="admin_password" class="form-control" autocomplete="new-password" placeholder="{{ isset($clinic) ? 'Leave blank to skip' : 'Min 8 characters' }}" @unless(isset($clinic)) required @endunless>
  </div>
</div>
