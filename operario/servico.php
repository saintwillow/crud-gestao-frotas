<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

if (is_gestor_ou_admin()) {
  header("Location: " . base_url() . "/index.php");
  exit;
}

$active = 'operario_painel';

require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function gerar_codigo_servico(): string {
  return 'SRV-' . date('Ymd-His') . '-' . random_int(100, 999);
}

function fmtDataHora($d) {
  if (!$d) return '—';
  $ts = strtotime($d);
  return $ts ? date('d/m/Y H:i', $ts) : h($d);
}

$motorista_id = motorista_id_sessao();
$usuario_id = usuario_id_sessao();
$colaborador_id = colaborador_id_sessao();

if (!$motorista_id) {
  echo '<div class="glass-card p-4 text-center text-muted">Conta não associada a motorista.</div>';
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

/* Buscar atribuição aberta */
$atribuicao = atribuicao_aberta_motorista($ligacao, $motorista_id);

if (!$atribuicao) {
  ?>
  <div class="page-max-4xl space-y-6">
    <a class="back-link" href="index.php">← Voltar ao painel</a>

    <div>
      <h1 class="page-title">O Meu Serviço</h1>
      <div class="page-subtitle">Não existe viatura atribuída neste momento.</div>
    </div>

    <div class="glass-card p-4 text-center">
      <i class="bi bi-car-front-fill fs-1 mb-3" style="color:hsl(38,92%,50%);"></i>
      <h2 class="h5 fw-bold mb-2">Sem viatura atribuída</h2>
      <p class="text-muted mb-0">
        Contacte o gestor de frota para receber uma atribuição ativa.
      </p>
    </div>
  </div>
  <?php
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$atribuicao_id = (int)$atribuicao['atribuicao_id'];
$viatura_id = (int)$atribuicao['viatura_id'];

/* Buscar motorista */
$stmt = mysqli_prepare($ligacao, "SELECT * FROM motoristas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $motorista_id);
mysqli_stmt_execute($stmt);
$mot = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

/* Buscar serviço aberto */
$servicoAberto = null;
$stmt = mysqli_prepare($ligacao,
  "SELECT *
   FROM servicos_operacionais
   WHERE motorista_id = ?
     AND estado = 'aberto'
   ORDER BY data_inicio DESC, id DESC
   LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $motorista_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$servicoAberto = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

$erros = [];
$msg = '';

/* ============================================================
   INICIAR SERVIÇO
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'iniciar') {
  if ($servicoAberto) {
    $erros[] = "Já existe um serviço aberto.";
  }

  $km_inicio = trim($_POST['km_inicio'] ?? '');
  $nivel_combustivel_inicio = trim($_POST['nivel_combustivel_inicio'] ?? '');
  $observacoes_inicio = trim($_POST['observacoes_inicio'] ?? '');

  $pneus_ok = isset($_POST['pneus_ok']) ? 1 : 0;
  $luzes_ok = isset($_POST['luzes_ok']) ? 1 : 0;
  $travoes_ok = isset($_POST['travoes_ok']) ? 1 : 0;
  $documentos_ok = isset($_POST['documentos_ok']) ? 1 : 0;
  $limpeza_ok = isset($_POST['limpeza_ok']) ? 1 : 0;
  $danos_visiveis = isset($_POST['danos_visiveis']) ? 1 : 0;

  if ($km_inicio === '' || !ctype_digit($km_inicio)) {
    $erros[] = "Informe a quilometragem inicial.";
  } else {
    $km_inicio_int = (int)$km_inicio;
    $km_atual_viatura = (int)$atribuicao['quilometragem'];

    if ($km_inicio_int < $km_atual_viatura) {
      $erros[] = "A quilometragem inicial não pode ser menor que a quilometragem atual da viatura ({$km_atual_viatura} km).";
    }
  }

  if ($nivel_combustivel_inicio !== '') {
    if (!ctype_digit($nivel_combustivel_inicio) || (int)$nivel_combustivel_inicio > 100) {
      $erros[] = "O nível de combustível deve estar entre 0 e 100.";
    }
  }

  if (!$erros) {
    $codigo = gerar_codigo_servico();
    $km_inicio_int = (int)$km_inicio;
    $nivel_inicio_val = $nivel_combustivel_inicio !== '' ? (int)$nivel_combustivel_inicio : null;
    $obs_inicio_val = $observacoes_inicio !== '' ? $observacoes_inicio : null;

    mysqli_begin_transaction($ligacao);

    try {
      $stmt = mysqli_prepare($ligacao,
        "INSERT INTO servicos_operacionais
          (
            codigo,
            atribuicao_id,
            motorista_id,
            colaborador_id,
            usuario_id,
            viatura_id,
            km_inicio,
            nivel_combustivel_inicio,
            observacoes_inicio,
            estado
          )
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'aberto')"
      );

      mysqli_stmt_bind_param(
        $stmt,
        "siiiiiiis",
        $codigo,
        $atribuicao_id,
        $motorista_id,
        $colaborador_id,
        $usuario_id,
        $viatura_id,
        $km_inicio_int,
        $nivel_inicio_val,
        $obs_inicio_val
      );

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($ligacao));
      }

      $servico_id = mysqli_insert_id($ligacao);
      mysqli_stmt_close($stmt);

      $stmt = mysqli_prepare($ligacao,
        "INSERT INTO checklists_servico
          (
            servico_id,
            momento,
            pneus_ok,
            luzes_ok,
            travoes_ok,
            documentos_ok,
            limpeza_ok,
            danos_visiveis,
            nivel_combustivel,
            quilometragem,
            observacoes
          )
         VALUES (?, 'inicio', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
      );

      mysqli_stmt_bind_param(
        $stmt,
        "iiiiiiiiss",
        $servico_id,
        $pneus_ok,
        $luzes_ok,
        $travoes_ok,
        $documentos_ok,
        $limpeza_ok,
        $danos_visiveis,
        $nivel_inicio_val,
        $km_inicio_int,
        $obs_inicio_val
      );

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($ligacao));
      }

      mysqli_stmt_close($stmt);

      /* Atualizar km da viatura se o km informado for maior */
      $stmt = mysqli_prepare($ligacao,
        "UPDATE viaturas
         SET quilometragem = GREATEST(quilometragem, ?)
         WHERE id = ?"
      );
      mysqli_stmt_bind_param($stmt, "ii", $km_inicio_int, $viatura_id);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      mysqli_commit($ligacao);

      header("Location: servico.php?msg=iniciado");
      exit;

    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = "Erro ao iniciar serviço: " . $e->getMessage();
    }
  }
}

/* ============================================================
   FINALIZAR SERVIÇO
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'finalizar') {
  if (!$servicoAberto) {
    $erros[] = "Não existe serviço aberto para finalizar.";
  }

  $km_fim = trim($_POST['km_fim'] ?? '');
  $nivel_combustivel_fim = trim($_POST['nivel_combustivel_fim'] ?? '');
  $observacoes_fim = trim($_POST['observacoes_fim'] ?? '');

  $pneus_ok = isset($_POST['pneus_ok']) ? 1 : 0;
  $luzes_ok = isset($_POST['luzes_ok']) ? 1 : 0;
  $travoes_ok = isset($_POST['travoes_ok']) ? 1 : 0;
  $documentos_ok = isset($_POST['documentos_ok']) ? 1 : 0;
  $limpeza_ok = isset($_POST['limpeza_ok']) ? 1 : 0;
  $danos_visiveis = isset($_POST['danos_visiveis']) ? 1 : 0;

  if ($km_fim === '' || !ctype_digit($km_fim)) {
    $erros[] = "Informe a quilometragem final.";
  } else {
    $km_fim_int = (int)$km_fim;
    $km_inicio_servico = (int)$servicoAberto['km_inicio'];

    if ($km_fim_int < $km_inicio_servico) {
      $erros[] = "A quilometragem final não pode ser menor que a quilometragem inicial ({$km_inicio_servico} km).";
    }
  }

  if ($nivel_combustivel_fim !== '') {
    if (!ctype_digit($nivel_combustivel_fim) || (int)$nivel_combustivel_fim > 100) {
      $erros[] = "O nível de combustível final deve estar entre 0 e 100.";
    }
  }

  if (!$erros) {
    $servico_id = (int)$servicoAberto['id'];
    $km_fim_int = (int)$km_fim;
    $nivel_fim_val = $nivel_combustivel_fim !== '' ? (int)$nivel_combustivel_fim : null;
    $obs_fim_val = $observacoes_fim !== '' ? $observacoes_fim : null;

    mysqli_begin_transaction($ligacao);

    try {
      $stmt = mysqli_prepare($ligacao,
        "UPDATE servicos_operacionais
         SET
          data_fim = NOW(),
          km_fim = ?,
          nivel_combustivel_fim = ?,
          observacoes_fim = ?,
          estado = 'concluido'
         WHERE id = ?
           AND motorista_id = ?
           AND estado = 'aberto'"
      );

      mysqli_stmt_bind_param(
        $stmt,
        "iisii",
        $km_fim_int,
        $nivel_fim_val,
        $obs_fim_val,
        $servico_id,
        $motorista_id
      );

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($ligacao));
      }

      mysqli_stmt_close($stmt);

      $stmt = mysqli_prepare($ligacao,
        "INSERT INTO checklists_servico
          (
            servico_id,
            momento,
            pneus_ok,
            luzes_ok,
            travoes_ok,
            documentos_ok,
            limpeza_ok,
            danos_visiveis,
            nivel_combustivel,
            quilometragem,
            observacoes
          )
         VALUES (?, 'fim', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
      );

      mysqli_stmt_bind_param(
        $stmt,
        "iiiiiiiiss",
        $servico_id,
        $pneus_ok,
        $luzes_ok,
        $travoes_ok,
        $documentos_ok,
        $limpeza_ok,
        $danos_visiveis,
        $nivel_fim_val,
        $km_fim_int,
        $obs_fim_val
      );

      if (!mysqli_stmt_execute($stmt)) {
        throw new Exception(mysqli_error($ligacao));
      }

      mysqli_stmt_close($stmt);

      /* Atualizar km da viatura */
      $stmt = mysqli_prepare($ligacao,
        "UPDATE viaturas
         SET quilometragem = GREATEST(quilometragem, ?)
         WHERE id = ?"
      );
      mysqli_stmt_bind_param($stmt, "ii", $km_fim_int, $viatura_id);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      /* Atualizar atribuição */
      $stmt = mysqli_prepare($ligacao,
        "UPDATE atribuicoes
         SET km_fim = ?
         WHERE id = ?"
      );
      mysqli_stmt_bind_param($stmt, "ii", $km_fim_int, $atribuicao_id);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      mysqli_commit($ligacao);

      header("Location: servico.php?msg=finalizado");
      exit;

    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = "Erro ao finalizar serviço: " . $e->getMessage();
    }
  }
}

/* Recarregar serviço aberto depois de eventuais ações */
$stmt = mysqli_prepare($ligacao,
  "SELECT *
   FROM servicos_operacionais
   WHERE motorista_id = ?
     AND estado = 'aberto'
   ORDER BY data_inicio DESC, id DESC
   LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $motorista_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$servicoAberto = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

$msg = $_GET['msg'] ?? '';

?>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="index.php">← Voltar ao painel</a>

  <div>
    <h1 class="page-title">O Meu Serviço</h1>
    <div class="page-subtitle">
      <?php echo h($atribuicao['matricula'] . ' — ' . $atribuicao['marca_modelo']); ?>
    </div>
  </div>

  <?php if ($msg === 'iniciado'): ?>
    <div class="alert alert-success">Serviço iniciado com sucesso.</div>
  <?php elseif ($msg === 'finalizado'): ?>
    <div class="alert alert-success">Serviço finalizado com sucesso.</div>
  <?php endif; ?>

  <?php if ($erros): ?>
    <div class="alert alert-danger">
      <strong>Verifique os campos:</strong>
      <ul class="mb-0">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <h3 class="section-title mb-3">Viatura atribuída</h3>

    <div class="row g-3">
      <div class="col-12 col-md-3">
        <div class="detail-label">Matrícula</div>
        <div class="detail-value fw-bold"><?php echo h($atribuicao['matricula']); ?></div>
      </div>

      <div class="col-12 col-md-3">
        <div class="detail-label">Modelo</div>
        <div class="detail-value"><?php echo h($atribuicao['marca_modelo']); ?></div>
      </div>

      <div class="col-12 col-md-3">
        <div class="detail-label">Combustível</div>
        <div class="detail-value"><?php echo h($atribuicao['combustivel']); ?></div>
      </div>

      <div class="col-12 col-md-3">
        <div class="detail-label">Quilometragem atual</div>
        <div class="detail-value">
          <?php echo number_format((int)$atribuicao['quilometragem'], 0, ',', '.'); ?> km
        </div>
      </div>
    </div>
  </div>

  <?php if (!$servicoAberto): ?>

    <div class="glass-card p-4">
      <h3 class="section-title mb-3">Iniciar serviço</h3>

      <form method="post" class="row g-3">
        <input type="hidden" name="acao" value="iniciar">

        <div class="col-12 col-md-6">
          <label class="form-label form-label-soft">Quilometragem inicial *</label>
          <input
            type="number"
            name="km_inicio"
            class="form-control form-control-lg"
            min="<?php echo (int)$atribuicao['quilometragem']; ?>"
            value="<?php echo (int)$atribuicao['quilometragem']; ?>"
            required
          >
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label form-label-soft">Nível de combustível inicial (%)</label>
          <input
            type="number"
            name="nivel_combustivel_inicio"
            class="form-control form-control-lg"
            min="0"
            max="100"
            placeholder="Ex.: 80"
          >
        </div>

        <div class="col-12">
          <label class="form-label form-label-soft">Checklist inicial</label>

          <div class="row g-2">
            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="pneus_ok" checked>
                <span class="form-check-label">Pneus OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="luzes_ok" checked>
                <span class="form-check-label">Luzes OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="travoes_ok" checked>
                <span class="form-check-label">Travões OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="documentos_ok" checked>
                <span class="form-check-label">Documentos OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="limpeza_ok" checked>
                <span class="form-check-label">Limpeza OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="danos_visiveis">
                <span class="form-check-label">Existem danos visíveis</span>
              </label>
            </div>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label form-label-soft">Observações iniciais</label>
          <textarea name="observacoes_inicio" class="form-control" rows="4" placeholder="Opcional"></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
          <button class="btn btn-primary" type="submit">Iniciar serviço</button>
        </div>
      </form>
    </div>

  <?php else: ?>

    <div class="glass-card p-4" style="border-left:3px solid hsl(152,60%,40%);">
      <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
        <div>
          <h3 class="section-title mb-1">Serviço em andamento</h3>
          <div class="text-muted">
            Código: <strong><?php echo h($servicoAberto['codigo']); ?></strong><br>
            Iniciado em: <strong><?php echo h(fmtDataHora($servicoAberto['data_inicio'])); ?></strong><br>
            Km inicial: <strong><?php echo number_format((int)$servicoAberto['km_inicio'], 0, ',', '.'); ?> km</strong>
          </div>
        </div>
        <div>
          <a href="ocorrencia_create.php" class="btn btn-danger d-flex align-items-center gap-2" style="background-color: #dc2626; border-color: #dc2626;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            Reportar Ocorrência
          </a>
        </div>
      </div>
    </div>

    <div class="glass-card p-4">
      <h3 class="section-title mb-3">Finalizar serviço</h3>

      <form method="post" class="row g-3">
        <input type="hidden" name="acao" value="finalizar">

        <div class="col-12 col-md-6">
          <label class="form-label form-label-soft">Quilometragem final *</label>
          <input
            type="number"
            name="km_fim"
            class="form-control form-control-lg"
            min="<?php echo (int)$servicoAberto['km_inicio']; ?>"
            value="<?php echo max((int)$atribuicao['quilometragem'], (int)$servicoAberto['km_inicio']); ?>"
            required
          >
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label form-label-soft">Nível de combustível final (%)</label>
          <input
            type="number"
            name="nivel_combustivel_fim"
            class="form-control form-control-lg"
            min="0"
            max="100"
            placeholder="Ex.: 45"
          >
        </div>

        <div class="col-12">
          <label class="form-label form-label-soft">Checklist final</label>

          <div class="row g-2">
            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="pneus_ok" checked>
                <span class="form-check-label">Pneus OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="luzes_ok" checked>
                <span class="form-check-label">Luzes OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="travoes_ok" checked>
                <span class="form-check-label">Travões OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="documentos_ok" checked>
                <span class="form-check-label">Documentos OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="limpeza_ok" checked>
                <span class="form-check-label">Limpeza OK</span>
              </label>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="danos_visiveis">
                <span class="form-check-label">Existem danos visíveis</span>
              </label>
            </div>
          </div>
        </div>

        <div class="col-12">
          <label class="form-label form-label-soft">Observações finais</label>
          <textarea name="observacoes_fim" class="form-control" rows="4" placeholder="Opcional"></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
          <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
          <button class="btn btn-warning" type="submit">Finalizar serviço</button>
        </div>
      </form>
    </div>

  <?php endif; ?>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>