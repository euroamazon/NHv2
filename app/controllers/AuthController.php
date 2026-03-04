<?php
declare(strict_types=1);

final class AuthController {
  public function login(): void {
    if (Auth::check()) redirect('/?r=dashboard');
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      csrf_check();
      $email = trim((string)($_POST['email'] ?? ''));
      $pass  = (string)($_POST['password'] ?? '');
      $pdo = Database::pdo();
      $st = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
      $st->execute([$email]);
      $u = $st->fetch();
      if ($u && password_verify($pass, $u['password_hash'])) { Auth::login((int)$u['id']); redirect('/?r=dashboard'); }
      $error = "Identifiants invalides.";
    }

    require __DIR__ . '/../views/layout_top.php';
    ?>
    <div class="row justify-content-center">
      <div class="col-md-5">
        <div class="card shadow-sm">
          <div class="card-body">
            <h1 class="h5 mb-3">Connexion</h1>
            <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input class="form-control" type="email" name="email" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <input class="form-control" type="password" name="password" required>
              </div>
              <button class="btn btn-primary w-100">Se connecter</button>
            </form>
          </div>
        </div>
        <p class="text-muted small mt-3">Si c’est une nouvelle installation, créez l’admin via /install.</p>
      </div>
    </div>
    <?php
    require __DIR__ . '/../views/layout_bottom.php';
  }

  public function logout(): void { Auth::logout(); redirect('/?r=login'); }
}
