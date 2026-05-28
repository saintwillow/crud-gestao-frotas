<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

if (is_gestor_ou_admin()) {
  header("Location: " . base_url() . "/index.php");
  exit;
}

$active = 'operario_painel';
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

$motorista_id = motorista_id_sessao();
$usuario_id = usuario_id_sessao();

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

/* Dados do motorista */
$stmt = mysqli_prepare($ligacao,
  "SELECT *
   FROM motoristas
   WHERE id = ?
   LIMIT 1"
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

/* Nova lógica: buscar viatura por atribuição aberta */
$atribuicao = atribuicao_aberta_motorista($ligacao, $motorista_id);
$viatura_id = $atribuicao ? (int)$atribuicao['viatura_id'] : null;

/* Serviço operacional aberto */
$servicoAberto = null;
if ($viatura_id) {
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
}

/* Últimos abastecimentos */
$ultAbast = [];
$stmt = mysqli_prepare($ligacao,
  "SELECT
      a.id,
      a.posto,
      a.combustivel,
      a.litros,
      a.total,
      a.data_abastecimento,
      a.estado,
      v.matricula,
      v.marca_modelo
   FROM abastecimentos a
   LEFT JOIN viaturas v ON v.id = a.viatura_id
   WHERE a.motorista_id = ?
      OR a.colaborador_id = ?
   ORDER BY a.data_abastecimento DESC, a.id DESC
   LIMIT 5"
);
mysqli_stmt_bind_param($stmt, "ii", $motorista_id, $motorista_id);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);
if ($r) while ($row = mysqli_fetch_assoc($r)) $ultAbast[] = $row;
mysqli_stmt_close($stmt);

/* Manutenções da viatura atual */
$ultMan = [];
if ($viatura_id) {
  $stmt = mysqli_prepare($ligacao,
    "SELECT id, descricao, tipo, data_inicio, data_fim, custo, status
     FROM manutencoes
     WHERE viatura_id = ?
     ORDER BY data_inicio DESC, id DESC
     LIMIT 5"
  );
  mysqli_stmt_bind_param($stmt, "i", $viatura_id);
  mysqli_stmt_execute($stmt);
  $r = mysqli_stmt_get_result($stmt);
  if ($r) while ($row = mysqli_fetch_assoc($r)) $ultMan[] = $row;
  mysqli_stmt_close($stmt);
}

/* Alerta carta */
$diasCarta = null;
if (!empty($mot['carta_validade'])) {
  $hoje = new DateTime();
  $validade = new DateTime($mot['carta_validade']);
  $diasCarta = (int)$hoje->diff($validade)->days * ($validade >= $hoje ? 1 : -1);
}

// ── Estatísticas do mês corrente para o resumo do operário ─────────────────
$mesAtual = date('Y-m');
$stmtSum = mysqli_prepare($ligacao,
  "SELECT 
     COALESCE(SUM(litros), 0) AS total_litros,
     COALESCE(SUM(total), 0) AS total_custo,
     COUNT(*) AS total_count,
     SUM(CASE WHEN estado IN ('registado', 'corrigido') THEN 1 ELSE 0 END) AS total_validados
   FROM abastecimentos
   WHERE (motorista_id = ? OR colaborador_id = ?)
     AND DATE_FORMAT(data_abastecimento, '%Y-%m') = ?"
);
mysqli_stmt_bind_param($stmtSum, "iis", $motorista_id, $motorista_id, $mesAtual);
mysqli_stmt_execute($stmtSum);
$resSum = mysqli_stmt_get_result($stmtSum);
$rowSum = $resSum ? mysqli_fetch_assoc($resSum) : null;
mysqli_stmt_close($stmtSum);

$totalLitrosMes = $rowSum ? (float)$rowSum['total_litros'] : 0;
$totalCustoMes = $rowSum ? (float)$rowSum['total_custo'] : 0;
$countAbast = $rowSum ? (int)$rowSum['total_count'] : 0;
$validadosAbast = $rowSum ? (int)$rowSum['total_validados'] : 0;
$taxaValidadacao = $countAbast > 0 ? round(($validadosAbast / $countAbast) * 100) : 100;

// Determinar o mês por extenso em português para o cabeçalho
$meses = [
  '01' => 'janeiro', '02' => 'fevereiro', '03' => 'março', '04' => 'abril',
  '05' => 'maio', '06' => 'junho', '07' => 'julho', '08' => 'agosto',
  '09' => 'setembro', '10' => 'outubro', '11' => 'novembro', '12' => 'dezembro'
];
$data_formatada = date('d') . ' ' . $meses[date('m')] . ' ' . date('Y');
?>

<style>
  .list-row {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    padding: 12px 16px !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    transition: all 0.2s ease;
  }
  .list-row:hover {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
    transform: translateY(-1px);
  }
  .list-row.no-link {
    cursor: default;
  }
  .list-row.no-link:hover {
    transform: none;
  }
  .list-row .list-ico {
    background: rgba(59, 130, 246, 0.08) !important;
    color: #3b82f6 !important;
    border-radius: 10px !important;
    width: 40px !important;
    height: 40px !important;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
  }
  .list-row .list-ico.warn {
    background: rgba(245, 158, 11, 0.08) !important;
    color: #f59e0b !important;
  }
  .vehicle-svg {
    filter: drop-shadow(0 4px 8px rgba(0,0,0,0.06));
    transition: transform 0.2s ease;
  }
  .vehicle-svg:hover {
    transform: scale(1.03);
  }
  .kpi-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    border-radius: 16px;
  }
</style>

<!-- Cabeçalho do Painel do Operário -->
<div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
  <div>
    <h1 class="page-title text-dark fw-bold" style="font-family: 'DM Sans', sans-serif;">Painel do Operário</h1>
    <div class="page-subtitle text-muted">Visão geral da viatura atribuída, abastecimentos e dados do condutor</div>
  </div>
  <div class="d-flex align-items-center gap-2">
    <span class="badge bg-white text-dark border border-light-subtle px-3 py-2 rounded-3" style="font-size: 12px; font-weight: 500;">
      <?php echo $data_formatada; ?>
    </span>
    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-3 py-2 rounded-3" style="font-size: 12px; font-weight: 600;">
      Conta ativa
    </span>
  </div>
</div>

<?php if ($diasCarta !== null && $diasCarta <= 60): ?>
  <div class="glass-card p-3 mb-4" style="border-left:3px solid hsl(38,92%,50%);">
    <div class="d-flex align-items-center gap-2 text-dark">
      <i class="bi bi-exclamation-triangle-fill" style="color:hsl(38,92%,50%);"></i>
      <?php if ($diasCarta <= 0): ?>
        <span><strong>A sua carta de condução expirou.</strong> Contacte o gestor de frotas.</span>
      <?php else: ?>
        <span>A sua carta de condução vence em <strong><?php echo $diasCarta; ?> dias</strong> (<?php echo fmtData($mot['carta_validade']); ?>).</span>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Serviço operacional -->
<div class="glass-card p-4 mb-4" style="border-left:3px solid hsl(205,80%,40%);">
  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 text-dark">
    <div>
      <h3 class="section-title text-dark mb-1" style="font-size: 15px; font-family: 'DM Sans', sans-serif;">O Meu Serviço</h3>

      <?php if (!$viatura_id): ?>
        <div class="text-muted">
          Não existe nenhuma viatura atribuída neste momento.
        </div>
      <?php elseif ($servicoAberto): ?>
        <div class="text-muted">
          Serviço iniciado em
          <strong><?php echo h(date('d/m/Y H:i', strtotime($servicoAberto['data_inicio']))); ?></strong>
          com <?php echo number_format((int)$servicoAberto['km_inicio'], 0, ',', '.'); ?> km.
        </div>
      <?php else: ?>
        <div class="text-muted">
          Ainda não iniciou o serviço de hoje.
        </div>
      <?php endif; ?>
    </div>

    <div>
      <?php if ($viatura_id): ?>
        <?php if ($servicoAberto): ?>
          <a href="servico.php" class="btn btn-warning">
            Continuar serviço
          </a>
        <?php else: ?>
          <a href="servico.php" class="btn btn-primary">
            Iniciar serviço
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- KPIs pessoais -->
<div class="row g-3 mb-4">
  <!-- Card 1: Carta -->
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="kpi-card p-3 h-100 d-flex align-items-center gap-3">
      <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 48px; height: 48px; background: #22c55e; color: #fff; font-size: 20px; flex-shrink: 0;">
        <i class="bi bi-check-lg"></i>
      </div>
      <div>
        <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">Carta</div>
        <div class="fw-bold text-dark" style="font-size: 20px; line-height: 1.2;"><?php echo h($mot['carta_categoria'] ?: '—'); ?></div>
        <div class="text-muted" style="font-size: 11px; white-space: nowrap;">Válida até <?php echo fmtData($mot['carta_validade']); ?></div>
      </div>
    </div>
  </div>

  <!-- Card 2: Viagens -->
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="kpi-card p-3 h-100 d-flex align-items-center gap-3">
      <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 48px; height: 48px; background: #3b82f6; color: #fff; font-size: 20px; flex-shrink: 0;">
        <i class="bi bi-arrow-up-right-circle"></i>
      </div>
      <div>
        <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">Viagens</div>
        <div class="fw-bold text-dark" style="font-size: 20px; line-height: 1.2;"><?php echo number_format((int)$mot['viagens'], 0, ',', '.'); ?></div>
        <div class="text-muted" style="font-size: 11px; white-space: nowrap;">Desde <?php echo fmtData($mot['desde']); ?></div>
      </div>
    </div>
  </div>

  <!-- Card 3: Viatura -->
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="kpi-card p-3 h-100 d-flex align-items-center gap-3">
      <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 48px; height: 48px; background: #4f46e5; color: #fff; font-size: 20px; flex-shrink: 0;">
        <i class="bi bi-car-front"></i>
      </div>
      <div>
        <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">Viatura</div>
        <div class="fw-bold text-dark" style="font-size: 18px; line-height: 1.2;"><?php echo $atribuicao ? h($atribuicao['matricula']) : '—'; ?></div>
        <div class="text-muted text-truncate" style="font-size: 11px; max-width: 140px; white-space: nowrap;"><?php echo $atribuicao ? h($atribuicao['marca_modelo']) : 'Sem viatura'; ?></div>
      </div>
    </div>
  </div>

  <!-- Card 4: Quilometragem -->
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="kpi-card p-3 h-100 d-flex flex-column justify-content-center">
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 48px; height: 48px; background: #06b6d4; color: #fff; font-size: 20px; flex-shrink: 0;">
          <i class="bi bi-speedometer2"></i>
        </div>
        <div class="flex-grow-1 min-w-0">
          <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600;">Quilometragem</div>
          <div class="fw-bold text-dark" style="font-size: 20px; line-height: 1.2;"><?php echo $atribuicao ? number_format((int)$atribuicao['quilometragem'], 0, ',', '.') : '—'; ?></div>
          <div class="text-muted" style="font-size: 11px;">km registados</div>
        </div>
      </div>
      <?php if ($atribuicao): ?>
        <div class="progress mt-2" style="height: 6px; background: #e2e8f0; border-radius: 10px;">
          <div class="progress-bar" role="progressbar" style="width: 70%; background-color: #06b6d4; border-radius: 10px;" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($atribuicao): ?>
<!-- A minha viatura & Os meus dados -->
<div class="row g-3 mb-4">
  <!-- Card A minha viatura -->
  <div class="col-12 col-lg-6">
    <div class="kpi-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title text-dark mb-0" style="font-size: 16px; font-family: 'DM Sans', sans-serif;">A minha viatura</h3>
        <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-3 py-1 text-uppercase" style="font-size: 11px; font-weight: 700;">
          <?php echo h($atribuicao['viatura_estado']); ?>
        </span>
      </div>

      <div class="row g-3 align-items-center">
        <div class="col-12 col-md-5 text-center py-4 px-2" style="background: rgba(59, 130, 246, 0.04); border-radius: 16px; border: 1px solid rgba(59, 130, 246, 0.08);">
          <!-- Vector Car SVG (Side profile, light blue utility vehicle) -->
          <svg width="130" height="80" viewBox="0 0 160 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-2 vehicle-svg">
            <ellipse cx="80" cy="85" rx="60" ry="6" fill="rgba(15, 23, 42, 0.08)" />
            <!-- Van Cabin Base -->
            <path d="M22 68 C22 72, 25 75, 30 75 L130 75 C135 75, 138 72, 138 68 L138 52 L22 52 Z" fill="#94a3b8" />
            <!-- Van Roof / Top Cabin -->
            <path d="M30 52 L30 45 C30 38, 38 32, 46 32 L110 32 C118 32, 126 38, 126 45 L126 52 Z" fill="#cbd5e1" />
            <path d="M126 52 L142 52 C146 52, 149 55, 149 59 L149 68 C149 72, 146 75, 142 75 L138 75 L138 52 Z" fill="#cbd5e1" />
            <!-- Windows -->
            <path d="M48 38 L76 38 L76 48 L48 48 Z" fill="#38bdf8" opacity="0.7" />
            <path d="M82 38 L114 38 L114 48 L82 48 Z" fill="#38bdf8" opacity="0.7" />
            <path d="M120 38 L124 38 C126 38, 126 40, 126 42 L126 48 L120 48 Z" fill="#38bdf8" opacity="0.7" />
            <!-- Wheels -->
            <circle cx="50" cy="75" r="14" fill="#1e293b" />
            <circle cx="50" cy="75" r="6" fill="#e2e8f0" />
            <circle cx="115" cy="75" r="14" fill="#1e293b" />
            <circle cx="115" cy="75" r="6" fill="#e2e8f0" />
            <!-- Details -->
            <rect x="134" y="60" width="12" height="4" rx="2" fill="#ffedd5" />
            <circle cx="26" cy="60" r="3" fill="#f87171" />
          </svg>
          <div class="fw-bold text-dark mt-2" style="font-size: 13px; font-family: 'DM Sans', sans-serif;"><?php echo h($atribuicao['marca_modelo']); ?></div>
          <span class="badge bg-dark text-white mt-1 px-2.5 py-1 font-monospace" style="font-size: 10px; letter-spacing: 0.05em; border-radius: 4px;"><?php echo h($atribuicao['matricula']); ?></span>
        </div>
        
        <div class="col-12 col-md-7">
          <div class="row g-3">
            <div class="col-6">
              <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Matrícula</div>
              <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo h($atribuicao['matricula']); ?></div>
            </div>
            <div class="col-6">
              <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Marca / Modelo</div>
              <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo h($atribuicao['marca_modelo']); ?></div>
            </div>
            <div class="col-6">
              <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Tipo</div>
              <div class="fw-semibold text-dark" style="font-size: 14px;"><?php echo h($atribuicao['tipo'] ?: '—'); ?></div>
            </div>
            <div class="col-6">
              <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Combustível</div>
              <div class="fw-semibold text-dark" style="font-size: 14px;"><?php echo h($atribuicao['combustivel'] ?: '—'); ?></div>
            </div>
            <div class="col-6">
              <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Quilometragem</div>
              <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo number_format((int)$atribuicao['quilometragem'], 0, ',', '.'); ?> km</div>
            </div>
            <div class="col-6">
              <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Estado</div>
              <div class="fw-semibold text-dark" style="font-size: 14px;"><?php echo h($atribuicao['viatura_estado']); ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Card Os meus dados -->
  <div class="col-12 col-lg-6">
    <div class="kpi-card p-4 h-100">
      <h3 class="section-title text-dark mb-3" style="font-size: 16px; font-family: 'DM Sans', sans-serif;">Os meus dados</h3>
      
      <div class="d-flex align-items-center gap-3 mb-4">
        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 64px; height: 64px; background: rgba(59, 130, 246, 0.08); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.15); flex-shrink: 0;">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-user">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
            <circle cx="12" cy="7" r="4" />
          </svg>
        </div>
        <div>
          <h4 class="fw-bold text-dark mb-1" style="font-size: 18px; font-family: 'DM Sans', sans-serif;"><?php echo h($mot['nome']); ?></h4>
          <div class="text-muted mb-2" style="font-size: 12px; font-weight: 500;"><?php echo perfil_atual() === 'operario' ? 'Operário' : 'Colaborador'; ?></div>
          <div class="d-flex gap-2">
            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle rounded-pill px-2.5 py-0.5" style="font-size: 9px; font-weight: 700;">
              <?php echo h($mot['carta_numero'] ?: 'Sem Carta'); ?>
            </span>
            <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-2.5 py-0.5" style="font-size: 9px; font-weight: 700;">
              <?php echo h($mot['status'] ?? 'Ativo'); ?>
            </span>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-6 col-md-4">
          <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">NIF</div>
          <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo h($mot['nif'] ?: '—'); ?></div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Telefone</div>
          <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo h($mot['telefone'] ?: '—'); ?></div>
        </div>
        <div class="col-12 col-md-4">
          <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">E-mail</div>
          <div class="fw-bold text-dark text-truncate" style="font-size: 14px;"><?php echo h($mot['email'] ?: '—'); ?></div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Carta nº</div>
          <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo h($mot['carta_numero'] ?: '—'); ?></div>
        </div>
        <div class="col-6 col-md-4">
          <div class="text-muted small-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Estado</div>
          <div class="fw-bold text-dark" style="font-size: 14px;"><?php echo h($mot['status'] ?? '—'); ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Abastecimentos & Manutenções -->
<div class="row g-3">
  <!-- Abastecimentos recentes -->
  <div class="col-12 col-lg-6">
    <div class="kpi-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title text-dark mb-0" style="font-size: 16px; font-family: 'DM Sans', sans-serif;">Abastecimentos recentes</h3>
        <a class="link-soft text-primary" style="font-size: 12px; font-weight: 600;" href="<?php echo base_url(); ?>/operario/abastecimentos.php">Ver todos</a>
      </div>

      <div class="vstack gap-2">
        <?php if (count($ultAbast) > 0): ?>
          <?php foreach ($ultAbast as $a): ?>
            <div class="list-row no-link">
              <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
                <div class="list-ico"><i class="bi bi-fuel-pump-fill"></i></div>
                <div class="min-w-0">
                  <div class="fw-bold text-dark text-truncate" style="font-size: 14px;"><?php echo h($a['posto']); ?></div>
                  <div class="text-muted" style="font-size: 12px;">
                    <?php echo h($a['combustivel']); ?> • <?php echo number_format((float)$a['litros'], 1, ',', '.'); ?> L • <?php echo fmtData($a['data_abastecimento']); ?>
                  </div>
                </div>
              </div>
              
              <div class="d-flex align-items-center gap-2">
                <?php
                  $est = strtolower($a['estado'] ?? '');
                  $badge_class = 'bg-secondary text-secondary';
                  if ($est === 'registado' || $est === 'corrigido') {
                    $badge_class = 'bg-success bg-opacity-10 text-success border border-success-subtle';
                  } elseif ($est === 'anulado') {
                    $badge_class = 'bg-danger bg-opacity-10 text-danger border border-danger-subtle';
                  } elseif ($est === 'pendente') {
                    $badge_class = 'bg-warning bg-opacity-10 text-warning border border-warning-subtle';
                  }
                ?>
                <span class="badge <?php echo $badge_class; ?> rounded-pill px-2.5 py-1 text-uppercase" style="font-size: 9px; font-weight: 700;">
                  <?php echo h($a['estado']); ?>
                </span>
                <span class="fw-bold text-dark ms-2" style="font-size: 15px; white-space: nowrap;">€ <?php echo number_format((float)$a['total'], 2, ',', '.'); ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted small py-3">Sem abastecimentos registados.</div>
        <?php endif; ?>
      </div>

      <!-- Resumo do Mês Corrente -->
      <div class="d-flex align-items-center justify-content-between p-3 mt-3" style="background: rgba(59, 130, 246, 0.05); border: 1px solid rgba(59, 130, 246, 0.08); border-radius: 12px;">
        <div>
          <div class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Total mês</div>
          <div class="fw-bold text-dark" style="font-size: 15px;"><?php echo number_format($totalLitrosMes, 1, ',', '.'); ?> L</div>
        </div>
        <div>
          <div class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Despesa</div>
          <div class="fw-bold text-dark" style="font-size: 15px;">€ <?php echo number_format($totalCustoMes, 2, ',', '.'); ?></div>
        </div>
        <div class="text-end">
          <div class="text-muted" style="font-size: 11px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.02em;">Taxa validação</div>
          <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-2.5 py-1 mt-1" style="font-size: 10px; font-weight: 700;">
            <?php echo $taxaValidadacao; ?>%
          </span>
        </div>
      </div>
    </div>
  </div>

  <!-- Manutenções da viatura -->
  <div class="col-12 col-lg-6">
    <div class="kpi-card p-4 h-100">
      <?php
        $temAlertas = false;
        if ($viatura_id && count($ultMan) > 0) {
          foreach ($ultMan as $m) {
            $st = strtolower($m['status'] ?? '');
            if ($st !== 'concluída' && $st !== 'cancelada') {
              $temAlertas = true;
              break;
            }
          }
        }
      ?>
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title text-dark mb-0" style="font-size: 16px; font-family: 'DM Sans', sans-serif;">Manutenções da viatura</h3>
        <?php if (!$temAlertas): ?>
          <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle rounded-pill px-2.5 py-1 text-uppercase" style="font-size: 10px; font-weight: 700;">Sem alertas</span>
        <?php else: ?>
          <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle rounded-pill px-2.5 py-1 text-uppercase" style="font-size: 10px; font-weight: 700;">Intervenção ativa</span>
        <?php endif; ?>
      </div>

      <div class="vstack gap-2">
        <?php if ($temAlertas): ?>
          <?php foreach ($ultMan as $m): ?>
            <?php
              $st = (string)($m['status'] ?? '');
              $stLower = strtolower($st);
              if ($stLower === 'concluída' || $stLower === 'cancelada') continue; // only show active ones

              $badge_class = 'bg-info bg-opacity-10 text-info border border-info-subtle';
              if (stripos($stLower, 'andamento') !== false) {
                $badge_class = 'bg-warning bg-opacity-10 text-warning border border-warning-subtle';
              }
            ?>
            <div class="list-row no-link">
              <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
                <div class="list-ico warn"><i class="bi bi-tools"></i></div>
                <div class="min-w-0">
                  <div class="fw-bold text-dark text-truncate" style="font-size: 14px;"><?php echo h($m['descricao']); ?></div>
                  <div class="text-muted" style="font-size: 12px;">
                    <?php echo h($m['tipo']); ?> • <?php echo fmtData($m['data_inicio']); ?>
                  </div>
                </div>
              </div>
              
              <div class="d-flex align-items-center gap-2">
                <?php if ($m['custo']): ?>
                  <span class="fw-bold text-dark me-2" style="font-size: 14px;">€ <?php echo number_format((float)$m['custo'], 2, ',', '.'); ?></span>
                <?php endif; ?>
                <span class="badge <?php echo $badge_class; ?> rounded-pill px-2.5 py-1 text-uppercase" style="font-size: 9px; font-weight: 700;">
                  <?php echo h($st); ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <!-- Dial Gauge SVG Empty State -->
          <div class="d-flex flex-column align-items-center justify-content-center py-4 text-center">
            <div class="position-relative mb-3" style="width: 120px; height: 120px;">
              <svg width="120" height="120" viewBox="0 0 100 100">
                <!-- Background track -->
                <path d="M 50,50 m 0,-40 a 40,40 0 1,1 0,80 a 40,40 0 1,1 0,-80" 
                      stroke="#f1f5f9" stroke-width="6" fill="none" />
                <!-- Completed green track -->
                <path d="M 50,50 m 0,-40 a 40,40 0 1,1 0,80 a 40,40 0 1,1 0,-80" 
                      stroke="#22c55e" stroke-width="6" fill="none" 
                      stroke-dasharray="251.2" stroke-dashoffset="0" 
                      stroke-linecap="round" />
              </svg>
              <!-- Icon/Checkmark in the center -->
              <div class="position-absolute top-50 start-50 translate-middle d-flex align-items-center justify-content-center rounded-circle" 
                   style="width: 54px; height: 54px; background: rgba(34, 197, 94, 0.08); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.15);">
                <i class="bi bi-check-lg" style="font-size: 24px;"></i>
              </div>
            </div>
            <h5 class="fw-bold text-dark mb-1" style="font-size: 15px; font-family: 'DM Sans', sans-serif;">Sem manutenções registadas</h5>
            <p class="text-muted px-4 mb-0" style="font-size: 12px; max-width: 320px;">
              A <?php echo $atribuicao ? h($atribuicao['marca_modelo']) : 'viatura'; ?> não tem intervenções pendentes.
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>