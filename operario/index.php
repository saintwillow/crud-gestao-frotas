<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

// Gestor e admin não têm nada a fazer aqui — vão para o dashboard geral
if (is_gestor_ou_admin()) {
  header("Location: " . base_url() . "/index.php");
  exit;
}

$active = 'operario_painel';
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

// ── Sem associação a motorista ──────────────────────────────────────────
if (!$motorista_id) { ?>
  <div class="mb-4">
    <h1 class="page-title">O meu painel</h1>
    <div class="page-subtitle">Área pessoal do operário</div>
  </div>
  <div class="glass-card p-4 text-center" style="max-width:520px; margin:auto;">
    <i class="bi bi-person-x-fill fs-1 mb-3" style="color:hsl(38,92%,50%);"></i>
    <h2 class="h5 fw-bold mb-2">Conta ainda não configurada</h2>
    <p class="text-muted mb-0">
      A sua conta de utilizador ainda não está associada a um motorista.<br>
      Contacte o administrador para completar a configuração.
    </p>
  </div>
<?php
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

// ── Dados do motorista ──────────────────────────────────────────────────
$stmt = mysqli_prepare($ligacao,
  "SELECT m.*, v.matricula, v.marca_modelo, v.tipo, v.combustivel,
          v.quilometragem, v.estado AS viatura_estado
   FROM motoristas m
   LEFT JOIN viaturas v ON v.id = m.viatura_id
   WHERE m.id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $motorista_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$mot = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$mot) {
  echo '<div class="glass-card p-4 text-center text-muted">Motorista não encontrado. Contacte o administrador.</div>';
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

// ── Últimos 5 abastecimentos do operário ─────────────────────────────────
$ultAbast = [];
if ($motorista_id) {
  $stmt = mysqli_prepare($ligacao,
    "SELECT a.id, a.posto, a.combustivel, a.litros, a.total, a.data_abastecimento,
            v.matricula, v.marca_modelo
     FROM abastecimentos a
     LEFT JOIN viaturas v ON v.id = a.viatura_id
     WHERE a.colaborador_id = ?
     ORDER BY a.data_abastecimento DESC LIMIT 5"
  );
  mysqli_stmt_bind_param($stmt, "i", $motorista_id);
  mysqli_stmt_execute($stmt);
  $r = mysqli_stmt_get_result($stmt);
  if ($r) while ($row = mysqli_fetch_assoc($r)) $ultAbast[] = $row;
  mysqli_stmt_close($stmt);
}

// ── Manutenções da viatura do operário ───────────────────────────────────
$ultMan = [];
if ($viatura_id) {
  $stmt = mysqli_prepare($ligacao,
    "SELECT id, descricao, tipo, data_inicio, data_fim, custo, status
     FROM manutencoes
     WHERE viatura_id = ?
     ORDER BY data_inicio DESC LIMIT 5"
  );
  mysqli_stmt_bind_param($stmt, "i", $viatura_id);
  mysqli_stmt_execute($stmt);
  $r = mysqli_stmt_get_result($stmt);
  if ($r) while ($row = mysqli_fetch_assoc($r)) $ultMan[] = $row;
  mysqli_stmt_close($stmt);
}

// ── Alerta de carta ───────────────────────────────────────────────────────
$diasCarta = null;
if (!empty($mot['carta_validade'])) {
  $diasCarta = (int)(new DateTime())->diff(new DateTime($mot['carta_validade']))->days
               * ((new DateTime($mot['carta_validade'])) >= (new DateTime()) ? 1 : -1);
}
?>

<div class="mb-4">
  <h1 class="page-title">Olá, <?php echo h(explode(' ', $mot['nome'])[0]); ?>!</h1>
  <div class="page-subtitle">O seu painel pessoal — AquaFleet</div>
</div>

<?php if ($diasCarta !== null && $diasCarta <= 60): ?>
  <div class="glass-card p-3 mb-4" style="border-left:3px solid hsl(38,92%,50%);">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-triangle-fill" style="color:hsl(38,92%,50%);"></i>
      <?php if ($diasCarta <= 0): ?>
        <span><strong>A sua carta de condução expirou.</strong> Contacte o gestor de frotas.</span>
      <?php else: ?>
        <span>A sua carta de condução vence em <strong><?php echo $diasCarta; ?> dias</strong> (<?php echo fmtData($mot['carta_validade']); ?>).</span>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- KPIs pessoais -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Carta de Condução</div>
          <div class="kpi-value" style="font-size:1.4rem;"><?php echo h($mot['carta_categoria'] ?: '—'); ?></div>
          <div class="kpi-sub">Válida até <?php echo fmtData($mot['carta_validade']); ?></div>
        </div>
        <div class="kpi-ico"><i class="bi bi-card-text fs-5"></i></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Total de Viagens</div>
          <div class="kpi-value"><?php echo number_format((int)$mot['viagens'], 0, ',', '.'); ?></div>
          <div class="kpi-sub">Desde <?php echo fmtData($mot['desde']); ?></div>
        </div>
        <div class="kpi-ico"><i class="bi bi-signpost-2-fill fs-5"></i></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Viatura Atribuída</div>
          <div class="kpi-value" style="font-size:1.1rem;"><?php echo $mot['matricula'] ? h($mot['matricula']) : '—'; ?></div>
          <div class="kpi-sub"><?php echo $mot['marca_modelo'] ? h($mot['marca_modelo']) : 'Sem viatura'; ?></div>
        </div>
        <div class="kpi-ico"><i class="bi bi-car-front-fill fs-5"></i></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Quilometragem</div>
          <div class="kpi-value"><?php echo $mot['quilometragem'] ? number_format((int)$mot['quilometragem'], 0, ',', '.') : '—'; ?></div>
          <div class="kpi-sub">km registados</div>
        </div>
        <div class="kpi-ico"><i class="bi bi-speedometer2 fs-5"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- Viatura + dados pessoais -->
<?php if ($viatura_id): ?>
<div class="row g-3 mb-4">
  <div class="col-12 col-lg-6">
    <div class="glass-card p-4 h-100">
      <h3 class="section-title mb-3">A minha viatura</h3>
      <div class="detail-grid">
        <div class="detail-item">
          <div class="detail-label">Matrícula</div>
          <div class="detail-value fw-bold"><?php echo h($mot['matricula'] ?? '—'); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Marca / Modelo</div>
          <div class="detail-value"><?php echo h($mot['marca_modelo'] ?? '—'); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Tipo</div>
          <div class="detail-value"><?php echo h($mot['tipo'] ?? '—'); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Combustível</div>
          <div class="detail-value"><?php echo h($mot['combustivel'] ?? '—'); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Quilometragem</div>
          <div class="detail-value"><?php echo number_format((int)($mot['quilometragem'] ?? 0), 0, ',', '.'); ?> km</div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Estado</div>
          <div class="detail-value"><?php echo h($mot['viatura_estado'] ?? '—'); ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="glass-card p-4 h-100">
      <h3 class="section-title mb-3">Os meus dados</h3>
      <div class="detail-grid">
        <div class="detail-item">
          <div class="detail-label">Nome</div>
          <div class="detail-value fw-bold"><?php echo h($mot['nome']); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">NIF</div>
          <div class="detail-value"><?php echo h($mot['nif'] ?: '—'); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Telefone</div>
          <div class="detail-value"><?php echo h($mot['telefone'] ?: '—'); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">E-mail</div>
          <div class="detail-value"><?php echo h($mot['email'] ?: '—'); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Carta nº</div>
          <div class="detail-value"><?php echo h($mot['carta_numero'] ?: '—'); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Estado</div>
          <div class="detail-value"><?php echo h($mot['status'] ?? '—'); ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Listas: abastecimentos + manutenções -->
<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title mb-0">Os meus abastecimentos</h3>
        <a class="link-primary small" href="<?php echo base_url(); ?>/operario/abastecimentos.php">Ver todos</a>
      </div>
      <div class="vstack gap-2">
        <?php if (count($ultAbast) > 0): ?>
          <?php foreach ($ultAbast as $a): ?>
            <div class="list-row no-link">
              <div class="list-ico"><i class="bi bi-fuel-pump-fill"></i></div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate"><?php echo h($a['posto']); ?></div>
                <div class="small text-muted">
                  <?php echo h($a['combustivel']); ?> •
                  <?php echo number_format((float)$a['litros'], 1, ',', '.'); ?>L •
                  <?php echo fmtData($a['data_abastecimento']); ?>
                </div>
              </div>
              <span class="fw-semibold small">€ <?php echo number_format((float)$a['total'], 2, ',', '.'); ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted small">Sem abastecimentos registados.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title mb-0">Manutenções da minha viatura</h3>
      </div>
      <div class="vstack gap-2">
        <?php if (count($ultMan) > 0): ?>
          <?php foreach ($ultMan as $m): ?>
            <?php
              $st    = (string)($m['status'] ?? '');
              $pill  = 'badge-info-soft'; $label = $st;
              if ($st === 'Concluída')               { $pill = 'badge-success-soft'; }
              elseif (stripos($st, 'andamento') !== false) { $pill = 'badge-warning-soft'; }
              elseif ($st === 'Agendada')             { $pill = 'badge-info-soft'; }
            ?>
            <div class="list-row no-link">
              <div class="list-ico warn"><i class="bi bi-tools"></i></div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate"><?php echo h($m['descricao']); ?></div>
                <div class="small text-muted">
                  <?php echo h($m['tipo']); ?> •
                  <?php echo fmtData($m['data_inicio']); ?>
                  <?php if ($m['custo']): ?> • € <?php echo number_format((float)$m['custo'], 2, ',', '.'); ?><?php endif; ?>
                </div>
              </div>
              <span class="badge-pill <?php echo $pill; ?>"><?php echo h($label); ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted small">Sem manutenções registadas para esta viatura.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
