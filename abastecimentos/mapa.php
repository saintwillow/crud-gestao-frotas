<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'mapa_abastecimentos';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$rows = [];
$sql = "SELECT
          a.id,
          a.posto,
          a.combustivel,
          a.litros,
          a.total,
          a.data_abastecimento,
          a.latitude,
          a.longitude,
          v.matricula,
          v.marca_modelo,
          c.nome AS colaborador_nome
        FROM abastecimentos a
        LEFT JOIN viaturas v ON v.id = a.viatura_id
        LEFT JOIN colaboradores c ON c.id = a.colaborador_id
        WHERE a.latitude IS NOT NULL
          AND a.longitude IS NOT NULL
        ORDER BY a.data_abastecimento DESC, a.id DESC";

$res = mysqli_query($ligacao, $sql);
if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = [
      'id' => (int)$r['id'],
      'posto' => (string)($r['posto'] ?? ''),
      'combustivel' => (string)($r['combustivel'] ?? ''),
      'litros' => (float)($r['litros'] ?? 0),
      'total' => (float)($r['total'] ?? 0),
      'data_abastecimento' => (string)($r['data_abastecimento'] ?? ''),
      'latitude' => (float)$r['latitude'],
      'longitude' => (float)$r['longitude'],
      'matricula' => (string)($r['matricula'] ?? ''),
      'marca_modelo' => (string)($r['marca_modelo'] ?? ''),
      'colaborador_nome' => (string)($r['colaborador_nome'] ?? '')
    ];
  }
}

$semLocalizacao = 0;
$rSem = mysqli_query($ligacao, "SELECT COUNT(*) AS total FROM abastecimentos WHERE latitude IS NULL OR longitude IS NULL");
if ($rSem) {
  $semLocalizacao = (int)(mysqli_fetch_assoc($rSem)['total'] ?? 0);
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
  #mapaAbastecimentos {
    height: 560px;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid rgba(15,23,42,.08);
  }
  .side-card {
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(15,23,42,.08);
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(15,23,42,.06);
  }
  .abast-item {
    display: block;
    padding: 12px 14px;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    border: 1px solid transparent;
  }
  .abast-item:hover {
    background: #f8fafc;
    border-color: rgba(15,23,42,.08);
  }
  .mini-label {
    font-size: 12px;
    color: #64748b;
  }
  .mini-value {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
  }
</style>

<div class="space-y-6">

  <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title">Mapa de Abastecimentos</h1>
      <div class="page-subtitle">Visualização geográfica dos abastecimentos registados</div>
    </div>

    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary">Voltar à lista</a>
      <a href="create.php" class="btn btn-primary">Novo abastecimento</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-9">
      <div class="side-card p-3">
        <div id="mapaAbastecimentos"></div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="side-card p-3 mb-3">
        <div class="mini-label">Com localização</div>
        <div class="mini-value"><?php echo count($rows); ?></div>
      </div>

      <div class="side-card p-3 mb-3">
        <div class="mini-label">Sem localização</div>
        <div class="mini-value"><?php echo $semLocalizacao; ?></div>
      </div>

      <div class="side-card p-3">
        <div class="fw-semibold mb-2">Últimos registos com mapa</div>

        <div class="d-flex flex-column gap-2">
          <?php if ($rows): ?>
            <?php foreach (array_slice($rows, 0, 6) as $r): ?>
              <a href="edit.php?id=<?php echo (int)$r['id']; ?>" class="abast-item">
                <div class="fw-semibold"><?php echo h($r['posto']); ?></div>
                <div class="small text-muted">
                  <?php echo h($r['matricula']); ?> · <?php echo h($r['data_abastecimento']); ?>
                </div>
                <div class="small text-muted">
                  <?php echo number_format((float)$r['litros'], 2, ',', '.'); ?> L ·
                  € <?php echo number_format((float)$r['total'], 2, ',', '.'); ?>
                </div>
              </a>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-muted small">Ainda não existem abastecimentos com coordenadas.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const rows = <?php echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const map = L.map('mapaAbastecimentos').setView([37.2301, -8.0653], 9);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);

  const bounds = [];

  rows.forEach((row) => {
    const html = `
      <div style="min-width:220px;">
        <strong>${row.posto}</strong><br>
        <span>${row.marca_modelo || ''} ${row.matricula ? '· ' + row.matricula : ''}</span><br>
        <span>${Number(row.litros).toFixed(2)} L · € ${Number(row.total).toFixed(2)}</span><br>
        <span>${row.combustivel} · ${row.data_abastecimento}</span>
        ${row.colaborador_nome ? '<br><span>Motorista: ' + row.colaborador_nome + '</span>' : ''}
      </div>
    `;

    L.marker([row.latitude, row.longitude]).addTo(map).bindPopup(html);
    bounds.push([row.latitude, row.longitude]);
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