<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'ordens_servico';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function gerar_codigo_os(): string {
  return 'OS-' . date('Ymd') . '-' . random_int(100, 999);
}

$erros = [];
$titulo = '';
$descricao = '';
$tipo = 'outro';
$prioridade = 'media';
$infraestrutura_id = '';
$viatura_id = '';
$motorista_id = '';
$data_prevista = date('Y-m-d');
$hora_prevista = '';
$observacoes_gestor = '';

// Carregar infraestruturas
$infraestruturas = [];
$resI = mysqli_query($ligacao, "SELECT id, nome, tipo FROM infraestruturas WHERE ativo=1 ORDER BY nome ASC");
if ($resI) while ($r = mysqli_fetch_assoc($resI)) $infraestruturas[] = $r;

// Carregar viaturas
$viaturas = [];
$resV = mysqli_query($ligacao, "SELECT id, matricula, marca_modelo FROM viaturas ORDER BY marca_modelo ASC");
if ($resV) while ($r = mysqli_fetch_assoc($resV)) $viaturas[] = $r;

// Carregar motoristas ativos
$motoristas = [];
$resM = mysqli_query($ligacao, "SELECT id, nome, nif FROM motoristas WHERE status='Ativo' ORDER BY nome ASC");
if ($resM) while ($r = mysqli_fetch_assoc($resM)) $motoristas[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $titulo             = trim($_POST['titulo'] ?? '');
  $descricao          = trim($_POST['descricao'] ?? '');
  $tipo               = trim($_POST['tipo'] ?? 'outro');
  $prioridade         = trim($_POST['prioridade'] ?? 'media');
  $infraestrutura_id  = $_POST['infraestrutura_id'] ?? '';
  $viatura_id         = $_POST['viatura_id'] ?? '';
  $motorista_id       = $_POST['motorista_id'] ?? '';
  $data_prevista      = $_POST['data_prevista'] ?? '';
  $hora_prevista      = $_POST['hora_prevista'] ?? '';
  $observacoes_gestor = trim($_POST['observacoes_gestor'] ?? '');

  if ($titulo === '') $erros[] = "O título da ordem de serviço é obrigatório.";

  $infra_id_val = ($infraestrutura_id !== '' && (int)$infraestrutura_id > 0) ? (int)$infraestrutura_id : null;
  $viatura_id_val = ($viatura_id !== '' && (int)$viatura_id > 0) ? (int)$viatura_id : null;
  $motorista_id_val = ($motorista_id !== '' && (int)$motorista_id > 0) ? (int)$motorista_id : null;
  
  $data_prev_val = $data_prevista !== '' ? $data_prevista : null;
  $hora_prev_val = $hora_prevista !== '' ? $hora_prevista : null;
  
  $desc_val = $descricao !== '' ? $descricao : null;
  $obs_gestor_val = $observacoes_gestor !== '' ? $observacoes_gestor : null;

  // Estado inicial: se atribuído motorista, vira 'atribuida', senão 'rascunho'
  $estado = ($motorista_id_val !== null) ? 'atribuida' : 'rascunho';

  if (empty($erros)) {
    $codigo = gerar_codigo_os();
    $usuario_sessao = usuario_id_sessao();

    $stmt = mysqli_prepare($ligacao, "
      INSERT INTO ordens_servico 
        (codigo, titulo, descricao, tipo, prioridade, infraestrutura_id, viatura_id, motorista_id, atribuido_por_usuario_id, data_prevista, hora_prevista, estado, observacoes_gestor)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    mysqli_stmt_bind_param($stmt, "sssssiiiissss",
      $codigo, $titulo, $desc_val, $tipo, $prioridade, 
      $infra_id_val, $viatura_id_val, $motorista_id_val, $usuario_sessao, 
      $data_prev_val, $hora_prev_val, $estado, $obs_gestor_val
    );

    if (mysqli_stmt_execute($stmt)) {
      mysqli_stmt_close($stmt);
      header("Location: index.php?msg=criada");
      exit;
    } else {
      $erros[] = "Erro ao criar Ordem de Serviço: " . mysqli_error($ligacao);
    }
  }
}
?>

<div class="page-max-6xl space-y-6">
  <a class="back-link" href="index.php">← Voltar às ordens de serviço</a>

  <div>
    <h1 class="page-title">Nova Ordem de Serviço</h1>
    <div class="page-subtitle">Distribua e agende novas tarefas operacionais para a equipa</div>
  </div>

  <?php if (count($erros) > 0): ?>
    <div class="alert alert-danger">
      <strong>Erro ao criar:</strong>
      <ul class="mb-0 mt-1">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <form method="post" class="row g-3">

      <div class="col-12 col-md-8">
        <label class="form-label form-label-soft">Título da Tarefa *</label>
        <input class="form-control form-control-lg" name="titulo" value="<?php echo h($titulo); ?>" placeholder="Ex: Reparação de Bomba de Água / Inspeção Semestral" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Tipo de Serviço *</label>
        <select class="form-select form-select-lg" name="tipo" required>
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
        <textarea class="form-control" name="descricao" rows="4" placeholder="Descreva os passos que o operário deve seguir, ferramentas necessárias ou detalhes do problema operacional..."><?php echo h($descricao); ?></textarea>
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
          <option value="">Nenhuma / Sem local específico</option>
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
              <?php echo h($m['nome'] . " (NIF: " . $m['nif'] . ")"); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Viatura Designada (Opcional)</label>
        <select class="form-select" name="viatura_id">
          <option value="">Sem viatura designada</option>
          <?php foreach ($viaturas as $v): ?>
            <option value="<?php echo (int)$v['id']; ?>" <?php echo ((int)$viatura_id === (int)$v['id'])?'selected':''; ?>>
              <?php echo h($v['marca_modelo'] . " — " . $v['matricula']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Data Prevista de Execução</label>
        <input class="form-control" type="date" name="data_prevista" value="<?php echo h($data_prevista); ?>">
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Hora Prevista de Execução</label>
        <input class="form-control" type="time" name="hora_prevista" value="<?php echo h($hora_prevista); ?>">
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Notas Internas do Gestor</label>
        <textarea class="form-control" name="observacoes_gestor" rows="2" placeholder="Notas que apenas gestores conseguem visualizar..."><?php echo h($observacoes_gestor); ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Criar Ordem de Serviço</button>
        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
      </div>

    </form>
  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
