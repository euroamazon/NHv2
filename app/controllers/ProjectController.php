<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Project.php';
require_once __DIR__ . '/../models/Phase.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Decompte.php';

final class ProjectController {

  public function index(): void {
    Auth::requireLogin();
    $projects = Project::all();
    require __DIR__ . '/../views/layout_top.php';
    ?>
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h4 mb-0">Projets</h1>
      <a class="btn btn-primary" href="/?r=project_create">Nouveau projet</a>
    </div>
    <div class="card shadow-sm">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead><tr><th>ID</th><th>Client</th><th>Projet</th><th>% Honoraire</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($projects as $p): ?>
            <tr>
              <td class="mono"><?= e((string)$p['id']) ?></td>
              <td><?= e($p['client_name']) ?></td>
              <td><?= e($p['project_title']) ?></td>
              <td class="mono"><?= e(fmt_percent($p['honor_percent'])) ?>%</td>
              <td><a class="btn btn-sm btn-outline-primary" href="/?r=project_view&id=<?= e((string)$p['id']) ?>">Ouvrir</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php require __DIR__ . '/../views/layout_bottom.php';
  }

  public function create(): void {
    Auth::requireLogin();
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      $d = $this->readProjectPost();
      $phases = $this->readPhasesPost();
      $sum = array_sum(array_map(fn($x)=>(float)$x['rate_percent'], $phases));
      if (abs($sum - 100.0) > 0.0001) $error = "La somme des taux des phases doit être = 100% (actuel: {$sum}%).";
      if (!$error) {
        $id = Project::create($d);
        Phase::replaceAll($id, $phases);
        redirect('/?r=project_view&id=' . $id);
      }
    }
    $defaults = $this->defaultPhases();
    require __DIR__ . '/../views/layout_top.php';
    $this->renderForm('Créer un projet', null, $defaults, $error);
    require __DIR__ . '/../views/layout_bottom.php';
  }

  public function edit(): void {
    Auth::requireLogin();
    $id = (int)($_GET['id'] ?? 0);
    $project = Project::find($id);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }
    $error = null;
    $phases = Phase::listByProject($id);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      $d = $this->readProjectPost();
      $phases = $this->readPhasesPost();
      $sum = array_sum(array_map(fn($x)=>(float)$x['rate_percent'], $phases));
      if (abs($sum - 100.0) > 0.0001) $error = "La somme des taux des phases doit être = 100% (actuel: {$sum}%).";
      if (!$error) {
        Project::update($id, $d);
        Phase::replaceAll($id, $phases);
        redirect('/?r=project_view&id=' . $id);
      }
    }

    require __DIR__ . '/../views/layout_top.php';
    $this->renderForm('Modifier le projet', $project, $phases, $error);
    require __DIR__ . '/../views/layout_bottom.php';
  }

  public function view(): void {
    Auth::requireLogin();
    $id = (int)($_GET['id'] ?? 0);
    $project = Project::find($id);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }

    $phases = Phase::listByProject($id);
    $invoices = Invoice::listByProject($id);
    $decompteTotal = Decompte::totalHt($id);

    $draftCount = 0;
    $validatedCount = 0;
    foreach ($invoices as $inv) {
      if (($inv['status'] ?? '') === 'draft') $draftCount++;
      if (($inv['status'] ?? '') === 'validated') $validatedCount++;
    }

    require __DIR__ . '/../views/layout_top.php';
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h1 class="h4 mb-1"><?= e($project['project_title']) ?></h1>
        <div class="text-muted">
          <?= e($project['client_name']) ?>
          <?php if (!empty($project['contract_ref'])): ?>
            • Contrat: <?= e($project['contract_ref']) ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="/?r=projects">Retour</a>
        <a class="btn btn-primary" href="/?r=project_edit&id=<?= e((string)$id) ?>">Modifier</a>

        <!-- Actions pro -->
        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalDeleteNh">
          Supprimer toutes les NH
        </button>
        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalDeleteProject">
          Supprimer le projet
        </button>
      </div>
    </div>

    <!-- Résumé -->
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h2 class="h6">Paramètres</h2>
            <ul class="mb-0">
              <li>Montant travaux HT (Conception): <span class="mono"><?= e(fmt_money($project['work_amount_ht_conception'])) ?></span></li>
              <li>% Honoraire global: <span class="mono"><?= e(fmt_percent($project['honor_percent'])) ?>%</span></li>
              <li>Total décomptes HT (Suivi): <span class="mono"><?= e(fmt_money($decompteTotal)) ?></span> <a class="ms-2" href="/?r=decomptes&project_id=<?= e((string)$id) ?>">gérer</a></li>
              <li>TVA: <span class="mono"><?= e(fmt_percent($project['tva_percent'])) ?>%</span></li>
              <li>Retenue TVA: <span class="mono"><?= e(fmt_percent($project['retenue_tva_percent'])) ?>%</span></li>
              <li>Retenue HT: <span class="mono"><?= e(fmt_percent($project['retenue_ht_percent'])) ?>%</span></li>
            </ul>

            <hr class="my-3">

            <div class="d-flex flex-wrap gap-2">
              <span class="badge bg-secondary">NH: <?= e((string)count($invoices)) ?></span>
              <span class="badge bg-secondary">Draft: <?= e((string)$draftCount) ?></span>
              <span class="badge bg-success">Validées: <?= e((string)$validatedCount) ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-body">
            <h2 class="h6">Phases (somme = 100%)</h2>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead><tr><th>#</th><th>Phase</th><th>Base</th><th class="text-end">Taux</th></tr></thead>
                <tbody>
                  <?php foreach ($phases as $i => $ph): ?>
                    <tr>
                      <td class="mono"><?= e((string)($i+1)) ?></td>
                      <td><?= e($ph['label']) ?></td>
                      <td><?= e($ph['base_type']) ?></td>
                      <td class="mono text-end"><?= e(fmt_percent($ph['rate_percent'])) ?>%</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="mt-3">
              <a class="btn btn-sm btn-outline-primary" href="/?r=invoice_generate&project_id=<?= e((string)$id) ?>">
                Générer NH (brouillons)
              </a>
              <div class="text-muted small mt-1">Génère NH01..NH09 si elles n’existent pas.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-body">
        <h2 class="h6">Notes d’honoraires (NH)</h2>
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead><tr><th>NH</th><th>Statut</th><th class="text-end">HT</th><th class="text-end">TVA</th><th class="text-end">TTC</th><th class="text-end">Net</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv): ?>
              <tr>
                <td class="mono">NH<?= str_pad((string)$inv['nh_number'], 2, '0', STR_PAD_LEFT) ?></td>
                <td><?= e($inv['status']) ?></td>
                <td class="mono text-end"><?= e(fmt_money($inv['total_ht'])) ?></td>
                <td class="mono text-end"><?= e(fmt_money($inv['tva_amount'])) ?></td>
                <td class="mono text-end"><?= e(fmt_money($inv['total_ttc'])) ?></td>
                <td class="mono text-end fw-semibold"><?= e(fmt_money($inv['net_to_pay'])) ?></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="/?r=invoice_view&id=<?= e((string)$inv['id']) ?>">Voir</a>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($invoices)): ?>
              <tr><td colspan="7" class="text-muted">Aucune NH pour ce projet.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===================== MODAL : SUPPRIMER NH ===================== -->
    <div class="modal fade" id="modalDeleteNh" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Supprimer toutes les NH</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
          </div>
          <div class="modal-body">
            Cette action va supprimer <strong>toutes les NH</strong> de ce projet (draft + validées),
            ainsi que leurs lignes et snapshots.<br><br>
            <span class="text-danger fw-semibold">Action irréversible.</span>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <form method="post" action="/?r=project_delete_invoices&id=<?= e((string)$id) ?>">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <button class="btn btn-outline-danger">Oui, supprimer toutes les NH</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== MODAL : SUPPRIMER PROJET ===================== -->
    <div class="modal fade" id="modalDeleteProject" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Supprimer le projet</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
          </div>
          <div class="modal-body">
            Cette action va supprimer le projet <strong><?= e($project['project_title']) ?></strong> et toutes ses données :
            phases, décomptes, NH (draft + validées), lignes et snapshots.<br><br>
            <span class="text-danger fw-semibold">Action irréversible.</span>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
            <form method="post" action="/?r=project_delete&id=<?= e((string)$id) ?>">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <button class="btn btn-danger">Oui, supprimer le projet</button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php require __DIR__ . '/../views/layout_bottom.php';
  }

  /**
   * POST /?r=project_delete_invoices&id=XX
   * Supprime toutes les NH (draft + validated) d'un projet
   */
  public function deleteInvoices(): void {
    Auth::requireLogin();
    csrf_check();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      echo "Méthode non autorisée";
      exit;
    }

    $projectId = (int)($_GET['id'] ?? 0);
    if ($projectId <= 0) { http_response_code(400); echo "ID invalide"; exit; }

    $project = Project::find($projectId);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }

    $pdo = Database::pdo();
    $pdo->beginTransaction();

    try {
      $st = $pdo->prepare("SELECT id FROM invoices WHERE project_id=?");
      $st->execute([$projectId]);
      $ids = array_map(fn($r)=>(int)$r['id'], $st->fetchAll());

      if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("DELETE FROM invoice_snapshots WHERE invoice_id IN ($in)")->execute($ids);
        $pdo->prepare("DELETE FROM invoice_lines WHERE invoice_id IN ($in)")->execute($ids);
        $pdo->prepare("DELETE FROM invoices WHERE id IN ($in)")->execute($ids);
      }

      $pdo->commit();
      redirect('/?r=project_view&id=' . $projectId);
    } catch (Throwable $e) {
      $pdo->rollBack();
      http_response_code(500);
      echo "Erreur suppression NH: " . e($e->getMessage());
      exit;
    }
  }

  /**
   * POST /?r=project_delete&id=XX
   * Supprime un projet + tout ce qui dépend (phases, décomptes, NH, lignes, snapshots)
   */
  public function delete(): void {
    Auth::requireLogin();
    csrf_check();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      echo "Méthode non autorisée";
      exit;
    }

    $projectId = (int)($_GET['id'] ?? 0);
    if ($projectId <= 0) { http_response_code(400); echo "ID invalide"; exit; }

    $project = Project::find($projectId);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }

    $pdo = Database::pdo();
    $pdo->beginTransaction();

    try {
      // 1) invoices -> lines -> snapshots
      $st = $pdo->prepare("SELECT id FROM invoices WHERE project_id=?");
      $st->execute([$projectId]);
      $invoiceIds = array_map(fn($r)=>(int)$r['id'], $st->fetchAll());

      if (!empty($invoiceIds)) {
        $in = implode(',', array_fill(0, count($invoiceIds), '?'));
        $pdo->prepare("DELETE FROM invoice_snapshots WHERE invoice_id IN ($in)")->execute($invoiceIds);
        $pdo->prepare("DELETE FROM invoice_lines WHERE invoice_id IN ($in)")->execute($invoiceIds);
        $pdo->prepare("DELETE FROM invoices WHERE id IN ($in)")->execute($invoiceIds);
      }

      // 2) décomptes
      $pdo->prepare("DELETE FROM decomptes WHERE project_id=?")->execute([$projectId]);

      // 3) phases
      $pdo->prepare("DELETE FROM project_phases WHERE project_id=?")->execute([$projectId]);

      // 4) projet
      $pdo->prepare("DELETE FROM projects WHERE id=?")->execute([$projectId]);

      $pdo->commit();
      redirect('/?r=projects');
    } catch (Throwable $e) {
      $pdo->rollBack();
      http_response_code(500);
      echo "Erreur suppression projet: " . e($e->getMessage());
      exit;
    }
  }

  private function readProjectPost(): array {
    return [
      'client_name' => trim((string)($_POST['client_name'] ?? '')),
      'client_address' => trim((string)($_POST['client_address'] ?? '')),
      'project_title' => trim((string)($_POST['project_title'] ?? '')),
      'project_location' => trim((string)($_POST['project_location'] ?? '')),
      'contract_ref' => trim((string)($_POST['contract_ref'] ?? '')),
      'work_amount_ht_conception' => (float)($_POST['work_amount_ht_conception'] ?? 0),
      'honor_percent' => (float)($_POST['honor_percent'] ?? 0),
      'tva_percent' => (float)($_POST['tva_percent'] ?? 20),
      'retenue_tva_percent' => (float)($_POST['retenue_tva_percent'] ?? 75),
      'retenue_ht_percent' => (float)($_POST['retenue_ht_percent'] ?? 5),
    ];
  }

  private function readPhasesPost(): array {
    $labels = $_POST['phase_label'] ?? [];
    $rates  = $_POST['phase_rate'] ?? [];
    $bases  = $_POST['phase_base'] ?? [];
    $out = [];
    for ($i=0; $i<count($labels); $i++) {
      $label = trim((string)$labels[$i]);
      if ($label === '') continue;
      $out[] = [
        'label' => $label,
        'rate_percent' => (float)$rates[$i],
        'base_type' => ($bases[$i] === 'suivi') ? 'suivi' : 'conception',
      ];
    }
    return $out;
  }

  private function defaultPhases(): array {
    return [
      ['label'=>'Etude d’esquisse', 'rate_percent'=>5, 'base_type'=>'conception'],
      ['label'=>'APS', 'rate_percent'=>10, 'base_type'=>'conception'],
      ['label'=>'APD', 'rate_percent'=>10, 'base_type'=>'conception'],
      ['label'=>'Dossier permis / autorisation', 'rate_percent'=>5, 'base_type'=>'conception'],
      ['label'=>'Projet d’exécution (PE)', 'rate_percent'=>10, 'base_type'=>'conception'],
      ['label'=>'DCE', 'rate_percent'=>10, 'base_type'=>'conception'],
      ['label'=>'Suivi et contrôle des travaux', 'rate_percent'=>35, 'base_type'=>'suivi'],
      ['label'=>'Réception provisoire', 'rate_percent'=>10, 'base_type'=>'suivi'],
      ['label'=>'Réception définitive', 'rate_percent'=>5, 'base_type'=>'suivi'],
    ];
  }

  private function renderForm(string $title, ?array $project, array $phases, ?string $error): void {
    $p = $project ?? [
      'client_name'=>'','client_address'=>'','project_title'=>'','project_location'=>'','contract_ref'=>'',
      'work_amount_ht_conception'=>0,'honor_percent'=>4,'tva_percent'=>20,'retenue_tva_percent'=>75,'retenue_ht_percent'=>5
    ];
    ?>
    <h1 class="h4 mb-3"><?= e($title) ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Client / Maître d’ouvrage</label>
          <input class="form-control" name="client_name" value="<?= e((string)$p['client_name']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Adresse client</label>
          <input class="form-control" name="client_address" value="<?= e((string)$p['client_address']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Titre du projet</label>
          <input class="form-control" name="project_title" value="<?= e((string)$p['project_title']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Lieu du projet</label>
          <input class="form-control" name="project_location" value="<?= e((string)$p['project_location']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Référence contrat/marché</label>
          <input class="form-control" name="contract_ref" value="<?= e((string)$p['contract_ref']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Montant HT (Conception)</label>
          <input class="form-control mono" type="number" step="0.01" name="work_amount_ht_conception" value="<?= e((string)$p['work_amount_ht_conception']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">% Honoraire global</label>
          <input class="form-control mono" type="number" step="0.01" name="honor_percent" value="<?= e((string)$p['honor_percent']) ?>" required>
        </div>

        <div class="col-md-3">
          <label class="form-label">TVA (%)</label>
          <input class="form-control mono" type="number" step="0.01" name="tva_percent" value="<?= e((string)$p['tva_percent']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Retenue TVA (%)</label>
          <input class="form-control mono" type="number" step="0.01" name="retenue_tva_percent" value="<?= e((string)$p['retenue_tva_percent']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Retenue HT (%)</label>
          <input class="form-control mono" type="number" step="0.01" name="retenue_ht_percent" value="<?= e((string)$p['retenue_ht_percent']) ?>" required>
        </div>
      </div>

      <hr class="my-4">
      <div class="d-flex align-items-center justify-content-between">
        <h2 class="h6 mb-0">Phases (somme = 100%)</h2>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addRow()">+ Ajouter</button>
      </div>

      <div class="table-responsive mt-2">
        <table class="table table-sm" id="phasesTbl">
          <thead><tr><th>Phase</th><th class="text-end">Taux (%)</th><th>Base</th><th class="text-end"></th></tr></thead>
          <tbody>
            <?php foreach ($phases as $ph): ?>
              <tr>
                <td><input class="form-control" name="phase_label[]" value="<?= e($ph['label']) ?>" required></td>
                <td><input class="form-control mono text-end" type="number" step="0.01" name="phase_rate[]" value="<?= e((string)$ph['rate_percent']) ?>" required></td>
                <td>
                  <select class="form-select" name="phase_base[]">
                    <option value="conception" <?= $ph['base_type']==='conception'?'selected':'' ?>>conception</option>
                    <option value="suivi" <?= $ph['base_type']==='suivi'?'selected':'' ?>>suivi</option>
                  </select>
                </td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">Supprimer</button></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-primary">Enregistrer</button>
        <a class="btn btn-outline-secondary" href="/?r=projects">Annuler</a>
      </div>
    </form>

    <script>
      function addRow() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><input class="form-control" name="phase_label[]" required></td>
          <td><input class="form-control mono text-end" type="number" step="0.01" name="phase_rate[]" value="0" required></td>
          <td>
            <select class="form-select" name="phase_base[]">
              <option value="conception">conception</option>
              <option value="suivi">suivi</option>
            </select>
          </td>
          <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()">Supprimer</button></td>
        `;
        document.querySelector('#phasesTbl tbody').appendChild(tr);
      }
    </script>
    <?php
  }
}
