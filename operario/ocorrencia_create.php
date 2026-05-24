<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

if (is_gestor_ou_admin()) {
  header("Location: " . base_url() . "/index.php");
  exit;
}

$active = 'operario_ocorrencias';

require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function gerar_codigo_ocorrencia(): string {
  return 'OCR-' . date('Ymd-His') . '-' . random_int(100, 999);
}

$motorista_id = motorista_id_sessao();
$usuario_id = usuario_id_sessao();

if (!$motorista_id) {
  echo '<div class="glass-card p-4 text-center text-muted">Conta não associada a motorista. Contacte o administrador.</div>';
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

/* A viatura vem da atribuição aberta */
$atribuicao = atribuicao_aberta_motorista($ligacao, $motorista_id);

if (!$atribuicao) {
  ?>
  <div class="page-max-4xl space-y-6">
    <a class="back-link" href="ocorrencias.php">← Voltar às minhas ocorrências</a>

    <div>
      <h1 class="page-title">Nova Ocorrência</h1>
      <div class="page-subtitle">Não existe viatura atribuída neste momento.</div>
    </div>

    <div class="glass-card p-4 text-center">
      <i class="bi bi-car-front-fill fs-1 mb-3" style="color:hsl(38,92%,50%);"></i>
      <h2 class="h5 fw-bold mb-2">Sem viatura atribuída</h2>
      <p class="text-muted mb-0">
        Não é possível registar uma ocorrência sem uma atribuição de viatura aberta.
      </p>
    </div>
  </div>
  <?php
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$viatura_id = (int)$atribuicao['viatura_id'];

/* A ocorrência deve estar associada a um serviço aberto */
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
    <a class="back-link" href="ocorrencias.php">← Voltar às minhas ocorrências</a>

    <div>
      <h1 class="page-title">Nova Ocorrência</h1>
      <div class="page-subtitle">
        Viatura:
        <strong><?php echo h($atribuicao['matricula'] . ' — ' . $atribuicao['marca_modelo']); ?></strong>
      </div>
    </div>

    <div class="glass-card p-4 text-center">
      <i class="bi bi-clock-history fs-1 mb-3" style="color:hsl(38,92%,50%);"></i>
      <h2 class="h5 fw-bold mb-2">Serviço ainda não iniciado</h2>
      <p class="text-muted mb-4">
        Para registar uma ocorrência com a viatura, primeiro inicie o serviço operacional de hoje.
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

$titulo = '';
$tipo = 'avaria';
$gravidade = 'media';
$descricao = '';
$latitude = '';
$longitude = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $titulo      = trim($_POST['titulo'] ?? '');
  $tipo        = trim($_POST['tipo'] ?? 'outro');
  $gravidade   = trim($_POST['gravidade'] ?? 'media');
  $descricao   = trim($_POST['descricao'] ?? '');
  $latitude    = trim($_POST['latitude'] ?? '');
  $longitude   = trim($_POST['longitude'] ?? '');
  $bloquear_viatura = isset($_POST['bloquear_viatura']) ? 1 : 0;

  if ($titulo === '') {
    $erros[] = "O título é obrigatório.";
  }

  if ($descricao === '') {
    $erros[] = "A descrição detalhada é obrigatória.";
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

  // Tratamento de foto
  $foto_db = null;
  if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['foto']['tmp_name'];
    $fileName = $_FILES['foto']['name'];
    $fileSize = $_FILES['foto']['size'];
    $fileType = $_FILES['foto']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    if (in_array($fileExtension, $allowedExtensions)) {
      if ($fileSize <= 5242880) { // 5MB max
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        
        $uploadFileDir = __DIR__ . '/../img/uploads/ocorrencias/';
        if (!is_dir($uploadFileDir)) {
          mkdir($uploadFileDir, 0755, true);
        }

        $dest_path = $uploadFileDir . $newFileName;
        if (move_uploaded_file($fileTmpPath, $dest_path)) {
          $foto_db = 'img/uploads/ocorrencias/' . $newFileName;
        } else {
          $erros[] = "Erro ao mover a foto para a pasta de uploads.";
        }
      } else {
        $erros[] = "A foto excede o limite de tamanho de 5MB.";
      }
    } else {
      $erros[] = "Formato de arquivo inválido. Apenas JPG, JPEG, PNG e GIF são permitidos.";
    }
  }

  if (!$erros) {
    $codigo = gerar_codigo_ocorrencia();
    $lat_val = $latitude !== '' ? (float)$latitude : null;
    $lng_val = $longitude !== '' ? (float)$longitude : null;
    $estado = 'aberta';

    mysqli_begin_transaction($ligacao);

    try {
      $stmt = mysqli_prepare($ligacao,
        "INSERT INTO ocorrencias
          (
            codigo,
            servico_id,
            viatura_id,
            motorista_id,
            criado_por_usuario_id,
            tipo,
            gravidade,
            titulo,
            descricao,
            latitude,
            longitude,
            foto,
            estado
          )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
      );

      mysqli_stmt_bind_param(
        $stmt,
        "siiiissssddss",
        $codigo,
        $servico_id,
        $viatura_id,
        $motorista_id,
        $usuario_id,
        $tipo,
        $gravidade,
        $titulo,
        $descricao,
        $lat_val,
        $lng_val,
        $foto_db,
        $estado
      );

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($ligacao));
      }

      mysqli_stmt_close($stmt);

      // Se o motorista marcou a viatura como incapacitada (bloquear viatura)
      if ($bloquear_viatura) {
        $obs = "Bloqueio automático por relato de ocorrência crítica (" . $codigo . ") pelo motorista.";
        $stmt = mysqli_prepare($ligacao,
          "UPDATE viaturas
           SET estado = 'Em Manutenção',
               observacoes = CONCAT(COALESCE(observacoes, ''), '\n', ?)
           WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, "si", $obs, $viatura_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
      }

      mysqli_commit($ligacao);

      header("Location: ocorrencias.php?msg=criada");
      exit;

    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = "Erro ao guardar a ocorrência: " . $e->getMessage();
    }
  }
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
  #ocorrenciaMap {
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
  <a class="back-link" href="ocorrencias.php">← Voltar às minhas ocorrências</a>

  <div>
    <h1 class="page-title">Reportar Ocorrência</h1>
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
        <label class="form-label form-label-soft">Título Curto do Problema *</label>
        <input
          name="titulo"
          class="form-control form-control-lg"
          placeholder="Ex: Pneu furado, Ruído no motor, Luz da bateria acesa"
          value="<?php echo h($titulo); ?>"
          required
        >
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Tipo *</label>
        <select name="tipo" class="form-select form-select-lg">
          <option value="avaria" <?php echo ($tipo === 'avaria') ? 'selected' : ''; ?>>Avaria Mecânica</option>
          <option value="acidente" <?php echo ($tipo === 'acidente') ? 'selected' : ''; ?>>Acidente/Colisão</option>
          <option value="dano" <?php echo ($tipo === 'dano') ? 'selected' : ''; ?>>Dano no Veículo</option>
          <option value="documentacao" <?php echo ($tipo === 'documentacao') ? 'selected' : ''; ?>>Problema com Documentos</option>
          <option value="seguranca" <?php echo ($tipo === 'seguranca') ? 'selected' : ''; ?>>Problema de Segurança</option>
          <option value="outro" <?php echo ($tipo === 'outro') ? 'selected' : ''; ?>>Outro</option>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Gravidade *</label>
        <select name="gravidade" class="form-select form-select-lg">
          <option value="baixa" <?php echo ($gravidade === 'baixa') ? 'selected' : ''; ?>>Baixa (Dá para seguir rota)</option>
          <option value="media" <?php echo ($gravidade === 'media') ? 'selected' : ''; ?>>Média (Problema incomodativo)</option>
          <option value="alta" <?php echo ($gravidade === 'alta') ? 'selected' : ''; ?>>Alta (Problema grave de circulação)</option>
          <option value="critica" <?php echo ($gravidade === 'critica') ? 'selected' : ''; ?>>Crítica (Viatura imobilizada)</option>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Descrição Detalhada do Incidente *</label>
        <textarea 
          name="descricao" 
          class="form-control" 
          rows="5" 
          placeholder="Descreva o que aconteceu, quando começou o problema, se ouve ruídos estranhos, etc."
          required
        ><?php echo h($descricao); ?></textarea>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Foto / Comprovativo do Problema</label>
        <input type="file" name="foto" class="form-control form-control-lg" accept="image/*">
        <div class="form-text">Opcional. Formato JPG, PNG ou GIF. Máx 5MB.</div>
      </div>

      <div class="col-12 col-md-6 d-flex align-items-center">
        <div class="form-check p-3 border rounded w-100" style="border-color: rgba(220, 38, 38, 0.25) !important; background-color: rgba(220, 38, 38, 0.02);">
          <input class="form-check-input ms-0 me-2" type="checkbox" name="bloquear_viatura" id="bloquear_viatura">
          <label class="form-check-label fw-bold text-danger" for="bloquear_viatura">
            Viatura incapacitada de circular? (Bloqueia viatura para manutenção)
          </label>
          <div class="form-text text-muted ms-4">
            Selecione apenas se a viatura não puder circular em segurança. Isso alterará o estado dela para "Em Manutenção" imediatamente.
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <div>
            <label class="form-label form-label-soft mb-1">Localização Exata do Incidente</label>
            <div class="map-hint">Clique no mapa para marcar o local exato da ocorrência ou use o GPS do dispositivo.</div>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnGeo">Usar minha localização</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClear">Limpar mapa</button>
          </div>
        </div>

        <div id="ocorrenciaMap"></div>

        <input type="hidden" name="latitude" id="latitude" value="<?php echo h($latitude); ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?php echo h($longitude); ?>">

        <div class="coords-box mt-3" id="coordsText">Nenhuma coordenada selecionada.</div>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2 pt-2">
        <a href="ocorrencias.php" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-danger" type="submit" style="background-color: #dc2626; border-color: #dc2626;">Reportar Ocorrência</button>
      </div>

    </form>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  const iLat = <?php echo ($latitude !== '' ? json_encode((float)$latitude) : 'null'); ?>;
  const iLng = <?php echo ($longitude !== '' ? json_encode((float)$longitude) : 'null'); ?>;

  const def = [37.2301, -8.0653];
  const map = L.map('ocorrenciaMap').setView(def, 9);

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
