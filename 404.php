<?php
http_response_code(404);
$baseSitio = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($baseSitio === '.') {
    $baseSitio = '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#06392C">
    <title>Página no encontrada | EnsamblaStore</title>
    <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars($baseSitio, ENT_QUOTES, 'UTF-8') ?>/favicon.svg">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseSitio, ENT_QUOTES, 'UTF-8') ?>/css/estilos.css">
</head>
<body>
    <main class="error-page">
        <a class="brand" href="<?= htmlspecialchars($baseSitio, ENT_QUOTES, 'UTF-8') ?>/index.php" aria-label="EnsamblaStore, inicio">
            <span class="brand-mark">e/</span>
            <span class="brand-name">ENSAMBLA<span>STORE</span></span>
        </a>
        <p class="eyebrow">ERROR 404</p>
        <h1>No encontramos esa página</h1>
        <p>El enlace quizá cambió o la página ya no está disponible. Puedes volver al inicio y seguir explorando.</p>
        <a class="button button-light" href="<?= htmlspecialchars($baseSitio, ENT_QUOTES, 'UTF-8') ?>/index.php">VOLVER AL INICIO <span aria-hidden="true">→</span></a>
    </main>
</body>
</html>
