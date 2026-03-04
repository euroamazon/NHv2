</main>

<footer class="py-4 no-print">
  <div class="container app-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      © <?= e(date('Y')) ?> <?= e(($cfg['app']['name'] ?? 'NH')) ?> • Gestion Notes d’Honoraires
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="badge text-bg-light border" style="border-color: rgba(15,23,42,.08) !important;">
        <i class="bi bi-shield-check me-1"></i> Sécurisé
      </span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  // ✅ Toast helper (optionnel)
  function showToast(message, type = 'success') {
    const container = document.querySelector('.toast-container');
    if (!container) return;

    const bg = (type === 'danger') ? 'text-bg-danger' :
               (type === 'warning') ? 'text-bg-warning' :
               (type === 'info') ? 'text-bg-info' : 'text-bg-success';

    const el = document.createElement('div');
    el.className = `toast align-items-center ${bg} border-0`;
    el.setAttribute('role','alert');
    el.setAttribute('aria-live','assertive');
    el.setAttribute('aria-atomic','true');

    el.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;
    container.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: 2500 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  }

  // ✅ Confirmation helper
  function confirmAction(msg) {
    return confirm(msg || "Confirmer l'opération ?");
  }
</script>

</body>
</html>
