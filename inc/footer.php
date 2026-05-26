    </div> <!-- /content -->
  </div> <!-- /mainwrap -->

</div> <!-- /app -->

<!-- Toast Container -->
<div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>

<script src="<?php echo $BASE_URL; ?>/js/bootstrap_js/bootstrap.bundle.min.js"></script>

<script>
  function showToast(message, type = 'success', duration = 5000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast-popup ${type}`;

    let iconClass = 'bi-check-circle-fill';
    if (type === 'danger' || type === 'error') iconClass = 'bi-x-circle-fill';
    else if (type === 'warning') iconClass = 'bi-exclamation-triangle-fill';
    else if (type === 'info') iconClass = 'bi-info-circle-fill';

    toast.innerHTML = `
      <div class="toast-icon ${type}"><i class="bi ${iconClass}"></i></div>
      <div class="toast-content">${message}</div>
      <button class="toast-close" onclick="this.parentElement.classList.remove('show'); setTimeout(() => this.parentElement.remove(), 300);"><i class="bi bi-x"></i></button>
    `;

    container.appendChild(toast);

    // Fade in
    setTimeout(() => {
      toast.classList.add('show');
    }, 10);

    // Auto fade out
    setTimeout(() => {
      if (toast && toast.parentNode) {
        toast.classList.remove('show');
        setTimeout(() => {
          if (toast && toast.parentNode) {
            toast.remove();
          }
        }, 300);
      }
    }, duration);
  }

  // Auto-Toast Handler based on common URL query parameters
  document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Check parameters and trigger toasts
    if (urlParams.has('msg') || urlParams.has('ok') || urlParams.has('err')) {
      const msg = urlParams.get('msg');
      const ok = urlParams.get('ok');
      const err = urlParams.get('err');
      
      // Clean parameter from URL bar to prevent triggers on refresh
      try {
        let newSearch = window.location.search
          .replace(/[?&](msg|ok|err)=[^&]+/g, '')
          .replace(/^&/, '?');
        if (newSearch === '?') newSearch = '';
        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + newSearch;
        window.history.replaceState({ path: cleanUrl }, '', cleanUrl);
      } catch (e) {}

      // Mappings
      if (msg === 'criada' || msg === 'criado' || ok === '1' || ok === 'criada' || ok === 'criado') {
        showToast('Registo guardado com sucesso!', 'success');
      } else if (msg === 'editada' || msg === 'editado' || ok === 'editado' || ok === 'editada') {
        showToast('Registo atualizado com sucesso!', 'success');
      } else if (msg === 'apagada' || msg === 'apagado' || ok === 'apagado' || ok === 'apagada') {
        showToast('Registo removido com sucesso!', 'success');
      } else if (msg === 'salvo' || ok === 'salvo') {
        showToast('Configurações guardadas com sucesso!', 'success');
      } else if (msg === 'analisado') {
        showToast('Abastecimento marcado para análise.', 'warning');
      } else if (msg === 'anulado') {
        showToast('Abastecimento anulado com sucesso.', 'danger');
      } else if (msg === 'corrigido') {
        showToast('Abastecimento corrigido com sucesso.', 'success');
      } else if (err || msg === 'erro') {
        showToast(err || 'Ocorreu um erro ao processar o seu pedido.', 'danger');
      }
    }
  });
</script>
</body>
</html>
