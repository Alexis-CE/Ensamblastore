<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// credenciales: en produccion van en includes/config.local.php (fuera del repo)
$cfg = ['host' => 'localhost', 'user' => 'root', 'pass' => '', 'db' => 'dat'];
if (is_file(__DIR__ . '/config.local.php')) {
    $cfg = array_merge($cfg, require __DIR__ . '/config.local.php');
}

try {
    $conexion = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['db']);
    $conexion->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log('EnsamblaStore: no se pudo conectar a la base de datos: ' . $e->getMessage());
    http_response_code(503);
    exit('El servicio no está disponible por el momento. Inténtalo de nuevo en unos minutos.');
}

?>
