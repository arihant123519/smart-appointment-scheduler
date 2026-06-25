@extends('layouts.app')

@section('title', 'Integrations')

@section('content')
  <div class="row g-3 justify-content-center">
    <div class="col-xl-8">

      <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="fi fi-rr-lock me-2"></i>
        <div>Credentials are stored securely (secrets are encrypted) and used immediately for reminders, confirmations and alerts — no <code>.env</code> editing or redeploy needed. Leave a secret field blank to keep the saved value.</div>
      </div>

      {{-- ============================ EMAIL (SMTP) ============================ --}}
      <div class="card mb-3" style="height: auto;">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="fi fi-rr-envelope me-1"></i> Email (SMTP)</h6>
          <span class="badge bg-{{ ($values['mail.host'] ?? '') ? 'success' : 'secondary' }}">{{ ($values['mail.host'] ?? '') ? 'Configured' : 'Not set' }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('settings.integrations.update') }}">
            @csrf @method('PUT')
            <input type="hidden" name="section" value="email">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Mailer</label>
                <select name="mailer" class="form-select">
                  @foreach (['smtp' => 'SMTP (live)', 'log' => 'Log (testing)'] as $v => $label)
                    <option value="{{ $v }}" @selected(($values['mail.mailer'] ?? 'smtp') === $v)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-5">
                <label class="form-label">SMTP host</label>
                <input type="text" name="host" class="form-control" placeholder="smtp.mailgun.org" value="{{ old('host', $values['mail.host'] ?? '') }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Port</label>
                <input type="number" name="port" class="form-control" placeholder="587" value="{{ old('port', $values['mail.port'] ?? '') }}">
              </div>
              <div class="col-md-5">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" autocomplete="off" value="{{ old('username', $values['mail.username'] ?? '') }}">
              </div>
              <div class="col-md-4">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" autocomplete="new-password" placeholder="{{ $secretSet['mail.password'] ? '•••••••• (saved)' : 'Enter password' }}">
              </div>
              <div class="col-md-3">
                <label class="form-label">Encryption</label>
                <select name="scheme" class="form-select">
                  <option value="" @selected(($values['mail.scheme'] ?? '') === '')>None</option>
                  @foreach (['tls' => 'TLS', 'ssl' => 'SSL', 'smtps' => 'SMTPS'] as $v => $label)
                    <option value="{{ $v }}" @selected(($values['mail.scheme'] ?? '') === $v)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">From address</label>
                <input type="email" name="from_address" class="form-control" placeholder="no-reply@clinic.com" value="{{ old('from_address', $values['mail.from_address'] ?? '') }}">
              </div>
              <div class="col-md-6">
                <label class="form-label">From name</label>
                <input type="text" name="from_name" class="form-control" placeholder="Clinic Name" value="{{ old('from_name', $values['mail.from_name'] ?? '') }}">
              </div>
            </div>
            <div class="mt-3"><button class="btn btn-primary"><i class="fi fi-rr-disk me-1"></i> Save email settings</button></div>
          </form>

          <form method="POST" action="{{ route('settings.integrations.test') }}" class="mt-2">
            @csrf
            <input type="hidden" name="channel" value="email">
            <div class="input-group input-group-sm" style="max-width:460px">
              <input type="email" name="to" class="form-control" placeholder="Send test to… (blank = your own email)">
              <button class="btn btn-outline-secondary"><i class="fi fi-rr-paper-plane me-1"></i> Send test email</button>
            </div>
          </form>
        </div>
      </div>

      {{-- ============================ WHATSAPP (GUPSHUP) ============================ --}}
      <div class="card mb-3" style="height: auto;">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0"><i class="fi fi-brands-whatsapp me-1"></i> WhatsApp <span class="text-muted small">via Gupshup</span></h6>
          <span class="badge bg-{{ (($values['messaging.whatsapp_driver'] ?? 'log') !== 'log') ? 'success' : 'secondary' }}">{{ strtoupper($values['messaging.whatsapp_driver'] ?? 'log') }}</span>
        </div>
        <div class="card-body">
          <form method="POST" action="{{ route('settings.integrations.update') }}">
            @csrf @method('PUT')
            <input type="hidden" name="section" value="whatsapp">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Driver</label>
                <select name="driver" class="form-select">
                  @foreach (['log' => 'Log (testing)', 'gupshup' => 'Gupshup'] as $v => $label)
                    <option value="{{ $v }}" @selected(($values['messaging.whatsapp_driver'] ?? 'log') === $v)>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-8">
                <label class="form-label">Gupshup source number</label>
                <input type="text" name="source" class="form-control" placeholder="919876543210" value="{{ old('source', $values['whatsapp.gupshup_source'] ?? '') }}">
                <div class="form-text">The WhatsApp number registered with your Gupshup app (digits, with country code).</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Gupshup app name</label>
                <input type="text" name="app_name" class="form-control" placeholder="MyClinicApp" value="{{ old('app_name', $values['whatsapp.gupshup_app_name'] ?? '') }}">
              </div>
              <div class="col-md-6">
                <label class="form-label">Gupshup API key</label>
                <input type="password" name="api_key" class="form-control" autocomplete="new-password" placeholder="{{ $secretSet['whatsapp.gupshup_api_key'] ? '•••••••• (saved)' : 'Enter Gupshup API key' }}">
              </div>
            </div>

            <hr class="my-3">
            <p class="text-muted small mb-2"><i class="fi fi-rr-info me-1"></i> WhatsApp reminders are sent through an <strong>approved template</strong>. Paste the <strong>Gupshup template ID</strong> of your reminder template (it must have 3 variables: day, time, provider).</p>
            <div class="row g-3">
              <div class="col-md-7">
                <label class="form-label">Reminder template ID</label>
                <input type="text" name="template_id" class="form-control" placeholder="3515c95f-f515-45c1-8b0c-04141e8d858d" value="{{ old('template_id', $values['whatsapp.gupshup_template_id'] ?? '') }}">
              </div>
              <div class="col-md-5">
                <label class="form-label">Namespace <span class="text-muted">(optional)</span></label>
                <input type="text" name="namespace" class="form-control" placeholder="6052367a_2d65_4299_9118_9edc67f2de62" value="{{ old('namespace', $values['whatsapp.gupshup_namespace'] ?? '') }}">
              </div>
            </div>
            <div class="mt-3"><button class="btn btn-primary"><i class="fi fi-rr-disk me-1"></i> Save WhatsApp settings</button></div>
          </form>

          <form method="POST" action="{{ route('settings.integrations.test') }}" class="mt-2">
            @csrf
            <input type="hidden" name="channel" value="whatsapp">
            <div class="input-group input-group-sm" style="max-width:520px">
              <input type="text" name="to" class="form-control" placeholder="Test to WhatsApp number with country code, e.g. 9198XXXXXXXX">
              <button class="btn btn-outline-secondary"><i class="fi fi-rr-paper-plane me-1"></i> Send test WhatsApp</button>
            </div>
            <div class="form-text">Leave blank to use your profile number. On an unverified Gupshup app the number must be opted-in / sandbox-allowed.</div>
          </form>
        </div>
      </div>

    </div>
  </div>
@endsection
