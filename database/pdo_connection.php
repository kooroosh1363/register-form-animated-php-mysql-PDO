<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/bootstrap.php';

// Legacy compatibility alias. New code should use the PDO instance from bootstrap.php.
$conn = $pdo;
