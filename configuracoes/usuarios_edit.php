<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_perfil(['admin']);

$active = 'config';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  echo '<div class="glass-card p-4 text-center text-muted">ID inválido.</div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$erro = '';

$stmt = mysqli_prepare($ligacao, "SELECT id, nome, username, perfil, ativo, motorista_id, infraestrutura_id FROM usuarios WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$u   = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$u) {
  echo '<div class="glass-card p-4 text-center text-muted">Utilizador não encontrado.</div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$nome     = $u['nome'];
$username = $u['username'];
$perfil   = strtolower(trim($u['perfil']));
$ativo    = (int)$u['ativo'];
$motorista_id_sel = $u['motorista_id'] ?? '';
$infraestrutura_id_sel = $u['infraestrutura_id'] ?? '';

// Motoristas disponíveis: sem utilizador OU o que já está associado a este user
$motoristas_disp = [];
$rMot = mysqli_prepare($ligacao,
  "SELECT m.id, m.nome FROM motoristas m
   WHERE m.status='Ativo'
     AND (m.id NOT IN (SELECT motorista_id FROM usuarios WHERE motorista_id IS NOT NULL AND id<>?)
          OR m.id = ?)
   ORDER BY m.nome ASC"
);
mysqli_stmt_bind_param($rMot, "ii", $id, $motorista_id_sel);
mysqli_stmt_execute($rMot);
$rMotRes = mysqli_stmt_get_result($rMot);
if ($rMotRes) while ($r = mysqli_fetch_assoc($rMotRes)) $motoristas_disp[] = $r;
mysqli_stmt_close($rMot);

// Carregar todas as infraestruturas ativas para o select de gestores
$infraestruturas = [];
$resI = mysqli_query($ligacao, "SELECT id, nome, tipo FROM infraestruturas WHERE ativo = 1 ORDER BY nome ASC");
if ($resI) while ($r = mysqli_fetch_assoc($resI)) $infraestruturas[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome      = trim($_POST['nome'] ?? '');
  $username  = trim($_POST['username'] ?? '');
  $perfil    = strtolower(trim($_POST['perfil'] ?? 'operario'));
  $ativo     = isset($_POST['ativo']) ? 1 : 0;
  $novaSenha = (string)($_POST['senha'] ?? '');
  $motorista_id_sel = trim($_POST['motorista_id'] ?? '');
  $infraestrutura_id_sel = trim($_POST['infraestrutura_id'] ?? '');

  if ($nome === '' || $username === '') {
    $erro = 'Preencha nome e username.';
  } elseif ($novaSenha !== '' && strlen($novaSenha) < 6) {
    $erro = 'A nova senha deve ter pelo menos 6 caracteres.';
  } elseif (!in_array($perfil, ['admin','gestor','operario'], true)) {
    $erro = 'Perfil inválido.';
  } else {
    $chk = mysqli_prepare($ligacao, "SELECT id FROM usuarios WHERE username=? AND id<>? LIMIT 1");
    mysqli_stmt_bind_param($chk, "si", $username, $id);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    $dup = mysqli_stmt_num_rows($chk) > 0;
    mysqli_stmt_close($chk);

    if ($dup) {
      $erro = 'Já existe outro utilizador com esse username.';
    } else {
      $mid_val = ($motorista_id_sel !== '' && (int)$motorista_id_sel > 0) ? (int)$motorista_id_sel : null;
      $infra_val = ($infraestrutura_id_sel !== '' && (int)$infraestrutura_id_sel > 0) ? (int)$infraestrutura_id_sel : null;
      
      if ($novaSenha !== '') {
        $novoHash = password_hash($novaSenha, PASSWORD_BCRYPT);
        $upd = mysqli_prepare($ligacao,
          "UPDATE usuarios SET nome=?, username=?, perfil=?, ativo=?, senha=?, motorista_id=?, infraestrutura_id=? WHERE id=? LIMIT 1"
        );
        mysqli_stmt_bind_param($upd, "sssisiii", $nome, $username, $perfil, $ativo, $novoHash, $mid_val, $infra_val, $id);
      } else {
        $upd = mysqli_prepare($ligacao,
          "UPDATE usuarios SET nome=?, username=?, perfil=?, ativo=?, motorista_id=?, infraestrutura_id=? WHERE id=? LIMIT 1"
        );
        mysqli_stmt_bind_param($upd, "sssisiii", $nome, $username, $perfil, $ativo, $mid_val, $infra_val, $id);
      }

      if (mysqli_stmt_execute($upd)) {
        header("Location: usuarios.php?updated=1");
        exit;
      } else {
        $erro = 'Erro ao atualizar: ' . mysqli_error($ligacao);
      }
      mysqli_stmt_close($upd);
    }
  }
}
?>

<div class="page-max-6xl space-y-6">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1 class="page-title mb-1">Editar utilizador</h1>
      <div class="page-subtitle">@<?php echo h($u['username']); ?></div>
    </div>
    <a class="btn btn-outline-secondary" href="usuarios.php">Voltar</a>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo h($erro); ?></div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <form method="post" class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label">Nome</label>
        <input class="form-control" name="nome" value="<?php echo h($nome); ?>" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Username</label>
        <input class="form-control" name="username" value="<?php echo h($username); ?>" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Nova senha <span class="text-muted">(opcional — mínimo 6 caracteres)</span></label>
        <input type="password" class="form-control" name="senha" placeholder="Deixa vazio para não alterar">
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label">Perfil</label>
        <select class="form-select" name="perfil" id="selectPerfil">
          <option value="operario" <?php echo $perfil==='operario'?'selected':''; ?>>Operário</option>
          <option value="gestor"   <?php echo $perfil==='gestor'?'selected':''; ?>>Gestor</option>
          <option value="admin"    <?php echo $perfil==='admin'?'selected':''; ?>>Admin</option>
        </select>
      </div>

      <div class="col-12 col-md-3 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?php echo $ativo?'checked':''; ?>>
          <label class="form-check-label" for="ativo">Ativo</label>
        </div>
      </div>

      <div class="col-12" id="blocoMotorista">
        <label class="form-label">Motorista associado <span class="text-muted">(obrigatório para operários)</span></label>
        <select class="form-select" name="motorista_id">
          <option value="">Sem associação</option>
          <?php foreach ($motoristas_disp as $mt): ?>
            <option value="<?php echo (int)$mt['id']; ?>" <?php echo ((string)$motorista_id_sel===(string)$mt['id'])?'selected':''; ?>>
              <?php echo h($mt['nome']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Associe ao motorista correspondente na base de dados.</div>
      </div>

      <!-- Associação a base operacional — só relevante para gestor -->
      <div class="col-12" id="blocoBase">
        <label class="form-label">Base Operacional Associada <span class="text-muted">(Opcional - deixar vazio para Gestor Global)</span></label>
        <select class="form-select" name="infraestrutura_id">
          <option value="">Sem associação (Gestor Global)</option>
          <?php foreach ($infraestruturas as $infra): ?>
            <option value="<?php echo (int)$infra['id']; ?>" <?php echo ($infraestrutura_id_sel===(string)$infra['id'] || $infraestrutura_id_sel===(int)$infra['id'])?'selected':''; ?>>
              <?php echo h($infra['nome'] . ' (' . ucfirst($infra['tipo']) . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Restringe o acesso deste gestor apenas às viaturas e abastecimentos associados a esta base operacional.</div>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Salvar</button>
        <a class="btn btn-outline-secondary" href="usuarios.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<script>
  const sel = document.getElementById('selectPerfil');
  const blocoMotorista = document.getElementById('blocoMotorista');
  const blocoBase = document.getElementById('blocoBase');
  function toggleCamposPerfil() {
    blocoMotorista.style.display = sel.value === 'operario' ? '' : 'none';
    blocoBase.style.display = sel.value === 'gestor' ? '' : 'none';
  }
  sel.addEventListener('change', toggleCamposPerfil);
  toggleCamposPerfil();
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
