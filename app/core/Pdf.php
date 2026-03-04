<?php
declare(strict_types=1);

final class Pdf {

  private static function findAutoload(): ?string {
    // __DIR__ = /.../app/core
    $candidates = [
      // structure classique: /vendor/autoload.php à la racine projet
      __DIR__ . '/../../vendor/autoload.php',

      // si vendor est dans /app/vendor
      __DIR__ . '/../vendor/autoload.php',

      // si l’app a été déployée avec vendor dans /public/vendor
      __DIR__ . '/../../public/vendor/autoload.php',

      // si le projet est dans un sous-dossier inattendu
      dirname(__DIR__, 3) . '/vendor/autoload.php',
      dirname(__DIR__, 4) . '/vendor/autoload.php',
    ];

    foreach ($candidates as $p) {
      if (is_file($p)) return $p;
    }
    return null;
  }

  public static function streamFromHtml(string $html, string $filename, bool $download = false): void {
    $autoload = self::findAutoload();
    if ($autoload) {
      require_once $autoload;
    }

    if (!class_exists(\Dompdf\Dompdf::class)) {
      http_response_code(500);
      header('Content-Type: text/plain; charset=utf-8');

      echo "ERREUR: Dompdf n'est pas disponible.\n";
      echo "- autoload trouvé: " . ($autoload ? $autoload : 'NON') . "\n\n";
      echo "Actions:\n";
      echo "1) Vérifie que composer a installé dompdf (vendor/ présent).\n";
      echo "2) Déploie le dossier vendor/ sur le serveur (ou lance composer install).\n";
      exit;
    }

    $dompdf = new \Dompdf\Dompdf([
      'isRemoteEnabled' => true,
      'isHtml5ParserEnabled' => true,
    ]);

    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    $disp = $download ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disp . '; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');

    echo $dompdf->output();
    exit;
  }
}