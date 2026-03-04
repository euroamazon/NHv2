<?php
declare(strict_types=1);

final class Auth {
  public static function start(): void { if (session_status() !== PHP_SESSION_ACTIVE) session_start(); }
  public static function check(): bool { self::start(); return !empty($_SESSION['user_id']); }
  public static function requireLogin(): void { if (!self::check()) redirect('/?r=login'); }
  public static function login(int $id): void { self::start(); session_regenerate_id(true); $_SESSION['user_id'] = $id; }
  public static function logout(): void {
    self::start(); $_SESSION = [];
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time()-42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
  }
}
