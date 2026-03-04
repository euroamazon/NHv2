<?php
declare(strict_types=1);

final class Invoice {

  /* ========= Helpers Excel ========= */

  // TRONQUE(x;2)
  public static function trunc2(float $x): float {
    return ((float)((int)($x * 100))) / 100.0;
  }

  // ARRONDI(x;2)
  public static function round2(float $x): float {
    return round($x, 2, PHP_ROUND_HALF_UP);
  }

  // ARRONDI.INF(x;2)
  public static function roundDown2(float $x): float {
    return floor($x * 100) / 100.0;
  }

  /* ========= DAO ========= */

  public static function listByProject(int $projectId): array {
    $pdo = Database::pdo();
    $st = $pdo->prepare("SELECT * FROM invoices WHERE project_id=? ORDER BY nh_number ASC");
    $st->execute([$projectId]);
    return $st->fetchAll();
  }

  public static function find(int $id): ?array {
    $pdo = Database::pdo();
    $st = $pdo->prepare("SELECT * FROM invoices WHERE id=?");
    $st->execute([$id]);
    $inv = $st->fetch();
    return $inv ?: null;
  }

  public static function lines(int $invoiceId): array {
    $pdo = Database::pdo();
    $st = $pdo->prepare("
      SELECT il.*
      FROM invoice_lines il
      LEFT JOIN project_phases pp ON pp.id = il.phase_id
      WHERE il.invoice_id=?
      ORDER BY COALESCE(pp.sort_order, 9999) ASC, il.id ASC
    ");
    $st->execute([$invoiceId]);
    return $st->fetchAll();
  }

  /* =========================================================
     ✅ Excel : déjà perçu = TOTAL TTC de la NH précédente (E40)
     ========================================================= */
  public static function previousTotalTtcExcel(int $projectId, int $nhNumber): float {
    if ($nhNumber <= 1) return 0.0;

    $pdo = Database::pdo();
    $st = $pdo->prepare("
      SELECT COALESCE(total_ttc,0) v
      FROM invoices
      WHERE project_id=?
        AND nh_number = ?
        AND status IN ('draft','validated')
      LIMIT 1
    ");
    $st->execute([$projectId, $nhNumber - 1]);
    $row = $st->fetch();
    return (float)($row['v'] ?? 0.0);
  }

  /* =========================================================
     Delta phase (phase courante uniquement) pour retenues Excel
     - Retenue TVA : ARRONDI.INF( (DeltaHT*TVA)*75% ; 2 )
     - Retenue HT  : ARRONDI.INF( DeltaHT*5% ; 2 )
     ========================================================= */
  private static function computeRetenuesExcel(array $project, float $deltaHt): array {
    $tvaRate     = (float)$project['tva_percent'] / 100.0;          // 20%
    $retenueTva  = (float)$project['retenue_tva_percent'] / 100.0;  // 75%
    $retenueHt   = (float)$project['retenue_ht_percent'] / 100.0;   // 5%

    $deltaTvaExact = $deltaHt * $tvaRate;

    // Excel ARRONDI.INF
    $dedTva = self::roundDown2($deltaTvaExact * $retenueTva);
    $dedHt  = self::roundDown2($deltaHt * $retenueHt);

    return [$dedTva, $dedHt];
  }

  /* =========================================================
     Crée une NH draft au format Excel (cumulée)
     ========================================================= */
  public static function createDraft(
    int $projectId,
    int $nhNumber,
    array $phaseCurrent,
    array $project,
    float $workSuiviHtTotal
  ): int {

    $pdo = Database::pdo();
    $pdo->beginTransaction();

    try {
      // Charger toutes les phases
      $stPh = $pdo->prepare("
        SELECT id, label, rate_percent, base_type, sort_order
        FROM project_phases
        WHERE project_id=?
        ORDER BY sort_order ASC, id ASC
      ");
      $stPh->execute([$projectId]);
      $phases = $stPh->fetchAll();
      if (!$phases) throw new RuntimeException("Aucune phase trouvée.");

      // Trouver le sort_order de la phase courante (NH = index de phase)
      $currentSort = null;
      foreach ($phases as $p) {
        if ((int)$p['id'] === (int)$phaseCurrent['id']) {
          $currentSort = (int)$p['sort_order'];
          break;
        }
      }
      if ($currentSort === null) throw new RuntimeException("Phase courante introuvable.");

      // Paramètres
      $honRate = (float)$project['honor_percent'] / 100.0;
      $tvaRate = (float)$project['tva_percent'] / 100.0;

      // Bases honoraires (Excel)
      $baseConception = self::trunc2(((float)$project['work_amount_ht_conception']) * $honRate);
      $baseSuivi      = self::trunc2($workSuiviHtTotal * $honRate);

      // Construire toutes les lignes : phases <= courante cumulées
      $sumHt = 0.0;
      $linesToInsert = [];
      $deltaHtCurrent = 0.0;

      foreach ($phases as $p) {
        $baseType = ($p['base_type'] === 'suivi') ? 'suivi' : 'conception';
        $baseAmt  = ($baseType === 'suivi') ? $baseSuivi : $baseConception;

        $lineHt = 0.0;
        if ((int)$p['sort_order'] <= $currentSort) {
          $rate = (float)$p['rate_percent'] / 100.0;
          $lineHt = self::trunc2($baseAmt * $rate);
          $sumHt += $lineHt;

          // delta = ligne HT de la phase courante uniquement
          if ((int)$p['sort_order'] === $currentSort) {
            $deltaHtCurrent = $lineHt;
          }
        }

        $linesToInsert[] = [
          'phase_id' => (int)$p['id'],
          'phase_label' => (string)$p['label'],
          'phase_rate_percent' => (float)$p['rate_percent'],
          'base_type' => $baseType,
          'base_amount_ht' => $baseAmt,
          'line_ht' => $lineHt,
        ];
      }

      // Totaux cumulés Excel
      $totalHt   = self::round2($sumHt);
      $tvaAmount = self::trunc2($totalHt * $tvaRate);
      $totalTtc  = self::round2($totalHt + $tvaAmount);

      // ✅ Déjà perçu = TOTAL TTC de la NH précédente
      $already = self::previousTotalTtcExcel($projectId, $nhNumber);

      // ✅ Excel : TOTAL DES HONORAIRES TTC (après déjà perçu) ARRONDI
      $totalHonTtcAfterAlready = self::round2($totalTtc - $already);

      // Retenues sur DELTA uniquement
      [$dedTva, $dedHt] = self::computeRetenuesExcel($project, $deltaHtCurrent);

      /**
       * ✅ Correction Excel (clé pour ±0,01) :
       * Excel arrondit aussi après la déduction TVA avant de déduire la retenue HT.
       *
       * net = ARRONDI( ARRONDI( totalHonTtcAfterAlready - dedTva ) - dedHt )
       */
      $net = self::round2(self::round2($totalHonTtcAfterAlready - $dedTva) - $dedHt);

      // Insert invoice
      $st = $pdo->prepare("
        INSERT INTO invoices
        (project_id, nh_number, issue_date, status,
         base_conception_ht, base_suivi_work_ht, base_suivi_ht,
         total_ht, tva_amount, total_ttc, already_received,
         retenue_tva_amount, retenue_ht_amount, net_to_pay, created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
      ");
      $st->execute([
        $projectId,
        $nhNumber,
        date('Y-m-d'),
        'draft',
        $baseConception,
        $workSuiviHtTotal,
        $baseSuivi,
        $totalHt,
        $tvaAmount,
        $totalTtc,
        $already,
        $dedTva,
        $dedHt,
        $net,
      ]);

      $invoiceId = (int)$pdo->lastInsertId();

      // Insert lines
      $st2 = $pdo->prepare("
        INSERT INTO invoice_lines
        (invoice_id, phase_id, phase_label, phase_rate_percent, base_type, base_amount_ht, line_ht)
        VALUES (?,?,?,?,?,?,?)
      ");
      foreach ($linesToInsert as $ln) {
        $st2->execute([
          $invoiceId,
          $ln['phase_id'],
          $ln['phase_label'],
          $ln['phase_rate_percent'],
          $ln['base_type'],
          $ln['base_amount_ht'],
          $ln['line_ht'],
        ]);
      }

      // Snapshot (si tu l’utilises)
      $decomptes = Decompte::listByProject($projectId);
      $snap = [
        'project' => $projectId,
        'nh_number' => $nhNumber,
        'generated_at' => date('c'),
        'phase' => $phaseCurrent,
        'bases' => [
          'base_conception_ht' => $baseConception,
          'base_suivi_work_ht' => $workSuiviHtTotal,
          'base_suivi_ht'      => $baseSuivi,
        ],
        'totals' => [
          'total_ht' => $totalHt,
          'tva_amount' => $tvaAmount,
          'total_ttc' => $totalTtc,
          'already_received' => $already,
          'total_hon_ttc_after_already' => $totalHonTtcAfterAlready,
          'retenue_tva_amount' => $dedTva,
          'retenue_ht_amount' => $dedHt,
          'net_to_pay' => $net,
          'delta_ht' => $deltaHtCurrent,
        ],
        'decomptes' => $decomptes,
        'lines' => $linesToInsert,
      ];

      $pdo->prepare("INSERT INTO invoice_snapshots (invoice_id, snapshot_json) VALUES (?,?)")
          ->execute([$invoiceId, json_encode($snap, JSON_UNESCAPED_UNICODE)]);

      $pdo->commit();
      return $invoiceId;

    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
  }

  public static function validate(int $invoiceId): void {
    $pdo = Database::pdo();
    $pdo->prepare("UPDATE invoices SET status='validated', validated_at=NOW() WHERE id=?")
        ->execute([$invoiceId]);
  }

  public static function snapshot(int $invoiceId): ?array {
    $pdo = Database::pdo();
    $st = $pdo->prepare("SELECT snapshot_json FROM invoice_snapshots WHERE invoice_id=? LIMIT 1");
    $st->execute([$invoiceId]);
    $r = $st->fetch();
    if (!$r) return null;
    return json_decode((string)$r['snapshot_json'], true);
  }
}
