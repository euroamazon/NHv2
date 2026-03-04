<?php
return [
  'app' => [
    'name' => 'NH - Notes d’honoraires (v2)',
    'base_url' => '',
  ],
  'db' => [
    'host' => 'localhost',
    'name' => 'database_name',
    'user' => 'database_user',
    'pass' => 'database_pass',
    'charset' => 'utf8mb4',
  ],
  'security' => [
    'password_cost' => 12,
  ],
  'pdf' => [
    'engine' => 'auto', // auto|html (auto uses dompdf if installed)
  ],
];
