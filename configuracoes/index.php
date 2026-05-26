<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'config';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

$perfil = perfil_atual(); // admin | gestor | operario
$userId = usuario_id_sessao();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Load current user details ---
$me = null;
if ($userId > 0) {
    $stmt = mysqli_prepare($ligacao, "SELECT id, nome, username, perfil, senha FROM usuarios WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $me  = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);
}

if (!$me) {
    echo '<div class="glass-card p-4 text-center text-muted">Utilizador não encontrado.</div>';
    require_once __DIR__ . "/../inc/footer.php";
    exit;
}

// --- Process Credentials Update Form ---
$erro = '';
$ok   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'credentials') {
    $novoUsername = trim($_POST['username'] ?? '');
    $senhaAtual   = trim($_POST['senha_atual'] ?? '');
    $novaSenha    = trim($_POST['nova_senha'] ?? '');
    $confirmar    = trim($_POST['confirmar_senha'] ?? '');

    if ($novoUsername === '') {
        $erro = "O username não pode estar vazio.";
    } else {
        $hashAtual = $me['senha'];
        if (str_starts_with($hashAtual, '$2y$')) {
            $senhaOk = password_verify($senhaAtual, $hashAtual);
        } else {
            $senhaOk = ($senhaAtual === $hashAtual);
        }

        if (!$senhaOk) {
            $erro = "Senha atual incorreta.";
        }
    }

    if ($erro === '' && ($novaSenha !== '' || $confirmar !== '')) {
        if (strlen($novaSenha) < 6) {
            $erro = "A nova senha deve ter pelo menos 6 caracteres.";
        } elseif ($novaSenha !== $confirmar) {
            $erro = "Confirmação de senha não coincide.";
        }
    }

    if ($erro === '') {
        // Verificar username duplicado
        $chk = mysqli_prepare($ligacao, "SELECT id FROM usuarios WHERE username=? AND id<>? LIMIT 1");
        mysqli_stmt_bind_param($chk, "si", $novoUsername, $userId);
        mysqli_stmt_execute($chk);
        mysqli_stmt_store_result($chk);
        $dup = mysqli_stmt_num_rows($chk) > 0;
        mysqli_stmt_close($chk);

        if ($dup) {
            $erro = "Este nome de utilizador já está em uso.";
        } else {
            if ($novaSenha !== '') {
                $novoHash = password_hash($novaSenha, PASSWORD_BCRYPT);
                $upd = mysqli_prepare($ligacao, "UPDATE usuarios SET username=?, senha=? WHERE id=? LIMIT 1");
                mysqli_stmt_bind_param($upd, "ssi", $novoUsername, $novoHash, $userId);
            } else {
                $upd = mysqli_prepare($ligacao, "UPDATE usuarios SET username=? WHERE id=? LIMIT 1");
                mysqli_stmt_bind_param($upd, "si", $novoUsername, $userId);
            }

            if (mysqli_stmt_execute($upd)) {
                $_SESSION['user_username'] = $novoUsername;
                $me['username'] = $novoUsername;
                header("Location: index.php?ok=1");
                exit;
            } else {
                $erro = "Erro ao atualizar: " . mysqli_error($ligacao);
            }
            mysqli_stmt_close($upd);
        }
    }
}

$sys_config = get_configuracoes_sistema();
?>

<style>
  .nav-tabs .nav-link {
    border: none;
    background: transparent;
    color: var(--muted-foreground);
    padding: 12px 20px;
    border-bottom: 2px solid transparent;
    transition: all 0.2s ease;
  }
  .nav-tabs .nav-link:hover {
    color: var(--foreground);
    border-bottom: 2px solid rgba(13,71,121,.2);
  }
  .nav-tabs .nav-link.active {
    color: var(--primary) !important;
    border-bottom: 2px solid var(--primary);
    background: transparent;
  }
</style>

<div class="page-max-6xl space-y-6">
  <div class="mb-4">
    <h1 class="page-title">Configurações</h1>
    <div class="page-subtitle">
      <?php if ($perfil === 'operario'): ?>
        Gerir as suas credenciais de utilizador
      <?php else: ?>
        Definições do sistema, frotas e controlo de acessos
      <?php endif; ?>
    </div>
  </div>

  <?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><?php echo h($erro); ?></div>
  <?php endif; ?>

  <!-- Tabs Navigation -->
  <ul class="nav nav-tabs mb-4" id="configTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active fw-semibold" id="account-tab" data-bs-toggle="tab" data-bs-target="#account-pane" type="button" role="tab" aria-controls="account-pane" aria-selected="true">
        <i class="bi bi-person-fill me-2"></i>Minha Conta
      </button>
    </li>
    
    <?php if (in_array($perfil, ['admin','gestor'], true)): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="system-tab" data-bs-toggle="tab" data-bs-target="#system-pane" type="button" role="tab" aria-controls="system-pane" aria-selected="false">
          <i class="bi bi-sliders me-2"></i>Parâmetros do Sistema
        </button>
      </li>
    <?php endif; ?>

    <?php if ($perfil === 'admin'): ?>
      <li class="nav-item" role="presentation">
        <button class="nav-link fw-semibold" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-pane" type="button" role="tab" aria-controls="users-pane" aria-selected="false">
          <i class="bi bi-shield-lock-fill me-2"></i>Gestão de Utilizadores
        </button>
      </li>
    <?php endif; ?>
  </ul>

  <!-- Tabs Content -->
  <div class="tab-content" id="configTabsContent">
    
    <!-- Tab 1: Account Settings -->
    <div class="tab-pane fade show active" id="account-pane" role="tabpanel" aria-labelledby="account-tab" tabindex="0">
      <div class="row g-4">
        <div class="col-12 col-lg-4">
          <div class="glass-card p-4 text-center">
            <div class="avatar-soft mx-auto mb-3" style="width: 72px; height: 72px; font-size: 24px;">
              <?php
                $parts = preg_split('/\s+/', trim($me['nome']));
                echo strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
              ?>
            </div>
            <h4 class="fw-bold mb-1"><?php echo h($me['nome']); ?></h4>
            <div class="mb-3">
              <span class="badge-pill badge-info-soft text-uppercase"><?php echo h($me['perfil']); ?></span>
            </div>
            <p class="text-muted small mb-0">
              Conta ativa e registada. Se necessitar de alterar o seu perfil, contacte o administrador do sistema.
            </p>
          </div>
        </div>

        <div class="col-12 col-lg-8">
          <div class="glass-card p-4">
            <h3 class="section-title mb-4">Atualizar Credenciais</h3>
            
            <form method="post" class="row g-3">
              <input type="hidden" name="action" value="credentials">

              <div class="col-12">
                <label class="form-label form-label-soft">Nome de Utilizador</label>
                <input class="form-control form-control-lg" name="username" value="<?php echo h($me['username']); ?>" required autocomplete="username">
              </div>

              <div class="col-12">
                <label class="form-label form-label-soft">Senha Atual <span class="text-danger">*</span></label>
                <input class="form-control form-control-lg" type="password" name="senha_atual" required placeholder="Digite para validar a alteração" autocomplete="current-password">
                <div class="form-text">Necessária para confirmar a sua identidade ao efetuar alterações.</div>
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label form-label-soft">Nova Senha <span class="text-muted">(opcional)</span></label>
                <input class="form-control form-control-lg" type="password" name="nova_senha" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
              </div>

              <div class="col-12 col-md-6">
                <label class="form-label form-label-soft">Confirmar Nova Senha</label>
                <input class="form-control form-control-lg" type="password" name="confirmar_senha" autocomplete="new-password">
              </div>

              <div class="col-12 pt-3">
                <button class="btn btn-primary btn-lg" type="submit">Guardar Credenciais</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Tab 2: System Settings (Admin / Gestor) -->
    <?php if (in_array($perfil, ['admin','gestor'], true)): ?>
      <div class="tab-pane fade" id="system-pane" role="tabpanel" aria-labelledby="system-tab" tabindex="0">
        <div class="glass-card p-4">
          <h3 class="section-title mb-4">Parâmetros de Funcionamento do AquaFleet</h3>
          
          <form method="post" action="settings_save.php" class="row g-3">
            
            <div class="col-12 col-md-6">
              <label class="form-label form-label-soft">Nome da Empresa / Entidade</label>
              <input class="form-control form-control-lg" name="nome_empresa" value="<?php echo h($sys_config['nome_empresa']); ?>" required>
              <div class="form-text">Aparece na barra lateral do painel e nos relatórios gerados.</div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label form-label-soft">Símbolo Monetário Padrão</label>
              <input class="form-control form-control-lg" name="moeda" value="<?php echo h($sys_config['moeda']); ?>" required>
              <div class="form-text">Usado em todos os custos de abastecimentos e oficinas.</div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label form-label-soft">Alerta de Carta de Condução (Dias)</label>
              <input class="form-control form-control-lg" type="number" name="alerta_carta_dias" value="<?php echo (int)$sys_config['alerta_carta_dias']; ?>" min="5" required>
              <div class="form-text">Aviso prévio no painel antes da carta de um motorista expirar.</div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label form-label-soft">Tema Visual / Estilo dos Mapas</label>
              <select name="mapa_estilo" class="form-select form-select-lg">
                <option value="dark" <?php echo $sys_config['mapa_estilo'] === 'dark' ? 'selected' : ''; ?>>CartoDB Dark Matter (Escuro Neon)</option>
                <option value="light" <?php echo $sys_config['mapa_estilo'] === 'light' ? 'selected' : ''; ?>>OpenStreetMap Standard (Clássico)</option>
              </select>
              <div class="form-text">Estilo das peças cartográficas renderizadas nos módulos de GPS.</div>
            </div>

            <div class="col-12 pt-3">
              <button class="btn btn-primary btn-lg" type="submit">Guardar Parâmetros</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <!-- Tab 3: Access Management (Admin only) -->
    <?php if ($perfil === 'admin'): ?>
      <div class="tab-pane fade" id="users-pane" role="tabpanel" aria-labelledby="users-tab" tabindex="0">
        <div class="glass-card p-4">
          <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
              <h3 class="section-title mb-1">Controle de Utilizadores & Permissões</h3>
              <p class="text-muted small mb-0">Crie, edite e controle contas administrativas ou de operários.</p>
            </div>
            <a class="btn btn-primary" href="usuarios.php">
              <i class="bi bi-shield-lock me-2"></i>Abrir Consola Administrativa
            </a>
          </div>

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <div class="p-3 border rounded h-100" style="background: rgba(226,232,240,.15); border-color: var(--border) !important;">
                <h4 class="h6 fw-bold mb-2">Perfis Suportados:</h4>
                <ul class="small text-muted mb-0 ps-3">
                  <li class="mb-1"><strong>Admin:</strong> Acesso total às configurações globais, utilizadores e frotas.</li>
                  <li class="mb-1"><strong>Gestor:</strong> Acesso aos veículos, manutenções, abastecimentos e mapas. Pode ser restrito a uma ETAR específica.</li>
                  <li><strong>Operário:</strong> Acesso limitado ao seu próprio painel, registo de km/combustível e ocorrências da sua viatura atribuída.</li>
                </ul>
              </div>
            </div>

            <div class="col-12 col-md-6">
              <div class="p-3 border rounded h-100" style="background: rgba(226,232,240,.15); border-color: var(--border) !important;">
                <h4 class="h6 fw-bold mb-2">Dica de Configuração:</h4>
                <p class="small text-muted mb-0">
                  Ao criar utilizadores para perfil <strong>Operário</strong>, certifique-se de fazer a associação direta ao respetivo motorista no cadastro. Isso garante a preseleção automática e confidencialidade dos dados pessoais.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>