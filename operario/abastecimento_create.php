<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

if (is_gestor_ou_admin()) {
  header("Location: " . base_url() . "/abastecimentos/create.php");
  exit;
}

$active = 'operario_abastecimentos';

require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$motorista_id = motorista_id_sessao();
$usuario_id = usuario_id_sessao();
$colaborador_id = colaborador_id_sessao();

if (!$motorista_id) {
  echo '<div class="glass-card p-4 text-center text-muted">Conta não associada a motorista. Contacte o administrador.</div>';
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

/*
  A viatura vem da atribuição aberta, não da sessão.
*/
$atribuicao = atribuicao_aberta_motorista($ligacao, $motorista_id);

if (!$atribuicao) {
  ?>
  <div class="page-max-4xl space-y-6">
    <a class="back-link" href="abastecimentos.php">← Voltar aos meus abastecimentos</a>

    <div>
      <h1 class="page-title">Novo Abastecimento</h1>
      <div class="page-subtitle">Não existe viatura atribuída neste momento.</div>
    </div>

    <div class="glass-card p-4 text-center">
      <i class="bi bi-car-front-fill fs-1 mb-3" style="color:hsl(38,92%,50%);"></i>
      <h2 class="h5 fw-bold mb-2">Sem viatura atribuída</h2>
      <p class="text-muted mb-0">
        Não é possível registar abastecimento sem uma atribuição aberta.
      </p>
    </div>
  </div>
  <?php
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$viatura_id = (int)$atribuicao['viatura_id'];

/*
  O abastecimento do operário deve estar associado a um serviço aberto.
*/
$servicoAberto = null;

$stmt = mysqli_prepare($ligacao,
  "SELECT *
   FROM servicos_operacionais
   WHERE motorista_id = ?
     AND estado = 'aberto'
   ORDER BY data_inicio DESC, id DESC
   LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $motorista_id);
mysqli_stmt_execute($stmt);
$resServ = mysqli_stmt_get_result($stmt);
$servicoAberto = $resServ ? mysqli_fetch_assoc($resServ) : null;
mysqli_stmt_close($stmt);

if (!$servicoAberto) {
  ?>
  <div class="page-max-4xl space-y-6">
    <a class="back-link" href="abastecimentos.php">← Voltar aos meus abastecimentos</a>

    <div>
      <h1 class="page-title">Novo Abastecimento</h1>
      <div class="page-subtitle">
        Viatura:
        <strong><?php echo h($atribuicao['matricula'] . ' — ' . $atribuicao['marca_modelo']); ?></strong>
      </div>
    </div>

    <div class="glass-card p-4 text-center">
      <i class="bi bi-clock-history fs-1 mb-3" style="color:hsl(38,92%,50%);"></i>
      <h2 class="h5 fw-bold mb-2">Serviço ainda não iniciado</h2>
      <p class="text-muted mb-4">
        Para registar um abastecimento, primeiro inicie o serviço operacional.
      </p>
      <a href="servico.php" class="btn btn-primary">Iniciar serviço</a>
    </div>
  </div>
  <?php
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$servico_id = (int)$servicoAberto['id'];

$erros = [];

$posto = '';
$combustivel = $atribuicao['combustivel'] ?? 'Diesel';
$litros = '';
$preco_litro = '';
$km_atual = '';
$data_abastecimento = date('Y-m-d');
$observacoes = '';
$latitude = '';
$longitude = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $posto              = trim($_POST['posto'] ?? '');
  $combustivel        = trim($_POST['combustivel'] ?? '');
  $litros             = trim($_POST['litros'] ?? '');
  $preco_litro        = trim($_POST['preco_litro'] ?? '');
  $km_atual           = trim($_POST['km_atual'] ?? '');
  $data_abastecimento = trim($_POST['data_abastecimento'] ?? '');
  $observacoes        = trim($_POST['observacoes'] ?? '');
  $latitude           = trim($_POST['latitude'] ?? '');
  $longitude          = trim($_POST['longitude'] ?? '');

  if ($litros === '' || !is_numeric($litros) || (float)$litros <= 0) {
    $erros[] = "Informe os litros com valor maior que 0.";
  }

  if ($preco_litro === '' || !is_numeric($preco_litro) || (float)$preco_litro <= 0) {
    $erros[] = "Informe o preço por litro com valor maior que 0.";
  }
  $km_atual_int = null;

  if ($km_atual !== '') {
    if (!ctype_digit($km_atual)) {
      $erros[] = "A quilometragem deve ser um número válido.";
    } else {
      $km_atual_int = (int)$km_atual;
      $km_minimo = (int)$servicoAberto['km_inicio'];
      $km_viatura = (int)$atribuicao['quilometragem'];

      if ($km_atual_int < $km_minimo) {
        $erros[] = "A quilometragem do abastecimento não pode ser menor que a quilometragem inicial do serviço ({$km_minimo} km).";
      }
      if ($km_atual_int < $km_viatura) {
        $erros[] = "A quilometragem do abastecimento não pode ser menor que a quilometragem atual da viatura ({$km_viatura} km).";
      }
    }
  }

  if ($data_abastecimento === '') {
    $erros[] = "A data é obrigatória.";
  }

  if ($latitude !== '' && !is_numeric($latitude)) {
    $erros[] = "Latitude inválida.";
  }

  if ($longitude !== '' && !is_numeric($longitude)) {
    $erros[] = "Longitude inválida.";
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
    $lit = (float)$litros;
    $pl = (float)$preco_litro;
    $total = round($lit * $pl, 2);

    $posto_val = $posto !== '' ? $posto : null;
    $km_atual_val = $km_atual !== '' ? (int)$km_atual : null;

    $obs_val = $observacoes !== '' ? $observacoes : null;
    $lat_val = $latitude !== '' ? (float)$latitude : null;
    $lng_val = $longitude !== '' ? (float)$longitude : null;
    $estado = 'registado';

    mysqli_begin_transaction($ligacao);

    try {
      $stmt = mysqli_prepare($ligacao,
        "INSERT INTO abastecimentos
          (
            viatura_id,
            colaborador_id,
            motorista_id,
            registado_por_usuario_id,
            servico_id,
            posto,
            combustivel,
            litros,
            preco_litro,
            total,
            km_atual,
            data_abastecimento,
            observacoes,
            comprovativo,
            latitude,
            longitude,
            estado
          )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
      );

      mysqli_stmt_bind_param(
        $stmt,
        "iiiiissdddidsssds",
        $viatura_id,
        $colaborador_id,
        $motorista_id,
        $usuario_id,
        $servico_id,
        $posto_val,
        $combustivel,
        $lit,
        $pl,
        $total,
        $km_atual_val,
        $data_abastecimento,
        $obs_val,
        $comprovativo_db,
        $lat_val,
        $lng_val,
        $estado
      );

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($ligacao));
      }

      mysqli_stmt_close($stmt);

      if ($km_atual_val !== null) {
        $stmt = mysqli_prepare($ligacao,
          "UPDATE viaturas
           SET quilometragem = GREATEST(quilometragem, ?)
           WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, "ii", $km_atual_val, $viatura_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
      }

      mysqli_commit($ligacao);

      header("Location: abastecimentos.php?msg=criado");
      exit;

    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = "Erro ao salvar: " . $e->getMessage();
    }
  }
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
  #abastecimentoMap {
    height: 320px;
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
  <a class="back-link" href="abastecimentos.php">← Voltar aos meus abastecimentos</a>

  <div>
    <h1 class="page-title">Novo Abastecimento</h1>
    <div class="page-subtitle">
      Viatura:
      <strong><?php echo h($atribuicao['matricula'] . ' — ' . $atribuicao['marca_modelo']); ?></strong>
      <span class="text-muted">
        · Serviço <?php echo h($servicoAberto['codigo']); ?>
      </span>
    </div>
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

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Posto</label>
        <input
          name="posto"
          class="form-control form-control-lg"
          value="<?php echo h($posto); ?>"
        >
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Combustível</label>
        <select name="combustivel" class="form-select form-select-lg">
          <?php foreach (['Diesel','Gasolina','Etanol','Elétrico','Híbrido','Outro'] as $c): ?>
            <option value="<?php echo h($c); ?>" <?php echo ($combustivel === $c) ? 'selected' : ''; ?>>
              <?php echo h($c); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Litros *</label>
        <input
          type="number"
          step="0.01"
          min="0"
          name="litros"
          class="form-control form-control-lg"
          value="<?php echo h($litros); ?>"
          required
        >
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Preço/Litro *</label>
        <input
          type="number"
          step="0.001"
          min="0"
          name="preco_litro"
          class="form-control form-control-lg"
          value="<?php echo h($preco_litro); ?>"
          required
        >
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Km atual</label>
        <input
          type="number"
          step="1"
          min="<?php echo (int)$servicoAberto['km_inicio']; ?>"
          name="km_atual"
          class="form-control form-control-lg"
          value="<?php echo h($km_atual); ?>"
        >
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Data *</label>
        <input
          type="date"
          name="data_abastecimento"
          class="form-control form-control-lg"
          value="<?php echo h($data_abastecimento); ?>"
          required
        >
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Comprovativo de Abastecimento (Imagem ou PDF)</label>
        <input type="file" name="comprovativo" class="form-control form-control-lg" accept=".jpg,.jpeg,.png,.pdf">
        <div class="form-text text-muted" style="font-size: 12px;">Máximo 5MB. Formatos suportados: JPG, JPEG, PNG, PDF.</div>
      </div>

      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <div>
            <label class="form-label form-label-soft mb-1">Localização</label>
            <div class="map-hint">Clique no mapa ou use a geolocalização.</div>
          </div>

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
        <button class="btn btn-primary" type="submit">Salvar abastecimento</button>
      </div>

    </form>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  const iLat = <?php echo ($latitude !== '' ? json_encode((float)$latitude) : 'null'); ?>;
  const iLng = <?php echo ($longitude !== '' ? json_encode((float)$longitude) : 'null'); ?>;

  const def = [37.2301, -8.0653];
  const map = L.map('abastecimentoMap').setView(def, 9);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  let marker = null;

  const latI = document.getElementById('latitude');
  const lngI = document.getElementById('longitude');
  const txt = document.getElementById('coordsText');

  function showCoords(lat, lng) {
    txt.textContent = 'Lat: ' + Number(lat).toFixed(6) + ' | Lng: ' + Number(lng).toFixed(6);
  }

  function setPoint(lat, lng, z = 15) {
    if (!marker) {
      marker = L.marker([lat, lng], { draggable: true }).addTo(map);

      marker.on('dragend', function (e) {
        const p = e.target.getLatLng();
        latI.value = p.lat.toFixed(6);
        lngI.value = p.lng.toFixed(6);
        showCoords(p.lat, p.lng);
      });
    } else {
      marker.setLatLng([lat, lng]);
    }

    latI.value = Number(lat).toFixed(6);
    lngI.value = Number(lng).toFixed(6);
    showCoords(lat, lng);
    map.setView([lat, lng], z);
  }

  map.on('click', function (e) {
    setPoint(e.latlng.lat, e.latlng.lng);
  });

  document.getElementById('btnGeo').onclick = function () {
    if (!navigator.geolocation) return;

    navigator.geolocation.getCurrentPosition(function (p) {
      setPoint(p.coords.latitude, p.coords.longitude);
    });
  };

  document.getElementById('btnClear').onclick = function () {
    if (marker) {
      map.removeLayer(marker);
      marker = null;
    }

    latI.value = '';
    lngI.value = '';
    txt.textContent = 'Nenhuma coordenada selecionada.';
    map.setView(def, 9);
  };

  if (iLat && iLng) {
    setPoint(iLat, iLng, 13);
  }

  setTimeout(function () {
    map.invalidateSize();
  }, 200);
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>