<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'viaturas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function badge_estado_class($estado) {
  switch ($estado) {
    case 'Disponível': return 'badge-disponivel';
    case 'Atribuída': return 'badge-atribuida';
    case 'Em Manutenção': return 'badge-manutencao';
    case 'Inativo': return 'badge-inativo';
    default: return 'badge-default';
  }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header("Location: index.php");
  exit;
}

pode_ver_viatura($ligacao, $id);

$sql = "
  SELECT
    v.*,
    i.nome AS infraestrutura_nome,
    i.tipo AS infraestrutura_tipo,
    i.sub_regiao AS infraestrutura_sub_regiao,
    i.localidade AS infraestrutura_localidade,
    i.concelho AS infraestrutura_concelho
  FROM viaturas v
  LEFT JOIN infraestruturas i ON i.id = v.infraestrutura_id
  WHERE v.id = $id
  LIMIT 1
";

$res = mysqli_query($ligacao, $sql);
$veiculo = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;

if (!$veiculo) {
  echo '<div class="page-max-4xl"><div class="glass-card p-4">Veículo não encontrado.</div></div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$observacoes = trim((string)($veiculo['observacoes'] ?? ''));
$infraestruturaNome = (string)($veiculo['infraestrutura_nome'] ?? '');
$infraestruturaTipo = (string)($veiculo['infraestrutura_tipo'] ?? '');
$infraestruturaSubRegiao = (string)($veiculo['infraestrutura_sub_regiao'] ?? '');
$infraestruturaLocalidade = (string)($veiculo['infraestrutura_localidade'] ?? '');
$infraestruturaConcelho = (string)($veiculo['infraestrutura_concelho'] ?? '');

$baseOperacional = trim(
  ($infraestruturaLocalidade !== '' ? $infraestruturaLocalidade : '') .
  ($infraestruturaConcelho !== '' ? ', ' . $infraestruturaConcelho : '')
);

// 1. Buscar condutor atual (atribuição ativa)
$condutor = null;
$stmtCond = mysqli_prepare($ligacao, "
  SELECT a.id AS atribuicao_id, a.data_inicio, a.km_inicio, m.id AS motorista_id, m.nome, m.telefone, m.email
  FROM atribuicoes a
  JOIN motoristas m ON m.id = a.motorista_id
  WHERE a.viatura_id = ? AND a.estado = 'aberta'
  LIMIT 1
");
mysqli_stmt_bind_param($stmtCond, "i", $id);
mysqli_stmt_execute($stmtCond);
$resCond = mysqli_stmt_get_result($stmtCond);
if ($resCond) $condutor = mysqli_fetch_assoc($resCond);
mysqli_stmt_close($stmtCond);

// 2. Buscar últimas 5 ocorrências
$ocorrencias = [];
$stmtOco = mysqli_prepare($ligacao, "
  SELECT id, codigo, titulo, gravidade, estado, criado_em
  FROM ocorrencias
  WHERE viatura_id = ?
  ORDER BY criado_em DESC
  LIMIT 5
");
mysqli_stmt_bind_param($stmtOco, "i", $id);
mysqli_stmt_execute($stmtOco);
$resOco = mysqli_stmt_get_result($stmtOco);
if ($resOco) {
  while ($row = mysqli_fetch_assoc($resOco)) {
    $ocorrencias[] = $row;
  }
}
mysqli_stmt_close($stmtOco);

// 3. Buscar últimas 5 manutenções
$manutencoes = [];
$stmtMan = mysqli_prepare($ligacao, "
  SELECT id, tipo, descricao, data_inicio, data_fim, custo, status
  FROM manutencoes
  WHERE viatura_id = ?
  ORDER BY data_inicio DESC, id DESC
  LIMIT 5
");
mysqli_stmt_bind_param($stmtMan, "i", $id);
mysqli_stmt_execute($stmtMan);
$resMan = mysqli_stmt_get_result($stmtMan);
if ($resMan) {
  while ($row = mysqli_fetch_assoc($resMan)) {
    $manutencoes[] = $row;
  }
}
mysqli_stmt_close($stmtMan);

// 4. Buscar todos os abastecimentos válidos para cálculo de médias
$totalLitros = 0.0;
$totalCusto = 0.0;
$validKmsList = [];

$stmtAbast = mysqli_prepare($ligacao, "
  SELECT litros, total, km_atual, preco_litro, data_abastecimento
  FROM abastecimentos
  WHERE viatura_id = ? AND estado <> 'anulado'
  ORDER BY km_atual ASC, data_abastecimento ASC, id ASC
");
mysqli_stmt_bind_param($stmtAbast, "i", $id);
mysqli_stmt_execute($stmtAbast);
$resAbast = mysqli_stmt_get_result($stmtAbast);
if ($resAbast) {
  while ($row = mysqli_fetch_assoc($resAbast)) {
    $totalLitros += (float)$row['litros'];
    $totalCusto += (float)$row['total'];
    if ($row['km_atual'] !== null && (int)$row['km_atual'] > 0) {
      $validKmsList[] = $row;
    }
  }
}
mysqli_stmt_close($stmtAbast);

$mediaConsumo = null;
$anomalyBadge = '';
$anomalyClass = '';
$anomalyDesc = '';

if (count($validKmsList) >= 2) {
  $first_km = (int)$validKmsList[0]['km_atual'];
  $last_km = (int)$validKmsList[count($validKmsList) - 1]['km_atual'];
  $diff_km = $last_km - $first_km;
  if ($diff_km > 0) {
    $litros_consumidos = 0.0;
    for ($i = 1; $i < count($validKmsList); $i++) {
      $litros_consumidos += (float)$validKmsList[$i]['litros'];
    }
    $mediaConsumo = ($litros_consumidos / $diff_km) * 100;
  }
}

if ($mediaConsumo !== null) {
  $tipoViatura = $veiculo['tipo'] ?? 'Ligeiro';
  $lowLimit = 4.5;
  $highLimit = 8.5;
  
  if ($tipoViatura === 'Ligeiro' || $tipoViatura === 'Elétrico' || $tipoViatura === 'Híbrido') {
    $lowLimit = 3.0;
    $highLimit = 10.0;
  } elseif ($tipoViatura === 'Pick-up' || $tipoViatura === 'Carrinha') {
    $lowLimit = 4.5;
    $highLimit = 14.5;
  } elseif ($tipoViatura === 'Camião') {
    $lowLimit = 10.0;
    $highLimit = 45.0;
  } else {
    $lowLimit = 3.5;
    $highLimit = 20.0;
  }

  if ($mediaConsumo < $lowLimit) {
    $anomalyBadge = '<span class="badge bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25 text-uppercase" style="font-size: 11px; padding: 4px 8px;"><i class="bi bi-info-circle-fill me-1"></i>Consumo Baixo</span>';
    $anomalyDesc = 'O consumo médio calculado (' . number_format($mediaConsumo, 2, ',', '.') . ' L/100km) está abaixo do normal esperado para um ' . mb_strtolower($tipoViatura) . '. Isto pode indicar dados inseridos incorretamente ou falta de registo de abastecimentos.';
  } elseif ($mediaConsumo > $highLimit) {
    $anomalyBadge = '<span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25 text-uppercase animate-pulse" style="font-size: 11px; padding: 4px 8px;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Consumo Elevado</span>';
    $anomalyDesc = 'Alerta: O consumo médio está acima do limite recomendado para um ' . mb_strtolower($tipoViatura) . '. Pode indicar fugas de combustível, problemas mecânicos (filtros entupidos, injetores, pressão de pneus) ou condução ineficiente.';
  } else {
    $anomalyBadge = '<span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25 text-uppercase" style="font-size: 11px; padding: 4px 8px;"><i class="bi bi-check-circle-fill me-1"></i>Consumo Normal</span>';
    $anomalyDesc = 'O consumo médio está dentro do intervalo esperado para esta classe de veículo.';
  }
} else {
  $anomalyBadge = '<span class="badge bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-25 text-uppercase" style="font-size: 11px; padding: 4px 8px;"><i class="bi bi-dash-circle-fill me-1"></i>Sem Média</span>';
}
?>

<style>
  .detail-grid {
    display: grid;
    grid-template-columns: repeat(1, minmax(0, 1fr));
    gap: 16px;
  }

  @media (min-width: 768px) {
    .detail-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
  }

  .detail-item {
    background: rgba(248, 250, 252, .85);
    border: 1px solid rgba(15, 23, 42, .06);
    border-radius: 16px;
    padding: 16px;
  }

  .detail-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 6px;
  }

  .detail-value {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
  }

  .hero-title-row {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }

  @media (min-width: 768px) {
    .hero-title-row {
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
    }
  }

  .vehicle-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border-radius: 999px;
    background: rgba(59, 130, 246, .08);
    color: #2563eb;
    padding: 8px 14px;
    font-size: 14px;
    font-weight: 700;
  }

  .badge-estado {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 700;
  }

  .badge-disponivel {
    background: rgba(34, 197, 94, .12);
    color: #16a34a;
  }

  .badge-atribuida {
    background: rgba(59, 130, 246, .12);
    color: #2563eb;
  }

  .badge-manutencao {
    background: rgba(245, 158, 11, .14);
    color: #d97706;
  }

  .badge-inativo {
    background: rgba(148, 163, 184, .18);
    color: #475569;
  }

  .badge-default {
    background: rgba(15, 23, 42, .08);
    color: #334155;
  }

  .badge-tipo {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 12px;
    font-weight: 700;
  }

  .badge-eta {
    background: rgba(34, 197, 94, .12);
    color: #16a34a;
  }

  .badge-etar {
    background: rgba(59, 130, 246, .12);
    color: #2563eb;
  }

  .observacoes-box {
    min-height: 120px;
    white-space: pre-line;
    color: #334155;
  }

  .empty-soft {
    color: #94a3b8;
    font-style: italic;
  }
</style>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="index.php">← Voltar aos veículos</a>

  <div class="hero-title-row">
    <div>
      <div class="vehicle-chip mb-2">
        <i class="bi bi-car-front-fill"></i>
        <?php echo h($veiculo['matricula'] ?? ''); ?>
      </div>

      <h1 class="page-title mb-1"><?php echo h($veiculo['marca_modelo'] ?? ''); ?></h1>
      <div class="page-subtitle">Detalhe completo do veículo da frota</div>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <?php if (in_array(perfil_atual(), ['admin', 'gestor'], true)): ?>
        <a class="btn btn-outline-primary" href="edit.php?id=<?php echo (int)$id; ?>">Editar</a>
        <a class="btn btn-outline-danger" href="delete.php?id=<?php echo (int)$id; ?>">Apagar</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="glass-card p-4">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
      <h3 class="section-title mb-0">Informações do veículo</h3>

      <span class="badge-estado <?php echo h(badge_estado_class($veiculo['estado'] ?? '')); ?>">
        <?php echo h($veiculo['estado'] ?? ''); ?>
      </span>
    </div>

    <div class="detail-grid">
      <div class="detail-item">
        <div class="detail-label">Matrícula</div>
        <div class="detail-value"><?php echo h($veiculo['matricula'] ?? ''); ?></div>
      </div>

      <div class="detail-item">
        <div class="detail-label">Marca / Modelo</div>
        <div class="detail-value"><?php echo h($veiculo['marca_modelo'] ?? ''); ?></div>
      </div>

      <div class="detail-item">
        <div class="detail-label">Tipo</div>
        <div class="detail-value">
          <?php echo $veiculo['tipo'] !== '' ? h($veiculo['tipo']) : '<span class="empty-soft">Não definido</span>'; ?>
        </div>
      </div>

      <div class="detail-item">
        <div class="detail-label">Combustível</div>
        <div class="detail-value">
          <?php echo $veiculo['combustivel'] !== '' ? h($veiculo['combustivel']) : '<span class="empty-soft">Não definido</span>'; ?>
        </div>
      </div>

      <div class="detail-item">
        <div class="detail-label">Quilometragem</div>
        <div class="detail-value"><?php echo number_format((int)($veiculo['quilometragem'] ?? 0), 0, ',', '.'); ?> km</div>
      </div>

      <div class="detail-item">
        <div class="detail-label">Estado atual</div>
        <div class="detail-value"><?php echo h($veiculo['estado'] ?? ''); ?></div>
      </div>
    </div>
  </div>

  <!-- Gestão de Combustível e Consumos -->
  <div class="glass-card p-4">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
      <h3 class="section-title mb-0">Gestão de Combustível e Consumos</h3>
      <div>
        <?php echo $anomalyBadge; ?>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-12 col-md-4">
        <div class="detail-item" style="height: 100%;">
          <div class="detail-label">Consumo Médio</div>
          <div class="detail-value">
            <?php echo $mediaConsumo !== null ? number_format($mediaConsumo, 2, ',', '.') . ' L/100km' : '<span class="text-muted small">Sem dados suficientes</span>'; ?>
          </div>
          <div class="small text-muted mt-1" style="font-size: 11px;">
            <?php echo $mediaConsumo !== null ? 'Calculado sobre ' . count($validKmsList) . ' registos' : 'Requer pelo menos 2 abastecimentos com Km'; ?>
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="detail-item" style="height: 100%;">
          <div class="detail-label">Total Abastecido</div>
          <div class="detail-value">
            <?php echo number_format($totalLitros, 1, ',', '.'); ?> L
          </div>
          <div class="small text-muted mt-1" style="font-size: 11px;">
            Total acumulado (válidos)
          </div>
        </div>
      </div>

      <div class="col-12 col-md-4">
        <div class="detail-item" style="height: 100%;">
          <div class="detail-label">Custo Acumulado</div>
          <div class="detail-value">
            € <?php echo number_format($totalCusto, 2, ',', '.'); ?>
          </div>
          <div class="small text-muted mt-1" style="font-size: 11px;">
            Total investido em combustível
          </div>
        </div>
      </div>
    </div>

    <?php if ($mediaConsumo !== null && !empty($anomalyDesc)): ?>
      <div class="p-3 rounded bg-dark bg-opacity-25 border border-secondary border-opacity-10" style="font-size: 13px;">
        <span class="text-muted small">Análise de Consumo:</span>
        <div class="fw-medium text-light mt-1"><?php echo h($anomalyDesc); ?></div>
      </div>
    <?php endif; ?>
  </div>

  <div class="glass-card p-4">
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
      <h3 class="section-title mb-0">Infraestrutura / Base operacional</h3>

      <a class="btn btn-outline-secondary btn-sm" href="<?php echo $BASE_URL; ?>/mapa-frota/index.php">
        Ver mapa da frota
      </a>
    </div>

    <?php if ($infraestruturaNome !== ''): ?>
      <div class="detail-grid">
        <div class="detail-item">
          <div class="detail-label">Infraestrutura</div>
          <div class="detail-value"><?php echo h($infraestruturaNome); ?></div>
        </div>

        <div class="detail-item">
          <div class="detail-label">Tipo</div>
          <div class="detail-value">
            <span class="badge-tipo <?php echo ($infraestruturaTipo === 'ETA') ? 'badge-eta' : 'badge-etar'; ?>">
              <?php echo h($infraestruturaTipo); ?>
            </span>
          </div>
        </div>

        <div class="detail-item">
          <div class="detail-label">Sub-região</div>
          <div class="detail-value"><?php echo h($infraestruturaSubRegiao); ?></div>
        </div>

        <div class="detail-item">
          <div class="detail-label">Base operacional</div>
          <div class="detail-value">
            <?php echo $baseOperacional !== '' ? h($baseOperacional) : '<span class="empty-soft">Não definida</span>'; ?>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="detail-item">
        <div class="detail-label">Infraestrutura</div>
        <div class="detail-value">
          <span class="empty-soft">Este veículo ainda não está atribuído a nenhuma ETA/ETAR.</span>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="glass-card p-4">
    <h3 class="section-title mb-3">Observações</h3>

    <div class="detail-item observacoes-box">
      <?php if ($observacoes !== ''): ?>
        <?php echo nl2br(h($observacoes)); ?>
      <?php else: ?>
        <span class="empty-soft">Sem observações registadas para este veículo.</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Condutor Atual -->
  <?php if ($condutor !== null): ?>
    <div class="glass-card p-4">
      <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
        <h3 class="section-title mb-0">Condutor Atribuído</h3>
        <?php if (in_array(perfil_atual(), ['admin', 'gestor'], true)): ?>
          <a class="btn btn-outline-danger btn-sm" href="../atribuicoes/encerrar.php?id=<?php echo (int)$condutor['atribuicao_id']; ?>">
            <i class="bi bi-lock-fill me-1"></i>Encerrar Atribuição
          </a>
        <?php endif; ?>
      </div>
      <div class="detail-grid">
        <div class="detail-item">
          <div class="detail-label">Nome do Motorista</div>
          <div class="detail-value"><?php echo h($condutor['nome']); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Contacto</div>
          <div class="detail-value">
            <?php echo $condutor['telefone'] ? h($condutor['telefone']) : '—'; ?> <br>
            <span class="small text-muted" style="font-size: 12px; font-weight: normal;"><?php echo $condutor['email'] ? h($condutor['email']) : '—'; ?></span>
          </div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Data de Início</div>
          <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($condutor['data_inicio'])); ?></div>
        </div>
        <div class="detail-item">
          <div class="detail-label">Km no Início</div>
          <div class="detail-value"><?php echo number_format($condutor['km_inicio'], 0, ',', '.'); ?> km</div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="glass-card p-4">
      <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
        <div>
          <h3 class="section-title mb-1">Condutor Atribuído</h3>
          <div class="text-muted small">Este veículo não possui motorista ativo no momento.</div>
        </div>
        <?php if (in_array(perfil_atual(), ['admin', 'gestor'], true)): ?>
          <a class="btn btn-primary btn-sm animate-pulse" href="../atribuicoes/create.php?viatura_id=<?php echo (int)$id; ?>">
            <i class="bi bi-link-45deg me-1"></i>Atribuir Viatura
          </a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Histórico Recente (Manutenções e Ocorrências) -->
  <div class="row g-3">
    <!-- Últimas Ocorrências -->
    <div class="col-12 col-lg-6">
      <div class="glass-card p-4 h-100">
        <h3 class="section-title mb-3">Últimas Ocorrências</h3>
        <?php if (count($ocorrencias) > 0): ?>
          <div class="vstack gap-2">
            <?php foreach ($ocorrencias as $oc): ?>
              <?php
                $pillOco = match($oc['estado']) {
                  'aberta' => 'bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25',
                  'em_analise' => 'bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25',
                  'resolvida' => 'bg-success bg-opacity-25 text-success border border-success border-opacity-25',
                  'rejeitada' => 'bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-25',
                  default => 'bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-25'
                };
                $gravOco = match($oc['gravidade']) {
                  'baixa' => 'bg-info bg-opacity-25 text-info border border-info border-opacity-25',
                  'media' => 'bg-primary bg-opacity-25 text-primary border border-primary border-opacity-25',
                  'alta' => 'bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25',
                  'critica' => 'bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25',
                  default => 'bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-25'
                };
              ?>
              <a href="../ocorrencias/show.php?id=<?php echo (int)$oc['id']; ?>" class="p-2 rounded d-flex justify-content-between align-items-center bg-dark bg-opacity-25 border border-secondary border-opacity-10 text-decoration-none hover-card">
                <div>
                  <div class="fw-semibold text-light small"><?php echo h($oc['codigo'] . " - " . $oc['titulo']); ?></div>
                  <div class="text-muted" style="font-size: 10px;"><?php echo date('d/m/Y H:i', strtotime($oc['criado_em'])); ?></div>
                </div>
                <div class="d-flex gap-1">
                  <span class="badge-pill <?php echo $gravOco; ?> text-uppercase" style="font-size: 9px; padding: 2px 6px;">
                    <?php echo h($oc['gravidade']); ?>
                  </span>
                  <span class="badge-pill <?php echo $pillOco; ?> text-uppercase" style="font-size: 9px; padding: 2px 6px;">
                    <?php echo h($oc['estado']); ?>
                  </span>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-muted small italic">Nenhuma ocorrência reportada para este veículo.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Últimas Manutenções -->
    <div class="col-12 col-lg-6">
      <div class="glass-card p-4 h-100">
        <h3 class="section-title mb-3">Últimas Manutenções</h3>
        <?php if (count($manutencoes) > 0): ?>
          <div class="vstack gap-2">
            <?php foreach ($manutencoes as $man): ?>
              <?php
                $pillMan = match(mb_strtolower($man['status'])) {
                  'concluída', 'concluida' => 'bg-success bg-opacity-25 text-success border border-success border-opacity-25',
                  'em andamento' => 'bg-warning bg-opacity-25 text-warning border border-warning border-opacity-25',
                  'agendada', 'pendente' => 'bg-info bg-opacity-25 text-info border border-info border-opacity-25',
                  'cancelada' => 'bg-danger bg-opacity-25 text-danger border border-danger border-opacity-25',
                  default => 'bg-secondary bg-opacity-25 text-secondary border border-secondary border-opacity-25'
                };
              ?>
              <div class="p-2 rounded d-flex justify-content-between align-items-center bg-dark bg-opacity-25 border border-secondary border-opacity-10">
                <div>
                  <div class="fw-semibold text-light small"><?php echo h($man['tipo'] . " - " . $man['descricao']); ?></div>
                  <div class="text-muted" style="font-size: 10px;">
                    <?php echo date('d/m/Y', strtotime($man['data_inicio'])); ?>
                    <?php if ($man['custo'] > 0): ?> • € <?php echo number_format($man['custo'], 0, ',', '.'); ?><?php endif; ?>
                  </div>
                </div>
                <span class="badge-pill <?php echo $pillMan; ?> text-uppercase" style="font-size: 9px; padding: 2px 6px;">
                  <?php echo h($man['status']); ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-muted small italic">Nenhuma manutenção registrada para este veículo.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="d-flex flex-wrap justify-content-end gap-2">
    <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
    <?php if (in_array(perfil_atual(), ['admin', 'gestor'], true)): ?>
      <a href="edit.php?id=<?php echo (int)$id; ?>" class="btn btn-primary">Editar veículo</a>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>