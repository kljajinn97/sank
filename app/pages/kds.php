<?php
/** KDS — kuhinjski ekran (tablet u kuhinji/šanku).
 *  Pristup: aktiviran POS uređaj (kolačić), bez PIN-a — samo prikaz i "spremno". */
$device = pos_current_device();

if (!$device) {
    $kasa_title = 'Kuhinjski ekran';
    require __DIR__ . '/../partials/kasa_top.php';
    echo '<div class="kasa-center"><div class="kasa-box">
      <div class="sidebar__logo" style="width:56px;height:56px;margin:0 auto 14px">'.ico('kuhinja',26).'</div>
      <h2>Kuhinjski ekran</h2>
      <p class="muted">Ovaj uređaj nije aktiviran. Aktiviraj ga aktivacionim kodom pa se vrati ovde.</p>
      <a class="btn btn--primary btn--block" href="'.url('kasa').'">Aktivacija uređaja</a>
    </div></div>';
    require __DIR__ . '/../partials/kasa_bottom.php';
    return;
}
$lid = (int)$device['lokal_id'];

// JSON: stavke u pripremi
if (isset($_GET['json'])) {
    $rows = db_all(
       "SELECT rs.id, rs.naziv, rs.kolicina, rs.napomena, rs.poslato_at,
               r.id AS racun_id, COALESCE(s.naziv,'Šank') AS sto
        FROM racun_stavke rs
        JOIN racuni r ON r.id = rs.racun_id
        LEFT JOIN stolovi s ON s.id = r.sto_id
        WHERE r.lokal_id=? AND r.status='otvoren' AND rs.poslato=1 AND rs.spremljeno=0
        ORDER BY rs.poslato_at, rs.id", [$lid]);
    header('Content-Type: application/json');
    echo json_encode(array_map(fn($x)=>[
        'id'=>(int)$x['id'],'naziv'=>$x['naziv'],
        'kolicina'=>rtrim(rtrim(number_format((float)$x['kolicina'],3,',','.'),'0'),','),
        'napomena'=>$x['napomena'] ?? '', 'racun'=>(int)$x['racun_id'], 'sto'=>$x['sto'],
        'min'=>$x['poslato_at'] ? max(0,(int)floor((time()-strtotime($x['poslato_at']))/60)) : 0,
    ], $rows));
    exit;
}

// POST: označi spremno
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcija'] ?? '') === 'spremno') {
    csrf_check();
    $sid = (int)($_POST['stavka_id'] ?? 0);
    db_run('UPDATE racun_stavke rs JOIN racuni r ON r.id=rs.racun_id
            SET rs.spremljeno=1 WHERE rs.id=? AND r.lokal_id=?', [$sid,$lid]);
    echo 'ok'; exit;
}

$kasa_title = 'Kuhinja';
require __DIR__ . '/../partials/kasa_top.php';
?>
<div class="kds">
  <div class="kds__head">
    <h1><?= ico('kuhinja',22) ?> Porudžbine u pripremi</h1>
    <span class="badge badge--teal" id="kdsCount">0</span>
  </div>
  <div class="kds__grid" id="kdsGrid"></div>
  <div class="empty" id="kdsEmpty" style="display:none;padding:60px 20px">
    <h3>Sve je spremljeno</h3><p>Nove porudžbine se pojavljuju automatski.</p>
  </div>
</div>

<script>
var KDS_CSRF = <?= json_encode(csrf_token()) ?>, lastCount = -1;
function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
function beep(){ try{ var a=new (window.AudioContext||window.webkitAudioContext)(), o=a.createOscillator(), g=a.createGain();
  o.connect(g); g.connect(a.destination); o.frequency.value=880; g.gain.value=.12; o.start();
  setTimeout(function(){o.frequency.value=1174;},130); setTimeout(function(){o.stop(); a.close();},280);}catch(e){} }

function renderKds(items){
  var grid=document.getElementById('kdsGrid'), empty=document.getElementById('kdsEmpty');
  document.getElementById('kdsCount').textContent=items.length;
  if(lastCount>=0 && items.length>lastCount) beep();
  lastCount=items.length;
  if(!items.length){ grid.innerHTML=''; empty.style.display='block'; return; }
  empty.style.display='none';
  // grupiši po računu
  var g={};
  items.forEach(function(it){ (g[it.racun]=g[it.racun]||{sto:it.sto,items:[],min:0}).items.push(it);
    g[it.racun].min=Math.max(g[it.racun].min,it.min); });
  grid.innerHTML=Object.keys(g).map(function(rid){
    var o=g[rid], cls=o.min>=10?'is-late':(o.min>=5?'is-warn':'');
    return '<div class="kds-card '+cls+'">'
      +'<div class="kds-card__head"><strong>'+esc(o.sto)+'</strong><span class="kds-card__time">'+o.min+' min</span></div>'
      +o.items.map(function(it){
        return '<div class="kds-item" data-id="'+it.id+'">'
          +'<div class="kds-item__txt"><span class="kds-item__qty">'+esc(it.kolicina)+'×</span> '+esc(it.naziv)
          +(it.napomena?'<div class="kds-item__note">» '+esc(it.napomena)+'</div>':'')+'</div>'
          +'<button class="kds-item__done" onclick="spremno('+it.id+',this)">Spremno</button></div>';
      }).join('')
      +'</div>';
  }).join('');
}
async function ucitaj(){
  try{ var r=await fetch('<?= url('kds') ?>?json=1',{cache:'no-store'}); renderKds(await r.json()); }catch(e){}
}
async function spremno(id,btn){
  btn.disabled=true; btn.textContent='...';
  await fetch('<?= url('kds') ?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({akcija:'spremno',stavka_id:id,_csrf:KDS_CSRF})});
  ucitaj();
}
ucitaj(); setInterval(ucitaj, 5000);
</script>

<?php require __DIR__ . '/../partials/kasa_bottom.php'; ?>
