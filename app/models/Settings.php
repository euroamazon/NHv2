<?php
declare(strict_types=1);

final class Settings {
  public static function getAll(): array {
    $pdo = Database::pdo();
    $rows = $pdo->query("SELECT skey, svalue FROM settings")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[$r['skey']] = $r['svalue'];
    return $out;
  }

  public static function get(string $key, string $default=''): string {
    $pdo = Database::pdo();
    $st = $pdo->prepare("SELECT svalue FROM settings WHERE skey=? LIMIT 1");
    $st->execute([$key]);
    $r = $st->fetch();
    return $r ? (string)$r['svalue'] : $default;
  }

  public static function set(string $key, string $value): void {
    $pdo = Database::pdo();
    $st = $pdo->prepare("INSERT INTO settings (skey, svalue) VALUES (?,?)
      ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)");
    $st->execute([$key, $value]);
  }
}
