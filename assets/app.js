console.log('[bootAvailable] start');
/* TickItNow – UI-only behavior (no AJAX). Uses localStorage for preferences. */
const LS_KEY = "tickitnow_prefs_v1";
const PREFS = {
  get(){ try { return JSON.parse(localStorage.getItem(LS_KEY)) || []; } catch { return []; } },
  set(items){ localStorage.setItem(LS_KEY, JSON.stringify(items)); },
  add(item){
    const items = PREFS.get();
    const exists = items.find(x => x.id === item.id && x.start_at === item.start_at);
    if (exists){ alert("Already in your preferences."); return; }
    item.rank_pos = items.length + 1;
    items.push(item);
    PREFS.set(items);
    alert("Added to your preferences!");
  },
  remove(id, start_at){
    let items = PREFS.get().filter(x => !(x.id===id && x.start_at===start_at));
    items = items.map((x,i)=>({...x, rank_pos:i+1}));
    PREFS.set(items);
  },
  move(id, start_at, dir){
    const items = PREFS.get();
    const idx = items.findIndex(x=>x.id===id && x.start_at===start_at);
    if (idx<0) return;
    const swapIdx = dir === "up" ? idx-1 : idx+1;
    if (swapIdx < 0 || swapIdx >= items.length) return;
    const temp = items[idx];
    items[idx] = items[swapIdx];
    items[swapIdx] = temp;
    items.forEach((x,i)=>x.rank_pos=i+1);
    PREFS.set(items);
    renderPreferences && renderPreferences();
  },
  clear(){ localStorage.removeItem(LS_KEY); }
};

function qsel(q){ return document.querySelector(q); }
function qall(q){ return [...document.querySelectorAll(q)]; }
function fmtDateTime(iso){
  try{ return new Date(iso).toLocaleString(); } catch{ return iso; }
}
function fmtDateTimeLocal(isoLocal){
  if(!isoLocal) return '';
  return new Date(isoLocal).toLocaleString();
}

// ---------- Page-specific bootstraps ----------

// shows.html – populate static gallery
function bootShows(){
  const shows = [
    {id:1,title:"Fight Club",genre:"Action",rating:"R",poster:"assets/posters/fightclub.jpeg"},
    {id:2,title:"The Wolf of Wall Street",genre:"Biography",rating:"R",poster:"assets/posters/wolf.jpeg"},
    {id:3,title:"Interstellar",genre:"Sci-Fi",rating:"PG13",poster:"assets/posters/interstellar.jpeg"},
    {id:4,title:"Spiderman",genre:"Action",rating:"PG13",poster:"assets/posters/spiderman.jpeg"},
    {id:5,title:"Hacksaw Ridge",genre:"Sci-Fi",rating:"PG13",poster:"assets/posters/hacksaw.jpeg"}
  ];
  const grid = qsel("#shows-grid");
  if (!grid) return;
  grid.innerHTML = shows.map(s=>`
    <div class="card">
      <div class="card-body">
        <img class="poster" src="${s.poster}" alt="${s.title} poster"/>
        <div class="title">${s.title}</div>
        <div class="meta">${s.genre} • ${s.rating}</div>
        <div class="flex" style="margin-top:10px">
          <a class="btn primary" href="show.html?id=${s.id}">View Showtimes</a>
          <span class="tag">#${s.id.toString().padStart(3,"0")}</span>
        </div>
      </div>
    </div>
  `).join("");
}

// preferences.html – load from DB first, fall back to localStorage
async function renderPreferences(){
  const tbody = document.querySelector("#prefs-body");
  if (!tbody) return;

  let rows = [];
  try{
    const r = await fetch('api/list_preferences.php', {headers:{'Accept':'application/json'}});
    if(r.ok) rows = await r.json();
  }catch(e){ /* ignore */ }

  if(!rows.length){
    const items = PREFS.get();
    if(!items.length){
      tbody.innerHTML = `<tr><td colspan="6" class="meta">No preferences yet. Go to <a href="shows.html">Browse Shows</a>.</td></tr>`;
      setReviewAvailabilityState(false);
      return;
    }
    tbody.innerHTML = items.map(x=>`
      <tr>
        <td><span class="badge">#${x.rank_pos}</span></td>
        <td>${x.title}<div class="meta">${x.venue}</div></td>
        <td>${fmtDateTimeLocal(x.start_at)}</td>
        <td>${x.tickets || 2}</td>
        <td>$${Number(x.price||0).toFixed(2)}</td>
        <td class="right">
          <button class="btn" onclick="PREFS.move(${x.id}, '${x.start_at}','up')">↑</button>
          <button class="btn" onclick="PREFS.move(${x.id}, '${x.start_at}','down')">↓</button>
          <button class="btn danger" onclick="PREFS.remove(${x.id}, '${x.start_at}'); renderPreferences()">Remove</button>
        </td>
      </tr>
    `).join('');
    setReviewAvailabilityState(true);
    return;
  }

  tbody.innerHTML = rows.map((x,i)=>`
    <tr>
      <td><span class="badge">#${i+1}</span></td>
      <td>${x.show_title || ('Show #' + x.show_id)}<div class="meta">${x.venue_name}</div></td>
      <td>${fmtDateTimeLocal(x.start_at_iso)}</td>
      <td>${x.qty}</td>
      <td>$${Number(x.price||0).toFixed(2)}</td>
      <td class="right">
        <button class="btn" onclick="movePref(${x.id}, 'up')">↑</button>
        <button class="btn" onclick="movePref(${x.id}, 'down')">↓</button>
        <button class="btn danger" onclick="removePref(${x.id})">Remove</button>
      </td>
    </tr>
  `).join('');
}
// --- Review Availability guard (block if no prefs) ---
async function countServerPrefs() {
  try {
    const r = await fetch('api/list_preferences.php', { headers:{'Accept':'application/json'} });
    if (!r.ok) return 0;
    const rows = await r.json();
    return Array.isArray(rows) ? rows.length : 0;
  } catch { return 0; }
}

// keep these in module scope so renderPreferences can update the state
let _lastPrefCount = 0;
function setReviewAvailabilityState(hasAny) {
  const btn = document.getElementById('review-availability');
  if (!btn) return;

  _lastPrefCount = hasAny ? 1 : 0;

  // disable visuals & semantics
  if (!hasAny) {
    btn.setAttribute('aria-disabled', 'true');
    btn.classList.add('btn-disabled');       // optional class for styling if you have it
    // keep href for right-click copy, but block click below
  } else {
    btn.removeAttribute('aria-disabled');
    btn.classList.remove('btn-disabled');
  }
}

function initReviewAvailabilityGuard() {
  const btn = document.getElementById('review-availability');
  if (!btn) return;

  // Click interception
  btn.addEventListener('click', async (e) => {
    // quick localStorage check first
    const localCount = (Array.isArray(PREFS.get()) ? PREFS.get().length : 0);
    if (localCount > 0 || _lastPrefCount > 0) return; // allow

    e.preventDefault();

    // confirm with server (covers logged-in users using DB-backed prefs)
    const serverCount = await countServerPrefs();
    if (serverCount > 0) {
      // allow now
      location.href = btn.getAttribute('href') || 'available.html';
      return;
    }

    alert('You have no preferences yet. Add some showtimes first on the show page.');
  }, { passive:false });

  // Initial state from server+local
  (async () => {
    const serverCount = await countServerPrefs();
    const localCount  = Array.isArray(PREFS.get()) ? PREFS.get().length : 0;
    setReviewAvailabilityState( (serverCount + localCount) > 0 );
  })();
}

async function removePref(id){
  const form = new URLSearchParams(); form.set('id', id);
  const r = await fetch('api/remove_preference.php', { method:'POST', body:form });
  if(r.ok){ await renderPreferences(); } else { alert('Failed to remove'); }
}
async function clearAllPrefs(){
  if(!confirm('Clear all preferences?')) return;
  const r = await fetch('api/clear_preferences.php', { method:'POST' });
  if(r.ok){ await renderPreferences(); } else { alert('Failed to clear'); }
}
async function movePref(id, dir){
  const form = new URLSearchParams(); form.set('id', id); form.set('dir', dir);
  const r = await fetch('api/move_preference.php', { method:'POST', body:form });
  if(r.ok){ await renderPreferences(); } else { alert('Failed to move'); }
}

// --- auth helper: get current user from server (or unauthenticated) ---
async function getCurrentUser(){
  try{
    const r = await fetch('api/me.php', { headers:{'Accept':'application/json'} });
    if(!r.ok) return { authenticated:false };
    return await r.json(); // {authenticated, name, email, phone}
  }catch{ return { authenticated:false }; }
}

/* =========================================
   LIVE VALIDATION HELPERS (no framework)
   ========================================= */
function applyBuyerFieldConstraints() {
  const nameEl  = document.getElementById('buyer-name');
  const phoneEl = document.getElementById('buyer-phone');
  const emailEl = document.getElementById('buyer-email');
  if (!nameEl || !phoneEl || !emailEl) return;

  // Safe, browser-compatible constraints
  nameEl.setAttribute('required', 'true');
  nameEl.setAttribute('minlength', '2');
  nameEl.setAttribute('maxlength', '50');
  nameEl.setAttribute('autocomplete', 'name');

  phoneEl.setAttribute('required', 'true');
  phoneEl.setAttribute('inputmode', 'tel');
  phoneEl.setAttribute('autocomplete', 'tel');
  phoneEl.setAttribute('pattern', '^[0-9()+\\- ]{7,20}$'); // escape hyphen

  emailEl.setAttribute('required', 'true');
  // keep existing type if already email; otherwise enforce
  if (emailEl.getAttribute('type') !== 'email') emailEl.setAttribute('type', 'email');
  emailEl.setAttribute('autocomplete', 'email');
}

function attachLiveValidationForBuyerForm() {
  const nameEl  = document.getElementById('buyer-name');
  const phoneEl = document.getElementById('buyer-phone');
  const emailEl = document.getElementById('buyer-email');

  if (!nameEl || !phoneEl || !emailEl) return;

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/; // lenient & practical
  const nameOK  = v => v.trim().length >= 2;
  const phoneOK = v => /^[0-9()+\- ]{7,20}$/.test(v.trim());
  const emailOK = v => emailRegex.test(v.trim());

  function validateName()  { const ok = nameOK(nameEl.value);  nameEl.setCustomValidity(ok ? '' : 'Please enter at least 2 characters.'); return ok; }
  function validatePhone() { const ok = phoneOK(phoneEl.value); phoneEl.setCustomValidity(ok ? '' : 'Use digits, spaces, +, ( ), or - (7–20 characters).'); return ok; }
  function validateEmail() {
    // Trim in-place so the DOM shows the corrected value
    emailEl.value = emailEl.value.trim().toLowerCase();
    const ok = emailOK(emailEl.value);
    emailEl.setCustomValidity(ok ? '' : 'Please enter a valid email (e.g., name@site.com).');
    return ok;
  }

  nameEl.addEventListener('input',  () => validateName()  && nameEl.reportValidity());
  phoneEl.addEventListener('input', () => validatePhone() && phoneEl.reportValidity());
  emailEl.addEventListener('input', () => validateEmail() && emailEl.reportValidity());

  [nameEl, phoneEl, emailEl].forEach(el => el.addEventListener('blur', () => el.reportValidity()));

  // Final guard on submit (bootAvailable already calls reportValidity, this ensures we trim first)
  const form = document.querySelector('#final-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      const ok = validateName() & validatePhone() & validateEmail(); // bitwise & to run all
      if (!ok) { e.preventDefault(); [nameEl, phoneEl, emailEl].forEach(el => el.reportValidity()); }
    }, { once: true }); // only attach once
  }
}


// Optional: register/create-account page live validation
function attachLiveValidationForRegisterForm() {
  const nameEl  = document.getElementById('reg-name');
  const phoneEl = document.getElementById('reg-phone');
  const emailEl = document.getElementById('reg-email');
  const passEl  = document.getElementById('reg-pass');
  if (!nameEl || !phoneEl || !emailEl || !passEl) return;

  const nameOK  = v => v.trim().length >= 2;
  const phoneOK = v => /^[0-9()+\- ]{7,20}$/.test(v.trim());
  const emailOK = el => el.checkValidity();
  const passOK  = v => v.length >= 6;

  function bind(el, ok, msg) {
    const run = () => { el.setCustomValidity(ok(el.value) ? '' : msg); el.reportValidity(); };
    el.addEventListener('input', run);
    el.addEventListener('blur', run);
  }

  bind(nameEl,  nameOK,  'Please enter at least 2 characters.');
  bind(phoneEl, phoneOK, 'Use digits, spaces, +, (), or - (7–20 chars).');
  bind(emailEl, _ => emailOK(emailEl), 'Please enter a valid email.');
  bind(passEl,  passOK,  'Password must be at least 6 characters.');
}

/* =========================================
   available.html – availability table
   ========================================= */
window.bootAvailable = async function bootAvailable(){
  console.log('[bootAvailable] start');
  try { sessionStorage.removeItem('tickitnow_last_booking'); } catch {}
  const tbody = document.querySelector("#avail-body");
  const form  = document.querySelector("#final-form");
  const nameEl  = document.getElementById('buyer-name');
  const phoneEl = document.getElementById('buyer-phone');
  const emailEl = document.getElementById('buyer-email');
  const noteEl  = document.getElementById('buyer-note');
  const hintEl  = document.getElementById('buyer-hint');
  if(!tbody || !form){ console.error('missing form/table'); return; }
// At start of bootAvailable()
// try { sessionStorage.removeItem('tickitnow_last_booking'); } catch {}
  // Apply constraints & live validation immediately
  applyBuyerFieldConstraints();
  attachLiveValidationForBuyerForm();

  // 1) Get current user for prefill
  const me = await getCurrentUser(); // {authenticated, name, email, phone}
  if(me.authenticated){
    if(me.name)  nameEl.value  = me.name;
    if(me.phone) phoneEl.value = me.phone;
    if(me.email) emailEl.value = me.email;
    hintEl.style.display = 'block';
    hintEl.textContent = 'You are signed in. You can edit these details for this booking if needed.';
  }else{
    hintEl.style.display = 'block';
    hintEl.textContent = 'Not signed in — you can fill these now, but you’ll be asked to log in or create an account before confirming.';
  }

  // 2) Load rows (your existing logic)
  let rows = [];
  try{
    const r = await fetch('api/list_available.php', { headers:{'Accept':'application/json'} });
    rows = r.ok ? await r.json() : [];
  }catch{}
  if((!Array.isArray(rows) || rows.length === 0) && window.PREFS){
    const items = PREFS.get();
    if (items && items.length){
      rows = items.map((x,i)=>({
        id: i+1,
        rank: x.rank_pos || (i+1),
        show_id: x.id,
        show_title: x.title || ('Show #' + x.id),
        venue_name: x.venue,
        start_at_iso: (x.start_at || '').replace(' ', 'T'),
        ticket_class: x.ticket_class || 'Standard',
        qty: x.tickets || 2,
        price: x.price || 0,
        available_qty: 999,
        is_available: 1
      }));
    }
  }
  if(!Array.isArray(rows) || rows.length === 0){
    tbody.innerHTML = `<tr><td colspan="6" class="meta">No items in preferences.</td></tr>`;
    return;
  }
  tbody.innerHTML = rows.map((x,i)=>{
    const available = !!Number(x.is_available);
    const statusBadge = available
      ? `<span class="badge" style="border-color:#204f36;color:#7ae2a0;background:#0a2217">Available</span>`
      : `<span class="badge" style="border-color:#44202d;color:#f7a5b2;background:#220b12">Not Available</span>`;
    const checkbox = available
      ? `<input type="checkbox" name="select_item_ids" value="${x.id}">`
      : `<input type="checkbox" disabled title="Insufficient seats">`;
    return `
      <tr>
        <td><span class="badge">#${x.rank || i+1}</span></td>
        <td>${x.show_title || ('Show #' + x.show_id)}
          <div class="meta">${x.venue_name}${x.ticket_class ? ' • ' + x.ticket_class : ''}</div>
        </td>
        <td>${fmtDateTimeLocal(x.start_at_iso)}</td>
        <td>${x.qty}</td>
        <td>${statusBadge} ${typeof x.available_qty!=="undefined" ? `<span class="meta" style="margin-left:6px">(Left: ${x.available_qty})</span>` : ''}</td>
        <td class="right">${checkbox}</td>
      </tr>`;
  }).join('');

  // 3) Submit
  form.addEventListener('submit', async (e)=>{
    e.preventDefault(); // make sure we always stop the native navigation
  
    const selectedIds = [...document.querySelectorAll('input[name="select_item_ids"]:checked')].map(x=>x.value);
    if(!selectedIds.length){ alert('Please select at least one available booking.'); return; }
    if(!nameEl.reportValidity() || !phoneEl.reportValidity() || !emailEl.reportValidity()){ return; }
  
    // ... fetch to api/confirm_selection.php ...
  
    const resp = await fetch('api/confirm_selection.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        select_item_ids: selectedIds,
        buyer_name:  nameEl.value.trim(),
        buyer_phone: phoneEl.value.trim(),
        buyer_email: emailEl.value.trim(),
        buyer_note:  noteEl.value.trim()
      })
    });
  
    let json;
    try { json = await resp.json(); } catch { json = null; }
    if(!resp.ok || !json || json.error){
      console.error('Confirm error:', json || '(no JSON)');
      alert((json && json.error) || 'Failed to confirm selection');
      return;
    }
  
    // --- Save confirmation payload ---
    try {
      sessionStorage.setItem("tickitnow_last_booking", JSON.stringify({
        when: new Date().toISOString(),
        booking_ref: json.booking_ref,
        buyer: json.buyer,
        total: json.total,
        items: (json.items||[]).map(x=>({
          show_id: x.show_id,
          venue_name: x.venue_name,
          start_at: x.start_at,
          ticket_class: x.ticket_class,
          qty: x.qty,
          unit_price: x.unit_price,  // <- from server
          price: x.unit_price
        }))
      }));
    } catch (e) {
      console.error('Failed to write sessionStorage:', e);
    }
  
    // Clear preferences after a successful booking
    if (window.PREFS?.clear) { try { PREFS.clear(); } catch {} }
  
    // Navigate with a cache-buster + use replace() to avoid stale back nav
    location.replace('confirmation.html?bref=' + encodeURIComponent(json.booking_ref) + '&t=' + Date.now());
  });
};

// confirmation.html – read session data and show summary
window.bootConfirm = function bootConfirm(){
  console.log('[bootConfirm] start');
  const target = document.querySelector("#confirm-body");
  if (!target) { console.error('[bootConfirm] #confirm-body missing'); return; }

  function render(){
    let data = null;
    try { data = sessionStorage.getItem("tickitnow_last_booking"); } catch {}
    if (!data){
      target.innerHTML = `<div class="card"><div class="card-body">
        <p class="meta">No booking found. Go to <a href="shows.html">Browse Shows</a>.</p>
      </div></div>`;
      return;
    }
    let obj;
    try { obj = JSON.parse(data); } catch { obj = null; }
    if (!obj){
      target.innerHTML = `<div class="card"><div class="card-body">
        <p class="meta">Could not read booking data.</p>
      </div></div>`;
      return;
    }

    const total = obj.total ?? obj.items.reduce((s,x)=> s + (x.qty||2) * (x.unit_price||0), 0);

    target.innerHTML = `
      <div class="card"><div class="card-body">
        <h2>🎉 Booking Confirmed</h2>
        <p class="meta">Reference: <strong>${obj.booking_ref || '(pending)'}</strong></p>
        <p class="meta">Booked at ${new Date(obj.when).toLocaleString()}</p>

        ${obj.buyer ? `
        <div class="note-box" style="margin:10px 0">
          <div><strong>Buyer:</strong> ${obj.buyer.name || ''}</div>
          <div class="meta">Phone: ${obj.buyer.phone || '-'} • Email: ${obj.buyer.email || '-'}</div>
          ${obj.buyer.note ? `<div class="meta">Note: ${obj.buyer.note}</div>` : ''}
        </div>` : ''}

        <table class="table" style="margin-top:10px">
          <thead><tr><th>Show#</th><th>When</th><th>Venue</th><th>Class</th><th>Qty</th><th>Price</th></tr></thead>
          <tbody>
            ${(obj.items||[]).map(x=>`
              <tr>
                <td>${x.show_id}</td>
                <td>${x.start_at}</td>
                <td>${x.venue_name}</td>
                <td>${x.ticket_class}</td>
                <td>${x.qty}</td>
                <td>$${Number(x.unit_price||x.price||0).toFixed(2)}</td>
              </tr>
            `).join("")}
          </tbody>
        </table>

        <div class="flex space-between" style="margin-top:10px">
          <strong>Total</strong>
          <strong>$${Number(total).toFixed(2)}</strong>
        </div>
        <div class="flex" style="margin-top:16px; gap:10px">
        ${obj.booking_ref ? `
          <a class="btn" href="receipt.php?ref=${encodeURIComponent(obj.booking_ref)}" target="_blank" rel="noopener">
            View receipt
          </a>
        ` : ''}
        <a class="btn primary" href="shows.html">Book More</a>
        <a class="btn ghost" href="index.html">Home</a>
      </div>
      </div></div>
    `;
  }

  // initial render + handle bfcache restores
  render();
  window.addEventListener('pageshow', () => {
    console.log('[bootConfirm] pageshow — re-render');
    render();
    // optional: clean URL (remove cache-buster)
    try {
      const url = new URL(location.href);
      if (url.searchParams.has('t')) { url.searchParams.delete('t'); history.replaceState(null,'',url.toString()); }
    } catch {}
  });
};


/* ===== Featured Movies Carousel (no libs) ===== */
(function(){
  const data = [
    { id:1, title:"Fight Club", blurb:"An underground fight club becomes something far darker.", img:"assets/posters/fightclub.jpeg" },
    { id:2, title:"The Wolf of Wall Street", blurb:"Greed, excess, and chaos on Wall Street.", img:"assets/posters/wolf.jpeg" },
    { id:3, title:"Interstellar", blurb:"A journey through space to save humanity.", img:"assets/posters/interstellar.jpeg" },
    { id:4, title:"Spiderman", blurb:"An ordinary teen discovers extraordinary power.", img:"assets/posters/spiderman.jpeg" },
    { id:5, title:"Hacksaw Ridge", blurb:"A medic’s courage turns the tide on the bloodiest battlefield.", img:"assets/posters/hacksaw.jpeg" }
  ];

  const track = document.getElementById('carousel-track');
  if(!track) return;

  track.innerHTML = data.map(d => `
    <article class="slide" role="group" aria-roledescription="slide" aria-label="${d.title}">
      <img src="${d.img}" alt="${d.title} poster">
      <div class="caption">
        <h3>${d.title}</h3>
        <p class="meta">${d.blurb}</p>
        <div class="actions">
          <a class="btn primary" href="show.html?id=${d.id}">View Showtimes</a>
          <a class="btn ghost" href="shows.html">Browse All</a>
        </div>
      </div>
    </article>
  `).join('');

  const dotsWrap = document.getElementById('carousel-dots');
  dotsWrap.innerHTML = data.map((_,i)=>`<button aria-label="Go to slide ${i+1}" data-i="${i}"></button>`).join('');

  const prevBtn = document.getElementById('carousel-prev');
  const nextBtn = document.getElementById('carousel-next');
  const dots = [...dotsWrap.querySelectorAll('button')];

  let i = 0, N = data.length, timer = null, hovering = false;

  function go(n){
    i = (n + N) % N;
    track.style.transform = `translateX(-${i*100}%)`;
    dots.forEach((d,idx)=> d.setAttribute('aria-current', idx===i ? 'true' : 'false'));
  }
  function next(){ go(i+1); }
  function prev(){ go(i-1); }
  function start(){ stop(); timer = setInterval(()=>{ if(!hovering) next(); }, 4500); }
  function stop(){ if(timer) clearInterval(timer); }

  nextBtn.addEventListener('click', ()=>{ next(); start(); });
  prevBtn.addEventListener('click', ()=>{ prev(); start(); });
  dots.forEach(d=> d.addEventListener('click', e=>{ go(+e.currentTarget.dataset.i); start(); }));
  track.addEventListener('mouseenter', ()=>{ hovering = true; });
  track.addEventListener('mouseleave', ()=>{ hovering = false; });
  track.tabIndex = 0;
  track.addEventListener('keydown', e=>{
    if(e.key === 'ArrowRight') { next(); start(); }
    if(e.key === 'ArrowLeft')  { prev(); start(); }
  });
  let sx=0, dx=0;
  track.addEventListener('touchstart', e=>{ sx = e.touches[0].clientX; dx = 0; }, {passive:true});
  track.addEventListener('touchmove',  e=>{ dx = e.touches[0].clientX - sx; }, {passive:true});
  track.addEventListener('touchend',   ()=>{ if(Math.abs(dx) > 50){ dx < 0 ? next() : prev(); start(); } });

  go(0); start();
})();

/* ===== Show page (DB-driven times) ===== */
function toEmbed(url){
  if(!url) return "";
  try{
    const u = new URL(url);
    if (u.hostname.includes('youtu.be'))
      return `https://www.youtube-nocookie.com/embed/${u.pathname.slice(1)}?autoplay=0&mute=0&rel=0`;
    if (u.hostname.includes('youtube.com')){
      const v = u.searchParams.get('v');
      if (v) return `https://www.youtube-nocookie.com/embed/${v}?autoplay=0&mute=0&rel=0`;
      const parts = u.pathname.split('/');
      const id = parts.includes('embed') ? parts[parts.indexOf('embed')+1]
               : parts.includes('shorts') ? parts[parts.indexOf('shorts')+1]
               : null;
      if (id) return `https://www.youtube-nocookie.com/embed/${id}?autoplay=0&mute=0&rel=0`;
    }
  }catch(e){}
  return url;
}

function bootShowDetails(){
  const id = new URLSearchParams(location.search).get('id');
  const qs  = new URLSearchParams(location.search);
  const preVenue = qs.get('venue');      // e.g. "pvr1"
  const preDate  = qs.get('date');       // "YYYY-MM-DD"

  const dateStrip = document.getElementById('date-strip');
  const venueList = document.getElementById('venue-list');
  if(!id || !dateStrip || !venueList) return;

  // ---- Header (unchanged) ----
  const MOVIES = {
    1:{title:'Fight Club', genre:'Action', rating:'R', duration:'2h 19m', synopsis:'An underground fight club becomes something far darker.', trailer_url:'https://www.youtube.com/watch?v=SUXWAEX2jlg'},
    2:{title:'The Wolf of Wall Street', genre:'Biography', rating:'R', duration:'2h 59m', synopsis:'Greed, excess, and chaos on Wall Street.', trailer_url:'https://www.youtube.com/watch?v=iszwuX1AK6A'},
    3:{title:'Interstellar', genre:'Sci-Fi', rating:'PG13', duration:'2h 49m', synopsis:'A journey through space to save humanity.', trailer_url:'https://www.youtube.com/watch?v=zSWdZVtXT7E'},
    4:{title:'Spider-Man', genre:'Action', rating:'PG13', duration:'2h 10m', synopsis:'An ordinary teen discovers extraordinary power.', trailer_url:'https://www.youtube.com/watch?v=t06RUxPbp_c'},
    5:{title:'Hacksaw Ridge', genre:'War', rating:'R', duration:'2h 19m', synopsis:'A medic’s courage turns the tide on the bloodiest battlefield.', trailer_url:'https://www.youtube.com/watch?v=s2-1hz1juBI'}
  };
  const show = MOVIES[id] || {title:'Show', genre:'—', rating:'—', duration:'—', synopsis:'', trailer_url:''};
  qsel('#show-title').textContent = show.title;
  qsel('#show-meta').textContent  = `${show.genre} • ${show.duration} • Rated ${show.rating}`;
  qsel('#show-synopsis').textContent = show.synopsis;
  const iframe = qsel('#show-trailer'); const embed = toEmbed(show.trailer_url);
  if(embed){ iframe.src = embed; } else { qsel('.video-wrap')?.style && (qsel('.video-wrap').style.display='none'); }

  // ---- Helpers ----
  const fmtLocalDate = (d)=> {
    const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), day=String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${day}`;
  };
  const to12h = (d)=>{
    let h=d.getHours(), m=String(d.getMinutes()).padStart(2,'0');
    const ampm = h>=12 ? 'PM':'AM';
    h = h%12; if(h===0) h=12;
    return `${String(h).padStart(2,'0')}:${m} ${ampm}`;
  };

  // Build date pills for 5 days (local midnight to avoid TZ issues)
  const days = [...Array(5)].map((_,i)=>{
    const d = new Date(); d.setHours(0,0,0,0); d.setDate(d.getDate()+i);
    return { key: fmtLocalDate(d), dow: d.toLocaleDateString(undefined,{weekday:'short'}), day:d.getDate(), mon:d.toLocaleDateString(undefined,{month:'short'}) };
  });
  dateStrip.innerHTML = days.map((d,i)=>`
    <button class="date-pill ${i===0?'active':''}" data-date="${d.key}">
      <small>${d.dow} • ${d.mon}</small><strong>${d.day}</strong>
    </button>`).join('');

  let selectedDate = days[0].key;
  if (preDate && days.some(d=>d.key===preDate)) {
    selectedDate = preDate;
    requestAnimationFrame(()=>{ [...dateStrip.children].forEach((b,idx)=> b.classList.toggle('active', days[idx].key===preDate)); });
  }

  // ---- Load schedule from backend (now returns venue_id + start_at) ----
  let groupedByDate = {};
  fetch(`api/get_schedule.php?show_id=${id}&start_days=0&end_days=4`)
    .then(r => r.json())
    .then(rows => {
      // rows: [{ show_id, venue_id, venue, start_at }]
      const byDate = {};
      for (const r of rows) {
        // Treat as LOCAL by swapping space to 'T' (no 'Z'), avoids previous off-by-one issues
        const d = new Date(r.start_at.replace(' ','T'));
        const dateKey = fmtLocalDate(d);
        const vid = r.venue_id;
        const label = to12h(d);

        byDate[dateKey] ||= {};
        byDate[dateKey][vid] ||= { id: vid, name: r.venue, times: [] };
        byDate[dateKey][vid].times.push({ label, at: r.start_at });
      }
      // normalize to arrays + sort
      groupedByDate = {};
      for (const [dateKey, venuesObj] of Object.entries(byDate)) {
        groupedByDate[dateKey] = Object.values(venuesObj).map(v => ({
          ...v,
          times: v.times.sort((a,b)=> a.at.localeCompare(b.at))
        }));
      }
      renderDay(selectedDate);
    })
    .catch(err => {
      console.error('schedule load failed', err);
      groupedByDate = {};
      renderDay(selectedDate);
    });

  // ---- Render a day ----
  function renderDay(key){
    let rows = groupedByDate[key] || [];
    if (preVenue) rows = rows.filter(v => v.id === preVenue);

    if (!rows.length){
      venueList.innerHTML = `<div class="meta">No showtimes for ${key}.</div>`;
      return;
    }
    venueList.innerHTML = rows.map(v=>`
      <article class="venue-card" data-venue="${v.id}">
        <div class="venue-header">
          <div>
            <div class="venue-name">${v.name}</div>
            <div class="venue-meta">Allows cancellation</div>
          </div>
          <button class="btn ghost btn-sm" aria-label="Favourite">♡</button>
        </div>

        <div class="times">
          ${v.times.map(t=>`<button class="time-btn" data-time="${t.label}" data-start="${t.at}" data-venue="${v.id}">${t.label}</button>`).join('')}
        </div>

        <div class="booking-drawer" id="drawer-${v.id}">
          <div class="booking-grid">
            <select id="class-${v.id}">
              <option value="Standard|12.00">Standard — $12.00</option>
              <option value="Premium|15.50">Premium — $15.50</option>
              <option value="VIP|18.00">VIP — $18.00</option>
            </select>
            <select id="qty-${v.id}">${[1,2,3,4,5,6].map(n=>`<option>${n}</option>`).join('')}</select>
            <button class="btn primary" id="add-${v.id}">Add</button>
          </div>
          <div class="meta" id="sum-${v.id}" style="margin-top:8px"></div>
        </div>
      </article>
    `).join('');
  }

  // date click
  dateStrip.addEventListener('click', (e)=>{
    const btn = e.target.closest('.date-pill'); if(!btn) return;
    selectedDate = btn.dataset.date;
    [...dateStrip.children].forEach(b=>b.classList.toggle('active', b===btn));
    renderDay(selectedDate);
  });

  // open drawer + compute line total
  venueList.addEventListener('click', async (e)=>{
    const tbtn = e.target.closest('.time-btn');
    if(tbtn){
      const venueId = tbtn.dataset.venue;
      const drawer = document.getElementById(`drawer-${venueId}`);
      drawer.dataset.time  = tbtn.dataset.time;   // "hh:mm AM/PM"
      drawer.dataset.start = tbtn.dataset.start;  // "YYYY-MM-DD HH:MM:SS" from DB
      drawer.classList.add('open');

      const cls = document.getElementById(`class-${venueId}`);
      const qty = document.getElementById(`qty-${venueId}`);
      const sum = document.getElementById(`sum-${venueId}`);
      const upd = ()=>{
        const [label, price] = cls.value.split('|');
        const q = parseInt(qty.value,10);
        sum.textContent = `${label} × ${q} at ${drawer.dataset.time} — Total $${(q*parseFloat(price)).toFixed(2)}`;
      };
      cls.onchange = upd; qty.onchange = upd; upd();
      return;
    }

    // add to preferences (DB + keep LS sync if you want)
    const addBtn = e.target.id?.startsWith('add-') ? e.target : null;
    if(addBtn){
      const venueId = addBtn.id.replace('add-','');
      const drawer = document.getElementById(`drawer-${venueId}`);
      const [label, priceStr] = document.getElementById(`class-${venueId}`).value.split('|');
      const qty = parseInt(document.getElementById(`qty-${venueId}`).value,10);
      const startAt = drawer.dataset.start; // exact DB datetime
      const card = addBtn.closest('.venue-card');
      const venueName = card.querySelector('.venue-name')?.textContent?.trim() || venueId;

      const payload = {
        show_id: parseInt(id, 10),
        venue_id: venueId,
        venue_name: venueName,
        start_at: startAt,          // <- use exact value from API
        ticket_class: label,
        qty: qty,
        price: parseFloat(priceStr)
      };

      try{
        const r = await fetch('api/add_preference.php', {
          method:'POST',
          headers:{ 'Content-Type':'application/json' },
          body: JSON.stringify(payload)
        });
        const json = await r.json();
        if(!r.ok || json.error){
          alert(json.error || 'Failed to save.');
          return;
        }
        // optional local mirror
        if(window.PREFS?.add){
          PREFS.add({
            id: payload.show_id,
            title: qsel('#show-title')?.textContent || `Show #${payload.show_id}`,
            venue: payload.venue_name,
            start_at: payload.start_at,
            tickets: payload.qty,
            price: payload.price
          });
        }
        qsel(`#drawer-${venueId}`).classList.remove('open');
        alert('Added to preferences!');
      }catch(err){
        console.error(err);
        alert('Network error saving preference');
      }
    }
  });
}

// ===== Home: date dropdown (Today + 4) =====
function populateQuickSearchDate() {
  const sel = document.getElementById('qs-date');
  if (!sel) return;

  function fmtLocalDate(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  sel.innerHTML = '';
  for (let i = 0; i < 5; i++) {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + i);
    const opt = document.createElement('option');
    opt.value = fmtLocalDate(d);
    const label = d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    opt.textContent = (i === 0 ? 'Today — ' : '') + label;
    sel.appendChild(opt);
  }
}
document.addEventListener('DOMContentLoaded', populateQuickSearchDate);

document.addEventListener('DOMContentLoaded', () => {
  initReviewAvailabilityGuard();
});

// Highlight the current page link in the navbar
document.addEventListener('DOMContentLoaded', () => {
  const current = location.pathname.split('/').pop(); // e.g. "shows.html"
  document.querySelectorAll('.nav .links a').forEach(a => {
    const href = a.getAttribute('href');
    if (href && href.endsWith(current)) {
      a.classList.add('active');
    } else {
      a.classList.remove('active');
    }
  });
});


// =======================
// Account page bootstrap
// =======================
window.bootAccount = async function bootAccount(){
  const pane = document.getElementById('account-pane');
  if (!pane) { console.error('[bootAccount] #account-pane not found'); return; }

  // tiny helpers
  const $ = sel => document.querySelector(sel);
  const money = n => '$' + Number(n||0).toFixed(2);
  const dt = s => {
    try { return new Date((s||'').replace?.(' ','T') || s).toLocaleString(); }
    catch { return s || ''; }
  };

  // 1) Who am I?
  let me = { authenticated:false };
  try{
    const r = await fetch('api/me.php', { headers:{'Accept':'application/json'} });
    me = r.ok ? await r.json() : { authenticated:false };
  }catch(e){
    console.warn('[bootAccount] me.php failed', e);
  }

  // 2) If not logged in → show CTA
  if (!me.authenticated){
    pane.innerHTML = `
      <div class="card">
        <div class="card-body" style="text-align:center">
          <h2>Your Account</h2>
          <p class="meta">Log in to view your details and order history.</p>
          <div class="flex" style="justify-content:center;gap:12px;margin-top:12px">
            <a href="login.html" class="btn primary">Log In</a>
            <a href="register.html" class="btn ghost">Create Account</a>
          </div>
        </div>
      </div>
    `;
    // If you wired this helper elsewhere, this call is harmless; otherwise it no-ops.
    if (typeof attachLiveValidationForRegisterForm === 'function') {
      attachLiveValidationForRegisterForm();
    }
    return;
  }

  // 3) Logged in → scaffold profile + history shell
  pane.innerHTML = `
    <div class="grid" style="grid-template-columns: 1fr 1.5fr; gap:16px">
      <div class="card"><div class="card-body">
        <h2>Profile</h2>
        <div class="meta" style="margin-top:8px">Name</div>
        <div>${me.name || '-'}</div>
        <div class="meta" style="margin-top:8px">Email</div>
        <div>${me.email || '-'}</div>
        <div class="meta" style="margin-top:8px">Phone</div>
        <div>${me.phone || '-'}</div>
        <div class="flex" style="gap:10px;margin-top:14px">
          <a class="btn ghost" href="index.html">Home</a>
          <a class="btn danger" href="api/logout.php">Log out</a>
        </div>
      </div></div>

      <div class="card"><div class="card-body">
        <h2>Order History</h2>
        <div id="orders-placeholder" class="meta" style="margin-top:8px">Loading…</div>
        <table class="table" style="margin-top:10px; display:none">
          <thead>
            <tr>
              <th>Ref</th>
              <th>Date</th>
              <th>Total</th>
              <th>Items</th>
              <th class="right">Actions</th>
            </tr>
          </thead>
          <tbody id="order-body"></tbody>
        </table>
      </div></div>
    </div>
  `;

  // 4) Fetch order history
  let orders = [];
  try{
    const r = await fetch('api/order_history.php', { headers:{'Accept':'application/json'} });
    if (r.ok) {
      const j = await r.json();
      orders = Array.isArray(j.orders) ? j.orders : [];
    } else {
      console.warn('[bootAccount] order_history.php HTTP', r.status);
    }
  }catch(e){
    console.warn('[bootAccount] order_history failed', e);
  }

  const placeholder = $('#orders-placeholder');
  const table = pane.querySelector('table');
  const body = $('#order-body');

  if (!orders.length){
    placeholder.textContent = 'No orders yet.';
    table.style.display = 'none';
    return;
  }

  placeholder.style.display = 'none';
  table.style.display = '';

  body.innerHTML = orders.map(o=>{
    const ref = o.booking_ref || ('#' + (o.booking_id ?? '—'));
    const when = dt(o.created_at || o.booked_at);
    const total = money(o.total);
    const items = Array.isArray(o.items) ? o.items : [];

    const itemsHtml = items.length
      ? items.map(x => `
          <div class="meta">
            ${x.tickets ?? x.qty ?? 0} ticket(s)
            ${typeof x.price_each !== 'undefined' || typeof x.unit_price !== 'undefined'
              ? ` × $${Number((x.price_each ?? x.unit_price) || 0).toFixed(2)}`
              : '' }
            ${x.ticket_class ? ` • ${x.ticket_class}` : ''}
            ${x.venue_name ? ` • ${x.venue_name}` : ''}
            ${x.start_at ? ` • ${dt(x.start_at)}` : ''}
          </div>
        `).join('')
      : '<span class="meta">No line items</span>';

    const receiptHref = o.booking_ref
      ? `receipt.php?ref=${encodeURIComponent(o.booking_ref)}`
      : (o.booking_id ? `receipt.php?id=${encodeURIComponent(o.booking_id)}` : null);

    return `
      <tr>
        <td>${ref}</td>
        <td>${when}</td>
        <td>${total}</td>
        <td>${itemsHtml}</td>
        <td class="right">
          ${receiptHref ? `<a class="btn btn-sm ghost" href="${receiptHref}" target="_blank" rel="noopener">Receipt</a>` : ''}
        </td>
      </tr>
    `;
  }).join('');
};