<?php
$cfg = require __DIR__ . '/../config.php';
$appName = $cfg['app']['name'] ?? 'NH';
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= e($appName) ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    :root{
      --card-radius: 16px;
      --shadow-soft: 0 10px 30px rgba(16, 24, 40, .06);
    }

    body{
      background:
        radial-gradient(1200px 600px at 20% -10%, rgba(99,102,241,.14), transparent 60%),
        radial-gradient(1200px 600px at 80% -10%, rgba(34,197,94,.10), transparent 60%),
        #f6f7fb;
    }

    .mono { font-variant-numeric: tabular-nums; }

    .navbar{
      backdrop-filter: blur(10px);
      background: rgba(15, 23, 42, .92) !important;
    }

    .navbar .btn{
      border-radius: 999px;
    }

    .card{
      border: 0;
      border-radius: var(--card-radius);
      box-shadow: var(--shadow-soft);
    }

    .table thead th{
      color: #64748b;
      font-weight: 600;
      border-top: 0;
    }

    .table td, .table th{
      vertical-align: middle;
    }

    .btn{
      border-radius: 12px;
    }

    .page-title{
      letter-spacing: .2px;
    }

    .kpi-card .card-body{
      padding: 16px;
    }

    .kpi-icon{
      width: 42px;
      height: 42px;
      border-radius: 14px;
      display:flex;
      align-items:center;
      justify-content:center;
      font-size: 20px;
      color: #0f172a;
    }

    @media print { .no-print { display:none !important; } }
  </style>

  <script>
    // Confirm helper global
    function confirmAction(msg){
      return window.confirm(msg || 'Confirmer ?');
    }
  </script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark no-print">
  <div class="container py-2">
    <a class="navbar-brand d-flex align-items-center gap-2" href="/?r=dashboard" style="font-weight:700;">
      <span class="d-inline-flex align-items-center justify-content-center rounded-3"
            style="width:34px;height:34px;background:rgba(255,255,255,.12);">
        <i class="bi bi-grid-1x2-fill"></i>
      </span>
      <?= e($appName) ?>
    </a>

    <div class="ms-auto d-flex align-items-center gap-2">
      <?php if (Auth::check()): ?>
        <a class="btn btn-sm btn-outline-light" href="/?r=dashboard"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
        <a class="btn btn-sm btn-outline-light" href="/?r=projects"><i class="bi bi-folder2-open me-1"></i> Projets</a>
        <a class="btn btn-sm btn-outline-light" href="/?r=settings"><i class="bi bi-gear me-1"></i> Paramètres</a>
        <a class="btn btn-sm btn-outline-light" href="/?r=logout"><i class="bi bi-box-arrow-right me-1"></i> Déconnexion</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<main class="container py-4">
