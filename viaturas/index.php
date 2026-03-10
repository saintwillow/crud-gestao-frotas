<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'viaturas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$q = trim($_GET['q'] ?? '');
$where = "1=1";

if ($q !== '') {
  $qSafe = mysqli_real_escape_string($ligacao, $q);
  $where .= " AND (v.matricula LIKE '%$qSafe%' OR v.marca_modelo LIKE '%$qSafe%' OR v.tipo LIKE '%$qSafe%')";
}

$sql = "
  SELECT
    v.*,
    m.nome AS motorista_nome
  FROM viaturas v
  LEFT JOIN motoristas m ON m.viatura_id = v.id AND m.status='Ativo'
  WHERE $where
  ORDER BY v.id DESC
";

$res = mysqli_query($ligacao, $sql);
?>

<div class="page-max-6xl space-y-6">

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <div>
      <h1 class="page-title mb-1">Veículos</h1>
      <div class="page-subtitle">Gestão de viaturas da frota</div>
    </div>

    <a class="btn btn-primary" href="create.php">Novo Veículo</a>
  </div>

  <div class="glass-card p-3">
    <form class="row g-2 align-items-center" method="get">
      <div class="col-12 col-md-8">
        <div class="searchbox">
          <span class="search-ico"><i class="bi bi-search"></i></span>
          <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="Buscar por matrícula, modelo ou tipo...">
        </div>
      </div>
      <div class="col-12 col-md-4 d-flex gap-2 justify-content-end">
        <a class="btn btn-outline-secondary" href="index.php">Limpar</a>
        <button class="btn btn-outline-primary" type="submit">Filtrar</button>
      </div>
    </form>
  </div>

  <div class="vstack gap-3">
    <?php if ($res && mysqli_num_rows($res) > 0): ?>
      <?php while($v = mysqli_fetch_assoc($res)): ?>
        <?php
          $estado = trim((string)$v['estado']);
          $badge = 'badge-info-soft';
          $label = $estado ?: '—';

          if ($estado === 'Disponível') { $badge='badge-success-soft'; $label='Ativo'; }
          elseif ($estado === 'Atribuída') { $badge='badge-info-soft'; $label='Em rota'; }
          elseif ($estado === 'Em Manutenção') { $badge='badge-warning-soft'; $label='Manutenção'; }
          elseif ($estado === 'Inativo') { $badge='badge-danger-soft'; $label='Inativo'; }

          $motorista = $v['motorista_nome'] ?: 'Sem motorista';
        ?>

        <div class="glass-card p-3 vehicle-card-clean">
          <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div class="d-flex gap-3 align-items-start min-w-0">
              <div class="icon-box stat-gradient" style="width:52px;height:52px;">
                <i class="bi bi-car-front-fill"></i>
              </div>

              <div class="min-w-0">
                <a class="vehicle-title-link text-truncate" href="show.php?id=<?php echo (int)$v['id']; ?>">
                  <?php echo h($v['marca_modelo']); ?>
                </a>
                <div class="page-subtitle">
                  <?php echo h($v['matricula']); ?> • <?php echo h($v['tipo']); ?> • <?php echo h($v['combustivel']); ?>
                </div>
                <div class="small text-muted mt-1">Motorista: <?php echo h($motorista); ?></div>
              </div>
            </div>

            <div class="d-flex gap-2 align-items-center">
              <span class="badge-pill <?php echo $badge; ?>"><?php echo h($label); ?></span>
              <a class="btn btn-outline-primary btn-sm" href="edit.php?id=<?php echo (int)$v['id']; ?>">Editar</a>
              <a class="btn btn-outline-danger btn-sm" href="delete.php?id=<?php echo (int)$v['id']; ?>">Apagar</a>
            </div>
          </div>
        </div>

      <?php endwhile; ?>
    <?php else: ?>
      <div class="glass-card p-4 text-center text-muted">Nenhum veículo encontrado.</div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>