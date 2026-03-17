<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

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
      <a class="btn btn-outline-primary" href="edit.php?id=<?php echo (int)$id; ?>">Editar</a>
      <a class="btn btn-outline-danger" href="delete.php?id=<?php echo (int)$id; ?>">Apagar</a>
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

  <div class="d-flex flex-wrap justify-content-end gap-2">
    <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
    <a href="edit.php?id=<?php echo (int)$id; ?>" class="btn btn-primary">Editar veículo</a>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>