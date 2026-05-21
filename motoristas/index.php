<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'motoristas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$where = "1=1";
if ($q !== '') {
  $q_safe = mysqli_real_escape_string($ligacao, $q);
  $where .= " AND (
    m.nome LIKE '%$q_safe%' OR
    m.nif LIKE '%$q_safe%' OR
    m.cc LIKE '%$q_safe%' OR
    m.carta_numero LIKE '%$q_safe%' OR
    m.email LIKE '%$q_safe%'
  )";
}

$sql = "
  SELECT
    m.*,
    v.matricula AS v_matricula,
    v.marca_modelo AS v_modelo
  FROM motoristas m
  LEFT JOIN viaturas v ON v.id = m.viatura_id
  WHERE $where
  ORDER BY (m.status='Ativo') DESC, m.nome ASC
";
$res = mysqli_query($ligacao, $sql);

$total = 0;
if ($res) $total = mysqli_num_rows($res);

function initials($name){
  $name = trim((string)$name);
  if ($name === '') return '—';
  $parts = preg_split('/\s+/', $name);
  $a = strtoupper(mb_substr($parts[0] ?? '', 0, 1));
  $b = strtoupper(mb_substr($parts[1] ?? $parts[0] ?? '', 0, 1));
  return $a . $b;
}
function pillClass($status){
  return ($status === 'Inativo') ? 'pill pill-danger' : 'pill pill-success';
}
function fmtDate($d){
  if (!$d) return '—';
  $ts = strtotime($d);
  return $ts ? date('Y-m-d', $ts) : '—';
}
?>

<div class="page-max-6xl space-y-6">

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
    <div>
      <h1 class="page-title">Motoristas</h1>
      <div class="page-subtitle"><?php echo (int)$total; ?> motoristas cadastrados</div>
    </div>

    <div class="d-flex gap-2">
      <?php if (in_array(perfil_atual(), ['admin', 'gestor'], true)): ?>
        <a href="create.php" class="btn btn-primary">Novo Motorista</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="glass-card p-3">
    <form class="d-flex gap-2 flex-wrap align-items-center" method="get">
      <div class="searchbox flex-grow-1" style="min-width:260px;">
        <span class="search-ico"><i class="bi bi-search"></i></span>
        <input class="form-control" name="q" value="<?php echo h($q); ?>"
               placeholder="Buscar por nome, NIF ou Carta...">
      </div>
      <a class="btn btn-outline-secondary" href="index.php">Limpar</a>
    </form>
  </div>

  <div class="row g-3">
    <?php if ($res && mysqli_num_rows($res) > 0): ?>
      <?php while($m = mysqli_fetch_assoc($res)): ?>
        <?php
          $id = (int)$m['id'];
          $status = (string)$m['status'];
          $sigla = initials($m['nome'] ?? '');
          $cartaLinha = trim((string)($m['carta_categoria'] ?? ''));
          $cartaLinha = $cartaLinha ? "Carta: {$m['carta_categoria']} • {$m['viagens']} viagens" : "{$m['viagens']} viagens";
          $vinculo = '';
          if (!empty($m['v_matricula'])) {
            $vinculo = $m['v_matricula'] . " — " . ($m['v_modelo'] ?? '');
          }
        ?>

        <div class="col-12 col-md-6 col-xl-4">
          <div class="glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="d-flex gap-3 min-w-0">
                <div class="avatar-soft"><?php echo h($sigla); ?></div>

                <div class="min-w-0">
                  <div class="fw-bold text-truncate"><?php echo h($m['nome']); ?></div>
                  <div class="small text-muted"><?php echo h($cartaLinha); ?></div>
                </div>
              </div>

              <span class="<?php echo pillClass($status); ?>"><?php echo h($status); ?></span>
            </div>

            <div class="driver-lines mt-3">
              <div class="driver-line">
                <span class="driver-ico"><i class="bi bi-person-vcard"></i></span>
                <span class="text-muted">NIF:</span>
                <span class="ms-1 fw-semibold"><?php echo h($m['nif'] ?: '—'); ?></span>
              </div>

              <div class="driver-line">
                <span class="driver-ico"><i class="bi bi-card-text"></i></span>
                <span class="text-muted">CC:</span>
                <span class="ms-1 fw-semibold"><?php echo h($m['cc'] ?: '—'); ?></span>
              </div>

              <div class="driver-line">
                <span class="driver-ico"><i class="bi bi-telephone-fill"></i></span>
                <span class="ms-1"><?php echo h($m['telefone'] ?: '—'); ?></span>
              </div>

              <div class="driver-line">
                <span class="driver-ico"><i class="bi bi-envelope-fill"></i></span>
                <span class="ms-1 text-truncate"><?php echo h($m['email'] ?: '—'); ?></span>
              </div>

              <div class="driver-line">
                <span class="driver-ico"><i class="bi bi-car-front-fill"></i></span>
                <span class="ms-1 text-truncate"><?php echo h($vinculo ?: 'Sem viatura atribuída'); ?></span>
              </div>
            </div>

            <div class="driver-footer mt-3 pt-3">
              <div class="small text-muted">Carta válida até: <?php echo h(fmtDate($m['carta_validade'] ?? null)); ?></div>
              <div class="small text-muted">Desde <?php echo h(fmtDate($m['desde'] ?? null)); ?></div>
            </div>

            <div class="d-flex gap-2 mt-3">
              <?php if (in_array(perfil_atual(), ['admin', 'gestor'], true)): ?>
                <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?php echo $id; ?>">Editar</a>
                <a class="btn btn-sm btn-outline-danger" href="delete.php?id=<?php echo $id; ?>">Apagar</a>
              <?php endif; ?>
            </div>
          </div>
        </div>

      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="glass-card p-4 text-center text-muted">Nenhum motorista encontrado.</div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>