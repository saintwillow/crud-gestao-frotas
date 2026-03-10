<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'viaturas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  echo '<div class="glass-card p-4 text-center text-muted">ID inválido.</div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

// combustível “bonito” (placeholder)
function fuelPercent($matricula) {
  $n = abs(crc32((string)$matricula));
  return 25 + ($n % 66); // 25..90
}
function fuelBarClass($pct) {
  if ($pct > 50) return 'fuel-high';
  if ($pct > 25) return 'fuel-mid';
  return 'fuel-low';
}

function statusLabel($estado) {
  $estado = trim((string)$estado);
  if ($estado === 'Disponível') return 'Ativo';
  if ($estado === 'Atribuída') return 'Em Rota';
  if ($estado === 'Em Manutenção') return 'Manutenção';
  if ($estado === 'Inativo') return 'Inativo';
  return $estado ?: '—';
}
function statusPillClass($estado) {
  $estado = trim((string)$estado);
  if ($estado === 'Disponível') return 'pill-success';
  if ($estado === 'Atribuída') return 'pill-info';
  if ($estado === 'Em Manutenção') return 'pill-warning';
  if ($estado === 'Inativo') return 'pill-danger';
  return 'pill-info';
}

// Buscar viatura + motorista
$sql = "SELECT 
          v.*,
          m.nome AS motorista_nome
        FROM viaturas v
        LEFT JOIN motoristas m 
          ON m.viatura_id = v.id AND m.status='Ativo'
        WHERE v.id = $id
        LIMIT 1";

$res = mysqli_query($ligacao, $sql);
$row = $res ? mysqli_fetch_assoc($res) : null;

if (!$row) {
  echo '<div class="glass-card p-4 text-center text-muted">Veículo não encontrado.</div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$mat = (string)$row['matricula'];
$fuel = fuelPercent($mat);
$fuelClass = fuelBarClass($fuel);

$estadoRaw = (string)$row['estado'];
$stLabel = statusLabel($estadoRaw);
$stClass = statusPillClass($estadoRaw);

$motorista = !empty($row['motorista_nome']) ? $row['motorista_nome'] : 'Sem motorista';

// Última manutenção concluída
$ultima = null;
$sqlUltima = "SELECT data_fim, data_inicio
              FROM manutencoes
              WHERE viatura_id = $id AND (status='Concluída' OR status='Concluida')
              ORDER BY COALESCE(data_fim, data_inicio) DESC
              LIMIT 1";
$rU = mysqli_query($ligacao, $sqlUltima);
if ($rU && mysqli_num_rows($rU) > 0) {
  $u = mysqli_fetch_assoc($rU);
  $ultima = $u['data_fim'] ?: $u['data_inicio'];
}

// Próxima manutenção (agendada/em andamento)
$proxima = null;
$sqlProx = "SELECT data_inicio
            FROM manutencoes
            WHERE viatura_id = $id AND status IN ('Agendada','Em andamento','Pendente')
            ORDER BY data_inicio ASC
            LIMIT 1";
$rP = mysqli_query($ligacao, $sqlProx);
if ($rP && mysqli_num_rows($rP) > 0) {
  $p = mysqli_fetch_assoc($rP);
  $proxima = $p['data_inicio'] ?? null;
}

// Histórico manutenções
$hist = [];
$sqlHist = "SELECT tipo, descricao, data_inicio, data_fim, custo, status
            FROM manutencoes
            WHERE viatura_id = $id
            ORDER BY COALESCE(data_fim, data_inicio) DESC
            LIMIT 6";
$rH = mysqli_query($ligacao, $sqlHist);
if ($rH) {
  while ($m = mysqli_fetch_assoc($rH)) $hist[] = $m;
}

// Localização placeholder
$loc = '—';
if ($estadoRaw === 'Em Manutenção') $loc = 'Oficina Central';
elseif ($estadoRaw === 'Atribuída') $loc = 'Rota: Reservatório Central → Bairro Leste';
else $loc = 'Zona Norte - Estação de Tratamento';

/* ===== Abastecimentos (se tabela existir) ===== */
$abastecimentos = [];
$abastecimentosResumo = [
  'total_litros' => 0,
  'total_custo'  => 0,
  'preco_medio'  => 0,
];

$checkAb = mysqli_query($ligacao, "SHOW TABLES LIKE 'abastecimentos'");
if ($checkAb && mysqli_num_rows($checkAb) > 0) {

  // últimos 6
  $sqlAb = "
    SELECT posto, combustivel, litros, preco_litro, total, data_abastecimento, observacoes
    FROM abastecimentos
    WHERE viatura_id = $id
    ORDER BY data_abastecimento DESC, id DESC
    LIMIT 6
  ";
  $rA = mysqli_query($ligacao, $sqlAb);
  if ($rA) {
    while ($a = mysqli_fetch_assoc($rA)) $abastecimentos[] = $a;
  }

  // resumo
  $sqlSum = "
    SELECT 
      COALESCE(SUM(litros),0) AS total_litros,
      COALESCE(SUM(total),0) AS total_custo,
      CASE 
        WHEN COALESCE(SUM(litros),0) > 0 THEN COALESCE(SUM(total),0) / SUM(litros)
        ELSE 0
      END AS preco_medio
    FROM abastecimentos
    WHERE viatura_id = $id
  ";
  $rS = mysqli_query($ligacao, $sqlSum);
  if ($rS) {
    $abastecimentosResumo = mysqli_fetch_assoc($rS) ?: $abastecimentosResumo;
  }
}
?>

<div class="page-max-6xl space-y-6">

  <a class="back-link" href="index.php">← Voltar aos veículos</a>

  <div class="glass-card p-4">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div class="d-flex gap-3 align-items-start">
        <div class="icon-box stat-gradient" style="width:52px;height:52px;">
          <span style="font-size:20px;">🚚</span>
        </div>

        <div class="min-w-0">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <h1 class="page-title" style="margin:0;"><?php echo h($row['marca_modelo']); ?></h1>
            <span class="pill <?php echo h($stClass); ?>"><?php echo h($stLabel); ?></span>
          </div>
          <div class="page-subtitle"><?php echo h($row['matricula']); ?> • <?php echo h($row['tipo']); ?> • <?php echo h($row['combustivel']); ?></div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="edit.php?id=<?php echo (int)$id; ?>">Editar</a>
        <a class="btn btn-outline-danger" href="delete.php?id=<?php echo (int)$id; ?>">Apagar</a>
      </div>
    </div>
  </div>

  <!-- cards info -->
  <div class="row g-3">
    <div class="col-12 col-md-4">
      <div class="glass-card p-3">
        <div class="info-label">Motorista</div>
        <div class="info-value"><?php echo h($motorista); ?></div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="glass-card p-3">
        <div class="info-label">Quilometragem</div>
        <div class="info-value"><?php echo number_format((int)$row['quilometragem'], 0, ',', '.'); ?> km</div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="glass-card p-3">
        <div class="info-label">Combustível</div>
        <div class="info-value"><?php echo h($row['combustivel']); ?> • <?php echo (int)$fuel; ?>%</div>
      </div>
    </div>

    <div class="col-12 col-md-6">
      <div class="glass-card p-3">
        <div class="info-label">Localização</div>
        <div class="info-value"><?php echo h($loc); ?></div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="glass-card p-3">
        <div class="info-label">Última Manutenção</div>
        <div class="info-value"><?php echo $ultima ? h($ultima) : '—'; ?></div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="glass-card p-3">
        <div class="info-label">Próxima Manutenção</div>
        <div class="info-value"><?php echo $proxima ? h($proxima) : '—'; ?></div>
      </div>
    </div>
  </div>

  <!-- combustível -->
  <div class="glass-card p-4">
    <h3 class="section-title mb-3">Nível de Combustível</h3>
    <div class="fuel-track">
      <div class="fuel-bar <?php echo h($fuelClass); ?>" style="width: <?php echo (int)$fuel; ?>%;"></div>
    </div>
    <div class="page-subtitle mt-2"><?php echo (int)$fuel; ?>% do tanque</div>
  </div>

  <!-- abastecimentos -->
  <div class="glass-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="section-title mb-0">Histórico de Abastecimentos</h3>
      <a class="link-primary small" href="../abastecimento/index.php">Ver abastecimentos</a>
    </div>

    <?php if (count($abastecimentos) > 0): ?>
      <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
          <div class="glass-card p-3" style="background: rgba(226,232,240,.25);">
            <div class="info-label">Total abastecido</div>
            <div class="info-value"><?php echo number_format((float)$abastecimentosResumo['total_litros'], 2, ',', '.'); ?> L</div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="glass-card p-3" style="background: rgba(226,232,240,.25);">
            <div class="info-label">Custo total</div>
            <div class="info-value">€ <?php echo number_format((float)$abastecimentosResumo['total_custo'], 2, ',', '.'); ?></div>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="glass-card p-3" style="background: rgba(226,232,240,.25);">
            <div class="info-label">Preço médio/L</div>
            <div class="info-value">€ <?php echo number_format((float)$abastecimentosResumo['preco_medio'], 2, ',', '.'); ?></div>
          </div>
        </div>
      </div>

      <div class="vstack gap-2">
        <?php foreach ($abastecimentos as $a): ?>
          <?php
            $litros = (float)($a['litros'] ?? 0);
            $pl = (float)($a['preco_litro'] ?? 0);
            $totalA = (float)($a['total'] ?? 0);
            $dt = (string)($a['data_abastecimento'] ?? '');
          ?>
          <div class="list-row no-link">
            <div class="list-ico">⛽</div>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate">
                <?php echo number_format($litros, 0, ',', '.'); ?>L — <?php echo h($a['combustivel'] ?? ''); ?>
              </div>
              <div class="small text-muted text-truncate">
                📍 <?php echo h($a['posto'] ?? ''); ?>
                <?php if (!empty($a['observacoes'])): ?>
                  • <?php echo h($a['observacoes']); ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="text-end">
              <div class="fw-semibold">€ <?php echo number_format($totalA, 2, ',', '.'); ?></div>
              <div class="small text-muted"><?php echo h($dt); ?> • € <?php echo number_format($pl, 2, ',', '.'); ?>/L</div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-muted small text-center py-3">Nenhum abastecimento encontrado para este veículo.</div>
    <?php endif; ?>
  </div>

  <!-- histórico manutenção -->
  <div class="glass-card p-4">
    <h3 class="section-title mb-3">Histórico de Manutenção</h3>

    <?php if (count($hist) > 0): ?>
      <div class="vstack gap-2">
        <?php foreach ($hist as $m): ?>
          <?php
            $tipo = (string)($m['tipo'] ?? '—');
            $desc = (string)($m['descricao'] ?? '—');
            $dt = $m['data_fim'] ?: $m['data_inicio'];
            $st = (string)($m['status'] ?? '');
            $custo = (float)($m['custo'] ?? 0);

            $pillClass = 'badge-info-soft';
            if ($st === 'Concluída' || $st === 'Concluida') $pillClass = 'badge-success-soft';
            elseif (stripos($st, 'andamento') !== false) $pillClass = 'badge-warning-soft';
            elseif ($st === 'Agendada') $pillClass = 'badge-info-soft';
          ?>
          <div class="list-row no-link">
            <div class="list-ico warn">🛠️</div>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate"><?php echo h($desc); ?></div>
              <div class="small text-muted"><?php echo h($tipo); ?> • <?php echo $dt ? h($dt) : '—'; ?></div>
            </div>
            <div class="text-end">
              <div class="small fw-semibold">€ <?php echo number_format($custo, 2, ',', '.'); ?></div>
              <span class="badge-pill <?php echo $pillClass; ?>"><?php echo h($st ?: '—'); ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="text-muted small text-center py-3">Nenhum registo de manutenção.</div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
