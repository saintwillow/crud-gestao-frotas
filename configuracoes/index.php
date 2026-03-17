<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'config';
require_once __DIR__ . "/../inc/header.php";

$perfil = perfil_atual(); // admin | gestor | operario
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>

<div class="mb-4">
  <h1 class="page-title">Configurações</h1>
  <div class="page-subtitle">
    <?php if ($perfil === 'operario'): ?>
      Gerir apenas as suas credenciais
    <?php else: ?>
      Definições do sistema e acessos
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">

  <!-- Minha Conta (TODOS) -->
  <div class="col-12 col-md-6 col-lg-4">
    <div class="glass-card p-4 h-100">
      <div class="d-flex align-items-center gap-2 mb-2">
        <div class="list-ico"><i class="bi bi-person-fill"></i></div>
        <h3 class="section-title mb-0">Minha Conta</h3>
      </div>
      <div class="text-muted small mb-3">Alterar utilizador e/ou senha.</div>
      <a class="btn btn-primary" href="minha_conta.php">Abrir</a>
    </div>
  </div>

  <?php if ($perfil === 'admin'): ?>
    <!-- Utilizadores (SÓ ADMIN) -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="glass-card p-4 h-100">
        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="list-ico"><i class="bi bi-shield-lock-fill"></i></div>
          <h3 class="section-title mb-0">Utilizadores</h3>
        </div>
        <div class="text-muted small mb-3">Criar/editar perfis e acessos.</div>
        <a class="btn btn-outline-primary" href="usuarios.php">Gerir</a>
      </div>\
    </div>
  <?php endif; ?>

  <?php if (in_array($perfil, ['admin','gestor'], true)): ?>
    <!-- (opcional) Parâmetros do Sistema (ADMIN+GESTOR) -->
    <div class="col-12 col-md-6 col-lg-4">
      <div class="glass-card p-4 h-100">
        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="list-ico"><i class="bi bi-gear-fill"></i></div>
          <h3 class="section-title mb-0">Parâmetros</h3>
        </div>
        <div class="text-muted small mb-3">Moeda, alertas, etc. (opcional).</div>
        <button class="btn btn-outline-secondary" disabled>Em breve</button>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . "/../inc/footer.php"; ?>