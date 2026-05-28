<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'ocorrencias';

require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmtData($d) {
  if (!$d) return '—';
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $d);
  if (!$dt) $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
  return $dt ? $dt->format('d/m/Y H:i') : h($d);
}

function badgeEstadoOcorrencia($estado) {
  $estado = (string)$estado;

  return match ($estado) {
    'aberta' => '<span class="badge-pill badge-info-soft">Aberta</span>',
    'em_analise' => '<span class="badge-pill badge-warning-soft">Em análise</span>',
    'convertida_manutencao' => '<span class="badge-pill badge-danger-soft">Convertida em Manutenção</span>',
    'resolvida' => '<span class="badge-pill badge-success-soft">Resolvida</span>',
    'rejeitada' => '<span class="badge-pill badge-danger-soft">Rejeitada</span>',
    default => '<span class="badge-pill badge-info-soft">' . h($estado) . '</span>',
  };
}

function badgeGravidadeOcorrencia($gravidade) {
  $gravidade = (string)$gravidade;

  return match ($gravidade) {
    'baixa' => '<span class="badge-pill badge-info-soft">Baixa</span>',
    'media' => '<span class="badge-pill badge-warning-soft">Média</span>',
    'alta' => '<span class="badge-pill badge-danger-soft" style="background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">Alta</span>',
    'critica' => '<span class="badge-pill badge-danger-soft" style="font-weight: 700; background-color: rgba(220, 38, 38, 0.15); color: #dc2626; border: 1px dashed #dc2626;">Crítica</span>',
    default => '<span class="badge-pill badge-info-soft">' . h($gravidade) . '</span>',
  };
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  header("Location: index.php");
  exit;
}

pode_ver_ocorrencia($ligacao, $id);

// Carregar ocorrência
$stmt = mysqli_prepare($ligacao,
  "SELECT
      o.*,
      v.matricula,
      v.marca_modelo,
      v.tipo AS viatura_tipo,
      v.quilometragem,
      v.estado AS viatura_estado,
      m.nome AS motorista_nome,
      m.telefone AS motorista_telefone,
      m.email AS motorista_email,
      s.codigo AS servico_codigo,
      u_criador.nome AS criador_nome,
      u_avaliador.nome AS avaliador_nome
   FROM ocorrencias o
   LEFT JOIN viaturas v ON v.id = o.viatura_id
   LEFT JOIN motoristas m ON m.id = o.motorista_id
   LEFT JOIN servicos_operacionais s ON s.id = o.servico_id
   LEFT JOIN usuarios u_criador ON u_criador.id = o.criado_por_usuario_id
   LEFT JOIN usuarios u_avaliador ON u_avaliador.id = o.avaliado_por_usuario_id
   WHERE o.id = ?
   LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$oc = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$oc) {
  echo '<div class="glass-card p-4 text-center text-muted">Ocorrência não encontrada.</div>';
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$msg = '';
$erros = [];

$usuario_gestor_id = usuario_id_sessao();

// Ação 1: Atualizar Estado e Resposta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar_triagem') {
  $novo_estado = trim($_POST['estado'] ?? '');
  $observacao = trim($_POST['observacao_gestor'] ?? '');

  $estados_validos = ['aberta', 'em_analise', 'resolvida', 'rejeitada'];
  if (!in_array($novo_estado, $estados_validos, true)) {
    $erros[] = "Estado selecionado é inválido.";
  }

  if (!$erros) {
    $stmt = mysqli_prepare($ligacao,
      "UPDATE ocorrencias
       SET estado = ?,
           observacao_gestor = ?,
           avaliado_por_usuario_id = ?,
           avaliado_em = NOW()
       WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, "ssii", $novo_estado, $observacao, $usuario_gestor_id, $id);
    
    if (mysqli_stmt_execute($stmt)) {
      mysqli_stmt_close($stmt);
      header("Location: show.php?id=$id&msg=triagem_salva");
      exit;
    } else {
      $erros[] = "Erro ao atualizar triagem: " . mysqli_error($ligacao);
    }
  }
}

// Ação 2: Converter em Manutenção
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'converter_manutencao') {
  $tipo_manutencao = trim($_POST['tipo_manutencao'] ?? 'Corretiva');
  $obs_gestor = trim($_POST['observacao_gestor'] ?? '');
  $descricao_manutencao = trim($_POST['descricao_manutencao'] ?? '');
  $oficina = trim($_POST['oficina'] ?? '');
  $custo = trim($_POST['custo'] ?? '0');

  if ($oc['estado'] === 'convertida_manutencao' || !empty($oc['manutencao_id'])) {
    $erros[] = "Esta ocorrência já foi convertida em manutenção anteriormente.";
  }

  if ($descricao_manutencao === '') {
    $descricao_manutencao = "Manutenção corretiva via Ocorrência " . $oc['codigo'] . " - " . $oc['titulo'];
  }

  $custo_val = is_numeric($custo) ? (float)$custo : 0.0;

  if (!$erros) {
    mysqli_begin_transaction($ligacao);

    try {
      // 1. Inserir na tabela manutencoes
      $stmt = mysqli_prepare($ligacao,
        "INSERT INTO manutencoes
          (
            viatura_id,
            tipo,
            descricao,
            data_inicio,
            custo,
            oficina,
            status,
            criado_por_usuario_id,
            observacoes
          )
         VALUES (?, ?, ?, CURDATE(), ?, ?, 'Em andamento', ?, ?)"
      );

      mysqli_stmt_bind_param(
        $stmt,
        "issdsis",
        $oc['viatura_id'],
        $tipo_manutencao,
        $descricao_manutencao,
        $custo_val,
        $oficina,
        $usuario_gestor_id,
        $obs_gestor
      );

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($ligacao));
      }

      $manutencao_id = mysqli_insert_id($ligacao);
      mysqli_stmt_close($stmt);

      // 2. Atualizar ocorrência
      $estado_manutencao = 'convertida_manutencao';
      $stmt = mysqli_prepare($ligacao,
        "UPDATE ocorrencias
         SET estado = ?,
             manutencao_id = ?,
             observacao_gestor = ?,
             avaliado_por_usuario_id = ?,
             avaliado_em = NOW()
         WHERE id = ?"
      );
      mysqli_stmt_bind_param($stmt, "sisii", $estado_manutencao, $manutencao_id, $obs_gestor, $usuario_gestor_id, $id);
      
      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($ligacao));
      }
      mysqli_stmt_close($stmt);

      // 3. Atualizar viatura estado usando recalcular_estado_viatura
      recalcular_estado_viatura($ligacao, $oc['viatura_id']);

      mysqli_commit($ligacao);
      header("Location: index.php?msg=manutencao");
      exit;

    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = "Erro ao converter para manutenção: " . $e->getMessage();
    }
  }
}

$msg = $_GET['msg'] ?? '';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
  #ocorrenciaShowMap {
    height: 300px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(15,23,42,.08);
  }
</style>

<div class="page-max-6xl space-y-6">

  <a class="back-link" href="index.php">← Voltar à listagem</a>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
        <h1 class="page-title mb-0"><?php echo h($oc['titulo']); ?></h1>
        <span class="badge-pill bg-light text-muted small" style="font-size: 13px;"><?php echo h($oc['codigo']); ?></span>
        <?php echo badgeGravidadeOcorrencia($oc['gravidade']); ?>
      </div>
      <div class="page-subtitle">
        Relatada em <?php echo fmtData($oc['criado_em']); ?> · Por <strong><?php echo h($oc['criador_nome'] ?: ($oc['motorista_nome'] ?: 'Operário')); ?></strong>
      </div>
    </div>

    <div>
      <?php echo badgeEstadoOcorrencia($oc['estado']); ?>
    </div>
  </div>

  <?php if ($msg === 'triagem_salva'): ?>
    <div class="alert alert-success">Triagem guardada e registada com sucesso!</div>
  <?php endif; ?>

  <?php if ($erros): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <!-- Detalhes do Relato e Viatura -->
    <div class="col-12 col-lg-8 space-y-6">
      
      <!-- Detalhes Ocorrência -->
      <div class="glass-card p-4">
        <h3 class="section-title mb-3">Relato do Incidente</h3>
        <p class="text-dark bg-light p-3 rounded border-start border-3 border-danger text-break" style="font-size: 1.05rem; line-height: 1.6;">
          <?php echo nl2br(h($oc['descricao'])); ?>
        </p>

        <div class="row g-3 mt-2">
          <div class="col-6 col-md-4">
            <div class="detail-label">Tipo</div>
            <div class="detail-value fw-bold text-uppercase" style="font-size: 13px;"><?php echo h($oc['tipo']); ?></div>
          </div>
          <div class="col-6 col-md-4">
            <div class="detail-label">Código do Serviço</div>
            <div class="detail-value"><?php echo h($oc['servico_codigo'] ?: 'Sem serviço associado'); ?></div>
          </div>
          <div class="col-6 col-md-4">
            <div class="detail-label">Latitude/Longitude</div>
            <div class="detail-value">
              <?php echo ($oc['latitude'] !== null) ? h(number_format((float)$oc['latitude'], 5) . ' , ' . number_format((float)$oc['longitude'], 5)) : 'Não geolocalizado'; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Evidência Fotográfica -->
      <?php if (!empty($oc['foto'])): ?>
        <div class="glass-card p-4">
          <h3 class="section-title mb-3">Foto Enviada pelo Operário</h3>
          <div class="text-center bg-light p-2 rounded border" style="overflow:hidden;">
            <img 
              src="<?php echo base_url() . '/' . h($oc['foto']); ?>" 
              alt="Foto da Ocorrência" 
              class="img-fluid rounded" 
              style="max-height: 480px; object-fit: contain;"
            >
          </div>
        </div>
      <?php endif; ?>

      <!-- Mapa de Geolocalização -->
      <?php if ($oc['latitude'] !== null && $oc['longitude'] !== null): ?>
        <div class="glass-card p-4">
          <h3 class="section-title mb-3">Localização do Relato</h3>
          <div id="ocorrenciaShowMap"></div>
        </div>
      <?php endif; ?>

    </div>

    <!-- Detalhes do Veículo, Condutor e Triagem -->
    <div class="col-12 col-lg-4 space-y-6">

      <!-- Painel do Veículo -->
      <div class="glass-card p-4">
        <h3 class="section-title mb-3">Viatura Associada</h3>
        <div class="vstack gap-2">
          <div class="d-flex justify-content-between border-bottom pb-2">
            <span class="text-muted small">Marca / Modelo:</span>
            <span class="fw-semibold text-dark"><?php echo h($oc['marca_modelo']); ?></span>
          </div>
          <div class="d-flex justify-content-between border-bottom pb-2">
            <span class="text-muted small">Matrícula:</span>
            <span class="fw-bold text-primary"><?php echo h($oc['matricula']); ?></span>
          </div>
          <div class="d-flex justify-content-between border-bottom pb-2">
            <span class="text-muted small">Tipo:</span>
            <span class="text-dark"><?php echo h($oc['viatura_tipo']); ?></span>
          </div>
          <div class="d-flex justify-content-between border-bottom pb-2">
            <span class="text-muted small">Quilometragem:</span>
            <span class="text-dark"><?php echo number_format((int)$oc['quilometragem'], 0, ',', '.'); ?> km</span>
          </div>
          <div class="d-flex justify-content-between pt-1">
            <span class="text-muted small">Estado Atual:</span>
            <span class="fw-semibold"><?php echo h($oc['viatura_estado']); ?></span>
          </div>
        </div>
        <div class="mt-3">
          <a class="btn btn-sm btn-outline-secondary w-100" href="<?php echo base_url(); ?>/viaturas/show.php?id=<?php echo (int)$oc['viatura_id']; ?>">
            Ver Ficha do Veículo
          </a>
        </div>
      </div>

      <!-- Painel do Motorista -->
      <div class="glass-card p-4">
        <h3 class="section-title mb-3">Motorista/Operário</h3>
        <div class="vstack gap-2">
          <div class="fw-bold text-dark fs-5"><?php echo h($oc['motorista_nome'] ?: 'Nome não disponível'); ?></div>
          <div class="small text-muted"><i class="bi bi-telephone-fill me-1"></i> <?php echo h($oc['motorista_telefone'] ?: 'Sem telefone'); ?></div>
          <div class="small text-muted"><i class="bi bi-envelope-fill me-1"></i> <?php echo h($oc['motorista_email'] ?: 'Sem e-mail'); ?></div>
        </div>
      </div>

      <!-- Triagem e Resposta do Gestor -->
      <div class="glass-card p-4" style="border-top: 3px solid #dc2626;">
        <h3 class="section-title mb-3">Triagem e Resolução</h3>

        <ul class="nav nav-tabs mb-3" id="actionTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active btn-sm small py-1" id="triagem-tab" data-bs-toggle="tab" data-bs-target="#triagem" type="button" role="tab" aria-controls="triagem" aria-selected="true">Alterar Estado</button>
          </li>
          <?php if ($oc['estado'] !== 'convertida_manutencao'): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link btn-sm small py-1 text-danger" id="manutencao-tab" data-bs-toggle="tab" data-bs-target="#manutencao" type="button" role="tab" aria-controls="manutencao" aria-selected="false">Manutenção 🛠️</button>
            </li>
          <?php endif; ?>
        </ul>

        <div class="tab-content" id="actionTabsContent">
          
          <!-- Formulário Triagem Padrão -->
          <div class="tab-pane fade show active" id="triagem" role="tabpanel" aria-labelledby="triagem-tab">
            <form method="post" class="space-y-3">
              <input type="hidden" name="acao" value="salvar_triagem">

              <div>
                <label class="form-label form-label-soft">Estado da Ocorrência</label>
                <select name="estado" class="form-select">
                  <option value="aberta" <?php echo ($oc['estado'] === 'aberta') ? 'selected' : ''; ?>>Aberta</option>
                  <option value="em_analise" <?php echo ($oc['estado'] === 'em_analise') ? 'selected' : ''; ?>>Em análise</option>
                  <option value="resolvida" <?php echo ($oc['estado'] === 'resolvida') ? 'selected' : ''; ?>>Resolvida (Problema resolvido)</option>
                  <option value="rejeitada" <?php echo ($oc['estado'] === 'rejeitada') ? 'selected' : ''; ?>>Rejeitada (Relato improcedente)</option>
                </select>
              </div>

              <div>
                <label class="form-label form-label-soft">Observações/Instruções para o Operário</label>
                <textarea name="observacao_gestor" class="form-control" rows="4" placeholder="Esta mensagem ficará visível para o operário no seu painel pessoal..."><?php echo h($oc['observacao_gestor']); ?></textarea>
              </div>

              <button type="submit" class="btn btn-primary w-100 mt-2">Salvar Triagem</button>
            </form>
          </div>

          <!-- Formulário Conversão Manutenção -->
          <?php if ($oc['estado'] !== 'convertida_manutencao'): ?>
            <div class="tab-pane fade" id="manutencao" role="tabpanel" aria-labelledby="manutencao-tab">
              <div class="alert alert-warning py-2 px-3 small mb-3">
                <i class="bi bi-info-circle-fill me-1"></i> Isso criará uma Manutenção Oficial em andamento e colocará a Viatura em manutenção.
              </div>

              <form method="post" class="space-y-3">
                <input type="hidden" name="acao" value="converter_manutencao">

                <div>
                  <label class="form-label form-label-soft">Tipo de Manutenção</label>
                  <select name="tipo_manutencao" class="form-select">
                    <option value="Corretiva" selected>Corretiva (Corrigir avaria)</option>
                    <option value="Preventiva">Preventiva (Revisão periódica)</option>
                    <option value="Inspeção">Inspeção Oficial</option>
                    <option value="Pneus">Substituição de Pneus</option>
                    <option value="Óleo">Troca de Óleo/Filtros</option>
                    <option value="Outro">Outro</option>
                  </select>
                </div>

                <div>
                  <label class="form-label form-label-soft">Título da Manutenção</label>
                  <input name="descricao_manutencao" class="form-control" value="Avaria: <?php echo h($oc['titulo']); ?>">
                </div>

                <div>
                  <label class="form-label form-label-soft">Oficina</label>
                  <input name="oficina" class="form-control" placeholder="Ex: Oficina Central, Galp, Oficina Externa">
                </div>

                <div class="row g-2">
                  <div class="col-12">
                    <label class="form-label form-label-soft">Custo Estimado (€)</label>
                    <input type="number" step="0.01" name="custo" class="form-control" value="0.00">
                  </div>
                </div>

                <div>
                  <label class="form-label form-label-soft">Notas Administrativas / Resposta</label>
                  <textarea name="observacao_gestor" class="form-control" rows="3" placeholder="Nota de resposta para o condutor e histórico da manutenção..."><?php echo h($oc['observacao_gestor'] ?: "Avaria identificada e enviada para manutenção oficial."); ?></textarea>
                </div>

                <button type="submit" class="btn btn-danger w-100 mt-2" style="background-color: #dc2626; border-color: #dc2626;">Converter em Manutenção 🛠️</button>
              </form>
            </div>
          <?php endif; ?>

        </div>
      </div>

    </div>
  </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<?php if ($oc['latitude'] !== null && $oc['longitude'] !== null): ?>
<script>
  const lat = <?php echo json_encode((float)$oc['latitude']); ?>;
  const lng = <?php echo json_encode((float)$oc['longitude']); ?>;

  const map = L.map('ocorrenciaShowMap').setView([lat, lng], 14);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  L.marker([lat, lng]).addTo(map)
    .bindPopup('<b>Ocorrência:</b> <?php echo h($oc['codigo']); ?><br><?php echo h($oc['titulo']); ?>')
    .openPopup();

  setTimeout(function () {
    map.invalidateSize();
  }, 200);
</script>
<?php endif; ?>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
