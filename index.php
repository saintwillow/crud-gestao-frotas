<?php
require_once __DIR__ . "/inc/auth.php";
exigir_gestor_ou_admin();

$active = 'dashboard';
require_once __DIR__ . "/inc/database.php";
require_once __DIR__ . "/inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── KPIs de viaturas ──────────────────────────────────────────────────────
$totalVeiculos = 0; $emManutencao = 0; $ativos = 0; $inativos = 0;
$r = mysqli_query($ligacao, "SELECT estado, COUNT(*) AS n FROM viaturas GROUP BY estado");
if ($r) while ($row = mysqli_fetch_assoc($r)) {
  $totalVeiculos += (int)$row['n'];
  $e = $row['estado'];
  if (in_array($e, ['Disponível','Atribuída'], true)) $ativos        += (int)$row['n'];
  elseif ($e === 'Em Manutenção')                     $emManutencao  += (int)$row['n'];
  elseif ($e === 'Inativo')                           $inativos      += (int)$row['n'];
}

// ── Custo real do mês atual (manutenções + abastecimentos) ────────────────
$mesAtual = date('Y-m');
$r = mysqli_query($ligacao,
  "SELECT COALESCE(SUM(custo),0) AS total FROM manutencoes
   WHERE DATE_FORMAT(data_inicio,'%Y-%m')='$mesAtual'"
);
$custoManutencao = $r ? (float)(mysqli_fetch_assoc($r)['total'] ?? 0) : 0;

$r = mysqli_query($ligacao,
  "SELECT COALESCE(SUM(total),0) AS total FROM abastecimentos
   WHERE DATE_FORMAT(data_abastecimento,'%Y-%m')='$mesAtual'"
);
$custoCombustivel = $r ? (float)(mysqli_fetch_assoc($r)['total'] ?? 0) : 0;
$custoMensal = $custoManutencao + $custoCombustivel;

// ── Preço médio por litro este mês (substitui "combustível médio 58%") ────
$r = mysqli_query($ligacao,
  "SELECT COALESCE(AVG(preco_litro),0) AS media FROM abastecimentos
   WHERE DATE_FORMAT(data_abastecimento,'%Y-%m')='$mesAtual'"
);
$precoMedioLitro = $r ? (float)(mysqli_fetch_assoc($r)['media'] ?? 0) : 0;

// ── Gráfico: custo de combustível dos últimos 6 meses ─────────────────────
$fuelData = [];
for ($i = 5; $i >= 0; $i--) {
  $mes = date('Y-m', strtotime("-$i months"));
  $label = strftime('%b', strtotime("-$i months"));
  // fallback se strftime não tiver locale
  $meses_pt = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  $label = $meses_pt[(int)date('m', strtotime("-$i months")) - 1];

  $r = mysqli_query($ligacao,
    "SELECT COALESCE(SUM(total),0) AS total FROM abastecimentos
     WHERE DATE_FORMAT(data_abastecimento,'%Y-%m')='$mes'"
  );
  $val = $r ? (float)(mysqli_fetch_assoc($r)['total'] ?? 0) : 0;
  $fuelData[] = ['month' => $label, 'value' => round($val, 2)];
}

// ── Dados do donut de status ──────────────────────────────────────────────
$statusData = [
  ['name' => 'Ativos',     'value' => $ativos,       'color' => 'hsl(152, 60%, 40%)'],
  ['name' => 'Manutenção', 'value' => $emManutencao, 'color' => 'hsl(38, 92%, 50%)'],
  ['name' => 'Inativos',   'value' => $inativos,     'color' => 'hsl(0, 72%, 51%)'],
];

// ── Veículos recentes ─────────────────────────────────────────────────────
$recentRes = mysqli_query($ligacao,
  "SELECT v.id, v.matricula, v.marca_modelo, v.estado,
          m.nome AS motorista_nome
   FROM viaturas v
   LEFT JOIN motoristas m ON m.viatura_id = v.id AND m.status='Ativo'
   ORDER BY v.id DESC LIMIT 5"
);

// ── Manutenções pendentes ─────────────────────────────────────────────────
$upcoming = [];
$manRes = mysqli_query($ligacao,
  "SELECT m.id, m.descricao, m.data_inicio, m.custo, m.status, v.matricula
   FROM manutencoes m
   LEFT JOIN viaturas v ON v.id = m.viatura_id
   WHERE m.status IN ('Agendada','Em andamento','Pendente')
   ORDER BY m.data_inicio ASC LIMIT 5"
);
if ($manRes) while ($row = mysqli_fetch_assoc($manRes)) $upcoming[] = $row;

// ── Alertas: cartas de condução a vencer nos próximos 60 dias ────────────
$alertasCarta = [];
$hoje = date('Y-m-d');
$limite60 = date('Y-m-d', strtotime('+60 days'));
$alertaRes = mysqli_query($ligacao,
  "SELECT nome, carta_categoria, carta_validade,
          DATEDIFF(carta_validade, CURDATE()) AS dias_restantes
   FROM motoristas
   WHERE status='Ativo'
     AND carta_validade IS NOT NULL
     AND carta_validade <= '$limite60'
   ORDER BY carta_validade ASC"
);
if ($alertaRes) while ($row = mysqli_fetch_assoc($alertaRes)) $alertasCarta[] = $row;

// ── Alertas: Ocorrências críticas/altas ativas ─────────────────────────────
$alertasOcorrencias = [];
$ocRes = mysqli_query($ligacao,
  "SELECT o.id, o.codigo, o.titulo, o.gravidade, v.matricula
   FROM ocorrencias o
   LEFT JOIN viaturas v ON v.id = o.viatura_id
   WHERE o.gravidade IN ('alta','critica') AND o.estado IN ('aberta','em_analise')
   ORDER BY o.criado_em DESC LIMIT 5"
);
if ($ocRes) while ($row = mysqli_fetch_assoc($ocRes)) $alertasOcorrencias[] = $row;

function badgeEstadoDashboard($estado) {
  $estado = trim((string)$estado);
  if ($estado === 'Disponível')    return '<span class="badge-pill badge-success-soft">Ativo</span>';
  if ($estado === 'Atribuída')     return '<span class="badge-pill badge-info-soft">Em rota</span>';
  if ($estado === 'Em Manutenção') return '<span class="badge-pill badge-warning-soft">Manutenção</span>';
  if ($estado === 'Inativo')       return '<span class="badge-pill badge-danger-soft">Inativo</span>';
  return '<span class="badge-pill badge-info-soft">'.h($estado).'</span>';
}
?>

<!-- Cabeçalho -->
<div class="mb-4">
  <h1 class="page-title">Painel de Controle</h1>
  <div class="page-subtitle">Visão geral da frota — AquaFleet</div>
</div>

<?php if (count($alertasCarta) > 0): ?>
<!-- Faixa de alertas de carta -->
<div class="glass-card p-3 mb-4" style="border-left: 3px solid hsl(38,92%,50%);">
  <div class="d-flex align-items-center gap-2 mb-2">
    <i class="bi bi-exclamation-triangle-fill" style="color:hsl(38,92%,50%);"></i>
    <strong class="small">Cartas de condução a vencer nos próximos 60 dias</strong>
  </div>
  <div class="vstack gap-2">
    <?php foreach ($alertasCarta as $a): ?>
      <?php
        $dias = (int)$a['dias_restantes'];
        $cor  = $dias <= 0 ? 'badge-danger-soft' : ($dias <= 15 ? 'badge-warning-soft' : 'badge-info-soft');
        $txt  = $dias <= 0 ? 'Expirada' : "Vence em {$dias}d";
      ?>
      <div class="d-flex align-items-center justify-content-between gap-2 small">
        <div>
          <span class="fw-semibold"><?php echo h($a['nome']); ?></span>
          <span class="text-muted ms-2">Carta <?php echo h($a['carta_categoria'] ?? '—'); ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="text-muted"><?php echo h($a['carta_validade']); ?></span>
          <span class="badge-pill <?php echo $cor; ?>"><?php echo $txt; ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if (count($alertasOcorrencias) > 0): ?>
<!-- Faixa de alertas de ocorrências críticas -->
<div class="glass-card p-3 mb-4" style="border-left: 3px solid hsl(0, 72%, 51%);">
  <div class="d-flex align-items-center gap-2 mb-2">
    <i class="bi bi-exclamation-octagon-fill text-danger"></i>
    <strong class="small text-danger">Ocorrências Críticas / Altas Ativas</strong>
  </div>
  <div class="vstack gap-2">
    <?php foreach ($alertasOcorrencias as $oc): ?>
      <div class="d-flex align-items-center justify-content-between gap-2 small">
        <div>
          <a href="ocorrencias/show.php?id=<?php echo (int)$oc['id']; ?>" class="fw-semibold text-danger text-decoration-none">
            <?php echo h($oc['codigo'] . " - " . $oc['titulo']); ?>
          </a>
          <span class="text-muted ms-2">Viatura: <?php echo h($oc['matricula'] ?? 'Sem Viatura'); ?></span>
        </div>
        <span class="badge-pill bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25 text-uppercase" style="font-size: 10px; padding: 2px 6px;">
          <?php echo h($oc['gravidade']); ?>
        </span>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Total de Veículos</div>
          <div class="kpi-value"><?php echo $totalVeiculos; ?></div>
          <div class="kpi-sub"><?php echo $ativos; ?> operacionais</div>
        </div>
        <div class="kpi-ico"><i class="bi bi-car-front-fill fs-5"></i></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Em Manutenção</div>
          <div class="kpi-value"><?php echo $emManutencao; ?></div>
          <div class="kpi-sub"><?php echo count($upcoming); ?> pendentes</div>
        </div>
        <div class="kpi-ico"><i class="bi bi-tools fs-5"></i></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Preço Médio/Litro</div>
          <div class="kpi-value">
            <?php echo $precoMedioLitro > 0
              ? '€ ' . number_format($precoMedioLitro, 3, ',', '.')
              : '—'; ?>
          </div>
          <div class="kpi-sub">Abastecimentos este mês</div>
        </div>
        <div class="kpi-ico"><i class="bi bi-fuel-pump-fill fs-5"></i></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Custo Mensal</div>
          <div class="kpi-value">€ <?php echo number_format($custoMensal, 0, ',', '.'); ?></div>
          <div class="kpi-sub">Manutenção + Combustível</div>
        </div>
        <div class="kpi-ico"><i class="bi bi-currency-euro fs-5"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- Gráficos -->
<div class="row g-3 mb-4">
  <div class="col-12 col-lg-8">
    <div class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title mb-0">Custo de Combustível (€) — últimos 6 meses</h3>
      </div>
      <canvas id="fuelBarChart" height="110"></canvas>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="glass-card p-4 h-100">
      <h3 class="section-title mb-3">Status da Frota</h3>
      <div class="d-flex justify-content-center">
        <canvas id="statusDonut" width="220" height="220"></canvas>
      </div>
      <div class="mt-3">
        <?php foreach ($statusData as $s): ?>
          <div class="d-flex align-items-center justify-content-between small py-1">
            <div class="d-flex align-items-center gap-2">
              <span class="legend-dot" style="background:<?php echo h($s['color']); ?>;"></span>
              <span class="text-muted"><?php echo h($s['name']); ?></span>
            </div>
            <strong><?php echo (int)$s['value']; ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Listas -->
<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title mb-0">Veículos Recentes</h3>
        <a class="link-primary small" href="viaturas/index.php">Ver todos</a>
      </div>
      <div class="vstack gap-2">
        <?php if ($recentRes && mysqli_num_rows($recentRes) > 0): ?>
          <?php while ($v = mysqli_fetch_assoc($recentRes)): ?>
            <a class="list-row" href="viaturas/show.php?id=<?php echo (int)$v['id']; ?>">
              <div class="list-ico"><i class="bi bi-car-front-fill"></i></div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate"><?php echo h($v['marca_modelo']); ?></div>
                <div class="small text-muted">
                  <?php echo h($v['matricula']); ?>
                  <?php if (!empty($v['motorista_nome'])): ?>
                    • <?php echo h($v['motorista_nome']); ?>
                  <?php endif; ?>
                </div>
              </div>
              <?php echo badgeEstadoDashboard($v['estado']); ?>
            </a>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="text-muted small">Sem veículos para mostrar.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title mb-0">Manutenções Pendentes</h3>
        <a class="link-primary small" href="manutencao/index.php">Ver todas</a>
      </div>
      <div class="vstack gap-2">
        <?php if (count($upcoming) > 0): ?>
          <?php foreach ($upcoming as $m): ?>
            <div class="list-row no-link">
              <div class="list-ico warn"><i class="bi bi-tools"></i></div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate"><?php echo h($m['descricao'] ?? 'Manutenção'); ?></div>
                <div class="small text-muted">
                  <?php echo h($m['matricula'] ?? '-'); ?> •
                  <?php echo h($m['data_inicio'] ?? '-'); ?> •
                  € <?php echo number_format((float)($m['custo'] ?? 0), 2, ',', '.'); ?>
                </div>
              </div>
              <?php
                $st    = (string)($m['status'] ?? '');
                $pill  = 'badge-info-soft'; $label = 'Agendada';
                if (stripos($st, 'andamento') !== false) { $pill = 'badge-warning-soft'; $label = 'Em andamento'; }
                elseif ($st === 'Pendente')               { $pill = 'badge-warning-soft'; $label = 'Pendente'; }
              ?>
              <span class="badge-pill <?php echo $pill; ?>"><?php echo $label; ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted small">Sem manutenções pendentes.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const fuelData   = <?php echo json_encode($fuelData,   JSON_UNESCAPED_UNICODE); ?>;
  const statusData = <?php echo json_encode($statusData, JSON_UNESCAPED_UNICODE); ?>;

  new Chart(document.getElementById('fuelBarChart'), {
    type: 'bar',
    data: {
      labels: fuelData.map(x => x.month),
      datasets: [{
        label: 'Custo',
        data: fuelData.map(x => x.value),
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: { label: (ctx) => '€ ' + Number(ctx.raw).toLocaleString('pt-PT') }
        }
      },
      scales: {
        x: { grid: { display: false } },
        y: { ticks: { callback: (v) => '€ ' + Number(v).toLocaleString('pt-PT') } }
      }
    }
  });

  new Chart(document.getElementById('statusDonut'), {
    type: 'doughnut',
    data: {
      labels: statusData.map(x => x.name),
      datasets: [{
        data: statusData.map(x => x.value),
        backgroundColor: statusData.map(x => x.color),
        borderWidth: 2,
        hoverOffset: 4,
        cutout: '65%'
      }]
    },
    options: { plugins: { legend: { display: false } } }
  });
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/inc/footer.php";
?>
