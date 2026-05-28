<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'abastecimento';

require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmtData($d) {
  if (!$d) return '—';
  $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
  return $dt ? $dt->format('d/m/Y') : h($d);
}

function badgeEstadoAbastecimento($estado) {
  $estado = (string)$estado;

  if ($estado === 'registado') {
    return '<span class="badge-pill badge-success-soft">Registado</span>';
  }

  if ($estado === 'em_analise') {
    return '<span class="badge-pill badge-warning-soft">Em análise</span>';
  }

  if ($estado === 'corrigido') {
    return '<span class="badge-pill badge-info-soft">Corrigido</span>';
  }

  if ($estado === 'anulado') {
    return '<span class="badge-pill badge-danger-soft">Anulado</span>';
  }

  return '<span class="badge-pill badge-info-soft">' . h($estado) . '</span>';
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  header("Location: index.php");
  exit;
}

pode_ver_abastecimento($ligacao, $id);

$usuario_id = usuario_id_sessao();
$erros = [];

/* Buscar abastecimento */
$stmt = mysqli_prepare($ligacao,
  "SELECT
      a.*,
      v.matricula,
      v.marca_modelo,
      v.combustivel AS combustivel_viatura,
      v.quilometragem AS viatura_quilometragem,
      m.nome AS motorista_nome,
      c.nome AS colaborador_nome,
      u.nome AS registado_por_nome,
      s.codigo AS servico_codigo,
      s.km_inicio AS servico_km_inicio,
      s.km_fim AS servico_km_fim
   FROM abastecimentos a
   LEFT JOIN viaturas v ON v.id = a.viatura_id
   LEFT JOIN motoristas m ON m.id = a.motorista_id
   LEFT JOIN colaboradores c ON c.id = a.colaborador_id
   LEFT JOIN usuarios u ON u.id = a.registado_por_usuario_id
   LEFT JOIN servicos_operacionais s ON s.id = a.servico_id
   WHERE a.id = ?
   LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$a = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$a) {
  echo '<div class="glass-card p-4 text-center text-muted">Abastecimento não encontrado.</div>';
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$posto              = (string)($a['posto'] ?? '');
$combustivel        = (string)($a['combustivel'] ?? '');
$litros             = (string)($a['litros'] ?? '');
$preco_litro        = (string)($a['preco_litro'] ?? '');
$km_atual           = $a['km_atual'] !== null ? (string)$a['km_atual'] : '';
$data_abastecimento = (string)($a['data_abastecimento'] ?? date('Y-m-d'));
$observacoes        = (string)($a['observacoes'] ?? '');
$latitude           = $a['latitude'] !== null ? (string)$a['latitude'] : '';
$longitude          = $a['longitude'] !== null ? (string)$a['longitude'] : '';
$estado             = (string)($a['estado'] ?? 'registado');
$motivo_gestao      = (string)($a['motivo_rejeicao'] ?? '');
$disabled           = ($a['aprovado_por_usuario_id'] !== null) ? 'disabled' : '';

/* Ações rápidas de estado */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_estado'])) {
  if ($a['aprovado_por_usuario_id'] !== null) {
    $erros[] = "Este abastecimento já se encontra aprovado e bloqueado.";
  } else {
    $acaoEstado = $_POST['acao_estado'];
    $motivo = trim($_POST['motivo_gestao'] ?? '');

    if ($acaoEstado === 'aprovar') {
      $stmt = mysqli_prepare($ligacao,
        "UPDATE abastecimentos
         SET
          aprovado_por_usuario_id = ?,
          aprovado_em = NOW()
         WHERE id = ?"
      );
      mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $id);

      if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        header("Location: index.php?msg=editado");
        exit;
      } else {
        $erros[] = "Erro ao aprovar abastecimento: " . mysqli_error($ligacao);
        mysqli_stmt_close($stmt);
      }
    } else {
      $novoEstado = null;
      $redirectMsg = '';

      if ($acaoEstado === 'marcar_analise') {
        $novoEstado = 'em_analise';
        $redirectMsg = 'analisado';
      } elseif ($acaoEstado === 'anular') {
        $novoEstado = 'anulado';
        $redirectMsg = 'anulado';
      } elseif ($acaoEstado === 'restaurar') {
        $novoEstado = 'registado';
        $redirectMsg = 'editado';
      }

      if ($novoEstado === null) {
        $erros[] = "Ação inválida.";
      } else {
        $motivo_val = $motivo !== '' ? $motivo : null;

        $stmt = mysqli_prepare($ligacao,
          "UPDATE abastecimentos
           SET
            estado = ?,
            motivo_rejeicao = ?,
            aprovado_por_usuario_id = NULL,
            aprovado_em = NULL
           WHERE id = ?"
        );

        mysqli_stmt_bind_param(
          $stmt,
          "ssi",
          $novoEstado,
          $motivo_val,
          $id
        );

        if (mysqli_stmt_execute($stmt)) {
          mysqli_stmt_close($stmt);
          header("Location: index.php?msg=" . urlencode($redirectMsg));
          exit;
        } else {
          $erros[] = "Erro ao alterar estado: " . mysqli_error($ligacao);
          mysqli_stmt_close($stmt);
        }
      }
    }
  }
}

/* Correção completa dos dados */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
  if ($a['aprovado_por_usuario_id'] !== null) {
    $erros[] = "Este abastecimento já se encontra aprovado e bloqueado.";
  } else {
    $posto              = trim($_POST['posto'] ?? '');
    $combustivel        = trim($_POST['combustivel'] ?? '');
    $litros             = trim($_POST['litros'] ?? '');
    $preco_litro        = trim($_POST['preco_litro'] ?? '');
    $km_atual           = trim($_POST['km_atual'] ?? '');
    $data_abastecimento = trim($_POST['data_abastecimento'] ?? '');
    $observacoes        = trim($_POST['observacoes'] ?? '');
    $latitude           = trim($_POST['latitude'] ?? '');
    $longitude          = trim($_POST['longitude'] ?? '');
    $estado             = trim($_POST['estado'] ?? 'corrigido');
    $motivo_gestao      = trim($_POST['motivo_gestao'] ?? '');

    if (!in_array($estado, ['registado','em_analise','corrigido','anulado'], true)) {
      $erros[] = "Estado inválido.";
    }

    if ($litros === '' || !is_numeric($litros) || (float)$litros <= 0) {
      $erros[] = "Informe os litros com valor maior que 0.";
    }

    if ($preco_litro === '' || !is_numeric($preco_litro) || (float)$preco_litro <= 0) {
      $erros[] = "Informe o preço por litro com valor maior que 0.";
    }

    $km_atual_val = null;

    if ($km_atual !== '') {
      if (!ctype_digit($km_atual)) {
        $erros[] = "A quilometragem deve ser um número válido.";
      } else {
        $km_atual_val = (int)$km_atual;
        $viatura_km_atual = (int)$a['viatura_quilometragem'];
        if ($km_atual_val < $viatura_km_atual) {
          $erros[] = "A quilometragem inserida ({$km_atual_val} km) não pode ser menor que a quilometragem atual da viatura ({$viatura_km_atual} km).";
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
    $comprovativo_db = $a['comprovativo'];
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
      $obs_val = $observacoes !== '' ? $observacoes : null;
      $lat_val = $latitude !== '' ? (float)$latitude : null;
      $lng_val = $longitude !== '' ? (float)$longitude : null;
      $motivo_val = $motivo_gestao !== '' ? $motivo_gestao : null;

      mysqli_begin_transaction($ligacao);

      try {
        $stmt = mysqli_prepare($ligacao,
          "UPDATE abastecimentos
           SET
            posto = ?,
            combustivel = ?,
            litros = ?,
            preco_litro = ?,
            total = ?,
            km_atual = ?,
            data_abastecimento = ?,
            observacoes = ?,
            comprovativo = ?,
            latitude = ?,
            longitude = ?,
            estado = ?,
            motivo_rejeicao = ?
           WHERE id = ?"
        );

        mysqli_stmt_bind_param(
          $stmt,
          "ssdddisssddssi",
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
          $estado,
          $motivo_val,
          $id
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

          mysqli_stmt_bind_param($stmt, "ii", $km_atual_val, $a['viatura_id']);
          mysqli_stmt_execute($stmt);
          mysqli_stmt_close($stmt);
        }

        mysqli_commit($ligacao);

        header("Location: index.php?msg=corrigido");
        exit;

      } catch (Exception $e) {
        mysqli_rollback($ligacao);
        $erros[] = "Erro ao atualizar: " . $e->getMessage();
      }
    }
  }
}

$temAlertas = false;
$alertas = [];

if (trim((string)$posto) === '') {
  $temAlertas = true;
  $alertas[] = 'Posto não informado';
}

if ($km_atual === '') {
  $temAlertas = true;
  $alertas[] = 'Km não informado';
}

if (empty($a['servico_id'])) {
  $temAlertas = true;
  $alertas[] = 'Sem serviço associado';
}

$motoristaNome = $a['motorista_nome'] ?: ($a['colaborador_nome'] ?: 'Não associado');
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
  #abastecimentoMap {
    height: 320px;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(15,23,42,.08);
  }

  .coords-box {
    background: #f8fafc;
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 12px;
    padding: 10px 12px;
    font-size: 13px;
    color: #334155;
  }

  .map-hint {
    font-size: 13px;
    color:#64748b;
  }
</style>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="index.php">← Voltar aos abastecimentos</a>

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
    <div>
      <h1 class="page-title">Gerir Abastecimento</h1>
      <div class="page-subtitle">
        <?php echo h(($a['matricula'] ?? '—') . ' — ' . ($a['marca_modelo'] ?? '')); ?>
      </div>
    </div>

    <div>
      <?php echo badgeEstadoAbastecimento($estado); ?>
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

  <?php if ($a['aprovado_por_usuario_id'] !== null): ?>
    <div class="alert alert-success d-flex align-items-center py-3 px-4 mb-4 border border-success border-opacity-25" style="background-color: rgba(34, 197, 94, 0.1); color: #16a34a; border-radius: 16px;">
      <i class="bi bi-patch-check-fill fs-4 me-3 animate-pulse"></i>
      <div>
        <strong>Abastecimento Validado e Aprovado</strong>
        <br>
        Este registo foi verificado e aprovado pelo gestor em <strong><?php echo date('d/m/Y H:i', strtotime($a['aprovado_em'])); ?></strong>. Para garantir a integridade dos dados operacionais e financeiros, o registo encontra-se <strong>trancado permanentemente</strong>.
      </div>
    </div>
  <?php endif; ?>

  <?php if ($temAlertas): ?>
    <div class="glass-card p-3" style="border-left:3px solid hsl(38,92%,50%);">
      <div class="fw-semibold mb-2">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        Pontos de atenção
      </div>

      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($alertas as $al): ?>
          <span class="badge-pill badge-warning-soft"><?php echo h($al); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <h3 class="section-title mb-3">Resumo</h3>

    <div class="row g-3">
      <div class="col-12 col-md-4">
        <div class="detail-label">Motorista</div>
        <div class="detail-value"><?php echo h($motoristaNome); ?></div>
      </div>

      <div class="col-12 col-md-4">
        <div class="detail-label">Registado por</div>
        <div class="detail-value"><?php echo h($a['registado_por_nome'] ?: '—'); ?></div>
      </div>

      <div class="col-12 col-md-4">
        <div class="detail-label">Serviço</div>
        <div class="detail-value"><?php echo h($a['servico_codigo'] ?: '—'); ?></div>
      </div>

      <div class="col-12 col-md-4">
        <div class="detail-label">Criado em</div>
        <div class="detail-value"><?php echo h($a['criado_em'] ?? '—'); ?></div>
      </div>

      <div class="col-12 col-md-4">
        <div class="detail-label">Km inicial serviço</div>
        <div class="detail-value">
          <?php echo !empty($a['servico_km_inicio']) ? number_format((int)$a['servico_km_inicio'], 0, ',', '.') . ' km' : '—'; ?>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="detail-label">Km final serviço</div>
        <div class="detail-value">
          <?php echo !empty($a['servico_km_fim']) ? number_format((int)$a['servico_km_fim'], 0, ',', '.') . ' km' : '—'; ?>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="detail-label">Comprovativo</div>
        <div class="detail-value">
          <?php if (!empty($a['comprovativo'])): ?>
            <a href="<?php echo base_url() . '/' . h($a['comprovativo']); ?>" target="_blank" class="btn btn-sm btn-outline-info">
              <i class="bi bi-file-earmark-text me-1"></i>Ver Comprovativo
            </a>
          <?php else: ?>
            <span class="text-muted small">Sem comprovativo</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php if ($a['aprovado_por_usuario_id'] === null): ?>
  <div class="glass-card p-4">
    <h3 class="section-title mb-3">Ações rápidas do gestor</h3>

    <form method="post" class="row g-3">
      <div class="col-12">
        <label class="form-label form-label-soft">Nota interna / motivo</label>
        <textarea name="motivo_gestao" class="form-control" rows="3" <?php echo $disabled; ?>><?php echo h($motivo_gestao); ?></textarea>
      </div>

      <div class="col-12 d-flex flex-wrap gap-2">
        <?php if ($a['aprovado_por_usuario_id'] === null): ?>
          <button
            class="btn btn-success"
            type="submit"
            name="acao_estado"
            value="aprovar"
          >
            <i class="bi bi-check-circle-fill me-1"></i> Aprovar Abastecimento
          </button>
        <?php endif; ?>

        <button
          class="btn btn-outline-warning"
          type="submit"
          name="acao_estado"
          value="marcar_analise"
          <?php echo $disabled; ?>
        >
          Marcar em análise
        </button>

        <button
          class="btn btn-outline-danger"
          type="submit"
          name="acao_estado"
          value="anular"
          <?php echo $disabled; ?>
          onclick="return confirm('Tem a certeza que deseja anular este abastecimento?');"
        >
          Anular abastecimento
        </button>

        <button
          class="btn btn-outline-success"
          type="submit"
          name="acao_estado"
          value="restaurar"
          <?php echo $disabled; ?>
        >
          Restaurar como registado
        </button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <h3 class="section-title mb-3">Corrigir dados do abastecimento</h3>

    <form method="post" enctype="multipart/form-data" class="row g-3">
      <input type="hidden" name="acao" value="salvar">

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Posto</label>
        <input
          name="posto"
          class="form-control form-control-lg"
          value="<?php echo h($posto); ?>"
          <?php echo $disabled; ?>
        >
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Combustível</label>
        <select name="combustivel" class="form-select form-select-lg" <?php echo $disabled; ?>>
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
          <?php echo $disabled; ?>
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
          <?php echo $disabled; ?>
        >
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Km atual</label>
        <input
          type="number"
          step="1"
          name="km_atual"
          class="form-control form-control-lg"
          value="<?php echo h($km_atual); ?>"
          <?php echo $disabled; ?>
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
          <?php echo $disabled; ?>
        >
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Estado</label>
        <select name="estado" class="form-select form-select-lg" <?php echo $disabled; ?>>
          <option value="registado" <?php echo $estado === 'registado' ? 'selected' : ''; ?>>Registado</option>
          <option value="em_analise" <?php echo $estado === 'em_analise' ? 'selected' : ''; ?>>Em análise</option>
          <option value="corrigido" <?php echo $estado === 'corrigido' ? 'selected' : ''; ?>>Corrigido</option>
          <option value="anulado" <?php echo $estado === 'anulado' ? 'selected' : ''; ?>>Anulado</option>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Nota interna / motivo</label>
        <input
          name="motivo_gestao"
          class="form-control form-control-lg"
          value="<?php echo h($motivo_gestao); ?>"
          <?php echo $disabled; ?>
        >
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Comprovativo (Imagem ou PDF)</label>
        <input
          type="file"
          name="comprovativo"
          class="form-control form-control-lg"
          accept=".jpg,.jpeg,.png,.pdf"
          <?php echo $disabled; ?>
        >
        <?php if (!empty($a['comprovativo'])): ?>
          <div class="mt-2 text-truncate">
            <a href="<?php echo base_url() . '/' . h($a['comprovativo']); ?>" target="_blank" class="btn btn-sm btn-outline-info py-1 px-2 small">
              <i class="bi bi-file-earmark-text me-1"></i>Ver Ficheiro Atual
            </a>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
          <div>
            <label class="form-label form-label-soft mb-1">Localização</label>
            <div class="map-hint">Clique no mapa ou use a geolocalização.</div>
          </div>

          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnGeo" <?php echo $disabled; ?>>Usar localização</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClear" <?php echo $disabled; ?>>Limpar</button>
          </div>
        </div>

        <div id="abastecimentoMap"></div>

        <input type="hidden" name="latitude" id="latitude" value="<?php echo h($latitude); ?>">
        <input type="hidden" name="longitude" id="longitude" value="<?php echo h($longitude); ?>">

        <div class="coords-box mt-3" id="coordsText">Nenhuma coordenada selecionada.</div>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Observações</label>
        <textarea name="observacoes" class="form-control" rows="3" <?php echo $disabled; ?>><?php echo h($observacoes); ?></textarea>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2 pt-2">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary" type="submit" <?php echo $disabled; ?>>Salvar correção</button>
      </div>

    </form>
  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  const iLat = <?php echo ($latitude !== '' ? json_encode((float)$latitude) : 'null'); ?>;
  const iLng = <?php echo ($longitude !== '' ? json_encode((float)$longitude) : 'null'); ?>;
  const isDisabled = <?php echo ($a['aprovado_por_usuario_id'] !== null ? 'true' : 'false'); ?>;

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
      marker = L.marker([lat, lng], { draggable: !isDisabled }).addTo(map);

      if (!isDisabled) {
        marker.on('dragend', function (e) {
          const p = e.target.getLatLng();
          latI.value = p.lat.toFixed(6);
          lngI.value = p.lng.toFixed(6);
          showCoords(p.lat, p.lng);
        });
      }
    } else {
      marker.setLatLng([lat, lng]);
    }

    latI.value = Number(lat).toFixed(6);
    lngI.value = Number(lng).toFixed(6);
    showCoords(lat, lng);
    map.setView([lat, lng], z);
  }

  if (!isDisabled) {
    map.on('click', function (e) {
      setPoint(e.latlng.lat, e.latlng.lng);
    });
  }

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