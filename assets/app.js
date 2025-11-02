
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
    // re-rank
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
    // re-number ranks
    items.forEach((x,i)=>x.rank_pos=i+1);
    PREFS.set(items);
    renderPreferences && renderPreferences(); // if on that page
  },
  clear(){ localStorage.removeItem(LS_KEY); }
};

function qsel(q){ return document.querySelector(q); }
function qall(q){ return [...document.querySelectorAll(q)]; }
function fmtDateTime(iso){
  try{ return new Date(iso).toLocaleString(); } catch{ return iso; }
}

// ---------- Page-specific bootstraps ----------

// shows.html – populate static gallery
function bootShows(){
  const shows = [
    {id:1,title:"Fight Club",genre:"Action",rating:"R",poster:"assets/posters/fightclub.jpeg"},
    {id:2,title:"The Wolf of Wall Street",genre:"Biography",rating:"R",poster:"assets/posters/wolf.jpeg"},
    {id:3,title:"Interstellar",genre:"Sci‑Fi",rating:"PG13",poster:"assets/posters/interstellar.jpeg"},
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


// preferences.html – ranked list
// preferences.html – load from DB first, fall back to localStorage
async function renderPreferences(){
  const tbody = document.querySelector("#prefs-body");
  if (!tbody) return;

  let rows = [];
  try{
    const r = await fetch('api/list_preferences.php', {headers:{'Accept':'application/json'}});
    if(r.ok) rows = await r.json();
  }catch(e){ /* ignore */ }

  // Fallback to localStorage only if server is empty (keep your old behavior)
  if(!rows.length){
    const items = PREFS.get();
    if(!items.length){
      tbody.innerHTML = `<tr><td colspan="6" class="meta">No preferences yet. Go to <a href="shows.html">Browse Shows</a>.</td></tr>`;
      return;
    }
    // local format
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
    return;
  }

  // Server rows
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

function fmtDateTimeLocal(isoLocal){
  // isoLocal like "2025-11-05T19:00:00" (no Z) -> treat as LOCAL, not UTC
  if(!isoLocal) return '';
  const d = new Date(isoLocal); // parsed as local time because no Z
  return d.toLocaleString();
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
// available.html – simulate availability & build form for final selection
function bootAvailable(){
  const list = PREFS.get();
  const tbody = qsel("#avail-body");
  if (!tbody) return;
  if (!list.length){
    tbody.innerHTML = `<tr><td colspan="6" class="meta">No items in preferences.</td></tr>`;
    return;
  }
  // Simple deterministic availability: even minutes = available
  const rows = list.map(x=>{
    const dt = new Date(x.start_at);
    const available = (dt.getMinutes() % 2 === 0);
    return {...x, available}
  });
  tbody.innerHTML = rows.map(x=>`
    <tr>
      <td><span class="badge">#${x.rank_pos}</span></td>
      <td>${x.title}<div class="meta">${x.venue}</div></td>
      <td>${fmtDateTime(x.start_at)}</td>
      <td>${x.tickets || 2}</td>
      <td>${x.available ? '<span class="badge" style="border-color:#204f36;color:#7ae2a0;background:#0a2217">Available</span>' :
                           '<span class="badge" style="border-color:#44202d;color:#f7a5b2;background:#220b12">Not Available</span>'}</td>
      <td class="right">
        ${x.available ? `<input type="checkbox" name="select_item_ids" value="${x.id}|${x.start_at}">` : ''}
      </td>
    </tr>
  `).join("");
  // client-side form validation
  const form = qsel("#final-form");
  form?.addEventListener("submit", (e)=>{
    const any = qall('input[name="select_item_ids"]:checked').length>0;
    if (!any){ e.preventDefault(); alert("Please select at least one available booking."); }
    else{
      // store a mock "last booking" for confirmation page
      const chosen = rows.filter(x=>{
        const key = `${x.id}|${x.start_at}`;
        return !!qsel(`input[value="${key}"]`)?.checked;
      });
      sessionStorage.setItem("tickitnow_last_booking", JSON.stringify({when:new Date().toISOString(), items:chosen}));
    }
  });
}

// confirmation.html – read session data and show summary
function bootConfirm(){
  const data = sessionStorage.getItem("tickitnow_last_booking");
  const target = qsel("#confirm-body");
  if (!target) return;
  if (!data){
    target.innerHTML = `<div class="meta">No booking found. Go to <a href="shows.html">Browse Shows</a>.</div>`;
    return;
  }
  const obj = JSON.parse(data);
  const total = obj.items.reduce((s,x)=> s + (x.tickets||2) * (x.price||0), 0);
  target.innerHTML = `
    <div class="card"><div class="card-body">
      <h2>🎉 Booking Confirmed</h2>
      <p class="meta">Booked at ${fmtDateTime(obj.when)}</p>
      <table class="table" style="margin-top:10px">
        <thead><tr><th>Show</th><th>When</th><th>Venue</th><th>Tickets</th><th>Price</th></tr></thead>
        <tbody>
          ${obj.items.map(x=>`
            <tr>
              <td>${x.title}</td>
              <td>${fmtDateTime(x.start_at)}</td>
              <td>${x.venue}</td>
              <td>${x.tickets||2}</td>
              <td>$${Number(x.price||0).toFixed(2)}</td>
            </tr>
          `).join("")}
        </tbody>
      </table>
      <div class="flex space-between" style="margin-top:10px">
        <strong>Total</strong>
        <strong>$${total.toFixed(2)}</strong>
      </div>
      <div class="flex" style="margin-top:16px">
        <a class="btn primary" href="shows.html">Book More</a>
        <a class="btn ghost" href="index.html">Home</a>
      </div>
    </div></div>
  `;
}

/* ===== Featured Movies Carousel (no libs) ===== */
(function(){
  const data = [
    { id:1, title:"Fight Club", blurb:"An underground fight club becomes something far darker.", img:"assets/posters/fightclub.jpeg" },
    { id:2, title:"The Wolf of Wall Street",   blurb:"Greed, excess, and chaos on Wall Street.",            img:"assets/posters/wolf.jpeg" },
    { id:3, title:"Interstellar",       blurb:"A journey through space to save humanity.",      img:"assets/posters/interstellar.jpeg" },
    { id:4, title:"Spiderman",   blurb:"An ordinary teen discovers extraordinary power.",                img:"assets/posters/spiderman.jpeg" },
    {id:5,title:"Hacksaw Ridge",blurb:"A medic’s courage turns the tide on the bloodiest battlefield." ,img:"assets/posters/hacksaw.jpeg"}
  ];

  const track = document.getElementById('carousel-track');
  if(!track) return; // only on home page

  // Build slides
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

  // Dots
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

  function start(){
    stop();
    timer = setInterval(()=>{ if(!hovering) next(); }, 4500);
  }
  function stop(){ if(timer) clearInterval(timer); }

  // Events
  nextBtn.addEventListener('click', ()=>{ next(); start(); });
  prevBtn.addEventListener('click', ()=>{ prev(); start(); });
  dots.forEach(d=> d.addEventListener('click', e=>{ go(+e.currentTarget.dataset.i); start(); }));

  // Pause on hover
  track.addEventListener('mouseenter', ()=>{ hovering = true; });
  track.addEventListener('mouseleave', ()=>{ hovering = false; });

  // Keyboard
  track.tabIndex = 0;
  track.addEventListener('keydown', e=>{
    if(e.key === 'ArrowRight') { next(); start(); }
    if(e.key === 'ArrowLeft')  { prev(); start(); }
  });

  // Basic swipe (mobile)
  let sx=0, dx=0;
  track.addEventListener('touchstart', e=>{ sx = e.touches[0].clientX; dx = 0; }, {passive:true});
  track.addEventListener('touchmove',  e=>{ dx = e.touches[0].clientX - sx; }, {passive:true});
  track.addEventListener('touchend',   ()=>{
    if(Math.abs(dx) > 50){ dx < 0 ? next() : prev(); start(); }
  });

  // init
  go(0);
  start();
})();

/* ===== Show page ===== */
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

  // ----- MOVIE HEADER (unchanged) -----
  const MOVIES = {
    1:{title:'Fight Club', genre:'Action', rating:'R',   duration:'2h 19m',
       synopsis:'An underground fight club becomes something far darker.',
       trailer_url:'https://www.youtube.com/watch?v=SUXWAEX2jlg'},
    2:{title:'The Wolf of Wall Street', genre:'Biography', rating:'R', duration:'2h 59m',
       synopsis:'Greed, excess, and chaos on Wall Street.',
       trailer_url:'https://www.youtube.com/watch?v=iszwuX1AK6A'},
    3:{title:'Interstellar', genre:'Sci-Fi', rating:'PG13', duration:'2h 49m',
       synopsis:'A journey through space to save humanity.',
       trailer_url:'https://www.youtube.com/watch?v=zSWdZVtXT7E'},
    4:{title:'Spider-Man', genre:'Action', rating:'PG13', duration:'2h 10m',
       synopsis:'An ordinary teen discovers extraordinary power.',
       trailer_url:'https://www.youtube.com/watch?v=t06RUxPbp_c'},
    5:{title:'Hacksaw Ridge', genre:'War', rating:'R', duration:'2h 19m',
       synopsis:'A medic’s courage turns the tide on the bloodiest battlefield.',
       trailer_url:'https://www.youtube.com/watch?v=s2-1hz1juBI'}
  };
  const show = MOVIES[id] || {title:'Show', genre:'—', rating:'—', duration:'—', synopsis:'', trailer_url:''};
  document.getElementById('show-title').textContent = show.title;
  document.getElementById('show-meta').textContent  = `${show.genre} • ${show.duration} • Rated ${show.rating}`;
  document.getElementById('show-synopsis').textContent = show.synopsis;
  const iframe = document.getElementById('show-trailer');
  const embed = toEmbed(show.trailer_url);
  if(embed){ iframe.src = embed; } else { document.querySelector('.video-wrap').style.display='none'; }

  // ----- DATES (5 days) -----
  function fmtLocalDate(d){
    const y = d.getFullYear();
    const m = String(d.getMonth()+1).padStart(2,'0');
    const day = String(d.getDate()).padStart(2,'0');
    return `${y}-${m}-${day}`; // YYYY-MM-DD in LOCAL time
  }
  
  const days = [...Array(5)].map((_,i)=>{
    const d = new Date();
    d.setHours(0,0,0,0);     // normalize to midnight local
    d.setDate(d.getDate()+i);
    return {
      key: fmtLocalDate(d),
      dow: d.toLocaleDateString(undefined,{weekday:'short'}),
      day: d.getDate(),
      mon: d.toLocaleDateString(undefined,{month:'short'})
    };
  });

  // ----- VENUES (Singapore, keep your IDs) -----
  const venues = [
    { id:'inox', name:'Orchard Cineplex A',   distance:'2.5 km', cancel:true },
    { id:'pvr1', name:'Marina Theatre Hall 2',distance:'2.6 km', cancel:true },
    { id:'pvr2', name:'Jewel Cinema 5',       distance:'3.8 km', cancel:true },
    { id:'pvr3', name:'Tampines Stage 1',     distance:'5.8 km', cancel:true },
  ];
  const baseTimes = ['09:55 PM','10:25 PM','10:55 PM','11:25 PM'];

  // build per-day schedule (demo)
  const byDate = {};
  days.forEach((d,i)=>{
    byDate[d.key] = venues.map((v,vi)=>({
      ...v,
      times: baseTimes.map((t,ti)=>{
        const [hm,ampm]=t.split(' '); let [h,m]=hm.split(':').map(Number);
        m=(m+((i+vi+ti)%3)*5)%60; if(m<10)m='0'+m;
        return `${h}:${m} ${ampm}`;
      })
    }));
  });

  // ----- Render date pills -----
  dateStrip.innerHTML = days.map((d,i)=>`
    <button class="date-pill ${i===0?'active':''}" data-date="${d.key}">
      <small>${d.dow} • ${d.mon}</small><strong>${d.day}</strong>
    </button>
  `).join('');

  // initial selected date (URL preselect if given)
  let selectedDate = days[0].key;
  if (preDate && days.some(d=>d.key===preDate)) {
    selectedDate = preDate;
    // visually mark the pill
    requestAnimationFrame(()=>{
      [...dateStrip.children].forEach((b,idx)=> b.classList.toggle('active', days[idx].key===preDate));
    });
  }

  // render venues for date (filter by preVenue if provided)
  function renderDay(key){
    let rows = byDate[key] || [];
    if (preVenue) rows = rows.filter(v => v.id === preVenue);
    venueList.innerHTML = rows.map(v=>`
      <article class="venue-card" data-venue="${v.id}">
        <div class="venue-header">
          <div>
            <div class="venue-name">${v.name}</div>
            <div class="venue-meta">${v.distance} away • ${v.cancel?'Allows cancellation':'No cancellation'}</div>
          </div>
          <button class="btn ghost btn-sm" aria-label="Favourite">♡</button>
        </div>
        <div class="times">
          ${v.times.map(t=>`<button class="time-btn" data-time="${t}" data-venue="${v.id}">${t}</button>`).join('')}
        </div>
        <div class="booking-drawer" id="drawer-${v.id}">
          <div class="booking-grid">
            <select id="class-${v.id}">
              <option value="Standard|12.00">Standard — $12.00</option>
              <option value="Premium|15.50">Premium — $15.50</option>
              <option value="VIP|18.00">VIP — $18.00</option>
            </select>
            <select id="qty-${v.id}">
              ${[1,2,3,4,5,6].map(n=>`<option>${n}</option>`).join('')}
            </select>
            <button class="btn primary" id="add-${v.id}">Add</button>
          </div>
          <div class="meta" id="sum-${v.id}" style="margin-top:8px"></div>
        </div>
      </article>
    `).join('');
  }

  renderDay(selectedDate);

  // change date
  dateStrip.addEventListener('click', e=>{
    const btn = e.target.closest('.date-pill'); if(!btn) return;
    selectedDate = btn.dataset.date;
    [...dateStrip.children].forEach(b=>b.classList.toggle('active', b===btn));
    renderDay(selectedDate);
  });

  // ----- Helpers -----
  // combine YYYY-MM-DD with "hh:mm AM/PM" => "YYYY-MM-DD HH:MM:00"
  function combineDateTime(dateStr, timeLabel){
    const [hm, ampm] = timeLabel.split(' ');
    let [h,m] = hm.split(':').map(Number);
    if (ampm.toUpperCase()==='PM' && h !== 12) h += 12;
    if (ampm.toUpperCase()==='AM' && h === 12) h = 0;
    const hh = String(h).padStart(2,'0');
    const mm = String(m).padStart(2,'0');
    return `${dateStr} ${hh}:${mm}:00`;
  }

  // ----- Interactions: open drawer / add preference -----
  venueList.addEventListener('click', async (e)=>{
    const tbtn = e.target.closest('.time-btn');
    if(tbtn){
      const venueId = tbtn.dataset.venue;
      const drawer = document.getElementById(`drawer-${venueId}`);
      drawer.dataset.time = tbtn.dataset.time;
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

    const addBtn = e.target.id?.startsWith('add-') ? e.target : null;
    if(addBtn){
      const venueId = addBtn.id.replace('add-','');
      const drawer = document.getElementById(`drawer-${venueId}`);
      const [label, priceStr] = document.getElementById(`class-${venueId}`).value.split('|');
      const qty = parseInt(document.getElementById(`qty-${venueId}`).value,10);
      const time = drawer.dataset.time;

      // venue name from the card
      const card = addBtn.closest('.venue-card');
      const venueName = card.querySelector('.venue-name')?.textContent?.trim() || venueId;

      // payload for DB
      const payload = {
        show_id: parseInt(id, 10),
        venue_id: venueId,
        venue_name: venueName,
        start_at: combineDateTime(selectedDate, time),
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

        // Keep localStorage in sync for your existing UI
        if(window.PREFS?.add){
          PREFS.add({
            id: payload.show_id,
            title: document.querySelector('#show-title')?.textContent || `Show #${payload.show_id}`,
            venue: payload.venue_name,
            start_at: payload.start_at,
            tickets: payload.qty,
            price: payload.price
          });
        } else {
          alert('Saved!');
        }

        drawer.classList.remove('open');
      }catch(err){
        console.error(err);
        alert('Network error saving preference');
      }
    }
  });
}

function populateQuickSearchDate() {
  const sel = document.getElementById('qs-date');
  if (!sel) return; // only present on the home page

  // Helper to format a local date as YYYY-MM-DD
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
    opt.value = fmtLocalDate(d); // LOCAL date value
    const label = d.toLocaleDateString(undefined, {
      weekday: 'short',
      month: 'short',
      day: 'numeric'
    });
    opt.textContent = (i === 0 ? 'Today — ' : '') + label;
    sel.appendChild(opt);
  }
}

document.addEventListener('DOMContentLoaded', populateQuickSearchDate);