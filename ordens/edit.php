<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'ordens_servico';
require_once __DIR__ . "/../inc/database.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header("Location: index.php");
  exit;
}

// Validar permissão da zona
pode_ver_ordem($ligacao, $id);

require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$erros = [];
$sucesso = false;

// Buscar ordem de serviço
$stmt = mysqli_prepare($ligacao, "SELECT * FROM ordens_servico WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$os = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$os) {
  header("Location: index.php");
  exit;
}

$codigo = (string)$os['codigo'];
$estado = (string)$os['estado'];

// Carregar dados gerais respeitando os filtros de zona
$whereInfra = "ativo=1";
$whereViatura = "1=1";
$whereMotorista = "status='Ativo'";

if (is_gestor_zona()) {
  $zid = (int)zona_id_sessao();
  $whereInfra .= " AND (zona_operacional_id = $zid OR zona_operacional_id IS NULL)";
  $whereViatura .= " AND zona_operacional_id = $zid";
  $whereMotorista .= " AND zona_operacional_id = $zid";
}

$infraestruturas = [];
$resI = mysqli_query($ligacao, "SELECT id, nome, tipo FROM infraestruturas WHERE $whereInfra ORDER BY nome ASC");
if ($resI) while ($r = mysqli_fetch_assoc($resI)) $infraestruturas[] = $r;

$viaturas = [];
$resV = mysqli_query($ligacao, "SELECT id, matricula, marca_modelo FROM viaturas WHERE $whereViatura ORDER BY marca_modelo ASC");
if ($resV) while ($r = mysqli_fetch_assoc($resV)) $viaturas[] = $r;

$motoristas = [];
$resM = mysqli_query($ligacao, "SELECT id, nome FROM motoristas WHERE $whereMotorista ORDER BY nome ASC");
if ($resM) while ($r = mysqli_fetch_assoc($resM)) $motoristas[] = $r;

$titulo = $os['titulo'] ?? '';
$descricao = $os['descricao'] ?? '';
$tipo = $os['tipo'] ?? 'outro';
$prioridade = $os['prioridade'] ?? 'media';
$infraestrutura_id = $os['infraestrutura_id'] ?? '';
$viatura_id = $os['viatura_id'] ?? '';
$motorista_id = $os['motorista_id'] ?? '';
$data_prevista = $os['data_prevista'] ?? '';
$hora_prevista = $os['hora_prevista'] ?? '';
$observacoes_gestor = $os['observacoes_gestor'] ?? '';

// Definir se a edição completa está bloqueada
$bloquearEdicaoCompleta = in_array($estado, ['em_deslocacao', 'em_execucao', 'concluida', 'impedida', 'cancelada'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $observacoes_gestor = trim($_POST['observacoes_gestor'] ?? '');

  if ($bloquearEdicaoCompleta) {
    // Apenas atualiza observações do gestor
    $obs_val = $observacoes_gestor !== '' ? $observacoes_gestor : null;
    $stmtUpd = mysqli_prepare($ligacao, "UPDATE ordens_servico SET observacoes_gestor=? WHERE id=?");
    mysqli_stmt_bind_param($stmtUpd, "si", $obs_val, $id);
    if (mysqli_stmt_execute($stmtUpd)) {
      mysqli_stmt_close($stmtUpd);
      header("Location: show.php?id=" . $id . "&msg=editada");
      exit;
    } else {
      $erros[] = "Erro ao atualizar observações: " . mysqli_error($ligacao);
    }
  } else {
    // Permite edição completa
    $titulo             = trim($_POST['titulo'] ?? '');
    $descricao          = trim($_POST['descricao'] ?? '');
    $tipo               = trim($_POST['tipo'] ?? 'outro');
    $prioridade         = trim($_POST['prioridade'] ?? 'media');
    $infraestrutura_id  = $_POST['infraestrutura_id'] ?? '';
    $viatura_id         = $_POST['viatura_id'] ?? '';
    $motorista_id       = $_POST['motorista_id'] ?? '';
    $data_prevista      = $_POST['data_prevista'] ?? '';
    $hora_prevista      = $_POST['hora_prevista'] ?? '';

    if ($titulo === '') $erros[] = "O título é obrigatório.";

    // Validação extra de segurança de zona ao salvar
    if (is_gestor_zona()) {
      $zid = (int)zona_id_sessao();
      if ($viatura_id !== '') {
        $chkV = mysqli_query($ligacao, "SELECT zona_operacional_id FROM viaturas WHERE id = " . (int)$viatura_id);
        if ($chkV && $rV = mysqli_fetch_assoc($chkV)) {
          if ((int)$rV['zona_operacional_id'] !== $zid) $erros[] = "A viatura selecionada não pertence à sua zona.";
        }
      }
      if ($motorista_id !== '') {
        $chkM = mysqli_query($ligacao, "SELECT zona_operacional_id FROM motoristas WHERE id = " . (int)$motorista_id);
        if ($chkM && $rM = mysqli_fetch_assoc($chkM)) {
          if ((int)$rM['zona_operacional_id'] !== $zid) $erros[] = "O motorista selecionado não pertence à sua zona.";
        }
      }
    }

    if (empty($erros)) {
      $infra_id_val = ($infraestrutura_id !== '' && (int)$infraestrutura_id > 0) ? (int)$infraestrutura_id : null;
      $viatura_id_val = ($viatura_id !== '' && (int)$viatura_id > 0) ? (int)$viatura_id : null;
      $motorista_id_val = ($motorista_id !== '' && (int)$motorista_id > 0) ? (int)$motorista_id : null;
      
      $data_prev_val = $data_prevista !== '' ? $data_prevista : null;
      $hora_prev_val = $hora_prevista !== '' ? $hora_prevista : null;
      $desc_val = $descricao !== '' ? $descricao : null;
      $obs_gestor_val = $observacoes_gestor !== '' ? $observacoes_gestor : null;

      // Atualizar o estado da ordem
      $novoEstado = $estado;
      if ($estado === 'rascunho' && $motorista_id_val !== null) {
        $novoEstado = 'atribuida';
      } elseif ($estado === 'atribuida' && $motorista_id_val === null) {
        $novoEstado = 'rascunho';
      }

      $stmtUpd = mysqli_prepare($ligacao, "
        UPDATE ordens_servico 
        SET 
          titulo=?, descricao=?, tipo=?, prioridade=?, 
          infraestrutura_id=?, viatura_id=?, motorista_id=?, 
          data_prevista=?, hora_prevista=?, estado=?, observacoes_gestor=? 
        WHERE id=?
      ");
      mysqli_stmt_bind_param($stmtUpd, "sssssiiiissi",
        $titulo, $desc_val, $tipo, $prioridade,
        $infra_id_val, $viatura_id_val, $motorista_id_val,
        $data_prev_val, $hora_prev_val, $novoEstado, $obs_gestor_val, $id
      );

      if (mysqli_stmt_execute($stmtUpd)) {
        mysqli_stmt_close($stmtUpd);
        header("Location: show.php?id=" . $id . "&msg=editada");
        exit;
      } else {
        $erros[] = "Erro ao atualizar: " . mysqli_error($ligacao);
      }
    }
  }
}
?>

<div class="page-max-6xl space-y-6">
  <a class="back-link" href="show.php?id=<?php echo $id; ?>">← Voltar aos detalhes</a>

  <div>
    <h1 class="page-title">Editar Ordem de Serviço</h1>
    <div class="page-subtitle">Modificar planeamento da OS: <strong><?php echo h($codigo); ?></strong></div>
  </div>

  <?php if (count($erros) > 0): ?>
    <div class="alert alert-danger">
      <strong>Erro ao salvar:</strong>
      <ul class="mb-0 mt-1">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($bloquearEdicaoCompleta): ?>
    <div class="alert alert-warning">
      <i class="bi bi-exclamation-triangle-fill me-1"></i>
      <strong>Edição Parcial:</strong> Esta Ordem de Serviço já foi iniciada ou finalizada pelo operário (Estado: <strong><?php echo h($estado); ?></strong>). Apenas é permitido alterar as observações do gestor.
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <form method="post" class="row g-3">

      <?php if (!$bloquearEdicaoCompleta): ?>
        <div class="col-12 col-md-8">
          <label class="form-label form-label-soft">Título da Tarefa *</label>
          <input class="form-control form-control-lg" name="titulo" value="<?php echo h($titulo); ?>" required>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label form-label-soft">Tipo de Serviço *</label>
          <select class="form-select" name="tipo" required>
            <option value="outro" <?php echo ($tipo==='outro')?'selected':''; ?>>Outro / Geral</option>
            <option value="inspecao" <?php echo ($tipo==='inspecao')?'selected':''; ?>>Inspeção / Vistoria</option>
            <option value="apoio_tecnico" <?php echo ($tipo==='apoio_tecnico')?'selected':''; ?>>Apoio Técnico</option>
            <option value="transporte" <?php echo ($tipo==='transporte')?'selected':''; ?>>Transporte</option>
            <option value="emergencia" <?php echo ($tipo==='emergencia')?'selected':''; ?>>Emergência</option>
            <option value="manutencao_externa" <?php echo ($tipo==='manutencao_externa')?'selected':''; ?>>Manutenção Externa</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label form-label-soft">Descrição Detalhada / Instruções</label>
          <textarea class="form-control" name="descricao" rows="4"><?php echo h($descricao); ?></textarea>
        </div>

        <div class="col-12 col-md-4">
          <label class="form-label form-label-soft">Prioridade *</label>
          <select class="form-select" name="prioridade" required>
            <option value="baixa" <?php echo ($prioridade==='baixa')?'selected':''; ?>>Baixa</option>
            <option value="media" <?php echo ($prioridade==='media')?'selected':''; ?>>Média</option>
            <option value="alta" <?php echo ($prioridade==='alta')?'selected':''; ?>>Alta</option>
            <option value="critica" <?php echo ($prioridade==='critica')?'selected':''; ?>>Crítica</option>
          </select>
        </div>

        <div class="col-12 col-md-8">
          <label class="form-label form-label-soft">Local / Infraestrutura Associada</label>
          <select class="form-select" name="infraestrutura_id">
            <option value="">Nenhuma / Sem local</option>
            <?php foreach ($infraestruturas as $inf): ?>
              <option value="<?php echo (int)$inf['id']; ?>" <?php echo ((int)$infraestrutura_id === (int)$inf['id'])?'selected':''; ?>>
                <?php echo h($inf['nome'] . " (" . $inf['tipo'] . ")"); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label form-label-soft">Motorista Designado (Opcional)</label>
          <select class="form-select" name="motorista_id">
            <option value="">Deixar em aberto (Rascunho)</option>
            <?php foreach ($motoristas as $m): ?>
              <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)$motorista_id === (int)$m['id'])?'selected':''; ?>>
                <?php echo h($m['nome']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label form-label-soft">Viatura Designada (Opcional)</label>
          <select class="form-select" name="viatura_id">
            <option value="">Sem viatura</option>
            <?php foreach ($viaturas as $v): ?>
              <option value="<?php echo (int)$v['id']; ?>" <?php echo ((int)$viatura_id === (int)$v['id'])?'selected':''; ?>>
                <?php echo h($v['marca_modelo'] . " — " . $v['matricula']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label form-label-soft">Data Prevista</label>
          <input class="form-control" type="date" name="data_prevista" value="<?php echo h($data_prevista); ?>">
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label form-label-soft">Hora Prevista</label>
          <input class="form-control" type="time" name="hora_prevista" value="<?php echo h($hora_prevista); ?>">
        </div>
      <?php endif; ?>

      <div class="col-12">
        <label class="form-label form-label-soft">Notas Internas do Gestor</label>
        <textarea class="form-control" name="observacoes_gestor" rows="3" placeholder="Opcional"><?php echo h($observacoes_gestor); ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Salvar alterações</button>
        <a class="btn btn-outline-secondary" href="show.php?id=<?php echo $id; ?>">Cancelar</a>
      </div>

    </form>
  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
