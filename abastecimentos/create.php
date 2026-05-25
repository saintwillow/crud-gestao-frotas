<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'abastecimentos';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$erros = [];

$viatura_id = '';
$colaborador_id = '';
$posto = '';
$combustivel = 'Diesel';
$litros = '';
$preco_litro = '';
$km_atual = '';
$data_abastecimento = date('Y-m-d');
$observacoes = '';
$latitude = '';
$longitude = '';

$viaturas = [];
$resV = mysqli_query($ligacao, "SELECT id, matricula, marca_modelo FROM viaturas ORDER BY marca_modelo ASC");
if ($resV) while ($r = mysqli_fetch_assoc($resV)) $viaturas[] = $r;

$colaboradores = [];
$resC = mysqli_query($ligacao, "SELECT id, nome FROM colaboradores ORDER BY nome ASC");
if ($resC) while ($r = mysqli_fetch_assoc($resC)) $colaboradores[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $viatura_id = trim($_POST['viatura_id'] ?? '');
  $colaborador_id = trim($_POST['colaborador_id'] ?? '');
  $posto = trim($_POST['posto'] ?? '');
  $combustivel = trim($_POST['combustivel'] ?? '');
  $litros = trim($_POST['litros'] ?? '');
  $preco_litro = trim($_POST['preco_litro'] ?? '');
  $km_atual = trim($_POST['km_atual'] ?? '');
  $data_abastecimento = trim($_POST['data_abastecimento'] ?? '');
  $observacoes = trim($_POST['observacoes'] ?? '');
  $latitude = trim($_POST['latitude'] ?? '');
  $longitude = trim($_POST['longitude'] ?? '');

  if ($viatura_id === '' || !ctype_digit($viatura_id)) {
    $erros[] = "Selecione uma viatura.";
  } else {
    $vid = (int)$viatura_id;
    // Buscar quilometragem atual da viatura para validar
    $resCheckV = mysqli_query($ligacao, "SELECT quilometragem FROM viaturas WHERE id = $vid LIMIT 1");
    $viaturaKmAtual = 0;
    if ($resCheckV && $vRow = mysqli_fetch_assoc($resCheckV)) {
      $viaturaKmAtual = (int)$vRow['quilometragem'];
    }
  }

  if ($colaborador_id !== '' && !ctype_digit($colaborador_id)) $erros[] = "Motorista inválido.";
  if ($posto === '') $erros[] = "O posto é obrigatório.";
  if ($litros === '' || !is_numeric($litros) || (float)$litros <= 0) $erros[] = "Informe os litros (maior que 0).";
  if ($preco_litro === '' || !is_numeric($preco_litro) || (float)$preco_litro <= 0) $erros[] = "Informe o preço por litro (maior que 0).";
  if ($data_abastecimento === '') $erros[] = "A data é obrigatória.";

  $km_val = null;
  if ($km_atual !== '') {
    if (!ctype_digit($km_atual)) {
      $erros[] = "A quilometragem deve ser um número inteiro válido.";
    } else {
      $km_val = (int)$km_atual;
      if (isset($viaturaKmAtual) && $km_val < $viaturaKmAtual) {
        $erros[] = "A quilometragem inserida ({$km_val} km) não pode ser menor que a quilometragem atual da viatura ({$viaturaKmAtual} km).";
      }
    }
  }

  if (($latitude !== '' && !is_numeric($latitude)) || ($longitude !== '' && !is_numeric($longitude))) {
    $erros[] = "As coordenadas são inválidas.";
  }

  if ($latitude !== '' && ((float)$latitude < -90 || (float)$latitude > 90)) {
    $erros[] = "Latitude fora do intervalo válido.";
  }

  if ($longitude !== '' && ((float)$longitude < -180 || (float)$longitude > 180)) {
    $erros[] = "Longitude fora do intervalo válido.";
  }

  // Processamento de Comprovativo
  $comprovativo_db = null;
  if (isset($_FILES['comprovativo']) && $_FILES['comprovativo']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['comprovativo']['tmp_name'];
    $fileName = $_FILES['comprovativo']['name'];
    $fileSize = $_FILES['comprovativo']['size'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
    if (in_array($fileExtension, $allowedExtensions)) {
      if ($fileSize <= 5242880) { // 5MB max
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $uploadFileDir = __DIR__ . '/../img/uploads/comprovativos/';
        if (!is_dir($uploadFileDir)) {
          mkdir($uploadFileDir, 0755, true);
        }
        $dest_path = $uploadFileDir . $newFileName;
        if (move_uploaded_file($fileTmpPath, $dest_path)) {
          $comprovativo_db = 'img/uploads/comprovativos/' . $newFileName;
        } else {
          $erros[] = "Erro ao mover o comprovativo para a pasta de uploads.";
        }
      } else {
        $erros[] = "O comprovativo excede o limite de tamanho de 5MB.";
      }
    } else {
      $erros[] = "Apenas são permitidos comprovativos nos formatos JPG, JPEG, PNG e PDF.";
    }
  }

  if (!$erros) {
    $vid        = (int)$viatura_id;
    $cid_val    = ($colaborador_id !== '' && (int)$colaborador_id > 0) ? (int)$colaborador_id : null;
    
    // Tentamos também obter o motorista_id associado a este colaborador se possível
    $motorista_val = null;
    if ($cid_val !== null) {
      $resMot = mysqli_query($ligacao, "SELECT id FROM motoristas WHERE colaborador_id = $cid_val LIMIT 1");
      if ($resMot && $mRow = mysqli_fetch_assoc($resMot)) {
        $motorista_val = (int)$mRow['id'];
      }
    }

    $lit        = (float)$litros;
    $pl         = (float)$preco_litro;
    $total      = round($lit * $pl, 2);
    $obs_val    = $observacoes !== '' ? $observacoes : null;
    $lat_val    = $latitude !== '' ? (float)$latitude : null;
    $lng_val    = $longitude !== '' ? (float)$longitude : null;
    $usuario_reg = usuario_id_sessao();

    mysqli_begin_transaction($ligacao);

    try {
      $stmt = mysqli_prepare($ligacao,
        "INSERT INTO abastecimentos
           (viatura_id, colaborador_id, motorista_id, registado_por_usuario_id, posto, combustivel, litros, preco_litro, total, km_atual, data_abastecimento, observacoes, comprovativo, latitude, longitude, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'registado')"
      );
      mysqli_stmt_bind_param($stmt, "iiiiissdddidsssdd",
        $vid, $cid_val, $motorista_val, $usuario_reg, $posto, $combustivel, $lit, $pl, $total, $km_val,
        $data_abastecimento, $obs_val, $comprovativo_db, $lat_val, $lng_val
      );

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($ligacao));
      }
      mysqli_stmt_close($stmt);

      // Atualizar quilometragem do veículo
      if ($km_val !== null) {
        $stmtV = mysqli_prepare($ligacao,
          "UPDATE viaturas
           SET quilometragem = GREATEST(quilometragem, ?)
           WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmtV, "ii", $km_val, $vid);
        if (!mysqli_stmt_execute($stmtV)) {
          throw new Exception(mysqli_error($ligacao));
        }
        mysqli_stmt_close($stmtV);
      }

      mysqli_commit($ligacao);

      header("Location: index.php?msg=criado");
      exit;
    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = "Erro ao salvar no banco de dados: " . $e->getMessage();
    }
  }
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
  #abastecimentoMap {
    height: 340px;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(15,23,42,.08);
  }
  .map-hint {
    font-size: 13px;
    color: #64748b;
  }
  .coords-box {
    background: #f8fafc;
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 12px;
    padding: 10px 12px;
    font-size: 13px;
    color: #334155;
  }
</style>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="index.php">← Voltar ao abastecimento</a>

  <div>
    <h1 class="page-title">Novo Abastecimento</h1>
    <div class="page-subtitle">Registar abastecimento para uma viatura</div>
  </div>

  <?php if ($erros): ?>
    <div class="alert alert-danger">
      <strong>Verifique os campos:</strong>
      <ul class="mb-0">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <form method="post" enctype="multipart/form-data" class="row g-3">

      <div class="col-12">
        <label class="form-label form-label-soft">Viatura *</label>
        <select name="viatura_id" class="form-select form-select-lg" required>
          <option value="">Selecione...</option>
          <?php foreach ($viaturas as $v): ?>
            <?php $id = (int)$v['id']; ?>
            <option value="<?php echo $id; ?>" <?php echo ((string)$viatura_id === (string)$id) ? 'selected' : ''; ?>>
              <?php echo h(($v['marca_modelo'] ?? '') . " • " . ($v['matricula'] ?? '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Motorista (opcional)</label>
        <select name="colaborador_id" class="form-select form-select-lg">
          <option value="">Sem motorista</option>
          <?php foreach ($colaboradores as $c): ?>
            <?php $cid = (int)$c['id']; ?>
            <option value="<?php echo $cid; ?>" <?php echo ((string)$colaborador_id === (string)$cid) ? 'selected' : ''; ?>>
              <?php echo h($c['nome'] ?? ''); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Posto *</label>
        <input name="posto" class="form-control form-control-lg" value="<?php echo h($posto); ?>" placeholder="BP Olhão / Galp Tavira / Cepsa Albufeira" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Combustível</label>
        <select name="combustivel" class="form-select form-select-lg">
          <?php foreach (['Diesel','Gasolina','Etanol','Elétrico','Híbrido','Outro'] as $c): ?>
            <option value="<?php echo h($c); ?>" <?php echo ($combustivel === $c) ? 'selected' : ''; ?>><?php echo h($c); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Litros *</label>
        <input type="number" step="0.01" min="0" name="litros" class="form-control form-control-lg" value="<?php echo h($litros); ?>" required>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Preço/Litro *</label>
        <input type="number" step="0.001" min="0" name="preco_litro" class="form-control form-control-lg" value="<?php echo h($preco_litro); ?>" required>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Km atual (opcional)</label>
        <input type="number" step="1" name="km_atual" class="form-control form-control-lg" value="<?php echo h($km_atual); ?>" placeholder="Ex: 85200">
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Data *</label>
        <input type="date" name="data_abastecimento" class="form-control form-control-lg" value="<?php echo h($data_abastecimento); ?>" required>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Comprovativo de Abastecimento (Imagem ou PDF)</label>
        <input type="file" name="comprovativo" class="form-control form-control-lg" accept=".jpg,.jpeg,.png,.pdf">
        <div class="form-text text-muted" style="font-size: 12px;">Máximo 5MB. Formatos suportados: JPG, JPEG, PNG, PDF.</div>
      </div>

      <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-2">
          <div>
            <label class="form-label form-label-soft mb-1">Localização do abastecimento</label>
            <div class="map-hint">Clique no mapa ou use a geolocalização do navegador.</div>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnGeo">Usar minha localização</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearCoords">Limpar ponto</button>
          </div>
        </div>

        <div id="abastecimentoMap"></div>

        <input type="hidden" name="latitude" id="latitude" value="<?php echo h($latitude); ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?php echo h($longitude); ?>">

        <div class="coords-box mt-3" id="coordsText">
          Nenhuma coordenada selecionada.
        </div>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Observações</label>
        <textarea name="observacoes" class="form-control" rows="4" placeholder="Opcional"><?php echo h($observacoes); ?></textarea>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2 pt-2">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary" type="submit">Salvar</button>
      </div>

    </form>
  </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const initialLat = <?php echo ($latitude !== '' ? json_encode((float)$latitude) : 'null'); ?>;
  const initialLng = <?php echo ($longitude !== '' ? json_encode((float)$longitude) : 'null'); ?>;

  const defaultCenter = [37.2301, -8.0653];
  const map = L.map('abastecimentoMap').setView(defaultCenter, 9);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  let marker = null;
  const latInput = document.getElementById('latitude');
  const lngInput = document.getElementById('longitude');
  const coordsText = document.getElementById('coordsText');

  function updateCoordsText(lat, lng) {
    if (lat === null || lng === null) {
      coordsText.textContent = 'Nenhuma coordenada selecionada.';
      return;
    }
    coordsText.textContent = 'Latitude: ' + Number(lat).toFixed(7) + ' | Longitude: ' + Number(lng).toFixed(7);
  }

  function ensureMarker(lat, lng) {
    if (!marker) {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);
      marker.on('dragend', function () {
        const p = marker.getLatLng();
        latInput.value = p.lat.toFixed(7);
        lngInput.value = p.lng.toFixed(7);
        updateCoordsText(p.lat, p.lng);
      });
    } else {
      marker.setLatLng([lat, lng]);
    }
  }

  function setPoint(lat, lng, zoomLevel = 15) {
    ensureMarker(lat, lng);
    latInput.value = Number(lat).toFixed(7);
    lngInput.value = Number(lng).toFixed(7);
    updateCoordsText(lat, lng);
    map.setView([lat, lng], zoomLevel);
  }

  function clearPoint() {
    if (marker) {
      map.removeLayer(marker);
      marker = null;
    }
    latInput.value = '';
    lngInput.value = '';
    updateCoordsText(null, null);
    map.setView(defaultCenter, 9);
  }

  map.on('click', function (e) {
    setPoint(e.latlng.lat, e.latlng.lng);
  });

  document.getElementById('btnGeo').addEventListener('click', function () {
    if (!navigator.geolocation) {
      alert('Geolocalização não suportada neste navegador.');
      return;
    }

    navigator.geolocation.getCurrentPosition(
      function (pos) {
        setPoint(pos.coords.latitude, pos.coords.longitude);
      },
      function () {
        alert('Não foi possível obter a localização. Permite o acesso à geolocalização no navegador.');
      },
      { enableHighAccuracy: true, timeout: 10000 }
    );
  });

  document.getElementById('btnClearCoords').addEventListener('click', clearPoint);

  if (initialLat !== null && initialLng !== null) {
    setPoint(initialLat, initialLng, 13);
  } else {
    updateCoordsText(null, null);
  }

  setTimeout(function () {
    map.invalidateSize();
  }, 200);
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>