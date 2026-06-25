{{-- Global AI navigation assistant — floating widget for every signed-in user. --}}
<style>
  #sasAiFab{position:fixed;right:22px;bottom:22px;z-index:1085;width:56px;height:56px;border-radius:50%;
    background:#5955D1;color:#fff;border:0;box-shadow:0 6px 20px rgba(89,85,209,.45);font-size:22px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;transition:transform .15s}
  #sasAiFab:hover{transform:scale(1.06)}
  #sasAiPanel{position:fixed;right:22px;bottom:88px;z-index:1086;width:340px;max-width:calc(100vw - 44px);
    background:#fff;border:1px solid #e6e6ef;border-radius:16px;box-shadow:0 12px 40px rgba(20,20,50,.18);
    display:none;overflow:hidden}
  #sasAiPanel.open{display:block}
  #sasAiPanel .sas-ai-head{background:#5955D1;color:#fff;padding:12px 16px;font-weight:600;display:flex;align-items:center;gap:8px}
  #sasAiLog{padding:12px;max-height:300px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;font-size:.9rem}
  #sasAiLog .b{padding:8px 12px;border-radius:12px;max-width:85%}
  #sasAiLog .b.bot{background:#f1f1f8;align-self:flex-start}
  #sasAiLog .b.me{background:#5955D1;color:#fff;align-self:flex-end}
  #sasAiPanel .sas-ai-foot{display:flex;gap:6px;padding:10px;border-top:1px solid #eee}
  #sasAiPanel .sas-ai-foot input{flex:1;border:1px solid #ddd;border-radius:10px;padding:8px 10px;font-size:.9rem}
  #sasAiPanel .sas-ai-foot button{border:0;background:#5955D1;color:#fff;border-radius:10px;padding:0 14px;cursor:pointer}
  .sas-ai-chip{display:inline-block;margin-top:4px;font-size:.78rem;background:#fff;border:1px solid #5955D1;color:#5955D1;
    border-radius:20px;padding:3px 12px;text-decoration:none}
</style>

<button id="sasAiFab" title="Ask the assistant" aria-label="Ask the assistant"><i class="fi fi-rr-sparkles"></i></button>

<div id="sasAiPanel" aria-live="polite">
  <div class="sas-ai-head">
    <i class="fi fi-rr-sparkles"></i> Quick Assistant
    <button type="button" id="sasAiClose" class="btn-close btn-close-white ms-auto" aria-label="Close"></button>
  </div>
  <div id="sasAiLog">
    <div class="b bot">Hi! Tell me where to go — e.g. <em>"open patients list"</em>, <em>"create a new clinic"</em>, or <em>"show reports"</em>.</div>
  </div>
  <div class="sas-ai-foot">
    <input type="text" id="sasAiInput" placeholder="Where do you want to go?" autocomplete="off">
    <button type="button" id="sasAiSend"><i class="fi fi-rr-paper-plane"></i></button>
  </div>
</div>

<script>
(function () {
  const fab = document.getElementById('sasAiFab');
  const panel = document.getElementById('sasAiPanel');
  const log = document.getElementById('sasAiLog');
  const input = document.getElementById('sasAiInput');
  const esc = s => (s || '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  function toggle(open) {
    panel.classList.toggle('open', open);
    if (open) setTimeout(() => input.focus(), 50);
  }
  fab.addEventListener('click', () => toggle(!panel.classList.contains('open')));
  document.getElementById('sasAiClose').addEventListener('click', () => toggle(false));

  function bubble(html, who) {
    const d = document.createElement('div');
    d.className = 'b ' + (who === 'me' ? 'me' : 'bot');
    d.innerHTML = html;
    log.appendChild(d);
    log.scrollTop = log.scrollHeight;
    return d;
  }

  function send() {
    const text = input.value.trim();
    if (!text) return;
    bubble(esc(text), 'me');
    input.value = '';
    const thinking = bubble('…', 'bot');
    fetch('{{ route('ai.command') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
      body: JSON.stringify({ text }),
    }).then(r => r.json()).then(data => {
      thinking.innerHTML = esc(data.reply);
      if (data.url) {
        const linkText = data.auto ? ('Go to ' + esc(data.label) + ' →') : ('View ' + esc(data.label || 'results') + ' →');
        thinking.innerHTML += '<br><a class="sas-ai-chip" href="' + data.url + '">' + linkText + '</a>';
        // Only auto-navigate for navigation intents; data answers stay in chat.
        if (data.auto) setTimeout(() => { window.location.href = data.url; }, 900);
      }
    }).catch(() => thinking.innerHTML = '<span class="text-danger">Something went wrong. Please try again.</span>');
  }

  document.getElementById('sasAiSend').addEventListener('click', send);
  input.addEventListener('keydown', e => { if (e.key === 'Enter') send(); });
})();
</script>
