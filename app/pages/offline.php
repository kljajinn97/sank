<?php
/** WAITER — Offline kasa. Stranica se kešira (service worker) i radi BEZ servera:
 *  katalog iz IndexedDB, računi u lokalni red, auto-sync kad se mreža vrati. */
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title>Offline kasa · Waiter</title>
<script>(function(){try{var t=localStorage.getItem('sank_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}})();</script>
<link rel="stylesheet" href="/assets/css/app.css">
<script src="/assets/js/ui.js"></script>
<script src="/assets/js/offline.js"></script>
</head>
<body class="kasa-body">
<header class="kasa-top">
  <div class="kasa-top__brand">
    <img src="/img/w_logo_color.png" alt="Waiter" style="height:34px" class="only-light">
    <img src="/img/w_logo_white.png" alt="Waiter" style="height:34px;display:none" class="only-dark">
    <div><div style="font-weight:800" id="offLokal">Offline kasa</div>
      <div style="font-size:.72rem;color:var(--text-3)">radi bez interneta</div></div>
  </div>
  <div class="kasa-top__right">
    <span class="badge badge--warn" id="offBadge">OFFLINE režim</span>
    <span class="badge badge--teal">Na čekanju: <span id="offQueueCount">0</span></span>
    <a class="btn btn--ghost btn--sm" href="/pos">Nazad na kasu</a>
  </div>
</header>
<main class="kasa-main">
  <div class="pos-layout">
    <div class="pos-cart card">
      <div class="card__head"><div class="card__title">Račun (offline)</div>
        <button class="btn btn--ghost btn--sm" style="color:var(--danger)" onclick="offClear()">Isprazni</button></div>
      <div id="cartItems" class="pos-cart__items"></div>
      <div class="pos-cart__foot">
        <div class="pos-cart__total"><span>Ukupno</span><span class="amt" id="cartTotal">0,00</span></div>
        <div class="pos-pay">
          <button class="btn btn--ghost" onclick="offNaplati('kes')">Keš</button>
          <button class="btn btn--ghost" onclick="offNaplati('kartica')">Kartica</button>
        </div>
      </div>
    </div>
    <div class="pos-products">
      <div class="tabs" id="catTabs" style="margin-bottom:14px;flex-wrap:wrap"></div>
      <div class="pgrid" id="prodGrid"></div>
      <div class="empty" id="noKat" style="display:none;padding:50px 20px">
        <h3>Katalog nije preuzet</h3>
        <p>Otvori kasu bar jednom dok ima interneta da se artikli sačuvaju za offline rad.</p>
      </div>
    </div>
  </div>
</main>
<script>
var KAT=null, CART=[], CATF=0;
function fmt(n){return (n||0).toLocaleString('sr-RS',{minimumFractionDigits:2,maximumFractionDigits:2});}
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}

function renderTabs(){
  var t=document.getElementById('catTabs');
  var h='<a href="#" class="'+(CATF==0?'is-active':'')+'" onclick="setCat(0,this);return false">Sve</a>';
  (KAT.kategorije||[]).forEach(function(k){ h+='<a href="#" class="'+(CATF==k.id?'is-active':'')+'" onclick="setCat('+k.id+',this);return false">'+esc(k.naziv)+'</a>'; });
  t.innerHTML=h;
}
function setCat(id,el){ CATF=id; renderTabs(); renderGrid(); }
function renderGrid(){
  var g=document.getElementById('prodGrid'), items=(KAT.artikli||[]).filter(function(a){return CATF==0||a.kategorija_id==CATF;});
  g.innerHTML=items.map(function(a){
    var b=a.boja||'#b1662c';
    return '<div class="ptile" onclick="addArt('+a.id+')"><div class="ptile__top" style="height:70px;background:linear-gradient(135deg,'+b+','+b+'cc)">'
      +'<span class="ptile__initial" style="font-size:1.5rem">'+esc((a.naziv||'?').charAt(0).toUpperCase())+'</span></div>'
      +'<div class="ptile__body" style="padding:8px 10px"><div class="ptile__name" style="font-size:.82rem">'+esc(a.naziv)+'</div>'
      +'<div class="ptile__price" style="font-size:.9rem">'+fmt(+a.prodajna_cena)+' RSD</div></div></div>';
  }).join('');
}
function addArt(id){
  var a=(KAT.artikli||[]).find(function(x){return x.id==id;}); if(!a)return;
  var ex=CART.find(function(x){return x.artikal_id==id;});
  if(ex) ex.kolicina++; else CART.push({artikal_id:a.id, naziv:a.naziv, cena:+a.prodajna_cena, kolicina:1});
  renderCart();
}
function setQty(i,n){ if(n<=0)CART.splice(i,1); else CART[i].kolicina=n; renderCart(); }
function offClear(){ CART=[]; renderCart(); }
function total(){ return CART.reduce(function(s,x){return s+x.cena*x.kolicina;},0); }
function renderCart(){
  var box=document.getElementById('cartItems');
  if(!CART.length){ box.innerHTML='<div class="empty" style="padding:30px 10px">Dodaj proizvode dodirom →</div>'; }
  else box.innerHTML=CART.map(function(s,i){
    return '<div class="cart-row"><div class="cart-row__info"><div class="cart-row__name">'+esc(s.naziv)+'</div>'
      +'<div class="cart-row__price muted">'+fmt(s.cena)+'</div></div>'
      +'<div class="cart-row__qty"><button onclick="setQty('+i+','+(s.kolicina-1)+')">−</button><span>'+s.kolicina+'</span>'
      +'<button onclick="setQty('+i+','+(s.kolicina+1)+')">+</button></div>'
      +'<div class="cart-row__sum">'+fmt(s.cena*s.kolicina)+'</div>'
      +'<button class="cart-row__del" onclick="setQty('+i+',0)">×</button></div>';
  }).join('');
  var ct=document.getElementById('cartTotal'); ct.textContent=fmt(total());
  ct.classList.remove('bump'); void ct.offsetWidth; ct.classList.add('bump');
}
function offNaplati(nacin){
  if(!CART.length){ SankUI.toast('Račun je prazan.','error'); return; }
  var t=total();
  SankUI.confirm('Naplatiti '+fmt(t)+' RSD ('+(nacin==='kes'?'keš':'kartica')+')? Račun će biti sinhronizovan kad se mreža vrati.',{title:'Offline naplata',ok:'Naplati'}).then(function(ok){
    if(!ok)return;
    var uuid=WaiterOffline.uuid();
    var racun={ uuid:uuid, kes:nacin==='kes'?t:0, kartica:nacin==='kartica'?t:0,
      created_at:new Date().toISOString(),
      stavke:CART.map(function(s){return {artikal_id:s.artikal_id, naziv:s.naziv, cena:s.cena, kolicina:s.kolicina};}) };
    WaiterOffline.queueRacun(racun).then(function(){
      stampajOff(racun, t, nacin, uuid);
      SankUI.toast('Račun sačuvan — biće sinhronizovan.','success');
      CART=[]; renderCart();
      WaiterOffline.trySync();
    });
  });
}
function stampajOff(r, t, nacin, uuid){
  var lok=(KAT&&KAT.lokal)||{}, br='OFF-'+uuid.slice(0,8).toUpperCase();
  var h='<!DOCTYPE html><html><head><meta charset="utf-8"><title>'+br+'</title>'
    +'<style>*{margin:0;padding:0;box-sizing:border-box;font-family:Segoe UI,Arial,sans-serif}body{width:80mm;margin:0 auto;padding:10px;color:#000;font-size:13px}.c{text-align:center}.b{font-weight:700}hr{border:none;border-top:1px dashed #999;margin:8px 0}table{width:100%;border-collapse:collapse}td{padding:2px 0}.r{text-align:right}.tot{font-size:16px;font-weight:800}@media print{@page{margin:4mm}}</style></head>'
    +'<body onload="window.print()" onafterprint="setTimeout(function(){window.close()},300)">'
    +'<div class="c"><h2>'+esc(lok.naziv||'Waiter')+'</h2>'+(lok.pib?'<div>PIB: '+esc(lok.pib)+'</div>':'')+'</div><hr>'
    +'<div class="c b">RAČUN '+br+' (OFFLINE)</div><div class="c">'+new Date().toLocaleString('sr-RS')+'</div><hr><table>';
  r.stavke.forEach(function(s){ h+='<tr><td colspan="2" class="b">'+esc(s.naziv)+'</td></tr><tr><td>'+s.kolicina+' × '+fmt(s.cena)+'</td><td class="r">'+fmt(s.cena*s.kolicina)+'</td></tr>'; });
  h+='</table><hr><table><tr><td class="tot">UKUPNO</td><td class="r tot">'+fmt(t)+' RSD</td></tr></table>'
    +'<div class="c" style="margin-top:6px">Plaćeno: '+(nacin==='kes'?'Keš':'Kartica')+'</div><hr>'
    +'<div class="c" style="font-size:11px">Hvala i doviđenja!<br>Ovo nije fiskalni račun.</div></body></html>';
  var w=window.open('','_blank','width=420,height=640'); if(w){ w.document.write(h); w.document.close(); }
}

document.addEventListener('DOMContentLoaded', function(){
  WaiterOffline.getKatalog().then(function(k){
    if(!k || !k.artikli || !k.artikli.length){ document.getElementById('noKat').style.display='block'; renderCart(); return; }
    KAT=k;
    if(k.lokal&&k.lokal.naziv){ document.getElementById('offLokal').textContent=k.lokal.naziv+' — offline kasa';
      if(k.lokal.boja) document.documentElement.style.setProperty('--brand', k.lokal.boja); }
    renderTabs(); renderGrid(); renderCart();
  });
  setInterval(function(){ var b=document.getElementById('offBadge');
    if(navigator.onLine){ b.textContent='Mreža se vratila'; b.className='badge badge--ok'; }
    else { b.textContent='OFFLINE režim'; b.className='badge badge--warn'; } }, 2000);
});
</script>
</body>
</html>
