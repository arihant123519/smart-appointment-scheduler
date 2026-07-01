{{-- Global AI navigation assistant — floating widget for every signed-in user. --}}
<style>
  #sasAiFab{position:fixed;right:22px;bottom:22px;z-index:1085;width:60px;height:60px;border-radius:50%;
    background:linear-gradient(135deg,#5955D1,#8a87e6);color:#fff;border:0;box-shadow:0 8px 24px rgba(89,85,209,.5);
    font-size:23px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:transform .15s}
  #sasAiFab:hover{transform:scale(1.08) rotate(6deg)}
  #sasAiFab::after{content:'';position:absolute;inset:0;border-radius:50%;border:2px solid rgba(89,85,209,.5);
    animation:sasFabPulse 2s infinite}
  @keyframes sasFabPulse{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.5);opacity:0}}
  #sasAiPanel{position:fixed;right:22px;bottom:92px;z-index:1086;width:360px;max-width:calc(100vw - 44px);
    background:#fff;border:0;border-radius:18px;box-shadow:0 16px 50px rgba(20,20,50,.22);
    display:none;overflow:hidden;transform-origin:bottom right}
  #sasAiPanel.open{display:block;animation:sasPanelIn .2s ease-out}
  @keyframes sasPanelIn{from{opacity:0;transform:translateY(12px) scale(.96)}to{opacity:1;transform:none}}
  #sasAiPanel .sas-ai-head{background:linear-gradient(135deg,#5955D1,#8a87e6);color:#fff;padding:14px 16px;
    font-weight:600;display:flex;align-items:center;gap:8px;position:relative;overflow:hidden}
  #sasAiPanel .sas-ai-head::after{content:'';position:absolute;right:-20px;top:-30px;width:90px;height:90px;
    border-radius:50%;background:rgba(255,255,255,.12)}
  #sasAiPanel .sas-ai-head .sas-ai-headicon{width:30px;height:30px;border-radius:9px;background:rgba(255,255,255,.2);
    display:grid;place-items:center}
  #sasAiLog{padding:14px;max-height:320px;overflow-y:auto;display:flex;flex-direction:column;gap:10px;font-size:.9rem;
    background:#fafaff}
  #sasAiLog .sas-row{display:flex;gap:8px;max-width:90%}
  #sasAiLog .sas-row.me{align-self:flex-end;flex-direction:row-reverse}
  #sasAiLog .av{width:28px;height:28px;border-radius:50%;flex:0 0 28px;display:grid;place-items:center;color:#fff;font-size:.8rem}
  #sasAiLog .sas-row.bot .av{background:linear-gradient(135deg,#5955D1,#8a87e6)}
  #sasAiLog .sas-row.me .av{background:#1f2140}
  #sasAiLog .b{padding:8px 12px;border-radius:13px;line-height:1.4}
  #sasAiLog .sas-row.bot .b{background:#fff;border:1px solid #ececf6;border-top-left-radius:4px}
  #sasAiLog .sas-row.me .b{background:#5955D1;color:#fff;border-top-right-radius:4px}
  .sas-ai-quick{display:flex;flex-wrap:wrap;gap:6px;padding:0 14px 12px;background:#fafaff}
  .sas-ai-quick button{border:1px solid #d9d8f0;background:#fff;color:#5955D1;border-radius:999px;padding:4px 11px;
    font-size:.76rem;cursor:pointer;transition:all .15s}
  .sas-ai-quick button:hover{background:#5955D1;color:#fff;border-color:#5955D1}
  #sasAiPanel .sas-ai-foot{display:flex;gap:8px;padding:12px;border-top:1px solid #eee;background:#fff}
  #sasAiPanel .sas-ai-foot input{flex:1;border:1px solid #ddd;border-radius:999px;padding:9px 14px;font-size:.9rem}
  #sasAiPanel .sas-ai-foot input:focus{outline:none;border-color:#8a87e6;box-shadow:0 0 0 3px rgba(138,135,230,.18)}
  #sasAiPanel .sas-ai-foot button{border:0;background:linear-gradient(135deg,#5955D1,#8a87e6);color:#fff;
    border-radius:50%;width:40px;height:40px;cursor:pointer;flex:0 0 40px}
  .sas-ai-chip{display:inline-block;margin-top:6px;font-size:.78rem;background:#fff;border:1px solid #5955D1;color:#5955D1;
    border-radius:20px;padding:4px 13px;text-decoration:none}
  .sas-ai-chip:hover{background:#5955D1;color:#fff}
  .sas-ai-typing span{display:inline-block;width:6px;height:6px;margin:0 1px;background:#9a98c4;border-radius:50%;
    animation:sasDot 1.2s infinite ease-in-out both}
  .sas-ai-typing span:nth-child(2){animation-delay:.15s}.sas-ai-typing span:nth-child(3){animation-delay:.3s}
  @keyframes sasDot{0%,80%,100%{transform:scale(.6);opacity:.5}40%{transform:scale(1);opacity:1}}
</style>

<button id="sasAiFab" title="Ask the assistant" aria-label="Ask the assistant"><i class="fi fi-rr-sparkles"></i></button>

<div id="sasAiPanel" aria-live="polite">
  <div class="sas-ai-head">
    <span class="sas-ai-headicon"><i class="fi fi-rr-sparkles"></i></span>
    <div>
      <div>Quick Assistant</div>
      <div style="font-size:.72rem;font-weight:400;opacity:.85">Navigate by typing</div>
    </div>
    <button type="button" id="sasAiClose" class="btn-close btn-close-white ms-auto" aria-label="Close"></button>
  </div>
  <div id="sasAiLog">
    <div class="sas-row bot">
      <div class="av"><i class="fi fi-rr-sparkles"></i></div>
      <div class="b">Hi! Tell me where to go — e.g. <em>"open patients list"</em>, <em>"create a new clinic"</em>, or <em>"show reports"</em>.</div>
    </div>
  </div>
  <div class="sas-ai-quick" id="sasAiQuick">
    <button type="button" data-fill="open patients list">👥 Patients</button>
    <button type="button" data-fill="show reports">📊 Reports</button>
    <button type="button" data-fill="open calendar">📅 Calendar</button>
    <button type="button" data-fill="new appointment">➕ New appointment</button>
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

  const TYPING = '<span class="sas-ai-typing"><span></span><span></span><span></span></span>';
  function bubble(html, who) {
    const row = document.createElement('div');
    row.className = 'sas-row ' + (who === 'me' ? 'me' : 'bot');
    const ico = who === 'me' ? 'fi-rr-user' : 'fi-rr-sparkles';
    row.innerHTML = '<div class="av"><i class="fi ' + ico + '"></i></div><div class="b">' + html + '</div>';
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
    return row.querySelector('.b');
  }

  function send() {
    const text = input.value.trim();
    if (!text) return;
    bubble(esc(text), 'me');
    input.value = '';
    const thinking = bubble(TYPING, 'bot');
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

  document.querySelectorAll('#sasAiQuick button').forEach(function (btn) {
    btn.addEventListener('click', function () { input.value = btn.dataset.fill; send(); });
  });
})();
</script>
