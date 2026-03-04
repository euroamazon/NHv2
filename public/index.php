<?php
declare(strict_types=1);

require __DIR__ . '/../app/core/helpers.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/core/Auth.php';

if (!file_exists(__DIR__ . '/../app/config.php')) redirect('/install/');

Auth::start();
$route = $_GET['r'] ?? 'dashboard';

$routes = [
  'login' => ['controller' => 'AuthController', 'action' => 'login'],
  'logout' => ['controller' => 'AuthController', 'action' => 'logout'],

  'dashboard' => ['controller' => 'DashboardController', 'action' => 'index'],

  'settings' => ['controller' => 'SettingsController', 'action' => 'index'],

  'projects' => ['controller' => 'ProjectController', 'action' => 'index'],
  'project_create' => ['controller' => 'ProjectController', 'action' => 'create'],
  'project_edit' => ['controller' => 'ProjectController', 'action' => 'edit'],
  'project_view' => ['controller' => 'ProjectController', 'action' => 'view'],

  // ✅ NOUVEAU: supprimer un projet
  'project_delete' => ['controller' => 'ProjectController', 'action' => 'delete'],
  'project_delete_invoices' => ['controller' => 'ProjectController', 'action' => 'deleteInvoices'],

  'decomptes' => ['controller' => 'DecompteController', 'action' => 'index'],
  'decompte_add' => ['controller' => 'DecompteController', 'action' => 'add'],
  'decompte_delete' => ['controller' => 'DecompteController', 'action' => 'delete'],

  'invoice_generate' => ['controller' => 'InvoiceController', 'action' => 'generate'],
  'invoice_view' => ['controller' => 'InvoiceController', 'action' => 'view'],
  'invoice_validate' => ['controller' => 'InvoiceController', 'action' => 'validate'],
  'invoice_pdf' => ['controller' => 'InvoiceController', 'action' => 'pdf'],
'invoice_print' => ['controller' => 'InvoiceController', 'action' => 'print'],

  // ✅ NOUVEAU: impression navigateur (Ctrl+P)
  'invoice_print' => ['controller' => 'InvoiceController', 'action' => 'print'],

  // ✅ NOUVEAU: supprimer génération en draft (NH draft uniquement)
  'invoice_delete_drafts' => ['controller' => 'InvoiceController', 'action' => 'deleteDrafts'],
];

if (!isset($routes[$route])) {
  http_response_code(404);
  echo "404";
  exit;
}

$ctrlName = $routes[$route]['controller'];
$action   = $routes[$route]['action'];

require __DIR__ . '/../app/controllers/' . $ctrlName . '.php';

$ctrl = new $ctrlName();
$ctrl->$action();
