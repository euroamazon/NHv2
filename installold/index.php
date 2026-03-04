<?php
declare(strict_types=1);

require __DIR__ . '/../app/core/helpers.php';

$configPath = __DIR__ . '/../app/config.php';
if (file_exists($configPath)) { echo "Déjà installé. Supprimez /install si possible."; exit; }

$error = null; $ok = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $host = trim((string)($_POST['db_host'] ?? ''));
  $name = trim((string)($_POST['db_name'] ?? ''));
  $user = trim((string)($_POST['db_user'] ?? ''));
  $pass = (string)($_POST['db_pass'] ?? '');
  $baseUrl = trim((string)($_POST['base_url'] ?? ''));

  $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
  $adminPass  = (string)($_POST['admin_pass'] ?? '');

  try {
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $schema = file_get_contents(__DIR__ . '/../schema.sql');
    $pdo->exec($schema);

    $config = "<?php\nreturn " . var_export([
      'app' => ['name' => "NH - Notes d’honoraires (v2)", 'base_url' => $baseUrl],
      'db' => ['host'=>$host,'name'=>$name,'user'=>$user,'pass'=>$pass,'charset'=>'utf8mb4'],
      'security' => ['password_cost'=>12],
      'pdf' => ['engine' => 'auto'],
    ], true) . ";\n";
    file_put_contents($configPath, $config);

    $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost'=>12]);
    $st = $pdo->prepare("INSERT INTO users (email, password_hash, role, is_active, created_at) VALUES (?,?,?,?,NOW())");
    $st->execute([$adminEmail, $hash, 'admin', 1]);

    // Default settings placeholders
    $pdo->exec("INSERT IGNORE INTO settings (skey, svalue) VALUES
      ('firm_name',''),('firm_address',''),('firm_phone',''),('firm_email',''),
      ('firm_ice',''),('firm_if',''),('firm_patente',''),('firm_cnss',''),('firm_rc',''),
      ('firm_rib',''),('firm_bank',''),('invoice_city',''),('logo_path','')");

    $ok = "Installation terminée. Connectez-vous via /?r=login puis SUPPRIMEZ le dossier /install.";
  } catch (Throwable $e) { $error = "Erreur: " . $e->getMessage(); }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Installer NH v2</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7">
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="h4">Installation - NH v2</h1>
          <p class="text-muted">Crée les tables + compte admin. Après installation, supprimez /install.</p>

          <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
          <?php if ($ok): ?><div class="alert alert-success"><?= e($ok) ?></div><?php endif; ?>

          <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

            <h2 class="h6 mt-3">Base de données</h2>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Serveur hôte</label><input class="form-control" name="db_host" required></div>
              <div class="col-md-6"><label class="form-label">Nom BDD</label><input class="form-control" name="db_name" required></div>
              <div class="col-md-6"><label class="form-label">Utilisateur</label><input class="form-control" name="db_user" required></div>
              <div class="col-md-6"><label class="form-label">Mot de passe</label><input class="form-control" type="password" name="db_pass"></div>
              <div class="col-md-12"><label class="form-label">Base URL (optionnel)</label><input class="form-control" name="base_url" placeholder="https://nh.acoconsulting.ma"></div>
            </div>

            <h2 class="h6 mt-4">Compte admin</h2>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Email admin</label><input class="form-control" type="email" name="admin_email" required></div>
              <div class="col-md-6"><label class="form-label">Mot de passe admin</label><input class="form-control" type="password" name="admin_pass" required></div>
            </div>

            <button class="btn btn-primary mt-4">Installer</button>
          </form>

          <hr>
          <div class="text-muted small">
            PDF: pour activer un vrai PDF, installez Dompdf via Composer (voir README).
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
