<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

if (is_gestor_ou_admin()) {
  header("Location: " . base_url() . "/abastecimentos/index.php");
  exit;
}

$active = 'operario_abastecimentos';

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

function badgeEstadoAbastecimento($a) {
  if (!empty($a['aprovado_por_usuario_id'])) {
    return '<span class="badge-pill badge-success-soft"><i class="bi bi-check-circle-fill me-1"></i>Aprovado</span>';
  }

  $estado = (string)($a['estado'] ?? 'registado');
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

/* KPIs pessoais do mês: ignora anulados */
$mesAtual = date('Y-m');

$stmt = mysqli_prepare($ligacao,
  "SELECT
      COALESCE(SUM(litros), 0) AS litros,
      COALESCE(SUM(total), 0) AS total,
      COALESCE(AVG(preco_litro), 0) AS media
   FROM abastecimentos
   WHERE motorista_id = ?
     AND estado <> 'anulado'
     AND DATE_FORMAT(data_abastecimento, '%Y-%m') = ?"
);
mysqli_stmt_bind_param($stmt, "is", $motorista_id, $mesAtual);
mysqli_stmt_execute($stmt);
$kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

/* Listagem */
$stmt = mysqli_prepare($ligacao,
  "SELECT
      a.id,
      a.posto,
      a.combustivel,
      a.litros,
      a.preco_litro,
      a.total,
      a.km_atual,
      a.data_abastecimento,
      a.latitude,
      a.longitude,
      a.estado,
      a.aprovado_por_usuario_id,
      a.comprovativo,
      a.servico_id,
      v.matricula,
      v.marca_modelo,
      s.codigo AS servico_codigo
   FROM abastecimentos a
   LEFT JOIN viaturas v ON v.id = a.viatura_id
   LEFT JOIN servicos_operacionais s ON s.id = a.servico_id
   WHERE a.motorista_id = ?
   ORDER BY a.data_abastecimento DESC, a.id DESC"
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
    <h1 class="page-title">Os meus abastecimentos</h1>
    <div class="page-subtitle">Registos pessoais de combustível</div>
  </div>

  <div class="d-flex gap-2">
    <?php if ($viatura_id): ?>
      <a href="abastecimento_create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Novo abastecimento
      </a>
    <?php endif; ?>
  </div>
</div>



<?php if (!$atribuicao): ?>
  <div class="glass-card p-4 text-center text-muted mb-4">
    Não existe viatura atribuída neste momento.
  </div>
<?php elseif (!$servicoAberto): ?>
  <div class="glass-card p-3 mb-4" style="border-left:3px solid hsl(200,80%,40%);">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">
      <div>
        <strong>Sem jornada ativa de serviço.</strong>
        <div class="text-muted small">
          Pode registar o abastecimento diretamente, mas ele não será associado a uma jornada de serviço diária.
        </div>
      </div>
      <a href="servico.php" class="btn btn-outline-primary btn-sm">Iniciar jornada</a>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-12 col-sm-4">
    <div class="glass-card stat-gradient p-3">
      <div class="kpi-label">Total Abastecido</div>
      <div class="kpi-value"><?php echo number_format((float)$kpi['litros'], 1, ',', '.'); ?> L</div>
      <div class="kpi-sub">Este mês, sem anulados</div>
    </div>
  </div>

  <div class="col-12 col-sm-4">
    <div class="glass-card stat-gradient p-3">
      <div class="kpi-label">Custo Total</div>
      <div class="kpi-value">€ <?php echo number_format((float)$kpi['total'], 2, ',', '.'); ?></div>
      <div class="kpi-sub">Este mês, sem anulados</div>
    </div>
  </div>

  <div class="col-12 col-sm-4">
    <div class="glass-card stat-gradient p-3">
      <div class="kpi-label">Preço Médio/Litro</div>
      <div class="kpi-value">
        <?php echo ((float)$kpi['media'] > 0) ? '€ ' . number_format((float)$kpi['media'], 3, ',', '.') : '—'; ?>
      </div>
      <div class="kpi-sub">Este mês</div>
    </div>
  </div>
</div>

<div class="glass-card p-4">
  <?php if (count($lista) > 0): ?>
    <div class="vstack gap-3">
      <?php foreach ($lista as $a): ?>
        <div
          class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 py-2"
          style="border-bottom:1px solid var(--color-border-tertiary);"
        >
          <div class="d-flex gap-3 align-items-center">
            <div class="list-ico">
              <i class="bi bi-fuel-pump-fill"></i>
            </div>

            <div>
              <div class="fw-semibold">
                <?php echo h($a['posto'] ?: 'Posto não informado'); ?>
              </div>

              <div class="small text-muted">
                <?php echo h($a['combustivel']); ?> ·
                <?php echo number_format((float)$a['litros'], 1, ',', '.'); ?> L ·
                € <?php echo number_format((float)$a['preco_litro'], 3, ',', '.'); ?>/L ·
                <?php echo fmtData($a['data_abastecimento']); ?>
              </div>

              <div class="small text-muted">
                <?php if (!empty($a['matricula'])): ?>
                  <?php echo h($a['matricula'] . ' — ' . $a['marca_modelo']); ?>
                <?php endif; ?>

                <?php if (!empty($a['km_atual'])): ?>
                  · Km <?php echo number_format((int)$a['km_atual'], 0, ',', '.'); ?>
                <?php endif; ?>

                <?php if (!empty($a['servico_codigo'])): ?>
                  · Serviço <?php echo h($a['servico_codigo']); ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="d-flex align-items-center gap-3">
            <div class="text-end">
              <div class="fw-bold">
                € <?php echo number_format((float)$a['total'], 2, ',', '.'); ?>
              </div>
              <div class="mt-1">
                <?php echo badgeEstadoAbastecimento($a); ?>
              </div>
              <?php if (!empty($a['comprovativo'])): ?>
                <div class="mt-1">
                  <a class="btn btn-xs btn-outline-info py-0 px-2 text-decoration-none" style="font-size:10px;" href="<?php echo base_url() . '/' . h($a['comprovativo']); ?>" target="_blank">
                    <i class="bi bi-file-earmark-text me-1"></i> Recibo
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-muted text-center py-4">
      Ainda não tens abastecimentos registados.
    </div>
  <?php endif; ?>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>