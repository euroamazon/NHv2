<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Project.php';
require_once __DIR__ . '/../models/Phase.php';
require_once __DIR__ . '/../models/Invoice.php';
require_once __DIR__ . '/../models/Decompte.php';
require_once __DIR__ . '/../models/Settings.php';
require_once __DIR__ . '/../core/Pdf.php';

final class InvoiceController {

  public function generate(): void {
    Auth::requireLogin();
    $projectId = (int)($_GET['project_id'] ?? 0);
    $project = Project::find($projectId);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }

    $phases = Phase::listByProject($projectId);
    $pdo = Database::pdo();

    // sécurité si Phase ne renvoie pas id
    if (empty($phases) || !isset($phases[0]['id'])) {
      $st = $pdo->prepare("SELECT * FROM project_phases WHERE project_id=? ORDER BY sort_order ASC, id ASC");
      $st->execute([$projectId]);
      $phases = $st->fetchAll();
    }

    $workSuiviHtTotal = Decompte::totalHt($projectId);

    // NH existantes (draft ou validated)
    $existing = $pdo->prepare("SELECT nh_number FROM invoices WHERE project_id=?");
    $existing->execute([$projectId]);
    $have = array_flip(array_map(fn($r)=>(int)$r['nh_number'], $existing->fetchAll()));

    foreach ($phases as $i => $ph) {
      $nh = $i + 1;
      if (isset($have[$nh])) continue;
      if (!isset($ph['id'])) { http_response_code(500); echo "Erreur: phase_id manquant"; exit; }

      // ✅ Logique Excel (Invoice.php)
      Invoice::createDraft($projectId, $nh, $ph, $project, $workSuiviHtTotal);
    }

    redirect('/?r=project_view&id=' . $projectId);
  }

  /**
   * Supprimer TOUTES les NH du projet (draft + validated)
   */
  public function deleteDrafts(): void {
    Auth::requireLogin();
    csrf_check();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      echo "Méthode non autorisée";
      exit;
    }

    $projectId = (int)($_GET['project_id'] ?? 0);
    if ($projectId <= 0) { http_response_code(400); echo "project_id invalide"; exit; }

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

  public function view(): void {
    Auth::requireLogin();
    $id = (int)($_GET['id'] ?? 0);
    $inv = Invoice::find($id);
    if (!$inv) { http_response_code(404); echo "NH introuvable"; exit; }

    $project = Project::find((int)$inv['project_id']);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }

    $lines = Invoice::lines($id);

    $status = (string)$inv['status'];
    $badgeClass = ($status === 'validated') ? 'bg-success' : 'bg-secondary';

    // ✅ Toggle dates (par défaut: sans dates)
    $showDates = ((int)($_GET['show_dates'] ?? 0) === 1);

    // URLs actions qui conservent show_dates
    $urlPdfOpen = '/?r=invoice_pdf&id=' . $id . ($showDates ? '&show_dates=1' : '');
    $urlPdfDl   = '/?r=invoice_pdf&id=' . $id . '&dl=1' . ($showDates ? '&show_dates=1' : '');
    $urlPrint   = '/?r=invoice_print&id=' . $id . ($showDates ? '&show_dates=1' : '');
    $urlToggle  = '/?r=invoice_view&id=' . $id . ($showDates ? '' : '&show_dates=1');

    require __DIR__ . '/../views/layout_top.php';
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <div class="text-muted small mb-1">
          <a href="/?r=projects" class="text-decoration-none">Projets</a>
          <span class="mx-1">/</span>
          <a href="/?r=project_view&id=<?= e((string)$inv['project_id']) ?>" class="text-decoration-none"><?= e($project['project_title'] ?? '') ?></a>
          <span class="mx-1">/</span>
          <span class="text-muted">NH<?= str_pad((string)$inv['nh_number'], 2, '0', STR_PAD_LEFT) ?></span>
        </div>

        <div class="d-flex align-items-center gap-2">
          <h1 class="h4 page-title mb-0">NH<?= str_pad((string)$inv['nh_number'], 2, '0', STR_PAD_LEFT) ?></h1>
          <span class="badge <?= e($badgeClass) ?>"><?= e($status) ?></span>
        </div>

        <div class="text-muted small mt-1">
          <?= e($project['client_name'] ?? '') ?> • <?= e($project['project_title'] ?? '') ?>
        </div>
      </div>

      <div class="d-flex flex-wrap gap-2">

        <!-- ✅ Toggle Avec/Sans dates -->
        <a class="btn btn-outline-dark" href="<?= e($urlToggle) ?>">
          <i class="bi bi-calendar2-week me-1"></i>
          <?= $showDates ? 'Sans dates' : 'Avec dates' ?>
        </a>

        <a class="btn btn-outline-secondary" href="/?r=project_view&id=<?= e((string)$inv['project_id']) ?>">
          <i class="bi bi-arrow-left me-1"></i> Retour
        </a>

        <!-- ✅ Ouvrir PDF -->
        <a class="btn btn-outline-secondary" href="<?= e($urlPdfOpen) ?>" target="_blank">
          <i class="bi bi-filetype-pdf me-1"></i> Ouvrir le PDF (Dompdf)
        </a>

        <!-- ✅ Télécharger PDF -->
        <a class="btn btn-outline-primary" href="<?= e($urlPdfDl) ?>">
          <i class="bi bi-download me-1"></i> Télécharger PDF
        </a>

        <!-- ✅ Impression navigateur -->
        <a class="btn btn-outline-primary" href="<?= e($urlPrint) ?>" target="_blank">
          <i class="bi bi-printer me-1"></i> Imprimer (Ctrl+P)
        </a>

        <?php if ($status !== 'validated'): ?>
          <form method="post" action="/?r=invoice_validate&id=<?= e((string)$id) ?>" class="d-inline"
                onsubmit="return confirmAction('Valider cette NH ? Après validation elle sera figée.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-success" type="submit">
              <i class="bi bi-check2-circle me-1"></i> Valider
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- KPI row -->
    <div class="row g-3 mb-3">
      <div class="col-md-3">
        <div class="card kpi-card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Date</div>
              <div class="mono fw-semibold"><?= e((string)$inv['issue_date']) ?></div>
            </div>
            <div class="kpi-icon" style="background:#ecfeff;">
              <i class="bi bi-calendar3"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card kpi-card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Total HT</div>
              <div class="mono fw-semibold"><?= e(fmt_money((float)$inv['total_ht'])) ?></div>
            </div>
            <div class="kpi-icon" style="background:#fff7ed;">
              <i class="bi bi-calculator"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card kpi-card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Total TTC</div>
              <div class="mono fw-semibold"><?= e(fmt_money((float)$inv['total_ttc'])) ?></div>
            </div>
            <div class="kpi-icon" style="background:#f0fdf4;">
              <i class="bi bi-receipt"></i>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="card kpi-card h-100">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="text-muted small">Net à payer</div>
              <div class="mono fw-bold fs-5"><?= e(fmt_money((float)$inv['net_to_pay'])) ?></div>
            </div>
            <div class="kpi-icon" style="background:#eef2ff;">
              <i class="bi bi-cash-stack"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Détails -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h2 class="h6 mb-0">Détails (phases)</h2>
          <span class="text-muted small">Lignes: <span class="mono fw-semibold"><?= e((string)count($lines)) ?></span></span>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Phase</th>
                <th style="width:120px;">Type</th>
                <th class="text-end" style="width:110px;">Taux</th>
                <th class="text-end" style="width:170px;">Montant de base</th>
                <th class="text-end" style="width:170px;">Honoraires (HT)</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $ln): ?>
              <tr>
                <td class="fw-semibold"><?= e((string)$ln['phase_label']) ?></td>
                <td class="text-muted"><?= e((string)$ln['base_type']) ?></td>
                <td class="mono text-end"><?= e(fmt_percent((float)$ln['phase_rate_percent'])) ?>%</td>
                <td class="mono text-end"><?= e(fmt_money((float)$ln['base_amount_ht'])) ?></td>
                <td class="mono text-end fw-semibold"><?= e(fmt_money((float)$ln['line_ht'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

    <!-- Totaux -->
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <h2 class="h6 mb-3">Récapitulatif</h2>
            <table class="table table-sm">
              <tr><th>Total HT</th><td class="mono text-end"><?= e(fmt_money((float)$inv['total_ht'])) ?></td></tr>
              <tr><th>TVA</th><td class="mono text-end"><?= e(fmt_money((float)$inv['tva_amount'])) ?></td></tr>
              <tr><th>Total TTC</th><td class="mono text-end"><?= e(fmt_money((float)$inv['total_ttc'])) ?></td></tr>
              <tr><th>Déjà perçu</th><td class="mono text-end">-<?= e(fmt_money((float)$inv['already_received'])) ?></td></tr>
              <tr><th>Retenue TVA</th><td class="mono text-end">-<?= e(fmt_money((float)$inv['retenue_tva_amount'])) ?></td></tr>
              <tr><th>Retenue HT</th><td class="mono text-end">-<?= e(fmt_money((float)$inv['retenue_ht_amount'])) ?></td></tr>
              <tr class="table-active"><th>Net à payer</th><td class="mono text-end fw-bold"><?= e(fmt_money((float)$inv['net_to_pay'])) ?></td></tr>
            </table>

            <?php if ($status === 'validated'): ?>
              <div class="alert alert-success mb-0">
                <i class="bi bi-lock-fill me-1"></i> Cette NH est validée (figée).
              </div>
            <?php else: ?>
              <div class="alert alert-secondary mb-0">
                <i class="bi bi-pencil-square me-1"></i> Cette NH est en brouillon (peut changer).
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100">
          <div class="card-body">
            <h2 class="h6 mb-3">Actions</h2>
            <div class="d-grid gap-2">
              <a class="btn btn-outline-secondary" href="<?= e($urlPdfOpen) ?>" target="_blank">
                <i class="bi bi-filetype-pdf me-1"></i> Ouvrir le PDF (Dompdf)
              </a>

              <a class="btn btn-outline-primary" href="<?= e($urlPdfDl) ?>">
                <i class="bi bi-download me-1"></i> Télécharger le PDF
              </a>

              <a class="btn btn-outline-primary" href="<?= e($urlPrint) ?>" target="_blank">
                <i class="bi bi-printer me-1"></i> Ouvrir la page à imprimer (Ctrl+P)
              </a>

              <a class="btn btn-outline-primary" href="/?r=project_view&id=<?= e((string)$inv['project_id']) ?>">
                <i class="bi bi-folder2-open me-1"></i> Retour au projet
              </a>

              <?php if ($status !== 'validated'): ?>
                <form method="post" action="/?r=invoice_validate&id=<?= e((string)$id) ?>"
                      onsubmit="return confirmAction('Valider cette NH ? Après validation elle sera figée.');">
                  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                  <button class="btn btn-success w-100" type="submit">
                    <i class="bi bi-check2-circle me-1"></i> Valider cette NH
                  </button>
                </form>
              <?php endif; ?>
            </div>

            <div class="text-muted small mt-3">
              Astuce : “Imprimer (Ctrl+P)” conserve mieux les couleurs (activer “Arrière-plans” dans l’impression).
            </div>
          </div>
        </div>
      </div>
    </div>

    <?php
    require __DIR__ . '/../views/layout_bottom.php';
  }

  public function validate(): void {
    Auth::requireLogin();
    csrf_check();
    $id = (int)($_GET['id'] ?? 0);
    $inv = Invoice::find($id);
    if (!$inv) { http_response_code(404); echo "NH introuvable"; exit; }
    Invoice::validate($id);
    redirect('/?r=invoice_view&id=' . $id);
  }

  /**
   * ✅ Page HTML optimisée pour Ctrl+P
   * Route: /?r=invoice_print&id=XX
   */
  public function print(): void {
    Auth::requireLogin();
    $id = (int)($_GET['id'] ?? 0);
    $inv = Invoice::find($id);
    if (!$inv) { http_response_code(404); echo "NH introuvable"; exit; }

    $project = Project::find((int)$inv['project_id']);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }

    $lines = Invoice::lines($id);
    $s = Settings::getAll();

    // ✅ Toggle dates (par défaut: sans dates)
    $showDates = ((int)($_GET['show_dates'] ?? 0) === 1);

    // Décomptes snapshot si validated
    $snap = Invoice::snapshot($id);
    $decomptes = [];
    if ($inv['status'] === 'validated' && $snap && isset($snap['decomptes']) && is_array($snap['decomptes'])) {
      $decomptes = $snap['decomptes'];
    } else {
      $decomptes = Decompte::listByProject((int)$inv['project_id']);
    }

    $formatDateFr = static function(?string $ymd): string {
      if (!$ymd) return '';
      try { $dt = new DateTime($ymd); } catch (Throwable $e) { return (string)$ymd; }
      $months = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
      $m = (int)$dt->format('n');
      return $dt->format('d').' '.($months[$m] ?? $dt->format('m')).' '.$dt->format('Y');
    };

    $moneyDh = static function(float $amount): string {
      return number_format($amount, 2, ',', ' ') . ' DH';
    };

    $marketNo = '';
    foreach ($decomptes as $d) {
      $txt = (string)($d['decompte_no'] ?? '');
      if (preg_match('~marché\s*([0-9]{1,4}\s*/\s*[0-9]{4})~iu', $txt, $m)) {
        $marketNo = preg_replace('~\s+~', '', $m[1]);
        break;
      }
    }

    $cfg = require __DIR__ . '/../config.php';
    $baseUrl = rtrim((string)($cfg['app']['base_url'] ?? 'https://nh.acoconsulting.ma'), '/');
    $logoPath = (string)($s['logo_path'] ?? '');
    $logoUrl  = $logoPath ? ($baseUrl . $logoPath) : '';

    $city = (string)($s['invoice_city'] ?? '');
    $amountWords = fr_number_to_words((float)$inv['net_to_pay']);

    $nhLabel = 'NH' . str_pad((string)$inv['nh_number'], 2, '0', STR_PAD_LEFT);
    $issueDateFr = $formatDateFr((string)$inv['issue_date']);

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title><?= e($nhLabel) ?></title>
  <meta name="color-scheme" content="light only">
  <style>
    @page { size: A4; margin: 7mm; }
    html, body { margin:0; padding:0; background:#fff; }
    body{ font-family: Arial, sans-serif; font-size: 10.2px; color:#0f172a; line-height:1.15; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    * { box-sizing:border-box; }
    table, tr, td, th { break-inside: avoid; page-break-inside: avoid; }
    .mono{font-variant-numeric: tabular-nums;}
    .muted{color:#475569;}
    .small{font-size:9px;}
    .row{display:flex; gap:8px;}
    .col{flex:1;}
    .right{text-align:right;}
    .center{text-align:center;}
    .avoid-break{break-inside: avoid; page-break-inside: avoid;}
    .topbar{ border:1px solid #e2e8f0; border-radius:12px; padding:8px; background:#f8fafc; margin-bottom:6px; }
    .headTbl{width:100%; border-collapse:collapse;}
    .headTbl td{border:none; padding:0; vertical-align:top;}
    .logo{max-height:44px;}
    .badge{ display:inline-block; padding:3px 8px; border-radius:999px; background:#0f172a; color:#fff; font-size:9px; font-weight:700; letter-spacing:.2px; }
    .card{ border:1px solid #e2e8f0; border-radius:10px; padding:8px; margin-bottom:6px; background:#fff; }
    h2{margin:0 0 6px; font-size:10.6px; letter-spacing:.2px;}
    table{width:100%; border-collapse:collapse; table-layout:fixed;}
    th,td{border:1px solid #cbd5e1; padding:3px 4px; vertical-align:top;}
    th{background:#e2e8f0; font-weight:700;}
    .totals .grand th, .totals .grand td{ background:#0f172a; color:#fff; font-weight:800; border-color:#0f172a; }
    .no-print{display:block; margin:10px 0;}
    @media print { .no-print{display:none !important;} }
  </style>
</head>
<body>

  <?php
    $basePrint = '/?r=invoice_print&id=' . urlencode((string)$id);
    $printNoDates = $basePrint;
    $printWithDates = $basePrint . '&show_dates=1';
  ?>
  <div class="no-print" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:10px 0;">
    <button type="button" onclick="window.print()">🖨️ Imprimer</button>

    <?php if ($showDates): ?>
      <a href="<?= e($printNoDates) ?>" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:8px; text-decoration:none;">Sans dates</a>
      <span style="padding:6px 10px; border-radius:8px; background:#0f172a; color:#fff;">Avec dates</span>
    <?php else: ?>
      <span style="padding:6px 10px; border-radius:8px; background:#0f172a; color:#fff;">Sans dates</span>
      <a href="<?= e($printWithDates) ?>" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:8px; text-decoration:none;">Avec dates</a>
    <?php endif; ?>

    <span class="small muted" style="margin-left:8px;">Chrome : activer “Arrière-plans (graphismes)”</span>
  </div>

  <div class="topbar avoid-break">
    <table class="headTbl">
      <tr>
        <td style="width:30%;">
          <?php if ($logoUrl): ?>
            <img class="logo" src="<?= e($logoUrl) ?>" alt="logo">
          <?php endif; ?>
        </td>
        <td style="width:40%;" class="center">
          <div style="font-weight:800; font-size:11px;"><?= e($s['firm_name'] ?? '') ?></div>
          <div class="small muted"><?= e($s['firm_address'] ?? '') ?></div>
          <div class="small muted">Tél: <?= e($s['firm_phone'] ?? '') ?> • <?= e($s['firm_email'] ?? '') ?></div>
        </td>
        <td style="width:30%;" class="right small">
          <div><span class="badge"><?= e($nhLabel) ?></span></div>
          <div style="height:4px;"></div>
          <?php if (!empty($project['contract_ref'])): ?>
            <div><strong>Contrat :</strong> <?= e((string)$project['contract_ref']) ?></div>
          <?php endif; ?>
          <?php if ($marketNo): ?>
            <div><strong>Marché :</strong> <?= e($marketNo) ?></div>
          <?php endif; ?>

          <?php if ($showDates): ?>
            <div><strong>Date :</strong> <?= e($issueDateFr) ?></div>
          <?php endif; ?>
        </td>
      </tr>
    </table>
  </div>

  <div class="row avoid-break">
    <div class="card col">
      <h2>Maître d’ouvrage</h2>
      <div style="font-weight:700;"><?= e($project['client_name'] ?? '') ?></div>
      <div class="muted"><?= e($project['client_address'] ?? '') ?></div>
    </div>
    <div class="card col">
      <h2>Projet</h2>
      <div style="font-weight:700;"><?= e($project['project_title'] ?? '') ?></div>
      <div class="muted"><?= e($project['project_location'] ?? '') ?></div>
      <?php if ($showDates): ?>
        <div class="muted"><?= e($city) ?>, le <?= e($issueDateFr) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card avoid-break">
    <h2>Montant des travaux / Honoraires / Décomptes</h2>
    <table>
      <tr><th>Montant des travaux HT</th><td class="right mono"><?= e($moneyDh((float)$project['work_amount_ht_conception'])) ?></td></tr>
      <tr><th>Pourcentage de l'honoraire</th><td class="right mono"><?= e(fmt_percent((float)$project['honor_percent'])) ?>%</td></tr>
      <tr><th>Honoraires HT (base travaux)</th><td class="right mono"><?= e($moneyDh((float)$inv['base_conception_ht'])) ?></td></tr>
      <tr><th>Total décomptes HT</th><td class="right mono"><?= e($moneyDh((float)$inv['base_suivi_work_ht'])) ?></td></tr>
      <tr><th>Honoraires HT (base décomptes)</th><td class="right mono"><?= e($moneyDh((float)$inv['base_suivi_ht'])) ?></td></tr>
    </table>

    <?php if (!empty($decomptes)): ?>
      <div style="height:4px;"></div>
      <div class="small muted" style="font-weight:700;">Détail des décomptes</div>
      <div style="height:4px;"></div>
      <table>
        <thead>
          <tr>
            <th style="width:58%;">Libellé</th>
            <th style="width:18%;">Date</th>
            <th class="right" style="width:24%;">Montant HT</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($decomptes as $d): ?>
            <tr>
              <td><?= e((string)($d['decompte_no'] ?? '')) ?></td>
              <td class="center mono"><?= $showDates ? e($formatDateFr((string)($d['decompte_date'] ?? ''))) : '' ?></td>
              <td class="right mono"><?= e($moneyDh((float)($d['amount_ht'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <th colspan="2" class="right">Total</th>
            <th class="right mono"><?= e($moneyDh((float)$inv['base_suivi_work_ht'])) ?></th>
          </tr>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php
    $groupA = array_values(array_filter($lines, fn($ln) => (string)$ln['base_type'] === 'conception'));
    $groupB = array_values(array_filter($lines, fn($ln) => (string)$ln['base_type'] === 'suivi'));
  ?>

  <div class="card avoid-break">
    <h2>Calcul des honoraires</h2>

    <table>
      <thead>
        <tr>
          <th style="width:8%;" class="center">Phase</th>
          <th>Contenu de la phase</th>
          <th style="width:12%;" class="right">Taux</th>
          <th style="width:22%;" class="right">Montant de base</th>
          <th style="width:20%;" class="right">Montant Honoraire</th>
        </tr>
      </thead>

      <tbody>
        <?php if (!empty($groupA)): ?>
          <?php foreach ($groupA as $i => $ln): ?>
            <tr>
              <?php if ($i === 0): ?>
                <td class="center" rowspan="<?= count($groupA) ?>" style="font-weight:700; background:#f1f5f9;">A</td>
              <?php endif; ?>
              <td><?= e((string)$ln['phase_label']) ?></td>
              <td class="right mono"><?= e(fmt_percent((float)$ln['phase_rate_percent'])) ?>%</td>
              <td class="right mono"><?= e($moneyDh((float)$ln['base_amount_ht'])) ?></td>
              <td class="right mono"><?= e($moneyDh((float)$ln['line_ht'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($groupB)): ?>
          <?php foreach ($groupB as $i => $ln): ?>
            <tr>
              <?php if ($i === 0): ?>
                <td class="center" rowspan="<?= count($groupB) ?>" style="font-weight:700; background:#f1f5f9;">B</td>
              <?php endif; ?>
              <td><?= e((string)$ln['phase_label']) ?></td>
              <td class="right mono"><?= e(fmt_percent((float)$ln['phase_rate_percent'])) ?>%</td>
              <td class="right mono"><?= e($moneyDh((float)$ln['base_amount_ht'])) ?></td>
              <td class="right mono"><?= e($moneyDh((float)$ln['line_ht'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <table class="totals avoid-break">
    <tr><th>Total HT</th><td class="right mono"><?= e($moneyDh((float)$inv['total_ht'])) ?></td></tr>
    <tr><th>TVA</th><td class="right mono"><?= e($moneyDh((float)$inv['tva_amount'])) ?></td></tr>
    <tr><th>Total TTC</th><td class="right mono"><?= e($moneyDh((float)$inv['total_ttc'])) ?></td></tr>
    <tr><th>À déduire – Déjà perçu</th><td class="right mono">-<?= e($moneyDh((float)$inv['already_received'])) ?></td></tr>
    <tr><th>À déduire – Retenue TVA</th><td class="right mono">-<?= e($moneyDh((float)$inv['retenue_tva_amount'])) ?></td></tr>
    <tr><th>À déduire – Retenue HT</th><td class="right mono">-<?= e($moneyDh((float)$inv['retenue_ht_amount'])) ?></td></tr>
    <tr class="grand"><th>MONTANT À PAYER</th><td class="right mono"><?= e($moneyDh((float)$inv['net_to_pay'])) ?></td></tr>
  </table>

  <div class="card avoid-break" style="margin-top:6px;">
    <strong>Arrêté la présente note à la somme de :</strong>
    <div class="mono" style="margin-top:3px;"><?= e($amountWords) ?></div>
  </div>

  <div class="row avoid-break" style="margin-top:6px;">
    <div class="col small">
      <strong>Banque :</strong> <?= e($s['firm_bank'] ?? '') ?><br>
      <strong>RIB :</strong> <?= e($s['firm_rib'] ?? '') ?>
    </div>
    <div class="col right">
      <div style="margin-top:18px;">Signature / Cachet</div>
    </div>
  </div>

</body>
</html>
    <?php
    exit;
  }

  public function pdf(): void {
    Auth::requireLogin();
    $id = (int)($_GET['id'] ?? 0);
    $inv = Invoice::find($id);
    if (!$inv) { http_response_code(404); echo "NH introuvable"; exit; }

    $project = Project::find((int)$inv['project_id']);
    if (!$project) { http_response_code(404); echo "Projet introuvable"; exit; }

    $lines = Invoice::lines($id);
    $s = Settings::getAll();

    // ✅ Toggle dates (par défaut: sans dates)
    $showDates = ((int)($_GET['show_dates'] ?? 0) === 1);

    // Décomptes snapshot si validated
    $snap = Invoice::snapshot($id);
    $decomptes = [];
    if ($inv['status'] === 'validated' && $snap && isset($snap['decomptes']) && is_array($snap['decomptes'])) {
      $decomptes = $snap['decomptes'];
    } else {
      $decomptes = Decompte::listByProject((int)$inv['project_id']);
    }

    $formatDateFr = static function(?string $ymd): string {
      if (!$ymd) return '';
      try { $dt = new DateTime($ymd); } catch (Throwable $e) { return (string)$ymd; }
      $months = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
      $m = (int)$dt->format('n');
      return $dt->format('d').' '.($months[$m] ?? $dt->format('m')).' '.$dt->format('Y');
    };

    $moneyDh = static function(float $amount): string {
      return number_format($amount, 2, ',', ' ') . ' DH';
    };

    $cfg = require __DIR__ . '/../config.php';
    $baseUrl = rtrim((string)($cfg['app']['base_url'] ?? 'https://nh.acoconsulting.ma'), '/');

    $logoPath = (string)($s['logo_path'] ?? '');
    $logoUrl  = $logoPath ? ($baseUrl . $logoPath) : '';

    $city = (string)($s['invoice_city'] ?? '');
    $amountWords = fr_number_to_words((float)$inv['net_to_pay']);

    $nhLabel = 'NH' . str_pad((string)$inv['nh_number'], 2, '0', STR_PAD_LEFT);
    $issueDateFr = $formatDateFr((string)$inv['issue_date']);

    $marketNo = '';
    foreach ($decomptes as $d) {
      $txt = (string)($d['decompte_no'] ?? '');
      if (preg_match('~marché\s*([0-9]{1,4}\s*/\s*[0-9]{4})~iu', $txt, $m)) {
        $marketNo = preg_replace('~\s+~', '', $m[1]);
        break;
      }
    }

    $download = ((int)($_GET['dl'] ?? 0) === 1);

    ob_start();
    ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <title><?= e($nhLabel) ?></title>
  <style>
    @page { size: A4; margin: 7mm; }
    html, body { margin:0; padding:0; }
    body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:10px; color:#0f172a; line-height:1.15; }
    .mono{font-variant-numeric: tabular-nums;}
    .muted{color:#475569;}
    .small{font-size:9px;}
    .row{display:flex; justify-content:space-between; gap:8px;}
    .card{ border:1px solid #e2e8f0; border-radius:10px; padding:8px; margin-bottom:6px; page-break-inside: avoid; }
    .topbar{ border:1px solid #e2e8f0; border-radius:12px; padding:8px; margin-bottom:6px; background:#f8fafc; page-break-inside: avoid; }
    .headTbl{width:100%; border-collapse:collapse;}
    .headTbl td{border:none; padding:0; vertical-align:top;}
    .logo{max-height:42px;}
    .right{text-align:right;}
    .center{text-align:center;}
    .badge{ display:inline-block; padding:3px 8px; border-radius:999px; background:#0f172a; color:#fff; font-size:9px; font-weight:700; letter-spacing:.2px; }
    h2{ margin:0 0 6px; font-size:10.5px; letter-spacing:.2px; }
    table{width:100%; border-collapse:collapse; table-layout:fixed;}
    th,td{border:1px solid #cbd5e1; padding:3px 4px; vertical-align:top;}
    th{background:#e2e8f0; color:#0f172a; font-weight:700;}
    tr{page-break-inside:avoid;}
    .totals .grand th, .totals .grand td{ background:#0f172a; color:#fff; font-weight:800; border-color:#0f172a; }
    .spacer{height:4px;}
  </style>
</head>
<body>

  <div class="topbar">
    <table class="headTbl">
      <tr>
        <td style="width:30%;">
          <?php if ($logoUrl): ?>
            <img class="logo" src="<?= e($logoUrl) ?>" alt="logo">
          <?php endif; ?>
        </td>
        <td style="width:40%;" class="center">
          <div style="font-weight:800; font-size:11px;"><?= e($s['firm_name'] ?? '') ?></div>
          <div class="small muted"><?= e($s['firm_address'] ?? '') ?></div>
          <div class="small muted">Tél: <?= e($s['firm_phone'] ?? '') ?> • <?= e($s['firm_email'] ?? '') ?></div>
        </td>
        <td style="width:30%;" class="right small">
          <div><span class="badge"><?= e($nhLabel) ?></span></div>
          <div class="spacer"></div>
          <?php if (!empty($project['contract_ref'])): ?>
            <div><strong>Contrat :</strong> <?= e((string)$project['contract_ref']) ?></div>
          <?php endif; ?>
          <?php if ($marketNo): ?>
            <div><strong>Marché :</strong> <?= e($marketNo) ?></div>
          <?php endif; ?>

          <?php if ($showDates): ?>
            <div><strong>Date :</strong> <?= e($issueDateFr) ?></div>
          <?php endif; ?>
        </td>
      </tr>
    </table>
  </div>

  <div class="row">
    <div class="card" style="flex:1;">
      <h2>Maître d’ouvrage</h2>
      <div style="font-weight:700;"><?= e($project['client_name'] ?? '') ?></div>
      <div class="muted"><?= e($project['client_address'] ?? '') ?></div>
    </div>
    <div class="card" style="flex:1;">
      <h2>Projet</h2>
      <div style="font-weight:700;"><?= e($project['project_title'] ?? '') ?></div>
      <div class="muted"><?= e($project['project_location'] ?? '') ?></div>
      <?php if ($showDates): ?>
        <div class="muted"><?= e($city) ?>, le <?= e($issueDateFr) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>Montant des travaux / Honoraires / Décomptes</h2>
    <table>
      <tr><th>Montant des travaux HT</th><td class="right mono"><?= e($moneyDh((float)$project['work_amount_ht_conception'])) ?></td></tr>
      <tr><th>Pourcentage de l'honoraire</th><td class="right mono"><?= e(fmt_percent((float)$project['honor_percent'])) ?>%</td></tr>
      <tr><th>Honoraires HT (base travaux)</th><td class="right mono"><?= e($moneyDh((float)$inv['base_conception_ht'])) ?></td></tr>
      <tr><th>Total décomptes HT</th><td class="right mono"><?= e($moneyDh((float)$inv['base_suivi_work_ht'])) ?></td></tr>
      <tr><th>Honoraires HT (base décomptes)</th><td class="right mono"><?= e($moneyDh((float)$inv['base_suivi_ht'])) ?></td></tr>
    </table>

    <?php if (!empty($decomptes)): ?>
      <div class="spacer"></div>
      <div class="small muted" style="font-weight:700;">Détail des décomptes</div>
      <div class="spacer"></div>
      <table>
        <thead>
          <tr>
            <th style="width:58%;">Libellé</th>
            <th style="width:18%;">Date</th>
            <th class="right" style="width:24%;">Montant HT</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($decomptes as $d): ?>
            <tr>
              <td><?= e((string)($d['decompte_no'] ?? '')) ?></td>
              <td class="center mono"><?= $showDates ? e($formatDateFr((string)($d['decompte_date'] ?? ''))) : '' ?></td>
              <td class="right mono"><?= e($moneyDh((float)($d['amount_ht'] ?? 0))) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr>
            <th colspan="2" class="right">Total</th>
            <th class="right mono"><?= e($moneyDh((float)$inv['base_suivi_work_ht'])) ?></th>
          </tr>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php
    $groupA = array_values(array_filter($lines, fn($ln) => (string)$ln['base_type'] === 'conception'));
    $groupB = array_values(array_filter($lines, fn($ln) => (string)$ln['base_type'] === 'suivi'));
  ?>

  <div class="card">
    <h2>Calcul des honoraires</h2>

    <table>
      <thead>
        <tr>
          <th style="width:8%;" class="center">Phase</th>
          <th>Contenu de la phase</th>
          <th style="width:12%;" class="right">Taux</th>
          <th style="width:22%;" class="right">Montant de base</th>
          <th style="width:20%;" class="right">Montant Honoraire</th>
        </tr>
      </thead>

      <tbody>
        <?php if (!empty($groupA)): ?>
          <?php foreach ($groupA as $i => $ln): ?>
            <tr>
              <?php if ($i === 0): ?>
                <td class="center" rowspan="<?= count($groupA) ?>" style="font-weight:700; background:#f1f5f9;">A</td>
              <?php endif; ?>
              <td><?= e((string)$ln['phase_label']) ?></td>
              <td class="right mono"><?= e(fmt_percent((float)$ln['phase_rate_percent'])) ?>%</td>
              <td class="right mono"><?= e($moneyDh((float)$ln['base_amount_ht'])) ?></td>
              <td class="right mono"><?= e($moneyDh((float)$ln['line_ht'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($groupB)): ?>
          <?php foreach ($groupB as $i => $ln): ?>
            <tr>
              <?php if ($i === 0): ?>
                <td class="center" rowspan="<?= count($groupB) ?>" style="font-weight:700; background:#f1f5f9;">B</td>
              <?php endif; ?>
              <td><?= e((string)$ln['phase_label']) ?></td>
              <td class="right mono"><?= e(fmt_percent((float)$ln['phase_rate_percent'])) ?>%</td>
              <td class="right mono"><?= e($moneyDh((float)$ln['base_amount_ht'])) ?></td>
              <td class="right mono"><?= e($moneyDh((float)$ln['line_ht'])) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <table class="totals" style="page-break-inside:avoid;">
    <tr><th>Total HT</th><td class="right mono"><?= e($moneyDh((float)$inv['total_ht'])) ?></td></tr>
    <tr><th>TVA</th><td class="right mono"><?= e($moneyDh((float)$inv['tva_amount'])) ?></td></tr>
    <tr><th>Total TTC</th><td class="right mono"><?= e($moneyDh((float)$inv['total_ttc'])) ?></td></tr>
    <tr><th>À déduire – Déjà perçu</th><td class="right mono">-<?= e($moneyDh((float)$inv['already_received'])) ?></td></tr>
    <tr><th>À déduire – Retenue TVA</th><td class="right mono">-<?= e($moneyDh((float)$inv['retenue_tva_amount'])) ?></td></tr>
    <tr><th>À déduire – Retenue HT</th><td class="right mono">-<?= e($moneyDh((float)$inv['retenue_ht_amount'])) ?></td></tr>
    <tr class="grand"><th>MONTANT À PAYER</th><td class="right mono"><?= e($moneyDh((float)$inv['net_to_pay'])) ?></td></tr>
  </table>

  <div class="card" style="margin-top:6px;">
    <strong>Arrêté la présente note à la somme de :</strong>
    <div class="mono" style="margin-top:3px;"><?= e($amountWords) ?></div>
  </div>

  <div class="row" style="margin-top:6px;">
    <div style="flex:1;" class="small">
      <strong>Banque :</strong> <?= e($s['firm_bank'] ?? '') ?><br>
      <strong>RIB :</strong> <?= e($s['firm_rib'] ?? '') ?>
    </div>
    <div style="flex:1;" class="right">
      <div style="margin-top:18px;">Signature / Cachet</div>
    </div>
  </div>

</body>
</html>
    <?php
    $html = ob_get_clean();

    // ✅ inline par défaut, attachment si &dl=1 (ton Pdf::streamFromHtml doit accepter $download)
    Pdf::streamFromHtml($html, $nhLabel . '.pdf', $download);
  }
}