<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();
if (is_gestor_ou_admin()) { header("Location: " . base_url() . "/abastecimentos/index.php"); exit; }

$active = 'operario_abastecimentos';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fmtData($d) {
  if (!$d) return '—';
  $dt = DateTime::createFromFormat('Y-m-d', substr($d,0,10));
  return $dt ? $dt->format('d/m/Y') : h($d);
}

$motorista_id = motorista_id_sessao();
$viatura_id   = viatura_id_sessao();

if (!$motorista_id) {
  echo '<div class="glass-card p-4 text-center text-muted">Conta não associada a um motorista. Contacte o administrador.</div>';
  mysqli_close($ligacao); require_once __DIR__ . "/../inc/footer.php"; exit;
}

// ── KPIs pessoais de abastecimentos ──────────────────────────────────────
$mesAtual = date('Y-m');
$stmt = mysqli_prepare($ligacao,
  "SELECT COALESCE(SUM(litros),0) AS litros, COALESCE(SUM(total),0) AS total,
          COALESCE(AVG(preco_litro),0) AS media
   FROM abastecimentos
   WHERE colaborador_id=? AND DATE_FORMAT(data_abastecimento,'%Y-%m')=?"
);
mysqli_stmt_bind_param($stmt, "is", $motorista_id, $mesAtual);
mysqli_stmt_execute($stmt);
$kpi = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

// ── Listagem ──────────────────────────────────────────────────────────────
$stmt = mysqli_prepare($ligacao,
  "SELECT a.id, a.posto, a.combustivel, a.litros, a.preco_litro, a.total,
          a.data_abastecimento, a.latitude, a.longitude,
          v.matricula, v.marca_modelo
   FROM abastecimentos a
   LEFT JOIN viaturas v ON v.id = a.viatura_id
   WHERE a.colaborador_id = ?
   ORDER BY a.data_abastecimento DESC"
);
mysqli_stmt_bind_param($stmt, "i", $motorista_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$lista = [];
if ($res) while ($row = mysqli_fetch_assoc($res)) $lista[] = $row;
mysqli_stmt_close($stmt);

// ── Mensagem de sucesso ───────────────────────────────────────────────────
$msg = $_GET['msg'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <h1 class="page-title">Os meus abastecimentos</h1>
    <div class="page-subtitle">Registos pessoais de combustível</div>
  </div>
  <?php if ($viatura_id): ?>
    <a href="abastecimento_create.php" class="btn btn-primary">
      <i class="bi bi-plus-lg me-1"></i> Novo abastecimento
    </a>
  <?php endif; ?>
</div>

<?php if ($msg === 'criado'): ?>
  <div class="alert alert-success mb-4">Abastecimento registado com sucesso.</div>
<?php elseif ($msg === 'editado'): ?>
  <div class="alert alert-success mb-4">Abastecimento atualizado com sucesso.</div>
<?php elseif ($msg === 'apagado'): ?>
  <div class="alert alert-success mb-4">Abastecimento removido.</div>
<?php endif; ?>

<!-- KPIs do mês -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-4">
    <div class="glass-card stat-gradient p-3">
      <div class="kpi-label">Total Abastecido</div>
      <div class="kpi-value"><?php echo number_format((float)$kpi['litros'], 1, ',', '.'); ?> L</div>
      <div class="kpi-sub">Este mês</div>
    </div>
  </div>
  <div class="col-12 col-sm-4">
    <div class="glass-card stat-gradient p-3">
      <div class="kpi-label">Custo Total</div>
      <div class="kpi-value">€ <?php echo number_format((float)$kpi['total'], 2, ',', '.'); ?></div>
      <div class="kpi-sub">Este mês</div>
    </div>
  </div>
  <div class="col-12 col-sm-4">
    <div class="glass-card stat-gradient p-3">
      <div class="kpi-label">Preço Médio/Litro</div>
      <div class="kpi-value"><?php echo $kpi['media'] > 0 ? '€ '.number_format((float)$kpi['media'], 3, ',', '.') : '—'; ?></div>
      <div class="kpi-sub">Este mês</div>
    </div>
  </div>
</div>

<!-- Lista -->
<div class="glass-card p-4">
  <?php if (count($lista) > 0): ?>
    <div class="vstack gap-3">
      <?php foreach ($lista as $a): ?>
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 py-2"
             style="border-bottom: 1px solid var(--color-border-tertiary);">
          <div class="d-flex gap-3 align-items-center">
            <div class="list-ico"><i class="bi bi-fuel-pump-fill"></i></div>
            <div>
              <div class="fw-semibold"><?php echo h($a['posto']); ?></div>
              <div class="small text-muted">
                <?php echo h($a['combustivel']); ?> •
                <?php echo number_format((float)$a['litros'], 1, ',', '.'); ?>L •
                € <?php echo number_format((float)$a['preco_litro'], 3, ',', '.'); ?>/L •
                <?php echo fmtData($a['data_abastecimento']); ?>
              </div>
              <?php if ($a['matricula']): ?>
                <div class="small text-muted"><?php echo h($a['matricula'].' — '.$a['marca_modelo']); ?></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="d-flex align-items-center gap-3">
            <span class="fw-bold">€ <?php echo number_format((float)$a['total'], 2, ',', '.'); ?></span>
            <div class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-primary" href="abastecimento_edit.php?id=<?php echo (int)$a['id']; ?>">Editar</a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="text-muted text-center py-4">Ainda não tens abastecimentos registados.</div>
  <?php endif; ?>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
