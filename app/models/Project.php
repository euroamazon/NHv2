<?php
declare(strict_types=1);

final class Project {
  public static function all(): array {
    $pdo = Database::pdo();
    return $pdo->query("SELECT * FROM projects ORDER BY id DESC")->fetchAll();
  }
  public static function find(int $id): ?array {
    $pdo = Database::pdo();
    $st = $pdo->prepare("SELECT * FROM projects WHERE id=?");
    $st->execute([$id]);
    $p = $st->fetch();
    return $p ?: null;
  }
  public static function create(array $d): int {
    $pdo = Database::pdo();
    $st = $pdo->prepare("INSERT INTO projects
      (client_name, client_address, project_title, project_location, contract_ref, work_amount_ht_conception, honor_percent, tva_percent, retenue_tva_percent, retenue_ht_percent, created_at)
      VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
    $st->execute([
      $d['client_name'], $d['client_address'], $d['project_title'], $d['project_location'], $d['contract_ref'],
      $d['work_amount_ht_conception'], $d['honor_percent'], $d['tva_percent'], $d['retenue_tva_percent'], $d['retenue_ht_percent']
    ]);
    return (int)$pdo->lastInsertId();
  }
  public static function update(int $id, array $d): void {
    $pdo = Database::pdo();
    $st = $pdo->prepare("UPDATE projects SET
      client_name=?, client_address=?, project_title=?, project_location=?, contract_ref=?,
      work_amount_ht_conception=?, honor_percent=?, tva_percent=?, retenue_tva_percent=?, retenue_ht_percent=?
      WHERE id=?");
    $st->execute([
      $d['client_name'], $d['client_address'], $d['project_title'], $d['project_location'], $d['contract_ref'],
      $d['work_amount_ht_conception'], $d['honor_percent'], $d['tva_percent'], $d['retenue_tva_percent'], $d['retenue_ht_percent'],
      $id
    ]);
  }
}
