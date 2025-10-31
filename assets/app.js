
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
    {id:1,title:"Fight Club",genre:"Action",rating:"G",poster:"assets/posters/Fight Club.jpeg"},
    {id:2,title:"The Wolf of Wall Street",genre:"Drama",rating:"PG",poster:"assets/posters/The Wolf of Wall Street.jpeg"},
    {id:3,title:"Interstellar",genre:"Sci‑Fi",rating:"NC16",poster:"assets/posters/Interstellar.jpeg"},
    {id:4,title:"Spiderman",genre:"Action",rating:"PG",poster:"assets/posters/Spiderman.jpeg"},
    {id:5,title:"Hacksaw Ridge",genre:"Action",rating:"PG",poster:"assets/posters/Hacksaw Ridge.jpeg"}
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

// show.html – details + add-to-preferences
function bootShowDetails(){
  const params = new URLSearchParams(location.search);
  const id = Number(params.get("id") || 1);
  const titleMap = {
    1:"Fight Club",2:"The Wolf of Wall Street",3:"Interstellar",4:"Spiderman",5:"Hacksaw Ridge"
  };
  const venues = ["Orchard Cineplex A","Marina Theatre Hall 2","Jewel Cinema 5","Tampines Stage 1"];
  const fakeShowtimes = [0,1,2,3,4].map(i=>{
    const start = new Date(Date.now()+ (i+1)*36e5).toISOString();
    const venue = venues[i%venues.length];
    const price = [12.0, 14.5, 16.0][i%3];
    return {id, title:titleMap[id], start_at:start, venue, price};
  });

  const title = titleMap[id];
  qsel("#show-title").textContent = title;
  qsel("#show-meta").textContent = "Genre • Rating • 2h 10m";
  qsel("#show-schedule").innerHTML = fakeShowtimes.map(st=>`
    <tr>
      <td>${fmtDateTime(st.start_at)}</td>
      <td>${st.venue}</td>
      <td>$${st.price.toFixed(2)}</td>
      <td class="right">
        <form onsubmit="event.preventDefault(); PREFS.add(${JSON.stringify(st)});">
          <label class="kbd">Tickets</label>
          <input required min="1" max="10" type="number" name="t" value="2" style="width:70px;margin:0 8px" 
            oninput="this.closest('form').dataset.tickets=this.value">
          <button class="btn success">Add to Preferences</button>
        </form>
      </td>
    </tr>
  `).join("");
}

// preferences.html – ranked list
function renderPreferences(){
  const tbody = qsel("#prefs-body");
  const items = PREFS.get();
  if (!tbody) return;
  if (!items.length){
    tbody.innerHTML = `<tr><td colspan="6" class="meta">No preferences yet. Go to <a href="shows.html">Browse Shows</a>.</td></tr>`;
    return;
  }
  tbody.innerHTML = items.map(x=>`
    <tr>
      <td><span class="badge">#${x.rank_pos}</span></td>
      <td>${x.title}<div class="meta">${x.venue}</div></td>
      <td>${fmtDateTime(x.start_at)}</td>
      <td>${x.tickets || 2}</td>
      <td>$${Number(x.price||0).toFixed(2)}</td>
      <td class="right">
        <button class="btn" onclick="PREFS.move(${x.id}, '${x.start_at}','up')">↑</button>
        <button class="btn" onclick="PREFS.move(${x.id}, '${x.start_at}','down')">↓</button>
        <button class="btn danger" onclick="PREFS.remove(${x.id}, '${x.start_at}'); renderPreferences()">Remove</button>
      </td>
    </tr>
  `).join("");
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
    { id:1, title:"Fight Club", blurb:"An underground fight club becomes something far darker.", img:"assets/posters/Fight Club.jpeg" },
    { id:2, title:"The Wolf of Wall Street",   blurb:"Greed, excess, and chaos on Wall Street.",            img:"assets/posters/The Wolf of Wall Street 2.jpeg" },
    { id:3, title:"Interstellar",       blurb:"A journey through space to save humanity.",      img:"assets/posters/Interstellar.jpeg" },
    { id:4, title:"Spiderman",   blurb:"An ordinary teen discovers extraordinary power.",                img:"assets/posters/Spiderman.jpeg" }
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