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
$infraestrutura_id_sel = '';
$nivel_gestao = 'global';
$zona_operacional_id_sel = '';

// Se veio do atalho de "Criar Acesso" no motoristas/index.php
$motorista_id = isset($_GET['motorista_id']) ? (string)(int)$_GET['motorista_id'] : '';
if ($motorista_id !== '') {
    $rName = mysqli_query($ligacao, "SELECT nome, zona_operacional_id FROM motoristas WHERE id = " . (int)$motorista_id . " LIMIT 1");
    if ($rName && $rowN = mysqli_fetch_assoc($rName)) {
        $nome = $rowN['nome'];
        $motorista_id_sel = $motorista_id;
        $zona_operacional_id_sel = $rowN['zona_operacional_id'] ?? '';
    }
}

// Carregar motoristas disponíveis (sem utilizador associado)
$motoristas = [];
$resM = mysqli_query($ligacao,
  "SELECT m.id, m.nome, m.zona_operacional_id FROM motoristas m
   WHERE m.status='Ativo'
     AND m.id NOT IN (SELECT motorista_id FROM usuarios WHERE motorista_id IS NOT NULL)
   ORDER BY m.nome ASC"
);
if ($resM) while ($r = mysqli_fetch_assoc($resM)) $motoristas[] = $r;

// Carregar todas as infraestruturas ativas
$infraestruturas = [];
$resI = mysqli_query($ligacao, "SELECT id, nome, tipo FROM infraestruturas WHERE ativo = 1 ORDER BY nome ASC");
if ($resI) while ($r = mysqli_fetch_assoc($resI)) $infraestruturas[] = $r;

// Carregar todas as zonas operacionais ativas
$zonas = [];
$resZ = mysqli_query($ligacao, "SELECT id, nome, cor FROM zonas_operacionais WHERE ativo = 1 ORDER BY nome ASC");
if ($resZ) while ($r = mysqli_fetch_assoc($resZ)) $zonas[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome             = trim($_POST['nome'] ?? '');
  $username         = trim($_POST['username'] ?? '');
  $senha            = (string)($_POST['senha'] ?? '');
  $perfil           = strtolower(trim($_POST['perfil'] ?? 'operario'));
  $ativo            = isset($_POST['ativo']) ? 1 : 0;
  $motorista_id_sel = trim($_POST['motorista_id'] ?? '');
  $infraestrutura_id_sel = trim($_POST['infraestrutura_id'] ?? '');
  $nivel_gestao     = trim($_POST['nivel_gestao'] ?? 'global');
  $zona_operacional_id_sel = trim($_POST['zona_operacional_id'] ?? '');

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
      
      // Ajustar variáveis conforme o perfil
      $mid = null;
      $infra_val = null;
      $nivel_val = null;
      $zona_val = null;

      if ($perfil === 'admin') {
        // Sem campos adicionais
      } elseif ($perfil === 'gestor') {
        $nivel_val = $nivel_gestao;
        if ($nivel_gestao === 'zona') {
          $zona_val = ($zona_operacional_id_sel !== '') ? (int)$zona_operacional_id_sel : null;
          if ($zona_val === null) {
            $erro = 'Selecione uma Zona Operacional para o Gestor de Zona.';
          }
        }
        $infra_val = ($infraestrutura_id_sel !== '' && (int)$infraestrutura_id_sel > 0) ? (int)$infraestrutura_id_sel : null;
      } elseif ($perfil === 'operario') {
        $mid = ($motorista_id_sel !== '' && (int)$motorista_id_sel > 0) ? (int)$motorista_id_sel : null;
        if ($mid === null) {
          $erro = 'Selecione o motorista associado para o Operário.';
        } else {
          // Zona herdada do motorista ou selecionada manualmente
          $zona_val = ($zona_operacional_id_sel !== '') ? (int)$zona_operacional_id_sel : null;
        }
      }

      if (empty($erro)) {
        $ins = mysqli_prepare($ligacao,
          "INSERT INTO usuarios (nome, username, senha, perfil, ativo, motorista_id, infraestrutura_id, nivel_gestao, zona_operacional_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($ins, "ssssiiisi", $nome, $username, $hash, $perfil, $ativo, $mid, $infra_val, $nivel_val, $zona_val);

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
}
?>

<div class="page-max-6xl space-y-6">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1 class="page-title mb-1">Novo utilizador</h1>
      <div class="page-subtitle">Criar conta e definir perfil operacional</div>
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
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?php echo $ativo?'checked':''; ?>>
          <label class="form-check-label" for="ativo">Ativo</label>
        </div>
      </div>

      <!-- Âmbito da Gestão (Global vs Zona) - Apenas para Gestores -->
      <div class="col-12 col-md-6" id="blocoNivelGestao">
        <label class="form-label">Nível de Gestão</label>
        <select class="form-select" name="nivel_gestao" id="selectNivelGestao">
          <option value="global" <?php echo $nivel_gestao==='global'?'selected':''; ?>>Gestor Global (Acesso Total)</option>
          <option value="zona" <?php echo $nivel_gestao==='zona'?'selected':''; ?>>Gestor de Zona (Acesso Restrito)</option>
        </select>
      </div>

      <!-- Zona Operacional - Para Gestores de Zona e Operários -->
      <div class="col-12 col-md-6" id="blocoZona">
        <label class="form-label">Zona Operacional</label>
        <select class="form-select" name="zona_operacional_id" id="selectZona">
          <option value="">Selecione uma zona...</option>
          <?php foreach ($zonas as $z): ?>
            <option value="<?php echo (int)$z['id']; ?>" <?php echo ($zona_operacional_id_sel==(string)$z['id'])?'selected':''; ?>>
              <?php echo h($z['nome']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Organiza as viaturas, ocorrências e motoristas de forma territorial.</div>
      </div>

      <!-- Associação a motorista — só relevante para operário -->
      <div class="col-12" id="blocoMotorista">
        <label class="form-label">Motorista associado <span class="text-muted">(obrigatório para perfil Operário)</span></label>
        <select class="form-select" name="motorista_id" id="selectMotorista">
          <option value="">Sem associação</option>
          <?php foreach ($motoristas as $m): ?>
            <option value="<?php echo (int)$m['id']; ?>" data-zona="<?php echo h($m['zona_operacional_id']); ?>" <?php echo ($motorista_id_sel==(string)$m['id'])?'selected':''; ?>>
              <?php echo h($m['nome']); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($motoristas)): ?>
          <div class="form-text text-warning">Não há motoristas ativos disponíveis para associar.</div>
        <?php endif; ?>
      </div>

      <!-- Associação a base operacional — só relevante para gestor -->
      <div class="col-12" id="blocoBase">
        <label class="form-label">Base Operacional Associada <span class="text-muted">(Opcional)</span></label>
        <select class="form-select" name="infraestrutura_id">
          <option value="">Sem associação</option>
          <?php foreach ($infraestruturas as $infra): ?>
            <option value="<?php echo (int)$infra['id']; ?>" <?php echo ($infraestrutura_id_sel===(string)$infra['id'])?'selected':''; ?>>
              <?php echo h($infra['nome'] . ' (' . ucfirst($infra['tipo']) . ')'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 d-flex gap-2 pt-3">
        <button class="btn btn-primary" type="submit">Salvar</button>
        <a class="btn btn-outline-secondary" href="usuarios.php">Cancelar</a>
      </div>

    </form>
  </div>
</div>

<script>
  const selPerfil = document.getElementById('selectPerfil');
  const selNivel = document.getElementById('selectNivelGestao');
  const selMotorista = document.getElementById('selectMotorista');
  const selZona = document.getElementById('selectZona');

  const blocoNivelGestao = document.getElementById('blocoNivelGestao');
  const blocoZona = document.getElementById('blocoZona');
  const blocoMotorista = document.getElementById('blocoMotorista');
  const blocoBase = document.getElementById('blocoBase');

  function toggleCamposPerfil() {
    const perfil = selPerfil.value;
    
    // Reset displays
    blocoNivelGestao.style.display = 'none';
    blocoZona.style.display = 'none';
    blocoMotorista.style.display = 'none';
    blocoBase.style.display = 'none';

    if (perfil === 'gestor') {
      blocoNivelGestao.style.display = '';
      blocoBase.style.display = '';
      if (selNivel.value === 'zona') {
        blocoZona.style.display = '';
      }
    } else if (perfil === 'operario') {
      blocoMotorista.style.display = '';
      blocoZona.style.display = '';
    }
  }

  // Ao mudar o motorista, auto-selecionar a zona correspondente
  selMotorista.addEventListener('change', function() {
    const selectedOpt = selMotorista.options[selMotorista.selectedIndex];
    if (selectedOpt) {
      const zonaId = selectedOpt.getAttribute('data-zona');
      if (zonaId) {
        selZona.value = zonaId;
      }
    }
  });

  selPerfil.addEventListener('change', toggleCamposPerfil);
  selNivel.addEventListener('change', toggleCamposPerfil);
  toggleCamposPerfil();
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
