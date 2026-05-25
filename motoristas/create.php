<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'motoristas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$erros = [];
$nome = $cc = $nif = $carta_numero = $carta_categoria = $carta_validade = '';
$telefone = $email = $desde = '';
$status = 'Ativo';
$viagens = 0;
$viatura_id = '';

// lista viaturas p/ select - REMOVIDO pois a atribuição agora é feita pelo módulo de Atribuições

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome           = trim($_POST['nome'] ?? '');
  $cc             = trim($_POST['cc'] ?? '');
  $nif            = trim($_POST['nif'] ?? '');
  $carta_numero   = trim($_POST['carta_numero'] ?? '');
  $carta_categoria= trim($_POST['carta_categoria'] ?? '');
  $carta_validade = $_POST['carta_validade'] ?? '';
  $telefone       = trim($_POST['telefone'] ?? '');
  $email          = trim($_POST['email'] ?? '');
  $status         = ($_POST['status'] ?? 'Ativo') === 'Inativo' ? 'Inativo' : 'Ativo';
  $desde          = $_POST['desde'] ?? '';
  $viagens        = max(0, (int)($_POST['viagens'] ?? 0));

  if ($nome === '') $erros[] = "O nome é obrigatório.";
  if ($nif !== '' && !preg_match('/^\d{9}$/', preg_replace('/\D/', '', $nif)))
    $erros[] = "NIF inválido (deve ter 9 dígitos).";
  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
    $erros[] = "E-mail inválido.";

  $carta_validade_db = ($carta_validade !== '') ? $carta_validade : null;
  $desde_db          = ($desde !== '') ? $desde : null;

  if (count($erros) === 0) {
    $stmt = mysqli_prepare($ligacao,
      "INSERT INTO motoristas (nome, cc, nif, carta_numero, carta_categoria, carta_validade,
         telefone, email, status, desde, viagens, viatura_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)"
    );
    mysqli_stmt_bind_param($stmt, "sssssssssi",
      $nome, $cc, $nif, $carta_numero, $carta_categoria, $carta_validade_db,
      $telefone, $email, $status, $desde_db, $viagens
    );

    if (mysqli_stmt_execute($stmt)) {
      header("Location: index.php?msg=criado");
      exit;
    } else {
      $erros[] = "Erro ao criar motorista: " . mysqli_error($ligacao);
    }
    mysqli_stmt_close($stmt);
  }
}
?>

<div class="page-max-6xl space-y-6">

  <a class="back-link" href="index.php">← Voltar aos motoristas</a>

  <div>
    <h1 class="page-title">Novo Motorista</h1>
    <div class="page-subtitle">Cadastrar um novo motorista na frota</div>
  </div>

  <?php if (count($erros) > 0): ?>
    <div class="alert alert-danger">
      <strong>Corrija os erros:</strong>
      <ul class="mb-0">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <form method="post" class="row g-3">

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Nome *</label>
        <input class="form-control" name="nome" value="<?php echo h($nome); ?>" required>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">NIF</label>
        <input class="form-control" name="nif" value="<?php echo h($nif); ?>" placeholder="9 dígitos">
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Cartão de Cidadão (CC)</label>
        <input class="form-control" name="cc" value="<?php echo h($cc); ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Carta (nº)</label>
        <input class="form-control" name="carta_numero" value="<?php echo h($carta_numero); ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Categoria</label>
        <input class="form-control" name="carta_categoria" value="<?php echo h($carta_categoria); ?>" placeholder="ex.: B, C, C+E">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Validade da carta</label>
        <input class="form-control" type="date" name="carta_validade" value="<?php echo h($carta_validade); ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Telefone</label>
        <input class="form-control" name="telefone" value="<?php echo h($telefone); ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">E-mail</label>
        <input class="form-control" name="email" value="<?php echo h($email); ?>">
      </div>

      <div class="col-12 col-md-2">
        <label class="form-label form-label-soft">Viagens</label>
        <input class="form-control" type="number" min="0" name="viagens" value="<?php echo (int)$viagens; ?>">
      </div>

      <div class="col-12 col-md-2">
        <label class="form-label form-label-soft">Status</label>
        <select class="form-select" name="status">
          <option value="Ativo" <?php echo ($status==='Ativo')?'selected':''; ?>>Ativo</option>
          <option value="Inativo" <?php echo ($status==='Inativo')?'selected':''; ?>>Inativo</option>
        </select>
      </div>

      <div class="col-12 col-md-6 d-flex align-items-center">
        <div class="alert alert-info w-100 mb-0 small py-2 px-3">
          <i class="bi bi-info-circle me-1"></i> A atribuição de viatura é gerida no módulo <a href="../atribuicoes/index.php" class="alert-link fw-semibold">Atribuições</a>.
        </div>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Desde</label>
        <input class="form-control" type="date" name="desde" value="<?php echo h($desde); ?>">
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary">Salvar</button>
        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
      </div>

    </form>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
