@extends('layouts.app')

@section('title', 'AI Scheduling Assistant')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-9">
    <div class="d-flex align-items-center gap-2 mb-3">
      <span class="badge bg-{{ $enabled ? 'success' : 'secondary' }}-subtle text-{{ $enabled ? 'success' : 'secondary' }}">
        {{ $enabled ? 'AI provider: '.$provider : 'Rule-based engine (no AI key configured)' }}
      </span>
      <span class="text-muted small">AI is assistive only — it never finalizes a booking. You confirm every slot.</span>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-chat" type="button">💬 Chat assistant</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-parse" type="button">⚡ Quick parse</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-symptom" type="button">🩺 Symptom helper</button></li>
    </ul>

    <div class="tab-content">
      {{-- Conversational chatbot --------------------------------------------- --}}
      <div class="tab-pane fade show active" id="tab-chat">
        <div class="card">
          <div class="card-body">
            <div id="chatLog" class="mb-3 d-flex flex-column gap-2" style="min-height:240px;max-height:420px;overflow-y:auto;">
              <div class="align-self-start bg-light rounded p-2 px-3">Hi! I can help you book, reschedule, or cancel an appointment. What do you need?</div>
            </div>
            <div class="input-group">
              <input type="text" id="chatInput" class="form-control" placeholder="e.g. I need a dentist next Tuesday afternoon, my tooth hurts">
              <button class="btn btn-primary" id="chatSend">Send</button>
            </div>
          </div>
        </div>
      </div>

      {{-- One-shot quick parse ----------------------------------------------- --}}
      <div class="tab-pane fade" id="tab-parse">
        <div class="card"><div class="card-body">
          <p class="text-muted">Type a request in plain language, e.g. <em>"book a dentist next Tuesday afternoon"</em>.</p>
          <div class="input-group mb-3">
            <input type="text" id="nlInput" class="form-control" placeholder="book a therapy session next Monday morning">
            <button class="btn btn-primary" id="nlGo">Interpret</button>
          </div>
          <div id="nlResult"></div>
        </div></div>
      </div>

      {{-- Symptom routing ----------------------------------------------------- --}}
      <div class="tab-pane fade" id="tab-symptom">
        <div class="card"><div class="card-body">
          <p class="text-muted">Describe your symptoms and we'll suggest the right kind of specialist.
            <span class="text-danger">This is informational only — not a diagnosis.</span></p>
          <div class="input-group mb-3">
            <input type="text" id="symInput" class="form-control" placeholder="e.g. sore throat and fever for 3 days">
            <button class="btn btn-primary" id="symGo">Check</button>
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
  function bubble(text, who) {
    const div = document.createElement('div');
    div.className = (who === 'user' ? 'align-self-end bg-primary text-white' : 'align-self-start bg-light') + ' rounded p-2 px-3';
    div.innerHTML = esc(text);
    chatLog.appendChild(div);
    chatLog.scrollTop = chatLog.scrollHeight;
    return div;
  }
  function sendChat() {
    const input = document.getElementById('chatInput');
    const text = input.value.trim();
    if (!text) return;
    bubble(text, 'user');
    history.push({ role: 'user', content: text });
    input.value = '';
    const thinking = bubble('…', 'bot');
    fetch('{{ route('ai.chat') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify({ messages: history }),
    }).then(r => r.json()).then(data => {
      thinking.innerHTML = esc(data.reply);
      history.push({ role: 'assistant', content: data.reply });
      if (data.booking_link) {
        const cta = document.createElement('div');
        cta.className = 'align-self-start';
        let prov = (data.providers || []).map(p => '<span class="badge bg-light text-dark">' + esc(p.name) + '</span>').join(' ');
        cta.innerHTML = (prov ? '<div class="mb-1 small">Matching: ' + prov + '</div>' : '') +
          '<a class="btn btn-sm btn-success" href="' + data.booking_link + '">Continue to booking →</a>';
        chatLog.appendChild(cta);
        chatLog.scrollTop = chatLog.scrollHeight;
      }
    }).catch(() => thinking.innerHTML = '<span class="text-danger">Sorry, something went wrong. Please try again.</span>');
  }
  document.getElementById('chatSend').addEventListener('click', sendChat);
  document.getElementById('chatInput').addEventListener('keydown', e => { if (e.key === 'Enter') sendChat(); });

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
      let html = '<div class="card border"><div class="card-body">';
      html += '<div class="mb-2">Suggested specialty: <strong>' + esc(data.specialty || '—') + '</strong> ';
      html += '<span class="badge bg-' + (colors[data.urgency] || 'secondary') + '">' + esc(data.urgency) + '</span></div>';
      html += '<p class="small text-muted">' + esc(data.advice) + '</p>';
      if ((data.providers || []).length) {
        html += '<div class="mb-2">Providers: ' + data.providers.map(p => '<span class="badge bg-light text-dark">' + esc(p.name) + '</span>').join(' ') + '</div>';
      }
      html += '<a class="btn btn-sm btn-primary" href="' + BOOK_URL + '">Book an appointment</a>';
      html += '</div></div>';
      box.innerHTML = html;
    }).catch(() => box.innerHTML = '<div class="text-danger small">Could not check symptoms. Try rephrasing.</div>');
  });
</script>
@endpush
