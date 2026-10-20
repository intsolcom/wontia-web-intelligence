<?php
/**
 * Wontia Medical — Demo en vivo (tipo Uber/inDriver) para inversionistas.
 * Mapa real (Leaflet + OpenStreetMap), geolocalización del dispositivo,
 * profesionales simulados alrededor de Barranquilla, filtro por modalidad,
 * búsqueda, lista por cercanía, ficha y "Agendar atención".
 * Todo es DEMO (datos simulados), no toca lógica real.
 */
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
.wm-demo{position:relative;width:100vw;margin-left:calc(50% - 50vw);background:#f4f8f7}
.wm-demo *{box-sizing:border-box}
.wm-demo__wrap{display:grid;grid-template-columns:380px 1fr;height:calc(100vh - 70px);min-height:560px}
.wm-demo__panel{display:flex;flex-direction:column;background:#fff;border-right:1px solid #e6eeea;min-height:0}
.wm-demo__head{padding:16px 16px 10px}
.wm-demo__title{font:800 18px/1.1 Inter,system-ui,sans-serif;color:#101d3a;margin:0 0 2px}
.wm-demo__sub{font-size:12px;color:#64748b;margin:0}
.wm-demo__loc{display:flex;align-items:center;gap:6px;margin:10px 0 0;font-size:12px;color:#0d7a58;font-weight:600}
.wm-demo__search{display:flex;gap:8px;margin:12px 0 0}
.wm-demo__search input{flex:1;padding:11px 12px;border:1px solid #dbe7e1;border-radius:12px;font:inherit;font-size:14px}
.wm-demo__mods{display:flex;gap:8px;padding:12px 16px 6px;flex-wrap:wrap}
.wm-mod{padding:8px 12px;border:1px solid #dbe7e1;background:#fff;border-radius:999px;font-size:12.5px;font-weight:700;color:#334155;cursor:pointer}
.wm-mod.is-on{background:#0d9f72;border-color:#0d9f72;color:#fff}
.wm-demo__count{padding:2px 16px 8px;font-size:11.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em}
.wm-demo__list{flex:1;overflow:auto;padding:0 12px 16px;min-height:0}
.wm-pro{display:flex;gap:10px;padding:10px;border:1px solid #eef3f0;border-radius:14px;margin-bottom:8px;cursor:pointer;transition:.15s;background:#fff}
.wm-pro:hover{border-color:#bfe6d6;box-shadow:0 8px 20px rgba(16,24,40,.08)}
.wm-pro__img{width:54px;height:54px;border-radius:12px;object-fit:cover;flex:none;background:#e5f5ef}
.wm-pro__main{min-width:0;flex:1}
.wm-pro__name{font-weight:800;font-size:14px;color:#101d3a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wm-pro__spec{font-size:12px;color:#64748b}
.wm-pro__meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;font-size:11px;color:#64748b}
.wm-pro__star{color:#b7791f;font-weight:700}
.wm-pro__mod{background:#eef4fb;color:#155eeb;border-radius:999px;padding:1px 7px;font-weight:700}
.wm-pro__mod.online{background:#efeaff;color:#6d4bf5}
.wm-pro__dist{margin-left:auto;color:#0d7a58;font-weight:700}
.wm-demo__map{position:relative;min-height:0}
#wm-demo-map{position:absolute;inset:0}
.wm-legend{position:absolute;left:12px;bottom:12px;z-index:500;background:rgba(255,255,255,.94);border-radius:12px;padding:8px 10px;font-size:11.5px;color:#334155;box-shadow:0 8px 20px rgba(16,24,40,.12)}
.wm-legend b{display:block;margin-bottom:4px}
.wm-legend i{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px}
/* ficha / modal */
.wm-sheet{position:fixed;inset:0;z-index:2000;display:none;align-items:flex-end;justify-content:center;background:rgba(9,15,25,.45)}
.wm-sheet.is-open{display:flex}
.wm-sheet__card{width:min(460px,94vw);background:#fff;border-radius:20px 20px 0 0;padding:18px;box-shadow:0 -10px 40px rgba(0,0,0,.25);animation:wmUp .25s ease}
@keyframes wmUp{from{transform:translateY(30px);opacity:.6}to{transform:none;opacity:1}}
.wm-sheet__top{display:flex;gap:12px;align-items:center}
.wm-sheet__img{width:74px;height:74px;border-radius:16px;object-fit:cover;background:#e5f5ef}
.wm-sheet__name{font:800 18px/1.15 Inter;color:#101d3a}
.wm-sheet__spec{font-size:13px;color:#64748b}
.wm-sheet__rows{display:grid;gap:6px;margin:14px 0}
.wm-sheet__row{display:flex;justify-content:space-between;font-size:13px;color:#334155;border-bottom:1px dashed #eef3f0;padding:6px 0}
.wm-sheet__row b{color:#101d3a}
.wm-sheet__cta{display:flex;gap:8px;margin-top:6px}
.wm-btn-demo{flex:1;padding:12px;border-radius:12px;border:0;background:#0d9f72;color:#fff;font-weight:800;font-size:14px;cursor:pointer}
.wm-btn-demo.ghost{background:#fff;border:1px solid #dbe7e1;color:#334155}
.wm-toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%) translateY(20px);background:#101d3a;color:#fff;padding:12px 18px;border-radius:12px;font-size:13px;font-weight:600;opacity:0;pointer-events:none;transition:.25s;z-index:3000}
.wm-toast.show{opacity:1;transform:translateX(-50%)}
.wm-badge-demo{display:inline-block;margin-left:6px;background:#fff3cd;color:#8a6d00;border-radius:6px;padding:1px 6px;font-size:10px;font-weight:800;vertical-align:middle}
@media(max-width:860px){.wm-demo__wrap{grid-template-columns:1fr;grid-template-rows:auto 46vh}.wm-demo__panel{order:2}.wm-demo__map{order:1;height:46vh}}
</style>

<section class="wm-demo" aria-label="Demo en vivo Wontia Medical">
  <div class="wm-demo__wrap">
    <div class="wm-demo__panel">
      <div class="wm-demo__head">
        <h2 class="wm-demo__title">Encuentra tu profesional <span class="wm-badge-demo">DEMO</span></h2>
        <p class="wm-demo__sub">Profesionales de la salud cerca de ti, en tiempo real.</p>
        <div class="wm-demo__loc" id="wm-loc">📍 Detectando tu ubicación…</div>
        <div class="wm-demo__search">
          <input id="wm-q" type="search" placeholder="¿Qué profesional necesitas? (médico, psicólogo, nutricionista…)" autocomplete="off" />
        </div>
      </div>
      <div class="wm-demo__mods" id="wm-mods" role="group" aria-label="Modalidad">
        <button class="wm-mod is-on" data-mod="all">Todas</button>
        <button class="wm-mod" data-mod="online">En línea · videollamada</button>
        <button class="wm-mod" data-mod="inperson">Presencial</button>
      </div>
      <div class="wm-demo__count" id="wm-count"></div>
      <div class="wm-demo__list" id="wm-list"></div>
    </div>
    <div class="wm-demo__map">
      <div id="wm-demo-map"></div>
      <div class="wm-legend"><b>Cercanos a ti</b><span><i style="background:#0d9f72"></i>Presencial</span> &nbsp; <span><i style="background:#7c5cff"></i>En línea</span> &nbsp; <span><i style="background:#f59e0b"></i>Ambas</span></div>
    </div>
  </div>

  <div class="wm-sheet" id="wm-sheet" role="dialog" aria-modal="true" aria-labelledby="wm-sheet-name">
    <div class="wm-sheet__card">
      <div class="wm-sheet__top">
        <img class="wm-sheet__img" id="wm-sheet-img" alt="" />
        <div>
          <div class="wm-sheet__name" id="wm-sheet-name"></div>
          <div class="wm-sheet__spec" id="wm-sheet-spec"></div>
          <div class="wm-sheet__spec" id="wm-sheet-rate"></div>
        </div>
      </div>
      <div class="wm-sheet__rows" id="wm-sheet-rows"></div>
      <div class="wm-sheet__cta">
        <button class="wm-btn-demo ghost" id="wm-sheet-close">Cerrar</button>
        <button class="wm-btn-demo" id="wm-sheet-book">Agendar atención</button>
      </div>
    </div>
  </div>
  <div class="wm-toast" id="wm-toast"></div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function(){
  var BQ = [10.9878, -74.7889];
  var SPECS = [
    ['Medicina General','steth'],['Psicología','brain'],['Nutrición','apple'],['Veterinaria','heart'],
    ['Pediatría','user'],['Dermatología','heart'],['Fisioterapia','brain'],['Odontología','steth'],
    ['Ginecología','user'],['Cardiología','steth']
  ];
  var NAMES = ['Dra. María González','Dr. Carlos Pérez','Dra. Laura Ruiz','Dr. Andrés Gómez','Dra. Valentina Díaz','Dr. Sebastián Ortiz','Dra. Camila Torres','Dr. Felipe Ramírez','Dra. Daniela Castro','Dr. Juan Herrera','Dra. Sofía Moreno','Dr. Diego Vargas','Dra. Paula Rojas','Dr. Miguel Jiménez','Dra. Ana Mendoza','Dr. Jorge Silva','Dra. Natalia Ramos','Dr. David Luna','Dra. Isabella Pineda','Dr. Ricardo Salas','Dra. Mariana Peña','Dr. Óscar Duarte','Dra. Gabriela Núñez','Dr. Antonio Reyes'];
  var MODS = ['inperson','online','both'];
  var PROS = [];
  var seed = 7;
  function rnd(){ seed = (seed*9301+49297)%233280; return seed/233280; }
  for (var i=0;i<NAMES.length;i++){
    var sp = SPECS[i % SPECS.length];
    var mod = MODS[i % 3];
    var dlat = (rnd()-0.5)*0.055, dlng = (rnd()-0.5)*0.06;
    PROS.push({
      id:i+1, name:NAMES[i], spec:sp[0], icon:sp[1], mod:mod,
      rating:(4.5+rnd()*0.5).toFixed(1), reviews:20+Math.floor(rnd()*180),
      avail: rnd()>0.25, lat:BQ[0]+dlat, lng:BQ[1]+dlng,
      img:'https://i.pravatar.cc/200?img='+(i+5)
    });
  }

  var map = L.map('wm-demo-map', {zoomControl:true}).setView(BQ, 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom:19, attribution:'© OpenStreetMap'}).addTo(map);

  var me = {lat:BQ[0], lng:BQ[1], city:'Barranquilla'};
  var meMarker = L.marker([me.lat, me.lng], {icon: L.divIcon({className:'', html:'<div style="width:20px;height:20px;border-radius:50%;background:#0d9f72;border:3px solid #fff;box-shadow:0 0 0 4px rgba(13,159,114,.35)"></div>', iconSize:[20,20], iconAnchor:[10,10]})}).addTo(map).bindPopup('<b>Tú estás aquí</b>');

  var markers = [];
  var state = {mod:'all', q:''};

  var COLORS = {inperson:'#0d9f72', online:'#7c5cff', both:'#f59e0b'};
  function pin(mod){ return L.divIcon({className:'', html:'<div style="width:16px;height:16px;border-radius:50%;background:'+COLORS[mod]+';border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.35)"></div>', iconSize:[16,16], iconAnchor:[8,8]}); }

  function distKm(a,b){
    var R=6371, dLat=(b.lat-a.lat)*Math.PI/180, dLng=(b.lng-a.lng)*Math.PI/180;
    var la1=a.lat*Math.PI/180, la2=b.lat*Math.PI/180;
    var h=Math.sin(dLat/2)**2+Math.cos(la1)*Math.cos(la2)*Math.sin(dLng/2)**2;
    return R*2*Math.atan2(Math.sqrt(h),Math.sqrt(1-h));
  }

  function matches(p){
    if (state.mod==='online' && !(p.mod==='online'||p.mod==='both')) return false;
    if (state.mod==='inperson' && !(p.mod==='inperson'||p.mod==='both')) return false;
    if (state.q){ var q=state.q.toLowerCase(); if ((p.name+' '+p.spec).toLowerCase().indexOf(q)<0) return false; }
    return true;
  }

  function render(){
    markers.forEach(function(m){ map.removeLayer(m); }); markers=[];
    var list = PROS.filter(matches).map(function(p){ p._d = distKm(me,p); return p; }).sort(function(a,b){ return a._d-b._d; });
    document.getElementById('wm-count').textContent = list.length + ' profesionales disponibles';
    if (!list.length){ list = PROS.slice().map(function(p){p._d=distKm(me,p);return p;}).sort(function(a,b){return a._d-b._d;}); }
    list.forEach(function(p){
      var mk = L.marker([p.lat,p.lng], {icon:pin(p.mod)}).addTo(map);
      mk.bindPopup('<b>'+p.name+'</b><br>'+p.spec+' · '+p._d.toFixed(1)+' km');
      mk.on('click', function(){ openSheet(p); });
      markers.push(mk);
    });
    var html='';
    list.forEach(function(p){
      var modLbl = p.mod==='online'?'En línea':(p.mod==='inperson'?'Presencial':'Ambas');
      html += '<div class="wm-pro" data-id="'+p.id+'">'
        + '<img class="wm-pro__img" src="'+p.img+'" alt="" loading="lazy">'
        + '<div class="wm-pro__main"><div class="wm-pro__name">'+p.name+'</div>'
        + '<div class="wm-pro__spec">'+p.spec+(p.avail?' · <span style="color:#0d9f72;font-weight:700">Disponible</span>':' · <span style="color:#94a3b8">Ocupado</span>')+'</div>'
        + '<div class="wm-pro__meta"><span class="wm-pro__star">★ '+p.rating+'</span><span>('+p.reviews+')</span><span class="wm-pro__mod '+p.mod+'">'+modLbl+'</span><span class="wm-pro__dist">'+p._d.toFixed(1)+' km</span></div>'
        + '</div></div>';
    });
    document.getElementById('wm-list').innerHTML = html;
    Array.prototype.forEach.call(document.querySelectorAll('.wm-pro'), function(el){
      el.addEventListener('click', function(){ var id=+el.getAttribute('data-id'); var p=PROS.filter(function(x){return x.id===id;})[0]; if(p) openSheet(p); });
    });
    var fit = list.slice(0,6).map(function(p){ return [p.lat,p.lng]; }); fit.push([me.lat,me.lng]);
    if (fit.length>1) map.fitBounds(fit, {padding:[40,40], maxZoom:14});
  }

  var cur = null;
  function openSheet(p){
    cur = p;
    document.getElementById('wm-sheet-img').src = p.img;
    document.getElementById('wm-sheet-name').textContent = p.name;
    document.getElementById('wm-sheet-spec').textContent = p.spec;
    document.getElementById('wm-sheet-rate').innerHTML = '<span style="color:#b7791f;font-weight:800">★ '+p.rating+'</span> ('+p.reviews+' reseñas) · '+(p.avail?'<span style="color:#0d9f72;font-weight:700">Disponible hoy</span>':'Ocupado');
    document.getElementById('wm-sheet-rows').innerHTML =
      '<div class="wm-sheet__row"><span>Modalidad</span><b>'+(p.mod==='online'?'En línea (videollamada)':(p.mod==='inperson'?'Presencial':'En línea y presencial'))+'</b></div>'
      + '<div class="wm-sheet__row"><span>Distancia</span><b>'+distKm(me,p).toFixed(1)+' km</b></div>'
      + '<div class="wm-sheet__row"><span>Ciudad</span><b>Barranquilla</b></div>'
      + '<div class="wm-sheet__row"><span>Próxima disponibilidad</span><b>'+(p.avail?'Hoy · 2:30 p.m.':'Mañana · 9:00 a.m.')+'</b></div>';
    document.getElementById('wm-sheet').classList.add('is-open');
  }
  function closeSheet(){ document.getElementById('wm-sheet').classList.remove('is-open'); }
  document.getElementById('wm-sheet-close').addEventListener('click', closeSheet);
  document.getElementById('wm-sheet').addEventListener('click', function(e){ if(e.target===this) closeSheet(); });
  document.getElementById('wm-sheet-book').addEventListener('click', function(){
    var p=cur; closeSheet();
    toast('✅ Solicitud de atención con '+(p?p.name:'el profesional')+' enviada (DEMO). Te contactarán para confirmar.');
  });

  var toastT=null;
  function toast(msg){ var t=document.getElementById('wm-toast'); t.textContent=msg; t.classList.add('show'); clearTimeout(toastT); toastT=setTimeout(function(){t.classList.remove('show');},3800); }

  document.getElementById('wm-q').addEventListener('input', function(e){ state.q=e.target.value.trim(); render(); });
  Array.prototype.forEach.call(document.querySelectorAll('.wm-mod'), function(b){
    b.addEventListener('click', function(){
      Array.prototype.forEach.call(document.querySelectorAll('.wm-mod'), function(x){ x.classList.remove('is-on'); });
      b.classList.add('is-on'); state.mod=b.getAttribute('data-mod'); render();
    });
  });

  function setLoc(lat,lng,city,demo){
    me.lat=lat; me.lng=lng; me.city=city;
    map.removeLayer(meMarker);
    meMarker = L.marker([lat,lng], {icon: L.divIcon({className:'', html:'<div style="width:20px;height:20px;border-radius:50%;background:#0d9f72;border:3px solid #fff;box-shadow:0 0 0 4px rgba(13,159,114,.35)"></div>', iconSize:[20,20], iconAnchor:[10,10]})}).addTo(map).bindPopup('<b>Tú estás aquí</b>');
    document.getElementById('wm-loc').textContent = '📍 '+(demo?'Demo centrada en Barranquilla':'Tu ubicación: '+city);
    render();
  }

  render();

  if (navigator.geolocation){
    navigator.geolocation.getCurrentPosition(function(pos){
      var lat=pos.coords.latitude, lng=pos.coords.longitude;
      var nearBQ = distKm({lat:lat,lng:lng},{lat:BQ[0],lng:BQ[1]}) < 40;
      fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat='+lat+'&lon='+lng+'&zoom=10&accept-language=es', {headers:{'Accept':'application/json'}})
        .then(function(r){return r.json();}).catch(function(){return null;}).then(function(j){
          var city = (j && (j.address.city||j.address.town||j.address.state||j.address.county)) || 'tu ciudad';
          if (nearBQ) setLoc(lat,lng,city,false);
          else setLoc(BQ[0],BQ[1],city,true);
        });
    }, function(){ setLoc(BQ[0],BQ[1],'Barranquilla',true); }, {timeout:8000, maximumAge:60000});
  } else {
    setLoc(BQ[0],BQ[1],'Barranquilla',true);
  }
})();
</script>
