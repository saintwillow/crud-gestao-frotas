<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'mapa_frota';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function qs(array $changes = []) {
  $params = $_GET;

  foreach ($changes as $k => $v) {
    if ($v === null || $v === '') {
      unset($params[$k]);
    } else {
      $params[$k] = $v;
    }
  }

  $query = http_build_query($params);
  return $query ? ('?' . $query) : '';
}

function badge_estado_class($estado) {
  switch ((string)$estado) {
    case 'Disponível': return 'badge-disponivel';
    case 'Atribuída': return 'badge-atribuida';
    case 'Em Manutenção': return 'badge-manutencao';
    case 'Inativo': return 'badge-inativo';
    default: return 'badge-default';
  }
}

$infraestrutura_id = isset($_GET['infraestrutura_id']) && ctype_digit((string)$_GET['infraestrutura_id'])
  ? (int)$_GET['infraestrutura_id']
  : 0;

$sql = "
  SELECT
    i.id,
    i.nome,
    i.tipo,
    i.concelho,
    i.localidade,
    i.sub_regiao,
    i.latitude,
    i.longitude,
    COUNT(v.id) AS total_viaturas,
    SUM(CASE WHEN v.estado = 'Disponível' THEN 1 ELSE 0 END) AS disponiveis,
    SUM(CASE WHEN v.estado = 'Atribuída' THEN 1 ELSE 0 END) AS atribuidas,
    SUM(CASE WHEN v.estado = 'Em Manutenção' THEN 1 ELSE 0 END) AS manutencao,
    SUM(CASE WHEN v.estado = 'Inativo' THEN 1 ELSE 0 END) AS inativas
  FROM infraestruturas i
  LEFT JOIN viaturas v ON v.infraestrutura_id = i.id
  WHERE i.ativo = 1
  GROUP BY
    i.id, i.nome, i.tipo, i.concelho, i.localidade,
    i.sub_regiao, i.latitude, i.longitude
  ORDER BY i.sub_regiao ASC, i.tipo ASC, i.nome ASC
";

$res = mysqli_query($ligacao, $sql);

$infraestruturas = [];
$subRegioes = [];

if ($res) {
  while ($row = mysqli_fetch_assoc($res)) {
    $item = [
      'id' => (int)$row['id'],
      'nome' => (string)$row['nome'],
      'tipo' => (string)$row['tipo'],
      'concelho' => (string)($row['concelho'] ?? ''),
      'localidade' => (string)($row['localidade'] ?? ''),
      'sub_regiao' => (string)$row['sub_regiao'],
      'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
      'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
      'total_viaturas' => (int)$row['total_viaturas'],
      'disponiveis' => (int)$row['disponiveis'],
      'atribuidas' => (int)$row['atribuidas'],
      'manutencao' => (int)$row['manutencao'],
      'inativas' => (int)$row['inativas'],
    ];

    $infraestruturas[] = $item;

    $sr = $item['sub_regiao'];
    if (!isset($subRegioes[$sr])) {
      $subRegioes[$sr] = [
        'nome' => $sr,
        'infraestruturas' => 0,
        'viaturas' => 0
      ];
    }

    $subRegioes[$sr]['infraestruturas']++;
    $subRegioes[$sr]['viaturas'] += $item['total_viaturas'];
  }
}

$subRegioes = array_values($subRegioes);

$circulos = [
  [
    'nome' => 'Faro / Loulé / São Brás',
    'lat' => 37.0720,
    'lng' => -7.9450,
    'raio' => 24000,
    'cor' => '#1d6fd8'
  ],
  [
    'nome' => 'Lagos / Portimão',
    'lat' => 37.1750,
    'lng' => -8.5950,
    'raio' => 26000,
    'cor' => '#22a45a'
  ],
  [
    'nome' => 'Tavira / Olhão / VRSA',
    'lat' => 37.1250,
    'lng' => -7.7200,
    'raio' => 34000,
    'cor' => '#f59e0b'
  ],
  [
    'nome' => 'Albufeira / Silves',
    'lat' => 37.1100,
    'lng' => -8.2500,
    'raio' => 23000,
    'cor' => '#9333ea'
  ]
];

$infraSelecionada = null;
$viaturasInfra = [];

if ($infraestrutura_id > 0) {
  $resSel = mysqli_query($ligacao, "
    SELECT *
    FROM infraestruturas
    WHERE id = $infraestrutura_id AND ativo = 1
    LIMIT 1
  ");

  if ($resSel && mysqli_num_rows($resSel) > 0) {
    $infraSelecionada = mysqli_fetch_assoc($resSel);

    $resV = mysqli_query($ligacao, "
      SELECT id, matricula, marca_modelo, tipo, combustivel, quilometragem, estado
      FROM viaturas
      WHERE infraestrutura_id = $infraestrutura_id
      ORDER BY
        CASE estado
          WHEN 'Disponível' THEN 1
          WHEN 'Atribuída' THEN 2
          WHEN 'Em Manutenção' THEN 3
          WHEN 'Inativo' THEN 4
          ELSE 5
        END,
        marca_modelo ASC,
        matricula ASC
    ");

    if ($resV) {
      while ($row = mysqli_fetch_assoc($resV)) {
        $viaturasInfra[] = $row;
      }
    }
  }
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
  #fleetMap {
    height: 500px;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid rgba(15,23,42,.08);
  }

  .region-card {
    background: rgba(255,255,255,.96);
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 10px 28px rgba(15,23,42,.05);
    height: 100%;
  }

  .region-dot {
    width: 12px;
    height: 12px;
    border-radius: 999px;
    display: inline-block;
    margin-right: 8px;
  }

  .region-number {
    font-size: 42px;
    font-weight: 800;
    line-height: 1;
    color: #0f172a;
    margin: 10px 0 8px;
  }

  .table-soft th {
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
  }

  .table-soft td {
    vertical-align: middle;
  }

  .badge-mini {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
  }

  .badge-eta {
    background: rgba(34,197,94,.12);
    color: #16a34a;
  }

  .badge-etar {
    background: rgba(59,130,246,.12);
    color: #2563eb;
  }

  .soft-panel {
    background: rgba(255,255,255,.96);
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 10px 28px rgba(15,23,42,.05);
  }

  .infra-card {
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(248,250,252,.92);
    border-radius: 16px;
    padding: 16px;
    height: 100%;
  }

  .infra-card.active {
    border-color: rgba(37,99,235,.35);
    background: rgba(239,246,255,.95);
  }

  .vehicle-list-card {
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(248,250,252,.92);
    border-radius: 16px;
    padding: 16px;
    height: 100%;
  }

  .badge-estado {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
  }

  .badge-disponivel {
    background: rgba(34,197,94,.12);
    color: #16a34a;
  }

  .badge-atribuida {
    background: rgba(59,130,246,.12);
    color: #2563eb;
  }

  .badge-manutencao {
    background: rgba(245,158,11,.14);
    color: #d97706;
  }

  .badge-inativo {
    background: rgba(148,163,184,.18);
    color: #475569;
  }

  .badge-default {
    background: rgba(15,23,42,.08);
    color: #334155;
  }

  .small-muted {
    color: #64748b;
    font-size: 13px;
  }
</style>

<div class="space-y-6">

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="page-title">Mapa da Frota — Algarve</h1>
      <div class="page-subtitle">Distribuição de veículos e infraestruturas por sub-região</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo $BASE_URL; ?>/viaturas/index.php" class="btn btn-outline-secondary">Ver viaturas</a>
      <?php if ($infraSelecionada): ?>
        <a href="index.php" class="btn btn-outline-primary">Limpar seleção</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php
      $cores = [
        'Faro / Loulé / São Brás' => '#1d6fd8',
        'Lagos / Portimão' => '#22a45a',
        'Tavira / Olhão / VRSA' => '#f59e0b',
        'Albufeira / Silves' => '#9333ea',
      ];
    ?>
    <?php foreach ($subRegioes as $sr): ?>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="region-card">
          <div class="fw-semibold">
            <span class="region-dot" style="background: <?php echo h($cores[$sr['nome']] ?? '#64748b'); ?>;"></span>
            <?php echo h($sr['nome']); ?>
          </div>

          <div class="region-number"><?php echo (int)$sr['viaturas']; ?></div>

          <div class="text-muted small">
            <?php echo (int)$sr['infraestruturas']; ?> infraestruturas nesta sub-região
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="glass-card p-3 mb-4">
    <div id="fleetMap"></div>
  </div>

  <?php if ($infraSelecionada): ?>
    <div class="soft-panel mb-4">
      <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-3">
        <div>
          <h3 class="section-title mb-1">
            <?php echo h($infraSelecionada['nome'] ?? ''); ?>
          </h3>
          <div class="small-muted">
            <?php echo h(($infraSelecionada['tipo'] ?? '') . ' • ' . ($infraSelecionada['sub_regiao'] ?? '')); ?>
            <?php if (($infraSelecionada['localidade'] ?? '') !== '' || ($infraSelecionada['concelho'] ?? '') !== ''): ?>
              · <?php echo h(trim(($infraSelecionada['localidade'] ?? '') . (($infraSelecionada['concelho'] ?? '') !== '' ? ', ' . $infraSelecionada['concelho'] : ''))); ?>
            <?php endif; ?>
          </div>
        </div>

        <a href="index.php" class="btn btn-outline-secondary btn-sm">Fechar seleção</a>
      </div>

      <div class="row g-3">
        <?php if ($viaturasInfra): ?>
          <?php foreach ($viaturasInfra as $v): ?>
            <div class="col-12 col-lg-6">
              <div class="vehicle-list-card">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                  <div>
                    <div class="fw-bold"><?php echo h($v['marca_modelo'] ?? ''); ?></div>
                    <div class="small-muted"><?php echo h($v['matricula'] ?? ''); ?></div>
                  </div>

                  <span class="badge-estado <?php echo h(badge_estado_class($v['estado'] ?? '')); ?>">
                    <?php echo h($v['estado'] ?? ''); ?>
                  </span>
                </div>

                <div class="small-muted mb-2">
                  <strong>Tipo:</strong>
                  <?php echo ($v['tipo'] ?? '') !== '' ? h($v['tipo']) : 'Não definido'; ?>
                </div>

                <div class="small-muted mb-2">
                  <strong>Combustível:</strong>
                  <?php echo ($v['combustivel'] ?? '') !== '' ? h($v['combustivel']) : 'Não definido'; ?>
                </div>

                <div class="small-muted mb-3">
                  <strong>Quilometragem:</strong>
                  <?php echo number_format((int)($v['quilometragem'] ?? 0), 0, ',', '.'); ?> km
                </div>

                <div class="d-flex gap-2 flex-wrap">
                  <a class="btn btn-primary btn-sm" href="<?php echo $BASE_URL; ?>/viaturas/show.php?id=<?php echo (int)$v['id']; ?>">Ver detalhe</a>
                  <a class="btn btn-outline-secondary btn-sm" href="<?php echo $BASE_URL; ?>/viaturas/edit.php?id=<?php echo (int)$v['id']; ?>">Editar</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12">
            <div class="vehicle-list-card text-muted">
              Ainda não existem viaturas atribuídas a esta infraestrutura.
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="section-title mb-0">Infraestruturas</h3>
      <div class="small text-muted"><?php echo count($infraestruturas); ?> registos</div>
    </div>

    <div class="row g-3">
      <?php if ($infraestruturas): ?>
        <?php foreach ($infraestruturas as $i): ?>
          <?php
            $isActive = ($infraestrutura_id > 0 && $infraestrutura_id === (int)$i['id']);
            $moradaBase = trim(($i['localidade'] ?: '') . ($i['concelho'] ? ', ' . $i['concelho'] : ''));
          ?>
          <div class="col-12 col-xl-6">
            <div class="infra-card <?php echo $isActive ? 'active' : ''; ?>">
              <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <div class="fw-bold"><?php echo h($i['nome']); ?></div>
                  <div class="small-muted"><?php echo h($i['sub_regiao']); ?></div>
                </div>

                <span class="badge-mini <?php echo $i['tipo'] === 'ETA' ? 'badge-eta' : 'badge-etar'; ?>">
                  <?php echo h($i['tipo']); ?>
                </span>
              </div>

              <div class="small-muted mb-2">
                <strong>Base:</strong>
                <?php echo $moradaBase !== '' ? h($moradaBase) : 'Não definida'; ?>
              </div>

              <div class="small-muted mb-2">
                <strong>Viaturas:</strong> <?php echo (int)$i['total_viaturas']; ?>
              </div>

              <div class="small-muted mb-3">
                <strong>Estado:</strong>
                <?php echo (int)$i['disponiveis']; ?> disponíveis,
                <?php echo (int)$i['atribuidas']; ?> atribuídas,
                <?php echo (int)$i['manutencao']; ?> em manutenção,
                <?php echo (int)$i['inativas']; ?> inativas
              </div>

              <div class="d-flex gap-2 flex-wrap">
                <a href="index.php<?php echo qs(['infraestrutura_id' => (int)$i['id']]); ?>" class="btn btn-primary btn-sm">
                  Ver viaturas desta base
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="text-center text-muted py-4">Ainda não existem infraestruturas cadastradas.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const infraestruturas = <?php echo json_encode($infraestruturas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const circulos = <?php echo json_encode($circulos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const selectedInfraId = <?php echo (int)$infraestrutura_id; ?>;

  const map = L.map('fleetMap').setView([37.1700, -8.0500], 9);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  circulos.forEach((item) => {
    L.circle([item.lat, item.lng], {
      radius: item.raio,
      color: item.cor,
      fillColor: item.cor,
      fillOpacity: 0.08,
      weight: 2,
      dashArray: '8, 6'
    }).addTo(map).bindPopup('<strong>' + item.nome + '</strong>');
  });

  const bounds = [];

  infraestruturas.forEach((item) => {
    if (item.latitude === null || item.longitude === null) return;

    const isSelected = Number(item.id) === Number(selectedInfraId);
    const color = item.tipo === 'ETA' ? '#16a34a' : '#2563eb';

    const marker = L.circleMarker([item.latitude, item.longitude], {
      radius: isSelected ? 11 : 8,
      color: color,
      fillColor: color,
      fillOpacity: isSelected ? 0.95 : 0.75,
      weight: isSelected ? 3 : 2
    }).addTo(map);

    const link = 'index.php?infraestrutura_id=' + encodeURIComponent(item.id);

    const html = `
      <div style="min-width:250px;">
        <strong>${item.nome}</strong><br>
        <span>${item.tipo} · ${item.sub_regiao}</span><br>
        <span>${item.localidade || ''}${item.concelho ? ', ' + item.concelho : ''}</span><br>
        <span>Viaturas: ${item.total_viaturas}</span><br>
        <span>Disponíveis: ${item.disponiveis} | Atribuídas: ${item.atribuidas}</span><br>
        <span>Manutenção: ${item.manutencao} | Inativas: ${item.inativas}</span><br><br>
        <a href="${link}" class="btn btn-sm btn-primary">Ver viaturas desta base</a>
      </div>
    `;

    marker.bindPopup(html);

    if (isSelected) {
      marker.openPopup();
    }

    bounds.push([item.latitude, item.longitude]);
  });

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [30, 30] });
  }

  setTimeout(function () {
    map.invalidateSize();
  }, 200);
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>