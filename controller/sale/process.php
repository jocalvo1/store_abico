<?php
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/salesController.php';

$db = getDBConnection();
$controller = new SalesController($db);
$controller->process();
