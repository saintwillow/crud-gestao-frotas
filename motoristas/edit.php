<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'motoristas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: index.php"); exit; }

$erros = [];

// buscar motorista
$stmt = mysqli_prepare($ligacao, "SELECT * FROM motoristas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$m = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$m) { header("Location: index.php"); exit; }

// carregar valores
$nome = $m['nome'] ?? '';
$cc = $m['cc'] ?? '';
$nif = $m['nif'] ?? '';
$carta_numero = $m['carta_numero'] ?? '';
$carta_categoria = $m['carta_categoria'] ?? '';
$carta_validade = $m['carta_validade'] ?? '';
$telefone = $m['telefone'] ?? '';
$email = $m['email'] ?? '';
$status = $m['status'] ?? 'Ativo';
$desde = $m['desde'] ?? '';
$viagens = (string)($m['viagens'] ?? '0');
$viatura_id = $m['viatura_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $cc = trim($_POST['cc'] ?? '');
  $nif = trim($_POST['nif'] ?? '');
  $carta_numero = trim($_POST['carta_numero'] ?? '');
  $carta_categoria = trim($_POST['carta_categoria'] ?? '');
  $carta_validade = $_POST['carta_validade'] ?? null;
  $telefone = trim($_POST['telefone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $status = ($_POST['status'] ?? 'Ativo') === 'Inativo' ? 'Inativo' : 'Ativo';
  $desde = $_POST['desde'] ?? null;
  $viagens_int = (int)($_POST['viagens'] ?? 0);
  if ($viagens_int < 0) $viagens_int = 0;

  $viatura_id = $_POST['viatura_id'] ?? '';
  $viatura_id_int = null;
  if ($viatura_id !== '') {
    $viatura_id_int = (int)$viatura_id;
    if ($viatura_id_int <= 0) $viatura_id_int = null;
  }

  if ($nome === '') $erros[] = "O nome é obrigatório.";

  if ($nif !== '' && !preg_match('/^\d{9}$/', preg_replace('/\D/', '', $nif))) {
    $erros[] = "NIF inválido (deve ter 9 dígitos).";
  }

  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = "E-mail inválido.";
  }

  $carta_validade_db = ($carta_validade !== '') ? $carta_validade : null;
  $desde_db = ($desde !== '') ? $desde : null;

  if (count($erros) === 0) {
    if ($viatura_id_int === null) {
      $stmt = mysqli_prepare(
        $ligacao,
        "UPDATE motoristas
          SET nome=?, cc=?, nif=?, carta_numero=?, carta_categoria=?, carta_validade=?,
              telefone=?, email=?, status=?, desde=?, viagens=?, viatura_id=NULL
          WHERE id=?"
      );
      mysqli_stmt_bind_param(
        $stmt,
        "ssssssssssii",
        $nome,$cc,$nif,$carta_numero,$carta_categoria,$carta_validade_db,
        $telefone,$email,$status,$desde_db,$viagens_int,$id
      );
    } else {
      $stmt = mysqli_prepare(
        $ligacao,
        "UPDATE motoristas
          SET nome=?, cc=?, nif=?, carta_numero=?, carta_categoria=?, carta_validade=?,
              telefone=?, email=?, status=?, desde=?, viagens=?, viatura_id=?
          WHERE id=?"
      );
      mysqli_stmt_bind_param(
        $stmt,
        "ssssssssssiii",
        $nome,$cc,$nif,$carta_numero,$carta_categoria,$carta_validade_db,
        $telefone,$email,$status,$desde_db,$viagens_int,$viatura_id_int,$id
      );
    }

    $ok = mysqli_stmt_execute($stmt);
    if ($ok) {
      header("Location: index.php?msg=editado");
      exit;
    } else {
      $erros[] = "Erro ao atualizar: " . mysqli_error($ligacao);
    }
    mysqli_stmt_close($stmt);
  }
}

// lista viaturas p/ select
$viaturas = [];
$rV = mysqli_query($ligacao, "SELECT id, matricula, marca_modelo FROM viaturas ORDER BY matricula ASC");
if ($rV) while ($row = mysqli_fetch_assoc($rV)) $viaturas[] = $row;
?>

<div class="page-max-6xl space-y-6">

  <a class="back-link" href="index.php">← Voltar aos motoristas</a>

  <div class="d-flex justify-content-between align-items-start">
    <div>
      <h1 class="page-title">Editar Motorista</h1>
      <div class="page-subtitle"><?php echo h($nome); ?></div>
    </div>

    <a class="btn btn-outline-danger" href="delete.php?id=<?php echo (int)$id; ?>">Apagar</a>
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
        <label class="form-label form-label-soft">Validade</label>
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
        <input class="form-control" type="number" min="0" name="viagens" value="<?php echo h($viagens); ?>">
      </div>

      <div class="col-12 col-md-2">
        <label class="form-label form-label-soft">Status</label>
        <select class="form-select" name="status">
          <option value="Ativo" <?php echo ($status==='Ativo')?'selected':''; ?>>Ativo</option>
          <option value="Inativo" <?php echo ($status==='Inativo')?'selected':''; ?>>Inativo</option>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Viatura atribuída</label>
        <select class="form-select" name="viatura_id">
          <option value="">Sem viatura</option>
          <?php foreach ($viaturas as $v): ?>
            <option value="<?php echo (int)$v['id']; ?>" <?php echo ((string)$viatura_id === (string)$v['id'])?'selected':''; ?>>
              <?php echo h($v['matricula'] . " — " . $v['marca_modelo']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Desde</label>
        <input class="form-control" type="date" name="desde" value="<?php echo h($desde); ?>">
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary">Salvar alterações</button>
        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
      </div>

    </form>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
