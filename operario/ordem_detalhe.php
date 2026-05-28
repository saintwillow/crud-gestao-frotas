<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

if (is_gestor_ou_admin()) {
  header("Location: " . base_url() . "/ordens/index.php");
  exit;
}

$active = 'operario_ordens';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function badgePrioridadeOS($prioridade) {
  switch ($prioridade) {
    case 'baixa': return 'badge-info-soft';
    case 'media': return 'badge-primary-soft';
    case 'alta': return 'badge-warning-soft';
    case 'critica': return 'badge-danger-soft';
    default: return 'badge-secondary-soft';
  }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header("Location: ordens.php");
  exit;
}

$motorista_id = motorista_id_sessao();
$erros = [];
$sucesso = false;

// Buscar ordem de serviço
$stmt = mysqli_prepare($ligacao, "
  SELECT 
    os.*,
    v.matricula AS viatura_matricula, v.marca_modelo AS viatura_marca_modelo,
    inf.nome AS infraestrutura_nome, inf.tipo AS infraestrutura_tipo, inf.localidade AS infraestrutura_localidade
  FROM ordens_servico os
  LEFT JOIN viaturas v ON v.id = os.viatura_id
  LEFT JOIN infraestruturas inf ON inf.id = os.infraestrutura_id
  WHERE os.id = ? AND os.motorista_id = ?
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "ii", $id, $motorista_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$os = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$os) {
  header("Location: ordens.php");
  exit;
}

$codigo = (string)$os['codigo'];
$titulo = (string)$os['titulo'];
$descricao = trim((string)($os['descricao'] ?? ''));
$tipo = (string)$os['tipo'];
$prio = (string)$os['prioridade'];
$est  = (string)$os['estado'];
$obsGestor = trim((string)($os['observacoes_gestor'] ?? ''));
$obsOperario = trim((string)($os['observacoes_operario'] ?? ''));

// Processamento de Ações de Estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
  $acao = $_POST['acao'];
  $relatorio = trim($_POST['observacoes_operario'] ?? '');
  
  $novoEstado = null;
  $updateTempos = "";
  $params = [];
  $types = "";

  if ($acao === 'aceitar' && $est === 'atribuida') {
    $novoEstado = 'aceite';
  } elseif ($acao === 'deslocacao' && $est === 'aceite') {
    $novoEstado = 'em_deslocacao';
    $updateTempos = ", inicio_real=NOW()"; // Grava início real da tarefa ao sair para viagem
  } elseif ($acao === 'execucao' && $est === 'em_deslocacao') {
    $novoEstado = 'em_execucao';
  } elseif ($acao === 'concluir' && $est === 'em_execucao') {
    $novoEstado = 'concluida';
    $updateTempos = ", fim_real=NOW(), observacoes_operario=?";
    $params[] = $relatorio;
    $types .= "s";
  } elseif ($acao === 'impedir' && $est === 'em_execucao') {
    if ($relatorio === '') {
      $erros[] = "Para declarar um impedimento, deve descrever detalhadamente o motivo nas observações.";
    } else {
      $novoEstado = 'impedida';
      $updateTempos = ", fim_real=NOW(), observacoes_operario=?";
      $params[] = $relatorio;
      $types .= "s";
    }
  }

  if ($novoEstado !== null && empty($erros)) {
    mysqli_begin_transaction($ligacao);
    try {
      $sqlUpd = "UPDATE ordens_servico SET estado=?" . $updateTempos . " WHERE id=?";
      $stmtUpd = mysqli_prepare($ligacao, $sqlUpd);
      
      // Bind dinâmico
      if ($updateTempos !== "") {
        if (str_contains($updateTempos, '?')) {
          mysqli_stmt_bind_param($stmtUpd, "ssi", $novoEstado, $params[0], $id);
        } else {
          mysqli_stmt_bind_param($stmtUpd, "si", $novoEstado, $id);
        }
      } else {
        mysqli_stmt_bind_param($stmtUpd, "si", $novoEstado, $id);
      }

      mysqli_stmt_execute($stmtUpd);
      mysqli_stmt_close($stmtUpd);
      mysqli_commit($ligacao);
      
      header("Location: ordens.php?msg=atualizada");
      exit;
    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = "Erro ao atualizar estado: " . $e->getMessage();
    }
  }
}
?>

<div class="page-max-4xl space-y-6">
  <a class="back-link" href="ordens.php">← Voltar às minhas ordens</a>

  <?php if (count($erros) > 0): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <!-- Cabeçalho da Tarefa -->
    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
      <div>
        <span class="text-muted small fw-semibold"><?php echo h($codigo); ?></span>
        <h1 class="h3 text-light fw-bold mt-1"><?php echo h($titulo); ?></h1>
      </div>
      <span class="badge-pill <?php echo badgePrioridadeOS($prio); ?> text-uppercase"><?php echo h($prio); ?></span>
    </div>

    <!-- Escopo e Detalhes -->
    <div class="vstack gap-3 border-top border-secondary border-opacity-10 pt-3">
      <div>
        <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Local da Execução</label>
        <div class="fw-semibold text-light"><?php echo h($os['infraestrutura_nome'] ?? 'Sem local específico'); ?></div>
        <?php if ($os['infraestrutura_localidade']): ?>
          <div class="small text-muted"><?php echo h($os['infraestrutura_localidade']); ?></div>
        <?php endif; ?>
      </div>

      <?php if ($os['viatura_marca_modelo']): ?>
        <div>
          <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Viatura Vinculada</label>
          <div class="fw-semibold text-light"><?php echo h($os['viatura_marca_modelo']); ?></div>
          <div class="small text-muted"><?php echo h($os['viatura_matricula']); ?></div>
        </div>
      <?php endif; ?>

      <div>
        <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Instruções de Trabalho</label>
        <div class="p-3 rounded bg-dark bg-opacity-25 border border-secondary border-opacity-10 text-light text-pre-line text-sm">
          <?php echo $descricao !== '' ? nl2br(h($descricao)) : '<span class="text-muted italic">Sem instruções detalhadas registradas.</span>'; ?>
        </div>
      </div>

      <?php if ($obsGestor !== ''): ?>
        <div>
          <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Observações do Gestor</label>
          <div class="small text-muted p-2 rounded bg-secondary bg-opacity-10 border border-secondary border-opacity-10">
            <?php echo nl2br(h($obsGestor)); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Cronômetro e Controle de Estados -->
  <div class="glass-card p-4">
    <h3 class="section-title mb-3">Progresso do Serviço</h3>

    <!-- Seção de Ações Interativas -->
    <form method="post" class="vstack gap-3">
      
      <?php if ($est === 'atribuida'): ?>
        <!-- Aceitar Ordem -->
        <div class="alert alert-info py-2 px-3 small">Uma nova Ordem de Serviço foi atribuída a si. Deve aceitá-la antes de iniciar o progresso.</div>
        <button class="btn btn-primary btn-lg w-100 py-3 fw-bold" type="submit" name="acao" value="aceitar">
          <i class="bi bi-check2-circle me-2 fs-5"></i>Aceitar Ordem de Serviço
        </button>

      <?php elseif ($est === 'aceite'): ?>
        <!-- Iniciar Viagem -->
        <div class="alert alert-info py-2 px-3 small">Ordem de Serviço aceite. Quando estiver pronto para deslocar-se ao local da tarefa, clique abaixo.</div>
        <button class="btn btn-warning btn-lg w-100 py-3 fw-bold text-dark" type="submit" name="acao" value="deslocacao">
          <i class="bi bi-geo-alt-fill me-2 fs-5"></i>Iniciar Deslocação ao Local
        </button>

      <?php elseif ($est === 'em_deslocacao'): ?>
        <!-- Chegou ao local -->
        <div class="alert alert-warning py-2 px-3 small">Viagem em curso... Ao chegar ao local e iniciar os trabalhos práticos, clique abaixo.</div>
        <button class="btn btn-success btn-lg w-100 py-3 fw-bold" type="submit" name="acao" value="execucao">
          <i class="bi bi-play-circle-fill me-2 fs-5"></i>Iniciar Execução da Tarefa
        </button>

      <?php elseif ($est === 'em_execucao'): ?>
        <!-- Em Execução - Form de Notas Finais -->
        <div class="alert alert-success py-2 px-3 small">A tarefa está atualmente em execução. Insira o relatório final de progresso e finalize.</div>

        <div class="mb-3">
          <label class="form-label form-label-soft fw-semibold">Relatório de Execução / Observações do Operário</label>
          <textarea class="form-control" name="observacoes_operario" rows="4" placeholder="Descreva o trabalho efetuado, materiais utilizados ou eventuais dificuldades..." required><?php echo h($obsOperario); ?></textarea>
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2">
          <button class="btn btn-success btn-lg flex-grow-1 py-3 fw-bold" type="submit" name="acao" value="concluir">
            <i class="bi bi-check-lg me-2 fs-5"></i>Concluir Serviço
          </button>
          
          <button class="btn btn-danger btn-lg flex-grow-1 py-3 fw-bold" type="submit" name="acao" value="impedir" onclick="return confirm('Deseja realmente declarar um impedimento nesta ordem? Justifique no campo de observações.');">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>Declarar Impedimento
          </button>
        </div>

      <?php else: ?>
        <!-- Finalizado -->
        <div class="alert alert-secondary py-2 px-3 small">
          Esta Ordem de Serviço está **<?php echo h(strtoupper($est)); ?>**.
        </div>
        
        <?php if ($obsOperario !== ''): ?>
          <div class="mt-2">
            <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">O seu relatório final</label>
            <div class="p-3 rounded bg-dark bg-opacity-25 border border-secondary border-opacity-10 text-light text-pre-line text-sm">
              <?php echo nl2br(h($obsOperario)); ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ($os['fim_real']): ?>
          <div class="small text-muted mt-2">
            Finalizado em: <?php echo date('d/m/Y \à\s H:i', strtotime($os['fim_real'])); ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </form>
  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
