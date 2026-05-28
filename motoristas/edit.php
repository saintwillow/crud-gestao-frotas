<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'motoristas';
require_once __DIR__ . "/../inc/database.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: index.php"); exit; }

// Proteção URL direta
pode_ver_motorista($ligacao, $id);

require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

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
$documento_tipo = 'Cartão de Cidadão';
$documento_num = $cc;
if ($cc !== '' && strpos($cc, ': ') !== false) {
  $parts = explode(': ', $cc, 2);
  $documento_tipo = $parts[0];
  $documento_num = $parts[1];
}
$nif = $m['nif'] ?? '';
$carta_numero = $m['carta_numero'] ?? '';
$carta_categoria = $m['carta_categoria'] ?? '';
$carta_validade = $m['carta_validade'] ?? '';
$telefone = $m['telefone'] ?? '';
$email = $m['email'] ?? '';
$status = $m['status'] ?? 'Ativo';
$desde = $m['desde'] ?? '';
$viagens = (string)($m['viagens'] ?? '0');
$zona_operacional_id = $m['zona_operacional_id'] ?? '';

// Carregar todas as zonas operacionais ativas para o select (se for admin ou gestor global)
$zonas = [];
$resZ = mysqli_query($ligacao, "SELECT id, nome FROM zonas_operacionais WHERE ativo=1 ORDER BY nome ASC");
if ($resZ) while ($r = mysqli_fetch_assoc($resZ)) $zonas[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome           = trim($_POST['nome'] ?? '');
  $documento_tipo = trim($_POST['documento_tipo'] ?? 'Cartão de Cidadão');
  $documento_num  = trim($_POST['documento_num'] ?? '');
  $cc             = $documento_num !== '' ? ($documento_tipo . ': ' . $documento_num) : '';
  $nif            = trim($_POST['nif'] ?? '');
  $carta_numero   = trim($_POST['carta_numero'] ?? '');
  $carta_categoria = trim($_POST['carta_categoria'] ?? '');
  $carta_validade = $_POST['carta_validade'] ?? null;
  $telefone = trim($_POST['telefone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $status = ($_POST['status'] ?? 'Ativo') === 'Inativo' ? 'Inativo' : 'Ativo';
  $desde = $_POST['desde'] ?? null;
  $viagens_int = (int)($_POST['viagens'] ?? 0);
  if ($viagens_int < 0) $viagens_int = 0;

  if (is_gestor_zona()) {
    $zona_val = zona_id_sessao();
  } else {
    $zona_val = (isset($_POST['zona_operacional_id']) && $_POST['zona_operacional_id'] !== '') ? (int)$_POST['zona_operacional_id'] : null;
  }

  if ($nome === '') $erros[] = "O nome é obrigatório.";

  if ($nif !== '' && !preg_match('/^\d{9}$/', $nif)) {
    $erros[] = "NIF inválido (deve ter exatamente 9 dígitos).";
  }

  if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = "E-mail inválido.";
  }

  $carta_validade_db = ($carta_validade !== '') ? $carta_validade : null;
  $desde_db = ($desde !== '') ? $desde : null;

  if (count($erros) === 0) {
    mysqli_begin_transaction($ligacao);
    try {
      $colaborador_id = (int)($m['colaborador_id'] ?? 0);
      if ($colaborador_id <= 0) {
        $stmtCol = mysqli_prepare($ligacao,
          "INSERT INTO colaboradores (nome, email, telefone, cargo, ativo)
           VALUES (?, ?, ?, 'Motorista', 1)"
        );
        mysqli_stmt_bind_param($stmtCol, "sss", $nome, $email, $telefone);
        if (!mysqli_stmt_execute($stmtCol)) {
          throw new Exception("Erro ao criar colaborador correspondente.");
        }
        $colaborador_id = mysqli_insert_id($ligacao);
        mysqli_stmt_close($stmtCol);

        $stmtUpdCol = mysqli_prepare($ligacao, "UPDATE motoristas SET colaborador_id=? WHERE id=?");
        mysqli_stmt_bind_param($stmtUpdCol, "ii", $colaborador_id, $id);
        mysqli_stmt_execute($stmtUpdCol);
        mysqli_stmt_close($stmtUpdCol);
      } else {
        $stmtCol = mysqli_prepare($ligacao,
          "UPDATE colaboradores SET nome=?, email=?, telefone=? WHERE id=?"
        );
        mysqli_stmt_bind_param($stmtCol, "sssi", $nome, $email, $telefone, $colaborador_id);
        if (!mysqli_stmt_execute($stmtCol)) {
          throw new Exception("Erro ao atualizar colaborador correspondente.");
        }
        mysqli_stmt_close($stmtCol);
      }

      $stmt = mysqli_prepare(
        $ligacao,
        "UPDATE motoristas
          SET nome=?, cc=?, nif=?, carta_numero=?, carta_categoria=?, carta_validade=?,
              telefone=?, email=?, status=?, desde=?, viagens=?, zona_operacional_id=?
          WHERE id=?"
      );
      mysqli_stmt_bind_param(
        $stmt,
        "ssssssssssiii",
        $nome,$cc,$nif,$carta_numero,$carta_categoria,$carta_validade_db,
        $telefone,$email,$status,$desde_db,$viagens_int,$zona_val,$id
      );

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Erro ao atualizar motorista: " . mysqli_error($ligacao));
      }
      mysqli_stmt_close($stmt);

      // Sincronizar zona do usuário operário ligado a este motorista se houver
      if ($zona_val !== null) {
        mysqli_query($ligacao, "UPDATE usuarios SET zona_operacional_id = $zona_val WHERE motorista_id = $id");
      }

      mysqli_commit($ligacao);
      header("Location: index.php?msg=editado");
      exit;
    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = $e->getMessage();
    }
  }
}
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
        <input class="form-control" name="nome" value="<?php echo h($nome); ?>" maxlength="120" required>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">NIF</label>
        <input class="form-control" name="nif" value="<?php echo h($nif); ?>" placeholder="9 dígitos" maxlength="9" pattern="\d{9}" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
      </div>

      <?php if (!is_gestor_zona()): ?>
        <div class="col-12 col-md-3">
          <label class="form-label form-label-soft">Zona Operacional</label>
          <select class="form-select" name="zona_operacional_id" required>
            <option value="">Selecione uma zona...</option>
            <?php foreach ($zonas as $z): ?>
              <option value="<?php echo (int)$z['id']; ?>" <?php echo ($zona_operacional_id == $z['id']) ? 'selected' : ''; ?>>
                <?php echo h($z['nome']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Tipo de Documento</label>
        <select class="form-select" name="documento_tipo">
          <option value="Cartão de Cidadão" <?php echo ($documento_tipo === 'Cartão de Cidadão') ? 'selected' : ''; ?>>Cartão de Cidadão</option>
          <option value="Autorização de Residência" <?php echo ($documento_tipo === 'Autorização de Residência') ? 'selected' : ''; ?>>Autorização de Residência</option>
          <option value="Passaporte" <?php echo ($documento_tipo === 'Passaporte') ? 'selected' : ''; ?>>Passaporte</option>
        </select>
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Nº Documento</label>
        <input class="form-control" name="documento_num" value="<?php echo h($documento_num); ?>" maxlength="20" placeholder="Insira o número do documento">
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Carta (nº)</label>
        <input class="form-control" name="carta_numero" value="<?php echo h($carta_numero); ?>" maxlength="30">
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label form-label-soft">Categoria</label>
        <input class="form-control" name="carta_categoria" value="<?php echo h($carta_categoria); ?>" placeholder="ex.: B, C, C+E" maxlength="10">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Validade</label>
        <input class="form-control" type="date" name="carta_validade" value="<?php echo h($carta_validade); ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Telefone</label>
        <input class="form-control" name="telefone" value="<?php echo h($telefone); ?>" maxlength="30">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">E-mail</label>
        <input class="form-control" name="email" value="<?php echo h($email); ?>" maxlength="120">
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

      <div class="col-12 col-md-8">
        <label class="form-label form-label-soft">Desde</label>
        <input class="form-control" type="date" name="desde" value="<?php echo h($desde); ?>">
      </div>

      <div class="col-12 col-md-12 d-flex align-items-center my-3">
        <div class="alert alert-info w-100 mb-0 small py-2 px-3">
          <i class="bi bi-info-circle me-1"></i> A atribuição de viatura é gerida no módulo <a href="../atribuicoes/index.php" class="alert-link fw-semibold">Atribuições</a>.
        </div>
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
