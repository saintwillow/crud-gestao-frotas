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
    'convertida_manutencao' => '<span class="badge-pill badge-danger-soft">Manutenção</span>',
    'resolvida' => '<span class="badge-pill badge-success-soft">Resolvida</span>',
    'rejeitada' => '<span class="badge-pill badge-danger-soft">Rejeitada</span>',
    default => '<span class="badge-pill badge-info-soft">' . h($estado) . '</span>',
  };
}

function badgeGravidadeOcorrencia($gravidade) {
  $gravidade = (string)$gravidade;

  return match ($gravidade) {
    'baixa' => '<span class="badge-pill badge-info-soft" style="font-size: 11px;">Baixa</span>',
    'media' => '<span class="badge-pill badge-warning-soft" style="font-size: 11px;">Média</span>',
    'alta' => '<span class="badge-pill badge-danger-soft" style="font-size: 11px; background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">Alta</span>',
    'critica' => '<span class="badge-pill badge-danger-soft" style="font-size: 11px; font-weight: 700; background-color: rgba(220, 38, 38, 0.15); color: #dc2626; border: 1px dashed #dc2626;">Crítica</span>',
    default => '<span class="badge-pill badge-info-soft" style="font-size: 11px;">' . h($gravidade) . '</span>',
  };
}

$motorista_id = motorista_id_sessao();

if (!$motorista_id) {
  echo '<div class="glass-card p-4 text-center text-muted">Conta não associada a um motorista. Contacte o administrador.</div>';
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

/* Verificar atribuição aberta */
$atribuicao = atribuicao_aberta_motorista($ligacao, $motorista_id);
$viatura_id = $atribuicao ? (int)$atribuicao['viatura_id'] : null;

/* Serviço aberto */
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

/* KPIs pessoais de ocorrências */
$stmt = mysqli_prepare($ligacao,
  "SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN estado IN ('aberta', 'em_analise') THEN 1 ELSE 0 END) AS pendentes,
      SUM(CASE WHEN estado = 'resolvida' THEN 1 ELSE 0 END) AS resolvidas
   FROM ocorrencias
   WHERE motorista_id = ?"
);
mysqli_stmt_bind_param($stmt, "i", $motorista_id);
mysqli_stmt_execute($stmt);
$kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

/* Listagem de Ocorrências */
$stmt = mysqli_prepare($ligacao,
  "SELECT
      o.id,
      o.codigo,
      o.tipo,
      o.gravidade,
      o.titulo,
      o.descricao,
      o.estado,
      o.criado_em,
      o.observacao_gestor,
      v.matricula,
      v.marca_modelo,
      s.codigo AS servico_codigo
   FROM ocorrencias o
   LEFT JOIN viaturas v ON v.id = o.viatura_id
   LEFT JOIN servicos_operacionais s ON s.id = o.servico_id
   WHERE o.motorista_id = ?
   ORDER BY o.criado_em DESC, o.id DESC"
);
mysqli_stmt_bind_param($stmt, "i", $motorista_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$lista = [];
if ($res) {
  while ($row = mysqli_fetch_assoc($res)) {
    $lista[] = $row;
  }
}
mysqli_stmt_close($stmt);

$msg = $_GET['msg'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h1 class="page-title">As minhas ocorrências</h1>
    <div class="page-subtitle">Acompanhamento de incidentes e avarias reportados</div>
  </div>

  <div class="d-flex gap-2">
    <?php if ($viatura_id && $servicoAberto): ?>
      <a href="ocorrencia_create.php" class="btn btn-primary" style="background-color: #dc2626; border-color: #dc2626;">
        <i class="bi bi-exclamation-triangle me-1"></i> Reportar ocorrência
      </a>
    <?php elseif ($viatura_id && !$servicoAberto): ?>
      <a href="servico.php" class="btn btn-outline-primary">
        Iniciar serviço
      </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($msg === 'criada'): ?>
  <div class="alert alert-success mb-4">Ocorrência reportada com sucesso. A equipa de gestão foi notificada.</div>
<?php endif; ?>

<?php if (!$atribuicao): ?>
  <div class="glass-card p-4 text-center text-muted mb-4">
    Não existe viatura atribuída neste momento.
  </div>
<?php elseif (!$servicoAberto): ?>
  <div class="glass-card p-3 mb-4" style="border-left:3px solid hsl(38,92%,50%);">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">
      <div>
        <strong>Serviço não iniciado.</strong>
        <div class="text-muted small">
          Para reportar qualquer ocorrência com a viatura, por favor inicie o serviço operacional primeiro.
        </div>
      </div>
      <a href="servico.php" class="btn btn-primary btn-sm">Iniciar serviço</a>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-12 col-sm-4">
    <div class="glass-card stat-gradient p-3">
      <div class="kpi-label">Total Reportado</div>
      <div class="kpi-value"><?php echo (int)($kpi['total'] ?? 0); ?></div>
      <div class="kpi-sub">Ocorrências registadas</div>
    </div>
  </div>

  <div class="col-12 col-sm-4">
    <div class="glass-card stat-gradient p-3">
      <div class="kpi-label">Pendentes</div>
      <div class="kpi-value"><?php echo (int)($kpi['pendentes'] ?? 0); ?></div>
      <div class="kpi-sub">Em análise ou abertas</div>
    </div>
  </div>

  <div class="col-12 col-sm-4">
    <div class="glass-card stat-gradient p-3">
      <div class="kpi-label">Resolvidas</div>
      <div class="kpi-value text-success"><?php echo (int)($kpi['resolvidas'] ?? 0); ?></div>
      <div class="kpi-sub">Ocorrências tratadas</div>
    </div>
  </div>
</div>

<div class="glass-card p-4">
  <?php if (count($lista) > 0): ?>
    <div class="vstack gap-3">
      <?php foreach ($lista as $o): ?>
        <div
          class="d-flex flex-column flex-md-row align-items-start justify-content-between gap-3 py-3"
          style="border-bottom:1px solid var(--color-border-tertiary);"
        >
          <div class="d-flex gap-3 align-items-start flex-grow-1 min-w-0">
            <div class="list-ico warn bg-danger-soft text-danger">
              <i class="bi bi-exclamation-triangle-fill"></i>
            </div>

            <div class="flex-grow-1 min-w-0">
              <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-bold text-dark fs-5"><?php echo h($o['titulo']); ?></span>
                <span class="badge-pill bg-light text-muted small" style="font-size: 11px;"><?php echo h($o['codigo']); ?></span>
                <?php echo badgeGravidadeOcorrencia($o['gravidade']); ?>
              </div>

              <div class="mt-1 text-muted text-break" style="font-size: 0.95rem;">
                <?php echo nl2br(h($o['descricao'])); ?>
              </div>

              <div class="small text-muted mt-2">
                <strong>Tipo:</strong> <?php echo h(ucfirst($o['tipo'])); ?> ·
                <strong>Reportado em:</strong> <?php echo fmtData($o['criado_em']); ?>
                <?php if (!empty($o['matricula'])): ?>
                  · <strong>Viatura:</strong> <?php echo h($o['matricula'] . ' — ' . $o['marca_modelo']); ?>
                <?php endif; ?>
                <?php if (!empty($o['servico_codigo'])): ?>
                  · <strong>Serviço:</strong> <?php echo h($o['servico_codigo']); ?>
                <?php endif; ?>
              </div>

              <?php if (!empty($o['observacao_gestor'])): ?>
                <div class="mt-2 p-2 border-start border-3 border-secondary bg-light rounded text-muted small">
                  <strong>Resposta do Gestor:</strong><br>
                  <?php echo nl2br(h($o['observacao_gestor'])); ?>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="d-flex align-items-md-center flex-column justify-content-end gap-2 text-md-end self-stretch align-self-start">
            <div>
              <?php echo badgeEstadoOcorrencia($o['estado']); ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-muted text-center py-4">
      <i class="bi bi-exclamation-circle fs-3 mb-2 d-block text-muted" style="opacity: 0.5;"></i>
      Ainda não tens ocorrências reportadas.
    </div>
  <?php endif; ?>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
