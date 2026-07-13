@extends('layouts.app')

@section('title', 'Appointment Notifications')

@section('content')
  <div class="row g-3 justify-content-center">
    <div class="col-xl-8">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fi fi-rr-bell me-1"></i> Appointment Notifications</h5>
        <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fi fi-rr-arrow-left me-1"></i> Back to appointments</a>
      </div>

      <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="fi fi-rr-info me-2"></i>
        <div>
          These send <strong>email + WhatsApp</strong> messages. WhatsApp uses your approved Gupshup templates
          (<a href="{{ route('settings.integrations.edit') }}">manage templates</a>):
          lead-time reminders use the <strong>Appointment reminder</strong> template
          @if($reminderTemplateSet)<span class="badge bg-success">set</span>@else<span class="badge bg-secondary">not set</span>@endif,
          status messages use the <strong>Status change update</strong> template
          @if($statusTemplateSet)<span class="badge bg-success">set</span>@else<span class="badge bg-secondary">not set</span>@endif.
          The legacy <code>reminders:send</code> command sends email only.
        </div>
      </div>

      <form method="POST" action="{{ route('appointments.notifications.update') }}">
        @csrf @method('PUT')

        {{-- =================== STATUS-CHANGE NOTIFICATIONS =================== --}}
        <div class="card mb-3" style="height:auto;">
          <div class="card-header"><h6 class="mb-0"><i class="fi fi-rr-refresh me-1"></i> Status-change notifications</h6></div>
          <div class="card-body">
            <p class="text-muted small mb-3">When an appointment's status changes (Booked, Confirmed, Checked In, Completed, Cancelled, No Show), notify the patient immediately.</p>
            <div class="form-check form-switch mb-2">
              <input type="checkbox" class="form-check-input" id="st-enabled" name="status[enabled]" value="1" @checked($status['enabled'])>
              <label class="form-check-label" for="st-enabled">Enable status-change notifications</label>
            </div>
            <div class="d-flex gap-4 ms-1">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="st-email" name="status[email]" value="1" @checked($status['email'])>
                <label class="form-check-label" for="st-email"><i class="fi fi-rr-envelope me-1"></i> Email</label>
              </div>
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="st-wa" name="status[whatsapp]" value="1" @checked($status['whatsapp'])>
                <label class="form-check-label" for="st-wa"><i class="fi fi-brands-whatsapp me-1"></i> WhatsApp</label>
              </div>
            </div>
          </div>
        </div>

        {{-- =================== LEAD-TIME REMINDERS =================== --}}
        <div class="card mb-3" style="height:auto;">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fi fi-rr-clock me-1"></i> Lead-time reminders <span class="text-muted small">(Booked & Confirmed)</span></h6>
            <button type="button" class="btn btn-sm btn-outline-primary" id="lt-add"><i class="fi fi-rr-plus me-1"></i> Add lead time</button>
          </div>
          <div class="card-body">
            <p class="text-muted small mb-3">Send a reminder a set number of hours <strong>before</strong> the appointment start. Pick the channel(s) for each. Times in the past for a given appointment are skipped.</p>

            <div id="lt-rows">
              @forelse ($leadTimes as $i => $lt)
                <div class="input-group input-group-sm mb-2 lt-row" style="max-width:520px">
                  <span class="input-group-text">Send</span>
                  <input type="number" name="lead_times[{{ $i }}][hours]" class="form-control" style="max-width:90px" min="1" max="8760" value="{{ $lt['hours'] }}">
                  <span class="input-group-text">hours before&nbsp;→</span>
                  <span class="input-group-text bg-white">
                    <input type="checkbox" class="form-check-input mt-0 me-1" name="lead_times[{{ $i }}][email]" value="1" @checked($lt['email'])> Email
                  </span>
                  <span class="input-group-text bg-white">
                    <input type="checkbox" class="form-check-input mt-0 me-1" name="lead_times[{{ $i }}][whatsapp]" value="1" @checked($lt['whatsapp'])> WhatsApp
                  </span>
                  <button type="button" class="btn btn-outline-danger lt-remove" title="Remove">&times;</button>
                </div>
              @empty
              @endforelse
            </div>

            <template id="lt-tpl">
              <div class="input-group input-group-sm mb-2 lt-row" style="max-width:520px">
                <span class="input-group-text">Send</span>
                <input type="number" name="lead_times[__I__][hours]" class="form-control" style="max-width:90px" min="1" max="8760" value="24">
                <span class="input-group-text">hours before&nbsp;→</span>
                <span class="input-group-text bg-white">
                  <input type="checkbox" class="form-check-input mt-0 me-1" name="lead_times[__I__][email]" value="1"> Email
                </span>
                <span class="input-group-text bg-white">
                  <input type="checkbox" class="form-check-input mt-0 me-1" name="lead_times[__I__][whatsapp]" value="1" checked> WhatsApp
                </span>
                <button type="button" class="btn btn-outline-danger lt-remove" title="Remove">&times;</button>
              </div>
            </template>

            <div class="form-text">Rows with no channel selected are dropped when you save.</div>
          </div>
        </div>

        {{-- =================== REPEAT REMINDER =================== --}}
        <div class="card mb-3" style="height:auto;">
          <div class="card-header"><h6 class="mb-0"><i class="fi fi-rr-rotate-right me-1"></i> Repeat reminder <span class="text-muted small">(Booked & Confirmed)</span></h6></div>
          <div class="card-body">
            <p class="text-muted small mb-3">Send a reminder <strong>every N hours</strong> leading up to the appointment (e.g. every 1 hour), stopping at the appointment time. Only future occurrences are sent; duplicates with a lead time above are merged.</p>
            <div class="form-check form-switch mb-3">
              <input type="checkbox" class="form-check-input" id="rp-enabled" name="repeat[enabled]" value="1" @checked($repeat['enabled'])>
              <label class="form-check-label" for="rp-enabled">Enable repeat reminder</label>
            </div>
            <div class="input-group input-group-sm" style="max-width:520px">
              <span class="input-group-text">Every</span>
              <input type="number" name="repeat[hours]" class="form-control" style="max-width:90px" min="1" max="8760" value="{{ $repeat['hours'] }}">
              <span class="input-group-text">hours&nbsp;→</span>
              <span class="input-group-text bg-white">
                <input type="checkbox" class="form-check-input mt-0 me-1" name="repeat[email]" value="1" @checked($repeat['email'])> Email
              </span>
              <span class="input-group-text bg-white">
                <input type="checkbox" class="form-check-input mt-0 me-1" name="repeat[whatsapp]" value="1" @checked($repeat['whatsapp'])> WhatsApp
              </span>
            </div>
            <div class="form-text">Capped at 48 occurrences per appointment as a safety limit.</div>
          </div>
        </div>

        <button class="btn btn-primary"><i class="fi fi-rr-disk me-1"></i> Save notification settings</button>
      </form>

      <script>
        (function () {
          const rows = document.getElementById('lt-rows');
          let nextIndex = {{ count($leadTimes) }};

          document.getElementById('lt-add').addEventListener('click', function () {
            const html = document.getElementById('lt-tpl').innerHTML.split('__I__').join(nextIndex++);
            rows.insertAdjacentHTML('beforeend', html);
          });

          rows.addEventListener('click', function (e) {
            const rm = e.target.closest('.lt-remove');
            if (rm) rm.closest('.lt-row').remove();
          });
        })();
      </script>

    </div>
  </div>
@endsection
