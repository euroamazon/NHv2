<?php
declare(strict_types=1);

final class Decompte {
  public static function listByProject(int $projectId): array {
    $pdo = Database::pdo();
    $st = $pdo->prepare("SELECT * FROM decomptes WHERE project_id=? ORDER BY decompte_date ASC, id ASC");
    $st->execute([$projectId]);
    return $st->fetchAll();
  }
  public static function add(int $projectId, array $d): void {
    $pdo = Database::pdo();
    $st = $pdo->prepare("INSERT INTO decomptes (project_id, decompte_no, decompte_date, amount_ht, created_at) VALUES (?,?,?,?,NOW())");
    $st->execute([$projectId, $d['decompte_no'], $d['decompte_date'], $d['amount_ht']]);
  }
  public static function delete(int $id, int $projectId): void {
    $pdo = Database::pdo();
    $st = $pdo->prepare("DELETE FROM decomptes WHERE id=? AND project_id=?");
    $st->execute([$id, $projectId]);
  }
  public static function totalHt(int $projectId): float {
    $pdo = Database::pdo();
    $st = $pdo->prepare("SELECT COALESCE(SUM(amount_ht),0) s FROM decomptes WHERE project_id=?");
    $st->execute([$projectId]);
    return (float)$st->fetch()['s'];
  }
}
