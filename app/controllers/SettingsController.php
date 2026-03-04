<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Settings.php';

final class SettingsController {

  public function index(): void {
    Auth::requireLogin();

    $pdo = Database::pdo();
    $s = Settings::getAll();

    $success = null;
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();

      try {
        // Champs texte
        $data = [
          'firm_name' => trim((string)($_POST['firm_name'] ?? '')),
          'firm_address' => trim((string)($_POST['firm_address'] ?? '')),
          'firm_phone' => trim((string)($_POST['firm_phone'] ?? '')),
          'firm_email' => trim((string)($_POST['firm_email'] ?? '')),
          'firm_ice' => trim((string)($_POST['firm_ice'] ?? '')),
          'firm_if' => trim((string)($_POST['firm_if'] ?? '')),
          'firm_patente' => trim((string)($_POST['firm_patente'] ?? '')),
          'firm_cnss' => trim((string)($_POST['firm_cnss'] ?? '')),
          'firm_rc' => trim((string)($_POST['firm_rc'] ?? '')),
          'firm_bank' => trim((string)($_POST['firm_bank'] ?? '')),
          'firm_rib' => trim((string)($_POST['firm_rib'] ?? '')),
          'invoice_city' => trim((string)($_POST['invoice_city'] ?? '')),
        ];

        // Upload logo (optionnel)
        if (!empty($_FILES['logo_file']) && is_array($_FILES['logo_file']) && ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
          $tmp = (string)$_FILES['logo_file']['tmp_name'];
          $name = (string)$_FILES['logo_file']['name'];
          $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

          if (!in_array($ext, ['png','jpg','jpeg','webp'], true)) {
            throw new RuntimeException("Format logo invalide. Autorisés: png, jpg, jpeg, webp.");
          }

          // ✅ Stockage correct : public/storage/uploads
          $dir = __DIR__ . '/../../public/storage/uploads';
          if (!is_dir($dir)) {
            if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
              throw new RuntimeException("Impossible de créer le dossier storage/uploads.");
            }
          }

          // ✅ Nom unique (évite cache navigateur)
          $stamp = date('Ymd_His');
          $destRel = 'public/storage/uploads/logo_' . $stamp . '.' . $ext;
          $destAbs = __DIR__ . '/../../' . $destRel;

          if (!move_uploaded_file($tmp, $destAbs)) {
            throw new RuntimeException("Impossible d’enregistrer le logo.");
          }

          // ✅ Chemin WEB qui fonctionne chez toi
          $data['logo_path'] = '/' . $destRel; // => /public/storage/uploads/...
        }

        // Sauvegarde key/value (UPSERT)
        $pdo->beginTransaction();
        $st = $pdo->prepare("
          INSERT INTO settings (skey, svalue)
          VALUES (?, ?)
          ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)
        ");

        foreach ($data as $k => $v) {
          $st->execute([$k, $v]);
        }

        $pdo->commit();

        $s = Settings::getAll();
        $success = "Paramètres enregistrés.";
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e->getMessage();
      }
    }

    require __DIR__ . '/../views/layout_top.php';
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
      <div>
        <h1 class="h4 page-title mb-1">Paramètres</h1>
        <div class="text-muted small">Informations du cabinet utilisées sur les PDFs.</div>
      </div>
      <a class="btn btn-outline-secondary" href="/?r=dashboard"><i class="bi bi-arrow-left me-1"></i> Retour</a>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> <?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i> <?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-body">
              <h2 class="h6 mb-3"><i class="bi bi-building me-1"></i> Cabinet</h2>

              <div class="mb-3">
                <label class="form-label">Nom</label>
                <input class="form-control" name="firm_name" value="<?= e((string)($s['firm_name'] ?? '')) ?>">
              </div>

              <div class="mb-3">
                <label class="form-label">Adresse</label>
                <textarea class="form-control" rows="3" name="firm_address"><?= e((string)($s['firm_address'] ?? '')) ?></textarea>
              </div>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Téléphone</label>
                  <input class="form-control" name="firm_phone" value="<?= e((string)($s['firm_phone'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Email</label>
                  <input class="form-control" name="firm_email" value="<?= e((string)($s['firm_email'] ?? '')) ?>">
                </div>
              </div>

              <hr class="my-4">
              <h3 class="h6 mb-3"><i class="bi bi-image me-1"></i> Logo</h3>

              <?php if (!empty($s['logo_path'])): ?>
                <div class="mb-2">
                  <img src="<?= e((string)$s['logo_path']) ?>" alt="logo" style="max-height:70px;">
                </div>
              <?php endif; ?>

              <input class="form-control" type="file" name="logo_file" accept=".png,.jpg,.jpeg,.webp">
              <div class="text-muted small mt-1">Formats: png, jpg, jpeg, webp</div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-body">
              <h2 class="h6 mb-3"><i class="bi bi-card-text me-1"></i> Identifiants</h2>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">ICE</label>
                  <input class="form-control mono" name="firm_ice" value="<?= e((string)($s['firm_ice'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">IF</label>
                  <input class="form-control mono" name="firm_if" value="<?= e((string)($s['firm_if'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Patente</label>
                  <input class="form-control mono" name="firm_patente" value="<?= e((string)($s['firm_patente'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">CNSS</label>
                  <input class="form-control mono" name="firm_cnss" value="<?= e((string)($s['firm_cnss'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">RC</label>
                  <input class="form-control mono" name="firm_rc" value="<?= e((string)($s['firm_rc'] ?? '')) ?>">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Ville facture</label>
                  <input class="form-control" name="invoice_city" value="<?= e((string)($s['invoice_city'] ?? '')) ?>">
                </div>
              </div>

              <hr class="my-4">
              <h2 class="h6 mb-3"><i class="bi bi-bank me-1"></i> Banque</h2>

              <div class="mb-3">
                <label class="form-label">Banque</label>
                <input class="form-control" name="firm_bank" value="<?= e((string)($s['firm_bank'] ?? '')) ?>">
              </div>
              <div class="mb-0">
                <label class="form-label">RIB</label>
                <input class="form-control mono" name="firm_rib" value="<?= e((string)($s['firm_rib'] ?? '')) ?>">
              </div>

            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary">
          <i class="bi bi-save me-1"></i> Enregistrer
        </button>
        <a class="btn btn-outline-secondary" href="/?r=dashboard">Annuler</a>
      </div>
    </form>
    <?php
    require __DIR__ . '/../views/layout_bottom.php';
  }
}
