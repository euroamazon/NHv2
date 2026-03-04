<?php
declare(strict_types=1);

final class Phase {
  public static function listByProject(int $projectId): array {
    $pdo = Database::pdo();
    $st = $pdo->prepare("SELECT * FROM project_phases WHERE project_id=? ORDER BY sort_order ASC, id ASC");
    $st->execute([$projectId]);
    return $st->fetchAll();
  }
  public static function replaceAll(int $projectId, array $phases): void {
    $pdo = Database::pdo();
    $pdo->beginTransaction();
    try {
      $pdo->prepare("DELETE FROM project_phases WHERE project_id=?")->execute([$projectId]);
      $st = $pdo->prepare("INSERT INTO project_phases (project_id, label, rate_percent, base_type, sort_order) VALUES (?,?,?,?,?)");
      $i = 1;
      foreach ($phases as $p) $st->execute([$projectId, $p['label'], $p['rate_percent'], $p['base_type'], $i++]);
      $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
  }
}
