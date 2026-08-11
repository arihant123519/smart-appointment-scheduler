<div class="row g-3">
  <div class="col-12"><h6 class="mb-2">Basic info</h6></div>
  <div class="col-md-8">
    <x-outline-field name="name" label="Name" :required="true" :value="old('name', $clinic->name ?? '')" />
  </div>
  <div class="col-md-4">
    <x-form-field name="timezone" label="Timezone" :value="old('timezone', $clinic->timezone ?? 'UTC')" />
  </div>
  <div class="col-md-6">
    <x-form-field name="email" label="Email" type="email" :value="old('email', $clinic->email ?? '')" />
  </div>
  <div class="col-md-6">
    <x-form-field name="phone" label="Phone" type="text" :value="old('phone', $clinic->phone ?? '')" />
  </div>
  <div class="col-12">
    <x-form-field name="address" label="Address" :value="old('address', $clinic->address ?? '')" />
  </div>
  <div class="col-md-4">
    <x-form-field name="city" label="City" :value="old('city', $clinic->city ?? '')" />
  </div>
  <div class="col-md-4">
    <x-form-field name="state" label="State" :value="old('state', $clinic->state ?? '')" />
  </div>
  <div class="col-md-4">
    <x-form-field name="country" label="Country" :value="old('country', $clinic->country ?? '')" />
  </div>

  <div class="col-12"><hr class="my-1"></div>

  <div class="col-12"><h6 class="mb-2">Branding</h6></div>
  <div class="col-md-6">
    <label class="form-label">Logo</label>
    <input type="file" name="logo" class="form-control @error('logo') is-invalid @enderror" accept="image/*">
    @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
    <input type="color" name="primary_color" class="form-control form-control-color @error('primary_color') is-invalid @enderror" value="{{ old('primary_color', $clinic->primary_color ?? '#2563eb') }}">
    @error('primary_color')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Best-effort accent color for buttons/links on this clinic's pages.</div>
  </div>
  <div class="col-md-6">
    <x-form-field name="prescription_header_note" label="Prescription header tagline (optional)"
      help="Shown under the clinic name on printed prescriptions, e.g. a specialty tagline."
      :value="old('prescription_header_note', $clinic->prescription_header_note ?? '')" />
  </div>
  <div class="col-md-6">
    <x-outline-field name="prescription_footer_text" label="Prescription footer text" textarea rows="2"
      :value="old('prescription_footer_text', $clinic->prescription_footer_text ?? '')"
      help="Printed at the bottom of every prescription (disclaimer, contact info, etc.)." />
  </div>

  <div class="col-12"><hr class="my-1"></div>

  <div class="col-12"><h6 class="mb-2">Compliance</h6></div>
  <div class="col-md-6">
    <x-form-field name="abdm_health_id" label="ABDM health ID (India, optional)" placeholder="Set once the clinic has its own ABDM registration"
      help="This system connects to it once you've registered directly with the government framework — it doesn't register the clinic for you."
      :value="old('abdm_health_id', $clinic->abdm_health_id ?? '')" />
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
    <x-form-field name="admin_name" label="Admin name" :required="! isset($clinic)" :value="old('admin_name')" />
  </div>
  <div class="col-md-4">
    <x-form-field name="admin_email" label="Admin email" type="email" :required="! isset($clinic)" :value="old('admin_email')" />
  </div>
  <div class="col-md-4">
    <x-form-field name="admin_password" label="Admin password" type="password" :required="! isset($clinic)"
      autocomplete="new-password" placeholder="{{ isset($clinic) ? 'Leave blank to skip' : 'Min 8 characters' }}" />
  </div>
</div>
