@extends('layouts.app')

@section('title', 'AI Scheduling Assistant')

@push('styles')
  <style>
    .sas-ai-hero {
      background: linear-gradient(120deg, #5955D1 0%, #7b78e0 55%, #9b7be0 100%);
      color: #fff; border: 0; border-radius: 1.1rem; position: relative; overflow: hidden;
      height: auto !important; /* theme sets .card height:100%; we don't want the hero to stretch the column */
    }
    .sas-ai-hero::after { content: ''; position: absolute; right: -30px; top: -50px; width: 200px; height: 200px;
      border-radius: 50%; background: rgba(255, 255, 255, .12); }
    .sas-ai-hero::before { content: ''; position: absolute; right: 120px; bottom: -70px; width: 150px; height: 150px;
      border-radius: 50%; background: rgba(255, 255, 255, .08); }
    .sas-ai-hero__icon { width: 56px; height: 56px; border-radius: 1rem; background: rgba(255,255,255,.2);
      display: grid; place-items: center; font-size: 1.6rem; }
    /* Pill tabs */
    .sas-ai-tabs { gap: .5rem; border: 0; }
    .sas-ai-tabs .nav-link { border: 1px solid #e6e6ef; border-radius: 999px; color: #5b5b6b; font-weight: 600;
      padding: .5rem 1.1rem; display: flex; align-items: center; gap: .5rem; transition: all .15s; background: #fff; }
    .sas-ai-tabs .nav-link:hover { border-color: #b7b4ea; color: #5955D1; }
    .sas-ai-tabs .nav-link.active { background: #5955D1; border-color: #5955D1; color: #fff;
      box-shadow: 0 .4rem 1rem rgba(89,85,209,.3); }
    .sas-ai-tabs .nav-link i { font-size: 1rem; }
    .sas-card-soft { border: 0; border-radius: 1rem; box-shadow: 0 .25rem .9rem rgba(31, 33, 64, .06); }
    /* Chat */
    #chatLog { scroll-behavior: smooth; }
    .sas-msg { display: flex; gap: .6rem; max-width: 88%; }
    .sas-msg.user { align-self: flex-end; flex-direction: row-reverse; }
    .sas-msg__avatar { width: 34px; height: 34px; border-radius: 50%; flex: 0 0 34px; display: grid; place-items: center;
      font-size: .95rem; color: #fff; }
    .sas-msg.bot .sas-msg__avatar { background: linear-gradient(135deg, #5955D1, #8a87e6); }
    .sas-msg.user .sas-msg__avatar { background: #1f2140; }
    .sas-msg__bubble { padding: .6rem .9rem; border-radius: 1rem; line-height: 1.4; }
    .sas-msg.bot .sas-msg__bubble { background: #f3f3fb; border-top-left-radius: .3rem; }
    .sas-msg.user .sas-msg__bubble { background: #5955D1; color: #fff; border-top-right-radius: .3rem; }
    .sas-typing span { display: inline-block; width: 7px; height: 7px; margin: 0 1px; background: #9a98c4;
      border-radius: 50%; animation: sasBounce 1.2s infinite ease-in-out both; }
    .sas-typing span:nth-child(2) { animation-delay: .15s; } .sas-typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes sasBounce { 0%, 80%, 100% { transform: scale(.6); opacity: .5; } 40% { transform: scale(1); opacity: 1; } }
    .sas-suggest { border: 1px solid #d9d8f0; background: #fff; color: #5955D1; border-radius: 999px;
      padding: .35rem .85rem; font-size: .82rem; cursor: pointer; transition: all .15s; }
    .sas-suggest:hover { background: #5955D1; color: #fff; border-color: #5955D1; }
    .sas-chat-input { border-radius: 999px !important; padding-left: 1.1rem; }
    .sas-send-btn { border-radius: 999px !important; width: 46px; height: 46px; display: grid; place-items: center;
      padding: 0; box-shadow: 0 .35rem .9rem rgba(89,85,209,.35); }
  </style>
@endpush

@section('content')
  <div class="row justify-content-center"><div class="col-xl-9">
    {{-- Hero header --}}
    <div class="card sas-ai-hero mb-4">
      <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="sas-ai-hero__icon"><i class="fi fi-rr-sparkles"></i></div>
        <div class="flex-grow-1">
          <h4 class="mb-1 text-white fw-bold">AI Scheduling Assistant</h4>
          <p class="mb-0 text-white-50">Book, reschedule, or find the right specialist — in plain language.</p>
        </div>
        <div class="text-end">
          <span class="badge bg-white text-{{ $enabled ? 'success' : 'secondary' }} px-3 py-2">
            <i class="fi fi-sr-circle me-1" style="font-size:.55rem;vertical-align:middle"></i>
            {{ $enabled ? 'AI provider: '.$provider : 'Rule-based engine' }}
          </span>
          <div class="text-white-50 small mt-1" style="max-width:240px">AI is assistive only — you confirm every slot.</div>
        </div>
      </div>
    </div>

    <ul class="nav sas-ai-tabs mb-3" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-chat" type="button"><i class="fi fi-rr-comment-dots"></i> Chat assistant</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-parse" type="button"><i class="fi fi-rr-bolt"></i> Quick parse</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-symptom" type="button"><i class="fi fi-rr-stethoscope"></i> Symptom helper</button></li>
    </ul>

    <div class="tab-content">
      {{-- Conversational chatbot --------------------------------------------- --}}
      <div class="tab-pane fade show active" id="tab-chat">
        <div class="card sas-card-soft">
          <div class="card-body">
            <div id="chatLog" class="mb-3 d-flex flex-column gap-3" style="min-height:280px;max-height:440px;overflow-y:auto;">
              <div class="sas-msg bot">
                <div class="sas-msg__avatar"><i class="fi fi-rr-sparkles"></i></div>
                <div class="sas-msg__bubble">Hi! I can help you book, reschedule, or cancel an appointment. What do you need?</div>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="chatSuggestions">
              <button type="button" class="sas-suggest" data-fill="I need a dentist next Tuesday afternoon">🦷 Dentist next Tuesday</button>
              <button type="button" class="sas-suggest" data-fill="Reschedule my appointment to next week">📅 Reschedule</button>
              <button type="button" class="sas-suggest" data-fill="Book a therapy session this Friday morning">🧠 Therapy Friday AM</button>
            </div>
            <div class="input-group">
              <input type="text" id="chatInput" class="form-control sas-chat-input" placeholder="e.g. I need a dentist next Tuesday afternoon, my tooth hurts">
              <button class="btn btn-primary sas-send-btn ms-2" id="chatSend" title="Send"><i class="fi fi-rr-paper-plane"></i></button>
            </div>
          </div>
        </div>
      </div>

      {{-- One-shot quick parse ----------------------------------------------- --}}
      <div class="tab-pane fade" id="tab-parse">
        <div class="card sas-card-soft"><div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="sas-stat__icon bg-warning-subtle text-warning" style="width:40px;height:40px;border-radius:.7rem;display:grid;place-items:center"><i class="fi fi-rr-bolt"></i></span>
            <p class="text-muted mb-0">Type a request in plain language, e.g. <em>"book a dentist next Tuesday afternoon"</em>.</p>
          </div>
          <div class="input-group mb-3">
            <input type="text" id="nlInput" class="form-control sas-chat-input" placeholder="book a therapy session next Monday morning">
            <button class="btn btn-primary sas-send-btn ms-2" id="nlGo" title="Interpret"><i class="fi fi-rr-arrow-right"></i></button>
          </div>
          <div id="nlResult"></div>
        </div></div>
      </div>

      {{-- Symptom routing ----------------------------------------------------- --}}
      <div class="tab-pane fade" id="tab-symptom">
        <div class="card sas-card-soft"><div class="card-body">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="sas-stat__icon bg-info-subtle text-info" style="width:40px;height:40px;border-radius:.7rem;display:grid;place-items:center"><i class="fi fi-rr-stethoscope"></i></span>
            <p class="text-muted mb-0">Describe your symptoms and we'll suggest the right kind of specialist.
              <span class="text-danger">Informational only — not a diagnosis.</span></p>
          </div>
          <div class="input-group mb-3">
            <input type="text" id="symInput" class="form-control sas-chat-input" placeholder="e.g. sore throat and fever for 3 days">
            <button class="btn btn-primary sas-send-btn ms-2" id="symGo" title="Check"><i class="fi fi-rr-search"></i></button>
          </div>
          <div id="symResult"></div>
        </div></div>
      </div>
    </div>
  </div></div>
@endsection

@push('scripts')
<script>
  const CSRF = document.querySelector('meta[name=csrf-token]').content;
  const BOOK_URL = '{{ route('booking.create') }}';
  const esc = s => (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  // --- Chat assistant -------------------------------------------------------
  const chatLog = document.getElementById('chatLog');
  const history = [];
  const TYPING = '<span class="sas-typing"><span></span><span></span><span></span></span>';
  function bubble(text, who, isHtml) {
    const wrap = document.createElement('div');
    wrap.className = 'sas-msg ' + (who === 'user' ? 'user' : 'bot');
    const avatar = who === 'user'
      ? '<div class="sas-msg__avatar"><i class="fi fi-rr-user"></i></div>'
      : '<div class="sas-msg__avatar"><i class="fi fi-rr-sparkles"></i></div>';
    wrap.innerHTML = avatar + '<div class="sas-msg__bubble">' + (isHtml ? text : esc(text)) + '</div>';
    chatLog.appendChild(wrap);
    chatLog.scrollTop = chatLog.scrollHeight;
    return wrap.querySelector('.sas-msg__bubble');
  }
  function sendChat() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    if (!text) return;
    bubble(text, 'user');
    history.push({ role: 'user', content: text });
    input.value = '';
    const thinking = bubble(TYPING, 'bot', true);
    fetch('{{ route('ai.chat') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ messages: history }),
    }).then(r => r.json()).then(data => {
      thinking.innerHTML = esc(data.reply);
      history.push({ role: 'assistant', content: data.reply });
      if (data.booking_link) {
        let prov = (data.providers || []).map(p => '<span class="badge bg-light text-dark">' + esc(p.name) + '</span>').join(' ');
        bubble((prov ? '<div class="mb-2 small">Matching: ' + prov + '</div>' : '') +
          '<a class="btn btn-sm btn-success" href="' + data.booking_link + '">Continue to booking →</a>', 'bot', true);
      }
    }).catch(() => thinking.innerHTML = '<span class="text-danger">Sorry, something went wrong. Please try again.</span>');
  }
  document.getElementById('chatSend').addEventListener('click', sendChat);
  document.getElementById('chatInput').addEventListener('keydown', e => { if (e.key === 'Enter') sendChat(); });

  // Suggestion chips → fill input and send
  document.querySelectorAll('#chatSuggestions .sas-suggest').forEach(function (chip) {
    chip.addEventListener('click', function () {
      document.getElementById('chatInput').value = chip.dataset.fill;
      sendChat();
    });
  });

  // --- Quick parse ----------------------------------------------------------
  document.getElementById('nlGo').addEventListener('click', function () {
    const text = document.getElementById('nlInput').value.trim();
    const box = document.getElementById('nlResult');
    if (!text) return;
    box.innerHTML = '<div class="text-muted small">Interpreting…</div>';
    fetch('{{ route('ai.parse') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ text }),
    }).then(r => r.json()).then(data => {
      const i = data.intent;
      let html = '<div class="card border"><div class="card-body">';
      html += '<h6>Interpreted request</h6><ul class="mb-2">';
      html += '<li>Service: ' + esc(data.service ? data.service.name : (i.specialty || '—')) + '</li>';
      html += '<li>Date: ' + esc(i.date || 'any') + '</li>';
      html += '<li>Time of day: ' + esc(i.period || 'any') + '</li>';
      html += '<li>Urgency: ' + esc(i.urgency || 'routine') + '</li>';
      html += '</ul>';
      if (data.providers.length) {
        html += '<div class="mb-2">Matching providers: ' + data.providers.map(p => '<span class="badge bg-light text-dark">' + esc(p.name) + '</span>').join(' ') + '</div>';
      }
      const params = new URLSearchParams();
      if (data.service) params.set('service', data.service.id);
      if (i.date) params.set('date', i.date);
      html += '<a class="btn btn-sm btn-primary" href="' + BOOK_URL + '?' + params.toString() + '">Continue to booking</a>';
      html += '<p class="text-muted small mt-2 mb-0">' + esc(i.note) + '</p>';
      html += '</div></div>';
      box.innerHTML = html;
    }).catch(() => box.innerHTML = '<div class="text-danger small">Could not interpret. Try rephrasing.</div>');
  });

  // --- Symptom helper -------------------------------------------------------
  document.getElementById('symGo').addEventListener('click', function () {
    const text = document.getElementById('symInput').value.trim();
    const box = document.getElementById('symResult');
    if (!text) return;
    box.innerHTML = '<div class="text-muted small">Checking…</div>';
    fetch('{{ route('ai.symptoms') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ text }),
    }).then(r => r.json()).then(data => {
      const colors = { urgent: 'danger', soon: 'warning', routine: 'success' };
      const labels = { urgent: 'Urgent — act now', soon: 'See a clinician soon', routine: 'Routine' };
      const uc = colors[data.urgency] || 'secondary';
      let html = '<div class="card border-0 sas-card-soft"><div class="card-body">';

      // Header: specialty + urgency
      html += '<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">';
      html += '<div>Suggested specialty: <strong>' + esc(data.specialty || '—') + '</strong></div>';
      html += '<span class="badge bg-' + uc + '-subtle text-' + uc + ' px-3 py-2"><i class="fi fi-rr-siren-on me-1"></i>' + esc(labels[data.urgency] || data.urgency) + '</span>';
      html += '</div>';

      // Why it's serious
      if (data.why_serious) {
        html += '<div class="d-flex gap-2 mb-2">';
        html += '<i class="fi fi-rr-triangle-warning text-' + uc + ' mt-1"></i>';
        html += '<div><div class="fw-semibold small text-uppercase text-muted">Why this matters</div>' + esc(data.why_serious) + '</div>';
        html += '</div>';
      }

      // What it can lead to
      if (data.can_lead_to) {
        html += '<div class="d-flex gap-2 mb-3">';
        html += '<i class="fi fi-rr-arrow-trend-up text-' + uc + ' mt-1"></i>';
        html += '<div><div class="fw-semibold small text-uppercase text-muted">If left untreated, it can lead to</div>' + esc(data.can_lead_to) + '</div>';
        html += '</div>';
      }

      // Urgent call-to-action banner
      if (data.urgency === 'urgent') {
        html += '<div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3">';
        html += '<i class="fi fi-rr-first-aid"></i><div class="small mb-0">If symptoms are severe or worsening, seek emergency care immediately — don\'t wait for an appointment.</div>';
        html += '</div>';
      }

      html += '<p class="small text-muted fst-italic">' + esc(data.advice) + '</p>';

      if ((data.providers || []).length) {
        html += '<div class="mb-3 small">Matching providers: ' + data.providers.map(p => '<span class="badge bg-light text-dark">' + esc(p.name) + '</span>').join(' ') + '</div>';
      }
      html += '<a class="btn btn-sm btn-primary" href="' + BOOK_URL + '"><i class="fi fi-rr-calendar-plus me-1"></i> Book an appointment</a>';
      html += '</div></div>';
      box.innerHTML = html;
    }).catch(() => box.innerHTML = '<div class="text-danger small">Could not check symptoms. Try rephrasing.</div>');
  });
</script>
@endpush
