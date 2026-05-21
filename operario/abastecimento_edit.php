<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();
if (is_gestor_ou_admin()) { header("Location: " . base_url() . "/abastecimentos/index.php"); exit; }

$active = 'operario_abastecimentos';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$motorista_id = motorista_id_sessao();
$viatura_id   = viatura_id_sessao();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0 || !$motorista_id) { header("Location: abastecimentos.php"); exit; }

// Garantir que o registo pertence ao operário
$stmt = mysqli_prepare($ligacao, "SELECT * FROM abastecimentos WHERE id=? AND colaborador_id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "ii", $id, $motorista_id);
mysqli_stmt_execute($stmt);
$a = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$a) { header("Location: abastecimentos.php"); exit; }

$erros = [];
$posto              = (string)$a['posto'];
$combustivel        = (string)$a['combustivel'];
$litros             = (string)$a['litros'];
$preco_litro        = (string)$a['preco_litro'];
$data_abastecimento = (string)$a['data_abastecimento'];
$observacoes        = (string)($a['observacoes'] ?? '');
$latitude           = $a['latitude'] !== null ? (string)$a['latitude'] : '';
$longitude          = $a['longitude'] !== null ? (string)$a['longitude'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posto              = trim($_POST['posto'] ?? '');
  $combustivel        = trim($_POST['combustivel'] ?? '');
  $litros             = trim($_POST['litros'] ?? '');
  $preco_litro        = trim($_POST['preco_litro'] ?? '');
  $data_abastecimento = trim($_POST['data_abastecimento'] ?? '');
  $observacoes        = trim($_POST['observacoes'] ?? '');
  $latitude           = trim($_POST['latitude'] ?? '');
  $longitude          = trim($_POST['longitude'] ?? '');

  if ($posto === '') $erros[] = "O posto é obrigatório.";
  if (!is_numeric($litros) || (float)$litros <= 0) $erros[] = "Litros inválidos.";
  if (!is_numeric($preco_litro) || (float)$preco_litro <= 0) $erros[] = "Preço inválido.";
  if ($data_abastecimento === '') $erros[] = "Data obrigatória.";

  if (!$erros) {
    $lit     = (float)$litros;
    $pl      = (float)$preco_litro;
    $total   = round($lit * $pl, 2);
    $obs_val = $observacoes !== '' ? $observacoes : null;
    $lat_val = $latitude !== '' ? (float)$latitude : null;
    $lng_val = $longitude !== '' ? (float)$longitude : null;

    $stmt = mysqli_prepare($ligacao,
      "UPDATE abastecimentos SET posto=?, combustivel=?, litros=?, preco_litro=?, total=?,
       data_abastecimento=?, observacoes=?, latitude=?, longitude=?
       WHERE id=? AND colaborador_id=?"
    );
    mysqli_stmt_bind_param($stmt, "ssdddssddii",
      $posto, $combustivel, $lit, $pl, $total,
      $data_abastecimento, $obs_val, $lat_val, $lng_val, $id, $motorista_id
    );

    if (mysqli_stmt_execute($stmt)) {
      header("Location: abastecimentos.php?msg=editado");
      exit;
    } else {
      $erros[] = "Erro ao atualizar: " . mysqli_error($ligacao);
    }
    mysqli_stmt_close($stmt);
  }
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<style>
  #abastecimentoMap { height:320px; border-radius:16px; overflow:hidden; border:1px solid rgba(15,23,42,.08); }
  .coords-box { background:#f8fafc; border:1px solid rgba(15,23,42,.08); border-radius:12px; padding:10px 12px; font-size:13px; color:#334155; }
</style>

<div class="page-max-4xl space-y-6">
  <a class="back-link" href="abastecimentos.php">← Voltar aos meus abastecimentos</a>
  <div>
    <h1 class="page-title">Editar Abastecimento</h1>
    <div class="page-subtitle"><?php echo h($posto); ?></div>
  </div>

  <?php if ($erros): ?>
    <div class="alert alert-danger">
      <ul class="mb-0"><?php foreach ($erros as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <form method="post" class="row g-3">

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Posto *</label>
        <input name="posto" class="form-control form-control-lg" value="<?php echo h($posto); ?>" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Combustível</label>
        <select name="combustivel" class="form-select form-select-lg">
          <?php foreach (['Diesel','Gasolina','Etanol','Elétrico','Híbrido','Outro'] as $c): ?>
            <option value="<?php echo h($c); ?>" <?php echo ($combustivel===$c)?'selected':''; ?>><?php echo h($c); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Litros *</label>
        <input type="number" step="0.01" min="0" name="litros" class="form-control form-control-lg" value="<?php echo h($litros); ?>" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Preço/Litro *</label>
        <input type="number" step="0.001" min="0" name="preco_litro" class="form-control form-control-lg" value="<?php echo h($preco_litro); ?>" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Data *</label>
        <input type="date" name="data_abastecimento" class="form-control form-control-lg" value="<?php echo h($data_abastecimento); ?>" required>
      </div>

      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <label class="form-label form-label-soft mb-0">Localização</label>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnGeo">Usar localização</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClear">Limpar</button>
          </div>
        </div>
        <div id="abastecimentoMap"></div>
        <input type="hidden" name="latitude" id="latitude" value="<?php echo h($latitude); ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?php echo h($longitude); ?>">
        <div class="coords-box mt-3" id="coordsText">Nenhuma coordenada selecionada.</div>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Observações</label>
        <textarea name="observacoes" class="form-control" rows="3"><?php echo h($observacoes); ?></textarea>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2 pt-2">
        <a href="abastecimentos.php" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary" type="submit">Salvar alterações</button>
      </div>

    </form>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const iLat=<?php echo ($latitude!==''?json_encode((float)$latitude):'null'); ?>;
  const iLng=<?php echo ($longitude!==''?json_encode((float)$longitude):'null'); ?>;
  const def=[37.2301,-8.0653];
  const map=L.map('abastecimentoMap').setView(def,9);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(map);
  let marker=null;
  const latI=document.getElementById('latitude'),lngI=document.getElementById('longitude'),txt=document.getElementById('coordsText');
  function showCoords(lat,lng){txt.textContent='Lat: '+Number(lat).toFixed(6)+' | Lng: '+Number(lng).toFixed(6);}
  function setPoint(lat,lng,z=15){
    if(!marker){marker=L.marker([lat,lng],{draggable:true}).addTo(map);marker.on('dragend',e=>{const p=e.target.getLatLng();latI.value=p.lat.toFixed(6);lngI.value=p.lng.toFixed(6);showCoords(p.lat,p.lng);});}
    else marker.setLatLng([lat,lng]);
    latI.value=Number(lat).toFixed(6);lngI.value=Number(lng).toFixed(6);showCoords(lat,lng);map.setView([lat,lng],z);
  }
  map.on('click',e=>setPoint(e.latlng.lat,e.latlng.lng));
  document.getElementById('btnGeo').onclick=()=>navigator.geolocation&&navigator.geolocation.getCurrentPosition(p=>setPoint(p.coords.latitude,p.coords.longitude));
  document.getElementById('btnClear').onclick=()=>{if(marker){map.removeLayer(marker);marker=null;}latI.value='';lngI.value='';txt.textContent='Nenhuma coordenada selecionada.';map.setView(def,9);};
  if(iLat&&iLng)setPoint(iLat,iLng,13);
  setTimeout(()=>map.invalidateSize(),200);
</script>

<?php mysqli_close($ligacao); require_once __DIR__ . "/../inc/footer.php"; ?>
