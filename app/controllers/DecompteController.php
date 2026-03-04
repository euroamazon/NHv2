<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Project.php';
require_once __DIR__ . '/../models/Decompte.php';

final class DecompteController {

  public function index(): void {
    Auth::requireLogin();

    $projectId = (int)($_GET['project_id'] ?? 0);
    $project = Project::find($projectId);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }

    $rows = Decompte::listByProject($projectId);
    $total = Decompte::totalHt($projectId);

    require __DIR__ . '/../views/layout_top.php';
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <div class="text-muted small mb-1">
          <a href="/?r=projects" class="text-decoration-none">Projets</a>
          <span class="mx-1">/</span>
          <a href="/?r=project_view&id=<?= e((string)$projectId) ?>" class="text-decoration-none"><?= e((string)($project['project_title'] ?? '')) ?></a>
          <span class="mx-1">/</span>
          <span class="text-muted">Décomptes</span>
        </div>
        <h1 class="h4 page-title mb-1">Décomptes</h1>
        <div class="text-muted small"><?= e((string)($project['client_name'] ?? '')) ?></div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="/?r=project_view&id=<?= e((string)$projectId) ?>">
          <i class="bi bi-arrow-left me-1"></i> Retour projet
        </a>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card kpi-card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Total décomptes HT</div>
              <div class="mono fw-bold fs-5"><?= e(fmt_money((float)$total)) ?></div>
            </div>
            <div class="kpi-icon" style="background:#f0fdf4;">
              <i class="bi bi-graph-up-arrow"></i>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card kpi-card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Nombre de décomptes</div>
              <div class="mono fw-bold fs-5"><?= e((string)count($rows)) ?></div>
            </div>
            <div class="kpi-icon" style="background:#eef2ff;">
              <i class="bi bi-list-check"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <h2 class="h6 mb-3"><i class="bi bi-plus-circle me-1"></i> Ajouter un décompte</h2>

        <form method="post" action="/?r=decompte_add&project_id=<?= e((string)$projectId) ?>" class="row g-2 align-items-end">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

          <div class="col-md-3">
            <label class="form-label">N° décompte</label>
            <input class="form-control" name="decompte_no" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Date</label>
            <input class="form-control mono" type="date" name="decompte_date" value="<?= e(date('Y-m-d')) ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Montant HT</label>
            <input class="form-control mono" type="number" step="0.01" name="amount_ht" required>
          </div>

          <div class="col-md-2">
            <button class="btn btn-primary w-100">
              <i class="bi bi-check2-circle me-1"></i> Ajouter
            </button>
          </div>
        </form>

      </div>
    </div>

    <div class="card">
      <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
          <h2 class="h6 mb-0"><i class="bi bi-table me-1"></i> Liste</h2>
          <div class="input-group" style="max-width:380px;">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input class="form-control" id="q" placeholder="Filtrer..." oninput="filterRows()">
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="tbl">
            <thead>
              <tr>
                <th style="width:130px;">N°</th>
                <th style="width:160px;">Date</th>
                <th class="text-end">Montant HT</th>
                <th style="width:140px;" class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="4" class="text-muted">Aucun décompte.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td class="mono fw-semibold"><?= e((string)$r['decompte_no']) ?></td>
                    <td class="mono text-muted"><?= e((string)$r['decompte_date']) ?></td>
                    <td class="mono text-end fw-semibold"><?= e(fmt_money((float)$r['amount_ht'])) ?></td>
                    <td class="text-end">
                      <form method="post" action="/?r=decompte_delete&project_id=<?= e((string)$projectId) ?>&id=<?= e((string)$r['id']) ?>"
                            class="d-inline"
                            onsubmit="return confirmAction('Supprimer ce décompte ?');">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">
                          <i class="bi bi-trash me-1"></i> Supprimer
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <script>
      function filterRows(){
        const q = (document.getElementById('q').value || '').toLowerCase();
        document.querySelectorAll('#tbl tbody tr').forEach(tr=>{
          tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
        });
      }
    </script>
    <?php
    require __DIR__ . '/../views/layout_bottom.php';
  }

  public function add(): void {
    Auth::requireLogin();
    csrf_check();

    $projectId = (int)($_GET['project_id'] ?? 0);
    $project = Project::find($projectId);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }

    $d = [
      'decompte_no' => trim((string)($_POST['decompte_no'] ?? '')),
      'decompte_date' => trim((string)($_POST['decompte_date'] ?? '')),
      'amount_ht' => (float)($_POST['amount_ht'] ?? 0),
    ];

    if ($d['decompte_no'] === '' || $d['decompte_date'] === '') {
      http_response_code(400);
      echo "Champs invalides";
      exit;
    }

    Decompte::add($projectId, $d);
    redirect('/?r=decomptes&project_id=' . $projectId);
  }

  public function delete(): void {
    Auth::requireLogin();
    csrf_check();

    $projectId = (int)($_GET['project_id'] ?? 0);
    $id = (int)($_GET['id'] ?? 0);

    $project = Project::find($projectId);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }
    if ($id <= 0) { http_response_code(400); echo "ID invalide"; exit; }

    Decompte::delete($id, $projectId);
    redirect('/?r=decomptes&project_id=' . $projectId);
  }
}
