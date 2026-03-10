<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../inc/auth.php";
exigir_admin();
$active = 'config';

// vamos manter usuarios.php só para admin
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// buscar utilizadores
$sql = "SELECT id, nome, username, perfil, ativo, criado_em FROM usuarios ORDER BY id DESC";
$res = mysqli_query($ligacao, $sql);
?>

<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title">Utilizadores</h1>
    <div class="page-subtitle">Apenas Admin • gerir acessos</div>
  </div>
  <a class="btn btn-primary" href="usuarios_create.php">Novo Utilizador</a>
</div>

<div class="glass-card p-3">
  <?php if ($res && mysqli_num_rows($res) > 0): ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>Username</th>
            <th>Perfil</th>
            <th>Ativo</th>
            <th>Criado em</th>
            <th class="text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php while($u = mysqli_fetch_assoc($res)): ?>
            <tr>
              <td><?php echo (int)$u['id']; ?></td>
              <td><?php echo h($u['nome']); ?></td>
              <td><?php echo h($u['username']); ?></td>
              <td><span class="badge-pill badge-info-soft"><?php echo h($u['perfil']); ?></span></td>
              <td>
                <?php if ((int)$u['ativo'] === 1): ?>
                  <span class="badge-pill badge-success-soft">Sim</span>
                <?php else: ?>
                  <span class="badge-pill badge-danger-soft">Não</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted"><?php echo h($u['criado_em']); ?></td>
              <td class="text-end">
                <a class="btn btn-outline-primary btn-sm" href="usuarios_edit.php?id=<?php echo (int)$u['id']; ?>">Editar</a>
                <a class="btn btn-outline-danger btn-sm" href="usuarios_delete.php?id=<?php echo (int)$u['id']; ?>">Apagar</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="text-muted small text-center py-3">Nenhum utilizador encontrado.</div>
  <?php endif; ?>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
