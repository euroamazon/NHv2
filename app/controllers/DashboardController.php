<?php
declare(strict_types=1);

final class DashboardController {
  public function index(): void {
    Auth::requireLogin();
    $pdo = Database::pdo();

    $projectsCount = (int)$pdo->query("SELECT COUNT(*) c FROM projects")->fetch()['c'];
    $invoicesCount = (int)$pdo->query("SELECT COUNT(*) c FROM invoices")->fetch()['c'];

    $draftCount = (int)$pdo->query("SELECT COUNT(*) c FROM invoices WHERE status='draft'")->fetch()['c'];
    $validatedCount = (int)$pdo->query("SELECT COUNT(*) c FROM invoices WHERE status='validated'")->fetch()['c'];

    $totValidated = $pdo->query("
      SELECT
        COALESCE(SUM(total_ht),0)  AS s_ht,
        COALESCE(SUM(tva_amount),0) AS s_tva,
        COALESCE(SUM(total_ttc),0) AS s_ttc,
        COALESCE(SUM(net_to_pay),0) AS s_net
      FROM invoices
      WHERE status='validated'
    ")->fetch();

    $totAll = $pdo->query("
      SELECT
        COALESCE(SUM(total_ht),0)  AS s_ht,
        COALESCE(SUM(tva_amount),0) AS s_tva,
        COALESCE(SUM(total_ttc),0) AS s_ttc,
        COALESCE(SUM(net_to_pay),0) AS s_net
      FROM invoices
    ")->fetch();

    $decomptesTotal = (float)$pdo->query("SELECT COALESCE(SUM(amount_ht),0) s FROM decomptes")->fetch()['s'];

    $lastProject = $pdo->query("SELECT id, client_name, project_title FROM projects ORDER BY id DESC LIMIT 1")->fetch();
    $lastInvoice = $pdo->query("
      SELECT id, project_id, nh_number, status, issue_date, net_to_pay
      FROM invoices
      ORDER BY id DESC
      LIMIT 1
    ")->fetch();

    // Top 5 projets (activité NH)
    $topProjects = $pdo->query("
      SELECT p.id, p.project_title, p.client_name,
             COALESCE(COUNT(i.id),0) nb_nh,
             COALESCE(SUM(CASE WHEN i.status='validated' THEN i.net_to_pay ELSE 0 END),0) net_validated
      FROM projects p
      LEFT JOIN invoices i ON i.project_id = p.id
      GROUP BY p.id
      ORDER BY p.id DESC
      LIMIT 5
    ")->fetchAll();

    require __DIR__ . '/../views/layout_top.php';
    ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h1 class="h4 page-title mb-1">Tableau de bord</h1>
        <div class="text-muted small">Vue rapide : projets, NH, montants et activité</div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-primary" href="/?r=project_create">
          <i class="bi bi-plus-lg me-1"></i> Nouveau projet
        </a>
        <a class="btn btn-outline-secondary" href="/?r=projects">
          <i class="bi bi-folder2-open me-1"></i> Projets
        </a>
      </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3">
      <div class="col-md-3">
        <div class="card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Projets</div>
              <div class="display-6 mono"><?= e((string)$projectsCount) ?></div>
            </div>
            <div class="rounded-3 p-2" style="background:#eef2ff;">
              <i class="bi bi-building fs-4"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">NH (Total)</div>
              <div class="display-6 mono"><?= e((string)$invoicesCount) ?></div>
              <div class="mt-2 d-flex gap-2">
                <span class="badge bg-secondary">Draft: <?= e((string)$draftCount) ?></span>
                <span class="badge bg-success">Validées: <?= e((string)$validatedCount) ?></span>
              </div>
            </div>
            <div class="rounded-3 p-2" style="background:#ecfeff;">
              <i class="bi bi-receipt fs-4"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Net (Validées)</div>
              <div class="h2 mono mb-0"><?= e(fmt_money((float)$totValidated['s_net'])) ?></div>
              <div class="text-muted small mt-1">Somme des NH validées</div>
            </div>
            <div class="rounded-3 p-2" style="background:#f0fdf4;">
              <i class="bi bi-cash-stack fs-4"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Décomptes HT (Total)</div>
              <div class="h2 mono mb-0"><?= e(fmt_money($decomptesTotal)) ?></div>
              <div class="text-muted small mt-1">Tous projets</div>
            </div>
            <div class="rounded-3 p-2" style="background:#fff7ed;">
              <i class="bi bi-clipboard2-data fs-4"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Totaux -->
    <div class="row g-3 mt-1">
      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h2 class="h6 mb-0">Totaux NH validées</h2>
              <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="bi bi-check2-circle me-1"></i> Validées
              </span>
            </div>
            <div class="table-responsive">
              <table class="table table-sm">
                <tr><th>Total HT</th><td class="mono text-end"><?= e(fmt_money((float)$totValidated['s_ht'])) ?></td></tr>
                <tr><th>TVA</th><td class="mono text-end"><?= e(fmt_money((float)$totValidated['s_tva'])) ?></td></tr>
                <tr><th>Total TTC</th><td class="mono text-end"><?= e(fmt_money((float)$totValidated['s_ttc'])) ?></td></tr>
                <tr class="table-active"><th>Net à payer</th><td class="mono text-end fw-semibold"><?= e(fmt_money((float)$totValidated['s_net'])) ?></td></tr>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
              <h2 class="h6 mb-0">Totaux toutes NH</h2>
              <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                <i class="bi bi-layers me-1"></i> Draft + Validées
              </span>
            </div>
            <div class="table-responsive">
              <table class="table table-sm">
                <tr><th>Total HT</th><td class="mono text-end"><?= e(fmt_money((float)$totAll['s_ht'])) ?></td></tr>
                <tr><th>TVA</th><td class="mono text-end"><?= e(fmt_money((float)$totAll['s_tva'])) ?></td></tr>
                <tr><th>Total TTC</th><td class="mono text-end"><?= e(fmt_money((float)$totAll['s_ttc'])) ?></td></tr>
                <tr class="table-active"><th>Net (calculé)</th><td class="mono text-end fw-semibold"><?= e(fmt_money((float)$totAll['s_net'])) ?></td></tr>
              </table>
            </div>
            <div class="text-muted small mt-2">Les “draft” peuvent évoluer si tu changes les paramètres.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Activité -->
    <div class="row g-3 mt-1">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <h2 class="h6 mb-3">Dernier projet</h2>
            <?php if ($lastProject): ?>
              <div class="fw-semibold"><?= e($lastProject['project_title'] ?? '') ?></div>
              <div class="text-muted"><?= e($lastProject['client_name'] ?? '') ?></div>
              <div class="mt-3">
                <a class="btn btn-sm btn-outline-primary" href="/?r=project_view&id=<?= e((string)$lastProject['id']) ?>">
                  <i class="bi bi-box-arrow-in-right me-1"></i> Ouvrir
                </a>
              </div>
            <?php else: ?>
              <div class="text-muted">Aucun projet pour le moment.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <h2 class="h6 mb-3">Dernière NH</h2>
            <?php if ($lastInvoice): ?>
              <div class="d-flex align-items-center justify-content-between">
                <div class="fw-semibold">NH<?= str_pad((string)$lastInvoice['nh_number'], 2, '0', STR_PAD_LEFT) ?></div>
                <span class="badge <?= ($lastInvoice['status']==='validated')?'bg-success':'bg-secondary' ?>">
                  <?= e($lastInvoice['status']) ?>
                </span>
              </div>
              <div class="text-muted small mt-1">Date: <?= e((string)$lastInvoice['issue_date']) ?></div>
              <div class="mono mt-2">Net: <span class="fw-semibold"><?= e(fmt_money((float)$lastInvoice['net_to_pay'])) ?></span></div>
              <div class="mt-3 d-flex gap-2">
                <a class="btn btn-sm btn-outline-primary" href="/?r=invoice_view&id=<?= e((string)$lastInvoice['id']) ?>">
                  <i class="bi bi-eye me-1"></i> Voir
                </a>
                <a class="btn btn-sm btn-outline-secondary" href="/?r=invoice_pdf&id=<?= e((string)$lastInvoice['id']) ?>" target="_blank">
                  <i class="bi bi-filetype-pdf me-1"></i> PDF
                </a>
              </div>
            <?php else: ?>
              <div class="text-muted">Aucune NH générée.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Liste rapide -->
    <div class="card mt-3">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h6 mb-0">Derniers projets</h2>
          <a class="btn btn-sm btn-outline-secondary" href="/?r=projects">
            <i class="bi bi-arrow-right me-1"></i> Tout voir
          </a>
        </div>
        <div class="table-responsive">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Projet</th>
                <th>Client</th>
                <th class="text-end">NH</th>
                <th class="text-end">Net (validées)</th>
                <th class="text-end"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($topProjects as $p): ?>
                <tr>
                  <td class="fw-semibold"><?= e($p['project_title'] ?? '') ?></td>
                  <td class="text-muted"><?= e($p['client_name'] ?? '') ?></td>
                  <td class="mono text-end"><?= e((string)$p['nb_nh']) ?></td>
                  <td class="mono text-end"><?= e(fmt_money((float)$p['net_validated'])) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="/?r=project_view&id=<?= e((string)$p['id']) ?>">
                      Ouvrir
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($topProjects)): ?>
                <tr><td colspan="5" class="text-muted">Aucun projet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php
    require __DIR__ . '/../views/layout_bottom.php';
  }
}
