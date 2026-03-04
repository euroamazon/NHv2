<?php
declare(strict_types=1);

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $path): void { header("Location: " . $path); exit; }

function csrf_token(): string {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function csrf_check(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $t = $_POST['csrf'] ?? '';
  if (!$t || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) { http_response_code(400); echo "Bad Request (CSRF)."; exit; }
}
function app_log(string $msg): void {
  $file = __DIR__ . '/../../storage/logs/app.log';
  $line = '[' . date('c') . '] ' . $msg . PHP_EOL;
  @file_put_contents($file, $line, FILE_APPEND);
}

function fmt_money($x): string {
  if ($x === null) return '';
  return number_format((float)$x, 2, '.', ' ');
}
function fmt_percent($x): string {
  if ($x === null) return '';
  return rtrim(rtrim(number_format((float)$x, 4, '.', ''), '0'), '.');
}

/**
 * Convert number to French words (MAD centimes style).
 * Simple robust implementation for invoices.
 */
function fr_number_to_words(float $amount, string $currency='MAD', string $cent='centimes'): string {
  $amount = round($amount, 2);
  $int = (int)floor($amount);
  $dec = (int)round(($amount - $int) * 100);

  $words = fr_int_to_words($int);
  $cur = ($currency === 'MAD') ? 'dirhams' : $currency;

  $centWords = fr_int_to_words($dec);
  return trim($words . ' ' . $cur . ' et ' . $centWords . ' ' . $cent);
}

function fr_int_to_words(int $n): string {
  if ($n === 0) return 'zéro';
  $units = ['','un','deux','trois','quatre','cinq','six','sept','huit','neuf'];
  $teens = [10=>'dix',11=>'onze',12=>'douze',13=>'treize',14=>'quatorze',15=>'quinze',16=>'seize',17=>'dix-sept',18=>'dix-huit',19=>'dix-neuf'];
  $tens = ['','dix','vingt','trente','quarante','cinquante','soixante','soixante','quatre-vingt','quatre-vingt'];

  $parts = [];

  $billions = intdiv($n, 1000000000);
  if ($billions) { $parts[] = fr_int_to_words($billions) . ' milliard' . ($billions>1?'s':''); $n %= 1000000000; }

  $millions = intdiv($n, 1000000);
  if ($millions) { $parts[] = fr_int_to_words($millions) . ' million' . ($millions>1?'s':''); $n %= 1000000; }

  $thousands = intdiv($n, 1000);
  if ($thousands) {
    if ($thousands === 1) $parts[] = 'mille';
    else $parts[] = fr_int_to_words($thousands) . ' mille';
    $n %= 1000;
  }

  $hundreds = intdiv($n, 100);
  if ($hundreds) {
    if ($hundreds === 1) $parts[] = 'cent' . (($n % 100)===0 ? '' : '');
    else $parts[] = $units[$hundreds] . ' cent' . (($n % 100)===0 ? 's' : '');
    $n %= 100;
  }

  if ($n >= 20) {
    $t = intdiv($n, 10);
    $u = $n % 10;

    if ($t === 7 || $t === 9) {
      $base = $tens[$t];
      $teen = 10 + $u;
      if ($teen === 11 && $t === 7) $parts[] = $base . ' et ' . $teens[$teen];
      else $parts[] = $base . '-' . $teens[$teen];
    } else {
      $base = $tens[$t];
      if ($t === 8 && $u === 0) $parts[] = $base . 's';
      elseif ($u === 1 && ($t===2||$t===3||$t===4||$t===5||$t===6)) $parts[] = $base . ' et un';
      elseif ($u) $parts[] = $base . '-' . $units[$u];
      else $parts[] = $base;
    }
  } elseif ($n >= 10) {
    $parts[] = $teens[$n];
  } elseif ($n > 0) {
    $parts[] = $units[$n];
  }

  return implode(' ', array_filter($parts));
}
