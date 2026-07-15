@extends('layouts.app')

@section('title', 'Integrations')

@section('content')
<style>
  /* ---- Integrations page: page-specific accents not covered by the shared design system ---- */
  .sas-int-icon{
    width:40px; height:40px; border-radius:.85rem; flex-shrink:0;
    display:inline-flex; align-items:center; justify-content:center; font-size:1.05rem;
  }
  .sas-int-icon--email{ background:rgba(59,130,246,.13); color:#3b82f6; }
  .sas-int-icon--whatsapp{ background:rgba(37,211,102,.15); color:#22b558; }
  .sas-int-icon--sms{ background:rgba(245,158,11,.15); color:#d98a0b; }
  .sas-int-icon--payments{ background:rgba(14,165,233,.15); color:#0ea5e9; }
  .sas-int-icon--ai{ background:var(--sas-primary-50); color:var(--sas-primary-600); }
  .sas-int-icon--info{ background:rgba(108,111,147,.14); color:#6c6f93; }

  /* Segmented pill tab strip */
  .sas-ai-tabs{ display:inline-flex; flex-wrap:wrap; gap:.3rem; background:var(--sas-gray-50); padding:.35rem; border-radius:1rem; border:1px solid var(--sas-gray-100); }
  .sas-ai-tabs .nav-link{
    border:0; border-radius:.7rem; padding:.6rem 1.15rem; font-weight:600; font-size:.86rem;
    color:var(--sas-gray-600); background:transparent; transition:all .15s ease; display:flex; align-items:center; gap:.45rem;
  }
  .sas-ai-tabs .nav-link:hover{ color:var(--sas-primary-700); background:var(--sas-primary-50); }
  .sas-ai-tabs .nav-link.active{ color:#fff; background:linear-gradient(135deg,var(--sas-primary-500),#7b78e0); box-shadow:0 .35rem .85rem rgba(89,85,209,.3); }

  /* "Other pending items" list rows */
  .sas-info-row{ display:flex; gap:.9rem; padding:.85rem 0; border-bottom:1px solid var(--sas-gray-100); }
  .sas-info-row:last-child{ border-bottom:0; padding-bottom:0; }
  .sas-info-row .sas-int-icon{ width:34px; height:34px; font-size:.9rem; }
</style>
  <div class="row g-3 justify-content-center">
    <div class="col-xl-8">

      <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="fi fi-rr-lock me-2"></i>
        <div>Credentials are stored securely (secrets are encrypted), <strong>scoped to one clinic</strong>, and used immediately for reminders, confirmations and alerts — no <code>.env</code> editing or redeploy needed. Leave a secret field blank to keep the saved value.</div>
      </div>

      @if ($clinics->isNotEmpty())
        <div class="card sas-card-hero mb-3">
          <div class="card-body d-flex align-items-center gap-3 flex-wrap">
            <span class="sas-int-icon" style="background:rgba(255,255,255,.2);color:#fff;"><i class="fi fi-rr-hospital"></i></span>
            <form method="GET" action="{{ route('settings.integrations.edit') }}" class="d-flex align-items-center gap-2 flex-grow-1">
              <label class="form-label mb-0 small fw-semibold text-white">Editing settings for</label>
              <select name="clinic" class="form-select form-select-sm" style="max-width:320px" onchange="this.form.submit()">
                <option value="" @selected($clinicId === null)>— Select a clinic —</option>
                @foreach ($clinics as $clinic)
                  <option value="{{ $clinic->id }}" @selected($clinic->id === $clinicId)>{{ $clinic->name }}</option>
                @endforeach
              </select>
            </form>
            <span class="badge bg-white bg-opacity-25 text-white">System admin — every clinic's credentials are separate</span>
          </div>
        </div>
      @endif

      @if ($clinicId === null)
        <x-card>
          <x-empty-state icon="fi-rr-hospital" title="Select a clinic" description="Select a clinic above to view or edit its integration credentials." />
        </x-card>
      @else

      <ul class="nav sas-ai-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-email" type="button"><i class="fi fi-rr-envelope me-1"></i> Email</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-whatsapp" type="button"><i class="fi fi-brands-whatsapp me-1"></i> WhatsApp</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sms" type="button"><i class="fi fi-rr-comment-sms me-1"></i> SMS</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-payments" type="button"><i class="fi fi-rr-credit-card me-1"></i> Payments</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-ai" type="button"><i class="fi fi-rr-sparkles me-1"></i> AI Assistant</button></li>
      </ul>

      <div class="tab-content">

      {{-- ============================ EMAIL (SMTP) ============================ --}}
      <div class="tab-pane fade show active" id="tab-email">
      <x-card class="mb-3">
        <x-slot:title><span class="sas-int-icon sas-int-icon--email"><i class="fi fi-rr-envelope"></i></span> Email (SMTP)</x-slot:title>
        <x-slot:toolbar>
          <x-badge-status :color="($values['mail.host'] ?? '') ? 'success' : 'secondary'" :label="($values['mail.host'] ?? '') ? 'Configured' : 'Not set'" />
        </x-slot:toolbar>

        <form method="POST" action="{{ route('settings.integrations.update') }}">
          @csrf @method('PUT')
          <input type="hidden" name="clinic_id" value="{{ $clinicId }}">
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
              <x-form-field name="host" label="SMTP host" placeholder="smtp.mailgun.org" :value="old('host', $values['mail.host'] ?? '')" />
            </div>
            <div class="col-md-3">
              <x-form-field name="port" type="number" label="Port" placeholder="587" :value="old('port', $values['mail.port'] ?? '')" />
            </div>
            <div class="col-md-5">
              <x-form-field name="username" label="Username" autocomplete="off" :value="old('username', $values['mail.username'] ?? '')" />
            </div>
            <div class="col-md-4">
              <x-form-field name="password" type="password" label="Password" autocomplete="new-password" :placeholder="$secretSet['mail.password'] ? '•••••••• (saved)' : 'Enter password'" />
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
              <x-form-field name="from_address" type="email" label="From address" placeholder="no-reply@clinic.com" :value="old('from_address', $values['mail.from_address'] ?? '')" />
            </div>
            <div class="col-md-6">
              <x-form-field name="from_name" label="From name" placeholder="Clinic Name" :value="old('from_name', $values['mail.from_name'] ?? '')" />
            </div>
          </div>
          <div class="mt-3"><button class="btn btn-primary"><i class="fi fi-rr-disk me-1"></i> Save email settings</button></div>
        </form>

        <form method="POST" action="{{ route('settings.integrations.test') }}" class="mt-2">
          @csrf
          <input type="hidden" name="clinic_id" value="{{ $clinicId }}">
          <input type="hidden" name="channel" value="email">
          <div class="input-group input-group-sm" style="max-width:460px">
            <input type="email" name="to" class="form-control" placeholder="Send test to… (blank = your own email)">
            <button class="btn btn-outline-secondary"><i class="fi fi-rr-paper-plane me-1"></i> Send test email</button>
          </div>
        </form>
      </x-card>
      </div>

      {{-- ============================ WHATSAPP (GUPSHUP) ============================ --}}
      <div class="tab-pane fade" id="tab-whatsapp">
      <x-card class="mb-3" id="wa-templates">
        <x-slot:title><span class="sas-int-icon sas-int-icon--whatsapp"><i class="fi fi-brands-whatsapp"></i></span> WhatsApp <span class="text-muted small fw-normal">via Gupshup</span></x-slot:title>
        <x-slot:toolbar>
          <x-badge-status :color="(($values['messaging.whatsapp_driver'] ?? 'log') !== 'log') ? 'success' : 'secondary'" :label="strtoupper($values['messaging.whatsapp_driver'] ?? 'log')" />
        </x-slot:toolbar>

        <form method="POST" action="{{ route('settings.integrations.update') }}">
          @csrf @method('PUT')
          <input type="hidden" name="clinic_id" value="{{ $clinicId }}">
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
              <x-form-field name="source" label="Gupshup source number" placeholder="919876543210"
                :value="old('source', $values['whatsapp.gupshup_source'] ?? '')"
                help="The WhatsApp number registered with your Gupshup app (digits, with country code)." />
            </div>
            <div class="col-md-6">
              <x-form-field name="app_name" label="Gupshup app name" placeholder="MyClinicApp" :value="old('app_name', $values['whatsapp.gupshup_app_name'] ?? '')" />
            </div>
            <div class="col-md-6">
              <x-form-field name="api_key" type="password" label="Gupshup API key" autocomplete="new-password"
                :placeholder="$secretSet['whatsapp.gupshup_api_key'] ? '•••••••• (saved)' : 'Enter Gupshup API key'" />
            </div>
            <div class="col-md-6">
              <x-form-field name="webhook_secret" type="password" label="Inbound webhook secret" autocomplete="new-password"
                :placeholder="$secretSet['whatsapp.gupshup_webhook_secret'] ? '•••••••• (saved)' : 'Enter a random secret string'"
                help="Checked against ?token= on every inbound Gupshup request — only Gupshup should know this value." />
            </div>
          </div>

          @if ($webhookUrl)
            <div class="alert alert-success d-flex align-items-center mt-3 mb-0" role="alert">
              <i class="fi fi-rr-webhook me-2"></i>
              <div>
                Paste this as the <strong>inbound URL</strong> in your Gupshup app's dashboard so patient replies (and conversation flows) reach this app:
                <code class="d-block mt-1 user-select-all">{{ $webhookUrl }}</code>
              </div>
            </div>
          @else
            <div class="alert alert-secondary mt-3 mb-0" role="alert">
              <i class="fi fi-rr-info me-2"></i> Save an inbound webhook secret above to get a ready-to-paste Gupshup inbound URL.
            </div>
          @endif

          <hr class="my-3">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <p class="text-muted small mb-0"><i class="fi fi-rr-info me-1"></i> Every WhatsApp message is sent through an <strong>approved template</strong>. Add a section per message type, assign its <strong>Gupshup template ID</strong>, and map each <code>@{{1}}</code>, <code>@{{2}}</code>… to a field below.</p>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0 ms-2" id="wa-add-section"><i class="fi fi-rr-plus me-1"></i> Add section</button>
          </div>

          {{-- One select option per template section — name + status right in the dropdown. --}}
          <select id="wa-section-select" class="form-select mb-3 {{ empty($sections) ? 'd-none' : '' }}">
            @foreach ($sections as $i => $section)
              <option value="{{ $i }}" @selected($loop->first)>{{ $section['label'] ?: 'Template section' }} — {{ $section['template_id'] ? 'Assigned' : 'Not set' }}</option>
            @endforeach
          </select>

          <div class="border rounded p-3" id="wa-sections">
            @foreach ($sections as $i => $section)
              <div class="wa-section {{ $loop->first ? '' : 'd-none' }}" data-index="{{ $i }}">
                <div class="d-flex justify-content-end mb-2">
                  <button type="button" class="btn btn-sm btn-outline-danger wa-remove-section"><i class="fi fi-rr-trash me-1"></i> Remove this section</button>
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
                    <input type="text" name="sections[{{ $i }}][label]" class="form-control wa-label-input" value="{{ $section['label'] }}" placeholder="e.g. Appointment reminder">
                  </div>
                  <div class="col-md-7">
                    <label class="form-label small">Template ID</label>
                    <input type="text" name="sections[{{ $i }}][template_id]" class="form-control wa-template-input" value="{{ $section['template_id'] }}" placeholder="e.g. 3515c95f-f515-45c1-8b0c-04141e8d858d">
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
            @if (empty($sections))
              <p class="text-muted small mb-0" id="wa-empty-state">No template sections yet — click "Add section" above to create one.</p>
            @endif
          </div>

          <div class="mt-3"><button class="btn btn-primary"><i class="fi fi-rr-disk me-1"></i> Save WhatsApp settings</button></div>
        </form>

        <form method="POST" action="{{ route('settings.integrations.test') }}" class="mt-2">
          @csrf
          <input type="hidden" name="clinic_id" value="{{ $clinicId }}">
          <input type="hidden" name="channel" value="whatsapp">
          <div class="input-group input-group-sm" style="max-width:520px">
            <input type="text" name="to" class="form-control" placeholder="Test to WhatsApp number with country code, e.g. 9198XXXXXXXX">
            <button class="btn btn-outline-secondary"><i class="fi fi-rr-paper-plane me-1"></i> Send test WhatsApp</button>
          </div>
          <div class="form-text">Leave blank to use your profile number. On an unverified Gupshup app the number must be opted-in / sandbox-allowed. The test sends the <strong>Appointment reminder</strong> template with sample values.</div>
        </form>

        {{-- Cloneable blank pane (JS swaps __I__ for a fresh index) --}}
        <template id="wa-section-tpl">
          <div class="wa-section" data-index="__I__">
            <div class="d-flex justify-content-end mb-2">
              <button type="button" class="btn btn-sm btn-outline-danger wa-remove-section"><i class="fi fi-rr-trash me-1"></i> Remove this section</button>
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
                <input type="text" name="sections[__I__][label]" class="form-control wa-label-input" placeholder="e.g. Appointment reminder">
              </div>
              <div class="col-md-7">
                <label class="form-label small">Template ID</label>
                <input type="text" name="sections[__I__][template_id]" class="form-control wa-template-input" placeholder="e.g. 3515c95f-f515-45c1-8b0c-04141e8d858d">
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
            const select = document.getElementById('wa-section-select');
            const container = document.getElementById('wa-sections');
            if (!container || !select) return;
            let nextIndex = {{ count($sections) }};
            const lb = String.fromCharCode(123, 123), rb = String.fromCharCode(125, 125);

            function renumber(section) {
              section.querySelectorAll('.wa-var').forEach(function (row, i) {
                const n = row.querySelector('.wa-var-num');
                if (n) n.textContent = lb + (i + 1) + rb;
              });
            }

            function showSection(idx) {
              idx = String(idx);
              container.querySelectorAll('.wa-section').forEach(function (el) {
                el.classList.toggle('d-none', el.dataset.index !== idx);
              });
            }

            function removeEmptyState() {
              const empty = document.getElementById('wa-empty-state');
              if (empty) empty.remove();
            }

            function optionLabelFor(pane) {
              const label = pane.querySelector('.wa-label-input').value.trim() || 'Template section';
              const assigned = pane.querySelector('.wa-template-input').value.trim() !== '';
              return label + ' — ' + (assigned ? 'Assigned' : 'Not set');
            }

            select.addEventListener('change', function () {
              showSection(select.value);
            });

            document.getElementById('wa-add-section').addEventListener('click', function () {
              const idx = nextIndex++;
              removeEmptyState();
              select.classList.remove('d-none');

              const paneHtml = document.getElementById('wa-section-tpl').innerHTML.split('__I__').join(idx);
              container.insertAdjacentHTML('beforeend', paneHtml);

              const option = document.createElement('option');
              option.value = idx;
              option.textContent = 'New template section — Not set';
              select.appendChild(option);
              select.value = idx;

              const pane = container.querySelector('.wa-section[data-index="' + idx + '"]');
              pane.querySelector('.wa-add-var').click(); // start with one variable row
              showSection(idx);
            });

            // Typing a section name or template ID updates its dropdown entry live.
            container.addEventListener('input', function (e) {
              if (! (e.target.classList.contains('wa-label-input') || e.target.classList.contains('wa-template-input'))) return;
              const pane = e.target.closest('.wa-section');
              const option = select.querySelector('option[value="' + pane.dataset.index + '"]');
              if (option) option.textContent = optionLabelFor(pane);
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
                const section = removeSection.closest('.wa-section');
                const idx = section.dataset.index;
                const wasSelected = select.value === idx;
                const option = select.querySelector('option[value="' + idx + '"]');

                section.remove();
                if (option) option.remove();

                const remaining = select.querySelector('option');
                if (remaining) {
                  if (wasSelected) { select.value = remaining.value; showSection(remaining.value); }
                } else {
                  select.classList.add('d-none');
                  container.insertAdjacentHTML('beforeend', '<p class="text-muted small mb-0" id="wa-empty-state">No template sections yet — click "Add section" above to create one.</p>');
                }
              }
            });
          })();
        </script>
      </x-card>
      </div>

      {{-- ============================ SMS (GUPSHUP) ============================ --}}
      <div class="tab-pane fade" id="tab-sms">
      <x-card class="mb-3">
        <x-slot:title><span class="sas-int-icon sas-int-icon--sms"><i class="fi fi-rr-comment-sms"></i></span> SMS <span class="text-muted small fw-normal">via Gupshup — booking &amp; missed-call text-back</span></x-slot:title>
        <x-slot:toolbar>
          <x-badge-status :color="(($values['messaging.sms_driver'] ?? 'log') !== 'log') ? 'success' : 'secondary'" :label="strtoupper($values['messaging.sms_driver'] ?? 'log')" />
        </x-slot:toolbar>

        <p class="text-muted small">Uses the same Gupshup account as WhatsApp above — only the sender id and endpoint differ. Once set to <strong>Gupshup</strong> with real credentials, this powers: cold "book" SMS keyword booking, and automatic text-back after a missed call.</p>
        <form method="POST" action="{{ route('settings.integrations.update') }}">
          @csrf @method('PUT')
          <input type="hidden" name="clinic_id" value="{{ $clinicId }}">
          <input type="hidden" name="section" value="sms">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Driver</label>
              <select name="driver" class="form-select">
                @foreach (['log' => 'Log (testing)', 'gupshup' => 'Gupshup'] as $v => $label)
                  <option value="{{ $v }}" @selected(($values['messaging.sms_driver'] ?? 'log') === $v)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-8">
              <x-form-field name="source" label="Gupshup SMS sender id" placeholder="Approved SMS sender id / number"
                :value="old('source', $values['sms.gupshup_source'] ?? '')"
                help="In India this must be a DLT-registered sender id — check your Gupshup dashboard." />
            </div>
            <div class="col-md-6">
              <x-form-field name="api_key" type="password" label="Gupshup SMS API key" autocomplete="new-password"
                :placeholder="$secretSet['sms.gupshup_api_key'] ? '•••••••• (saved)' : 'Enter Gupshup SMS API key (or leave blank to reuse the WhatsApp key)'" />
            </div>
            <div class="col-md-6"></div>
            <div class="col-md-6">
              <x-form-field name="inbound_webhook_secret" type="password" label="Inbound SMS webhook secret" autocomplete="new-password"
                :placeholder="$secretSet['sms.inbound_webhook_secret'] ? '•••••••• (saved)' : 'Enter a random secret string'"
                help='Used for "book" keyword replies over SMS.' />
            </div>
            <div class="col-md-6">
              <x-form-field name="missed_call_webhook_secret" type="password" label="Missed-call webhook secret" autocomplete="new-password"
                :placeholder="$secretSet['sms.missed_call_webhook_secret'] ? '•••••••• (saved)' : 'Enter a random secret string'"
                help="Point your telephony provider's missed-call/call-forwarding webhook here." />
            </div>
          </div>

          @if ($smsInboundWebhookUrl)
            <div class="alert alert-success d-flex align-items-center mt-3 mb-0" role="alert">
              <i class="fi fi-rr-webhook me-2"></i>
              <div>
                Paste this as the <strong>inbound SMS URL</strong> in Gupshup's dashboard so a "book" text reaches this app:
                <code class="d-block mt-1 user-select-all">{{ $smsInboundWebhookUrl }}</code>
              </div>
            </div>
          @else
            <div class="alert alert-secondary mt-3 mb-0" role="alert">
              <i class="fi fi-rr-info me-2"></i> Save an inbound SMS webhook secret above to get a ready-to-paste URL.
            </div>
          @endif

          @if ($missedCallWebhookUrl)
            <div class="alert alert-success d-flex align-items-center mt-2 mb-0" role="alert">
              <i class="fi fi-rr-phone-call me-2"></i>
              <div>
                Paste this as the <strong>missed-call webhook URL</strong> in your telephony provider's (or Gupshup call-forwarding) dashboard:
                <code class="d-block mt-1 user-select-all">{{ $missedCallWebhookUrl }}</code>
              </div>
            </div>
          @else
            <div class="alert alert-secondary mt-2 mb-0" role="alert">
              <i class="fi fi-rr-info me-2"></i> Save a missed-call webhook secret above to get a ready-to-paste URL.
            </div>
          @endif

          <div class="mt-3"><button class="btn btn-primary"><i class="fi fi-rr-disk me-1"></i> Save SMS settings</button></div>
        </form>

        <form method="POST" action="{{ route('settings.integrations.test') }}" class="mt-2">
          @csrf
          <input type="hidden" name="clinic_id" value="{{ $clinicId }}">
          <input type="hidden" name="channel" value="sms">
          <div class="input-group input-group-sm" style="max-width:460px">
            <input type="text" name="to" class="form-control" placeholder="Test to SMS number with country code (blank = your profile number)">
            <button class="btn btn-outline-secondary"><i class="fi fi-rr-paper-plane me-1"></i> Send test SMS</button>
          </div>
        </form>
      </x-card>
      </div>

      {{-- ============================ PAYMENTS (RAZORPAY) ============================ --}}
      <div class="tab-pane fade" id="tab-payments">
      <x-card class="mb-3">
        <x-slot:title><span class="sas-int-icon sas-int-icon--payments"><i class="fi fi-rr-credit-card"></i></span> Payments <span class="text-muted small fw-normal">deposits via Razorpay</span></x-slot:title>
        <x-slot:toolbar>
          <x-badge-status :color="(($values['payments.driver'] ?? 'manual') !== 'manual') ? 'success' : 'secondary'" :label="strtoupper($values['payments.driver'] ?? 'manual')" />
        </x-slot:toolbar>

        <p class="text-muted small">Powers deposit collection for services with "Require a deposit" turned on. <strong>Manual</strong> (default) lets front-desk mark a deposit paid/refunded by hand — everything (forfeiture rules, auto-release of abandoned holds) works today with zero external account. Switch to <strong>Razorpay</strong> once you have this clinic's own live keys to charge cards automatically — a clinic that hasn't entered keys yet simply keeps running on Manual.</p>
        <div class="alert alert-warning small mb-3" role="alert">
          <i class="fi fi-rr-triangle-warning me-1"></i> The backend (Order creation, webhook confirmation, refunds) is ready, but this build does not yet include a card-entry checkout page (Razorpay Checkout.js) — that's the one piece still needed on the code side before Razorpay can go fully live end-to-end. Ask and it can be added.
        </div>
        <form method="POST" action="{{ route('settings.integrations.update') }}">
          @csrf @method('PUT')
          <input type="hidden" name="clinic_id" value="{{ $clinicId }}">
          <input type="hidden" name="section" value="payments">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Driver</label>
              <select name="driver" class="form-select">
                @foreach (['manual' => 'Manual (front-desk marks paid)', 'razorpay' => 'Razorpay'] as $v => $label)
                  <option value="{{ $v }}" @selected(($values['payments.driver'] ?? 'manual') === $v)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-8">
              <x-form-field name="razorpay_key_id" label="Razorpay Key ID" placeholder="rzp_live_… or rzp_test_…" :value="old('razorpay_key_id', $values['payments.razorpay_key_id'] ?? '')" />
            </div>
            <div class="col-md-6">
              <x-form-field name="razorpay_key_secret" type="password" label="Razorpay Key Secret" autocomplete="new-password"
                :placeholder="$secretSet['payments.razorpay_key_secret'] ? '•••••••• (saved)' : 'Enter Razorpay key secret'" />
            </div>
            <div class="col-md-6">
              <x-form-field name="razorpay_webhook_secret" type="password" label="Razorpay webhook signing secret" autocomplete="new-password"
                :placeholder="$secretSet['payments.razorpay_webhook_secret'] ? '•••••••• (saved)' : 'Enter the webhook secret from Razorpay'" />
            </div>
          </div>

          @if ($razorpayWebhookUrl)
            <div class="alert alert-success d-flex align-items-center mt-3 mb-0" role="alert">
              <i class="fi fi-rr-webhook me-2"></i>
              <div>
                Register this <strong>endpoint URL</strong> in this clinic's Razorpay dashboard (Settings → Webhooks), listening for <code>payment.captured</code> and <code>payment.failed</code>, then paste the signing secret it gives you above:
                <code class="d-block mt-1 user-select-all">{{ $razorpayWebhookUrl }}</code>
              </div>
            </div>
          @else
            <div class="alert alert-secondary mt-3 mb-0" role="alert">
              <i class="fi fi-rr-info me-2"></i> Save a webhook signing secret above to get the ready-to-paste Razorpay endpoint URL.
            </div>
          @endif

          <div class="mt-3"><button class="btn btn-primary"><i class="fi fi-rr-disk me-1"></i> Save payment settings</button></div>
        </form>
      </x-card>
      </div>

      {{-- ============================ AI ASSISTANT (GEMINI / OPENAI) ============================ --}}
      <div class="tab-pane fade" id="tab-ai">
      <x-card class="mb-3">
        @php $aiProvider = $values['ai.provider'] ?? 'rule_based'; @endphp
        <x-slot:title><span class="sas-int-icon sas-int-icon--ai"><i class="fi fi-rr-sparkles"></i></span> AI Assistant <span class="text-muted small fw-normal">Gemini / OpenAI</span></x-slot:title>
        <x-slot:toolbar>
          <x-badge-status :color="$aiProvider !== 'rule_based' ? 'success' : 'secondary'" :label="$aiProvider === 'rule_based' ? 'RULE-BASED' : strtoupper($aiProvider)" />
        </x-slot:toolbar>

        <p class="text-muted small">Powers the AI booking assistant, symptom routing, pre-visit summaries, referral letter/recap drafting, and the natural-language report Q&amp;A. <strong>Rule-based</strong> (default) works with zero external account — every one of those features already has a deterministic fallback. Switch to <strong>Gemini</strong> or <strong>OpenAI</strong> once you have this clinic's own live API key for richer, natural-language responses.</p>
        <form method="POST" action="{{ route('settings.integrations.update') }}">
          @csrf @method('PUT')
          <input type="hidden" name="clinic_id" value="{{ $clinicId }}">
          <input type="hidden" name="section" value="ai">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Active provider</label>
              <select name="provider" class="form-select">
                @foreach (['rule_based' => 'Rule-based (default, no account needed)', 'gemini' => 'Google Gemini', 'openai' => 'OpenAI'] as $v => $label)
                  <option value="{{ $v }}" @selected($aiProvider === $v)>{{ $label }}</option>
                @endforeach
              </select>
              <div class="form-text">Which provider actually answers — but both providers' credentials below are saved regardless, so switching later doesn't need re-entering keys.</div>
            </div>
          </div>

          <hr class="my-3">
          <h6 class="small text-muted text-uppercase mb-2">Google Gemini</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <x-form-field name="gemini_model" label="Gemini model" placeholder="gemini-2.5-flash" :value="old('gemini_model', $values['ai.gemini_model'] ?? '')" help="Optional override — leave blank for the default model." />
            </div>
            <div class="col-md-6">
              <x-form-field name="gemini_key" type="password" label="Gemini API key" autocomplete="new-password"
                :placeholder="($secretSet['ai.gemini_key'] ?? false) ? '•••••••• (saved)' : 'Enter Gemini API key'" />
            </div>
          </div>

          <hr class="my-3">
          <h6 class="small text-muted text-uppercase mb-2">OpenAI</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <x-form-field name="openai_model" label="OpenAI model" placeholder="gpt-4o-mini" :value="old('openai_model', $values['ai.openai_model'] ?? '')" help="Optional override — leave blank for the default model." />
            </div>
            <div class="col-md-6">
              <x-form-field name="openai_key" type="password" label="OpenAI API key" autocomplete="new-password"
                :placeholder="($secretSet['ai.openai_key'] ?? false) ? '•••••••• (saved)' : 'Enter OpenAI API key'" />
            </div>
          </div>

          <div class="mt-3"><button class="btn btn-primary"><i class="fi fi-rr-disk me-1"></i> Save AI settings</button></div>
        </form>

        <form method="POST" action="{{ route('settings.integrations.test') }}" class="mt-2">
          @csrf
          <input type="hidden" name="clinic_id" value="{{ $clinicId }}">
          <input type="hidden" name="channel" value="ai">
          <button class="btn btn-outline-secondary btn-sm"><i class="fi fi-rr-paper-plane me-1"></i> Test AI</button>
          <span class="text-muted small ms-1">Sends a canned prompt and shows the reply below — confirms the connection without needing a phone/email.</span>
        </form>
      </x-card>
      </div>

      </div>{{-- /tab-content --}}

      {{-- ============================ OTHER PENDING ITEMS (read-only) ============================ --}}
      <x-card class="mb-3">
        <x-slot:title><span class="sas-int-icon sas-int-icon--info"><i class="fi fi-rr-info"></i></span> Other things left on your end</x-slot:title>

        <p class="text-muted small">These aren't API credentials, so there's nothing to type in here — they're either a one-time manual step per clinic, or a partner relationship this deployment doesn't have yet.</p>

        <div class="sas-info-row">
          <span class="sas-int-icon sas-int-icon--info"><i class="fi fi-rr-shield-check"></i></span>
          <div>
            <div class="fw-semibold small">Legal compliance sign-off (HIPAA / DPDP)</div>
            <div class="text-muted small">The app blocks a clinic from going active until someone confirms the clinic's own compliance agreements are signed — that agreement itself is a legal step outside this software. Check it off per clinic under
              @can('manage clinics')
                <a href="{{ route('clinics.index') }}">Clinics → Edit → Compliance</a>.
              @else
                Clinics → Edit → Compliance (owner/admin access needed).
              @endcan
            </div>
          </div>
        </div>

        <div class="sas-info-row">
          <span class="sas-int-icon sas-int-icon--info"><i class="fi fi-rr-document"></i></span>
          <div>
            <div class="fw-semibold small">ABDM (Ayushman Bharat Digital Mission) registration</div>
            <div class="text-muted small">The clinic must register with the government ABDM framework itself; once done, record the resulting Health ID in the same
              @can('manage clinics')
                <a href="{{ route('clinics.index') }}">Clinics → Edit</a>
              @else
                Clinics → Edit
              @endcan
              screen. This app does not perform the government registration.</div>
          </div>
        </div>

        <div class="sas-info-row">
          <span class="sas-int-icon sas-int-icon--info"><i class="fi fi-brands-google"></i></span>
          <div>
            <div class="fw-semibold small">Reserve with Google</div>
            <div class="text-muted small">Requires a Google Business Profile partner relationship this deployment doesn't have — needs to be requested through Google, not configurable from here.</div>
          </div>
        </div>

        <div class="sas-info-row">
          <span class="sas-int-icon sas-int-icon--info"><i class="fi fi-brands-facebook"></i></span>
          <div>
            <div class="fw-semibold small">Booking via social media (Meta)</div>
            <div class="text-muted small">Requires a Meta Business partner integration, separate from the Gupshup WhatsApp connection above.</div>
          </div>
        </div>

        <div class="sas-info-row">
          <span class="sas-int-icon sas-int-icon--info"><i class="fi fi-rr-phone-call"></i></span>
          <div>
            <div class="fw-semibold small">Automated phone tree / AI voice assistant</div>
            <div class="text-muted small">Needs a telephony/IVR or speech vendor (e.g. Exotel, Twilio, Knowlarity) — the existing AI assistant here is text-only. Not built in this deployment.</div>
          </div>
        </div>
      </x-card>

      @endif

    </div>
  </div>
@endsection
