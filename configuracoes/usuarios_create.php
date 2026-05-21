<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_perfil(['admin']);

$active = 'config';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$erro = '';
$nome = $username = '';
$perfil = 'operario';
$ativo = 1;
$motorista_id_sel = '';

// Lista de motoristas sem utilizador associado (ou nenhum)
$motoristas_disp = [];
$rMot = mysqli_query($ligacao,
  "SELECT m.id, m.nome FROM motoristas m
   WHERE m.status='Ativo'
     AND m.id NOT IN (SELECT motorista_id FROM usuarios WHERE motorista_id IS NOT NULL)
   ORDER BY m.nome ASC"
);
if ($rMot) while ($r = mysqli_fetch_assoc($rMot)) $motoristas_disp[] = $r;
$motorista_id = '';

// Carregar motoristas disponíveis (sem utilizador associado)
$motoristas = [];
$resM = mysqli_query($ligacao,
  "SELECT m.id, m.nome FROM motoristas m
   WHERE m.status='Ativo'
     AND m.id NOT IN (SELECT motorista_id FROM usuarios WHERE motorista_id IS NOT NULL)
   ORDER BY m.nome ASC"
);
if ($resM) while ($r = mysqli_fetch_assoc($resM)) $motoristas[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome         = trim($_POST['nome'] ?? '');
  $username     = trim($_POST['username'] ?? '');
  $senha        = (string)($_POST['senha'] ?? '');
  $perfil       = strtolower(trim($_POST['perfil'] ?? 'operario'));
  $ativo        = isset($_POST['ativo']) ? 1 : 0;
  $motorista_id = trim($_POST['motorista_id'] ?? '');

  if ($nome === '' || $username === '' || $senha === '') {
    $erro = 'Preencha nome, username e senha.';
  } elseif (strlen($senha) < 6) {
    $erro = 'A senha deve ter pelo menos 6 caracteres.';
  } elseif (!in_array($perfil, ['admin','gestor','operario'], true)) {
    $erro = 'Perfil inválido.';
  } else {
    $chk = mysqli_prepare($ligacao, "SELECT id FROM usuarios WHERE username=? LIMIT 1");
    mysqli_stmt_bind_param($chk, "s", $username);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    $dup = mysqli_stmt_num_rows($chk) > 0;
    mysqli_stmt_close($chk);

    if ($dup) {
      $erro = 'Já existe um utilizador com esse username.';
    } else {
      $hash   = password_hash($senha, PASSWORD_BCRYPT);
      $mid    = ($motorista_id !== '' && (int)$motorista_id > 0) ? (int)$motorista_id : null;

      $ins = mysqli_prepare($ligacao,
        "INSERT INTO usuarios (nome, username, senha, perfil, ativo, motorista_id) VALUES (?, ?, ?, ?, ?, ?)"
      );
      mysqli_stmt_bind_param($ins, "ssssii", $nome, $username, $hash, $perfil, $ativo, $mid);

      if (mysqli_stmt_execute($ins)) {
        header("Location: usuarios.php?ok=1");
        exit;
      } else {
        $erro = 'Erro ao criar utilizador: ' . mysqli_error($ligacao);
      }
      mysqli_stmt_close($ins);
    }
  }
}
?>

<div class="page-max-6xl space-y-6">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1 class="page-title mb-1">Novo utilizador</h1>
      <div class="page-subtitle">Criar conta e definir perfil</div>
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
        <label class="form-label">Senha</label>
        <input type="password" class="form-control" name="senha" required placeholder="Mínimo 6 caracteres">
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

      <!-- Associação a motorista — só relevante para operário -->
      <div class="col-12" id="blocoMotorista">
        <label class="form-label">Motorista associado <span class="text-muted">(obrigatório para perfil Operário)</span></label>
        <select class="form-select" name="motorista_id">
          <option value="">Sem associação</option>
          <?php foreach ($motoristas as $m): ?>
            <option value="<?php echo (int)$m['id']; ?>" <?php echo ($motorista_id==(string)$m['id'])?'selected':''; ?>>
              <?php echo h($m['nome']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($motoristas)): ?>
          <div class="form-text text-warning">Não há motoristas ativos disponíveis para associar.</div>
        <?php else: ?>
          <div class="form-text">Associa o utilizador ao motorista correspondente para que veja os seus próprios dados.</div>
        <?php endif; ?>
      </div>

      <?php if ($perfil === 'operario' || true): ?>
      <div class="col-12">
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
      <?php endif; ?>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Salvar</button>
        <a class="btn btn-outline-secondary" href="usuarios.php">Cancelar</a>
      </div>

    </form>
  </div>
</div>

<script>
  const sel = document.getElementById('selectPerfil');
  const bloco = document.getElementById('blocoMotorista');
  function toggleMotorista() {
    bloco.style.display = sel.value === 'operario' ? '' : 'none';
  }
  sel.addEventListener('change', toggleMotorista);
  toggleMotorista();
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
