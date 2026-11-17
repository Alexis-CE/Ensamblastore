<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexion = new mysqli("localhost", "root", "", "dat");
    $conexion->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log('EnsamblaStore: no se pudo conectar a la base de datos: ' . $e->getMessage());
    http_response_code(503);
    exit('El servicio no está disponible por el momento. Inténtalo de nuevo en unos minutos.');
}

?>
