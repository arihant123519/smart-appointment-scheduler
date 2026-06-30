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
            <div class="d-flex justify-content-between align-items-center mb-2">
              <p class="text-muted small mb-0"><i class="fi fi-rr-info me-1"></i> Every WhatsApp message is sent through an <strong>approved template</strong>. Add a section per message type, assign its <strong>Gupshup template ID</strong>, and map each <code>@{{1}}</code>, <code>@{{2}}</code>… to a field below.</p>
              <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0 ms-2" id="wa-add-section"><i class="fi fi-rr-plus me-1"></i> Add section</button>
            </div>

            <div id="wa-sections">
              @foreach ($sections as $i => $section)
                <div class="border rounded p-3 mb-3 wa-section" data-index="{{ $i }}">
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-{{ $section['template_id'] ? 'success' : 'secondary' }}">{{ $section['template_id'] ? 'Assigned' : 'Not set' }}</span>
                      <strong class="small">{{ $section['label'] ?: 'Template section' }}</strong>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger wa-remove-section"><i class="fi fi-rr-trash me-1"></i> Remove</button>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-5">
                      <label class="form-label small">Used for</label>
                      <select name="sections[{{ $i }}][event]" class="form-select">
                        @foreach ($events as $ek => $el)
                          <option value="{{ $ek }}" @selected($section['event'] === $ek)>{{ $el }}</option>
                        @endforeach
                      </select>
                    </div>
                    <div class="col-md-7">
                      <label class="form-label small">Section name</label>
                      <input type="text" name="sections[{{ $i }}][label]" class="form-control" value="{{ $section['label'] }}" placeholder="e.g. Appointment reminder">
                    </div>
                    <div class="col-md-7">
                      <label class="form-label small">Template ID</label>
                      <input type="text" name="sections[{{ $i }}][template_id]" class="form-control" value="{{ $section['template_id'] }}" placeholder="e.g. 3515c95f-f515-45c1-8b0c-04141e8d858d">
                    </div>
                    <div class="col-md-5">
                      <label class="form-label small">Namespace <span class="text-muted">(optional)</span></label>
                      <input type="text" name="sections[{{ $i }}][namespace]" class="form-control" value="{{ $section['namespace'] }}" placeholder="optional">
                    </div>
                    <div class="col-12">
                      <label class="form-label small">Template text <span class="text-muted">(for your reference — must match the approved template)</span></label>
                      <textarea name="sections[{{ $i }}][body]" rows="2" class="form-control" placeholder="Hi @{{1}}, your appointment at @{{2}} with Dr. @{{3}} is booked for @{{4}} at @{{5}}.">{{ $section['body'] }}</textarea>
                    </div>
                    <div class="col-12">
                      <label class="form-label small mb-1">Variables — map each <code>@{{n}}</code> to a field, in order</label>
                      <div class="wa-vars d-flex flex-column gap-2">
                        @foreach ($section['variables'] as $vi => $token)
                          <div class="input-group input-group-sm wa-var">
                            <span class="input-group-text wa-var-num" style="min-width:48px">{{ '{'.'{'.($vi + 1).'}'.'}' }}</span>
                            <select name="sections[{{ $i }}][variables][]" class="form-select">
                              @foreach ($tokens as $tk => $tl)
                                <option value="{{ $tk }}" @selected($token === $tk)>{{ $tl }}</option>
                              @endforeach
                            </select>
                            <button type="button" class="btn btn-outline-danger wa-remove-var" title="Remove variable">&times;</button>
                          </div>
                        @endforeach
                      </div>
                      <button type="button" class="btn btn-sm btn-outline-secondary mt-2 wa-add-var"><i class="fi fi-rr-plus me-1"></i> Add variable</button>
                    </div>
                  </div>
                </div>
              @endforeach
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
            <div class="form-text">Leave blank to use your profile number. On an unverified Gupshup app the number must be opted-in / sandbox-allowed. The test sends the <strong>Appointment reminder</strong> template with sample values.</div>
          </form>

          {{-- Cloneable blank section (JS swaps __I__ for a fresh index) --}}
          <template id="wa-section-tpl">
            <div class="border rounded p-3 mb-3 wa-section" data-index="__I__">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-secondary">Not set</span>
                  <strong class="small">New template section</strong>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger wa-remove-section"><i class="fi fi-rr-trash me-1"></i> Remove</button>
              </div>
              <div class="row g-3">
                <div class="col-md-5">
                  <label class="form-label small">Used for</label>
                  <select name="sections[__I__][event]" class="form-select">
                    @foreach ($events as $ek => $el)
                      <option value="{{ $ek }}">{{ $el }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="col-md-7">
                  <label class="form-label small">Section name</label>
                  <input type="text" name="sections[__I__][label]" class="form-control" placeholder="e.g. Appointment reminder">
                </div>
                <div class="col-md-7">
                  <label class="form-label small">Template ID</label>
                  <input type="text" name="sections[__I__][template_id]" class="form-control" placeholder="e.g. 3515c95f-f515-45c1-8b0c-04141e8d858d">
                </div>
                <div class="col-md-5">
                  <label class="form-label small">Namespace <span class="text-muted">(optional)</span></label>
                  <input type="text" name="sections[__I__][namespace]" class="form-control" placeholder="optional">
                </div>
                <div class="col-12">
                  <label class="form-label small">Template text <span class="text-muted">(for your reference)</span></label>
                  <textarea name="sections[__I__][body]" rows="2" class="form-control" placeholder="Hi @{{1}}, your appointment at @{{2}} with Dr. @{{3}} is booked for @{{4}} at @{{5}}."></textarea>
                </div>
                <div class="col-12">
                  <label class="form-label small mb-1">Variables — map each <code>@{{n}}</code> to a field, in order</label>
                  <div class="wa-vars d-flex flex-column gap-2"></div>
                  <button type="button" class="btn btn-sm btn-outline-secondary mt-2 wa-add-var"><i class="fi fi-rr-plus me-1"></i> Add variable</button>
                </div>
              </div>
            </div>
          </template>

          {{-- Cloneable blank variable row --}}
          <template id="wa-var-tpl">
            <div class="input-group input-group-sm wa-var">
              <span class="input-group-text wa-var-num" style="min-width:48px"></span>
              <select name="sections[__I__][variables][]" class="form-select">
                @foreach ($tokens as $tk => $tl)
                  <option value="{{ $tk }}">{{ $tl }}</option>
                @endforeach
              </select>
              <button type="button" class="btn btn-outline-danger wa-remove-var" title="Remove variable">&times;</button>
            </div>
          </template>

          <script>
            (function () {
              const container = document.getElementById('wa-sections');
              if (!container) return;
              let nextIndex = {{ count($sections) }};
              const lb = String.fromCharCode(123, 123), rb = String.fromCharCode(125, 125);

              function renumber(section) {
                section.querySelectorAll('.wa-var').forEach(function (row, i) {
                  const n = row.querySelector('.wa-var-num');
                  if (n) n.textContent = lb + (i + 1) + rb;
                });
              }

              document.getElementById('wa-add-section').addEventListener('click', function () {
                const html = document.getElementById('wa-section-tpl').innerHTML.split('__I__').join(nextIndex++);
                container.insertAdjacentHTML('beforeend', html);
                const section = container.lastElementChild;
                section.querySelector('.wa-add-var').click(); // start with one variable row
              });

              container.addEventListener('click', function (e) {
                const addVar = e.target.closest('.wa-add-var');
                const removeVar = e.target.closest('.wa-remove-var');
                const removeSection = e.target.closest('.wa-remove-section');

                if (addVar) {
                  const section = addVar.closest('.wa-section');
                  const idx = section.dataset.index;
                  const html = document.getElementById('wa-var-tpl').innerHTML.split('__I__').join(idx);
                  section.querySelector('.wa-vars').insertAdjacentHTML('beforeend', html);
                  renumber(section);
                } else if (removeVar) {
                  const section = removeVar.closest('.wa-section');
                  removeVar.closest('.wa-var').remove();
                  renumber(section);
                } else if (removeSection) {
                  removeSection.closest('.wa-section').remove();
                }
              });
            })();
          </script>
        </div>
      </div>

    </div>
  </div>
@endsection
