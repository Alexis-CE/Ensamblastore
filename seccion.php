<?php
require_once __DIR__ . '/includes/sesion.php';

$secciones = [
    'computadoras' => ['titulo' => 'Catálogo de computadoras', 'archivo' => 'computadoras.php'],
    'componentes' => ['titulo' => 'Catálogo de componentes', 'archivo' => 'componentes.php'],
    'carrito' => ['titulo' => 'Mi carrito', 'archivo' => 'carrito.php'],
    'soporte' => ['titulo' => 'Soporte técnico', 'archivo' => 'soporte.php'],
    'acerca' => ['titulo' => 'Acerca de', 'archivo' => 'acerca.php'],
    'privacidad' => ['titulo' => 'Aviso de privacidad', 'archivo' => 'privacidad.php'],
    'gracias' => ['titulo' => 'Gracias por contactarnos', 'archivo' => 'gracias.php'],
];
$vista = $_GET['vista'] ?? '';

if (!is_string($vista) || !isset($secciones[$vista])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

if ($vista === 'gracias' && empty($_SESSION['contacto_enviado'])) {
    header('Location: seccion.php?vista=soporte');
    exit;
}

$logueado = isset($_SESSION['usuario']);
$h = fn($texto) => htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
$enlaceContacto = $logueado ? 'href="seccion.php?vista=soporte#soporte"' : 'href="index.php?login=1"';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$cantidadCarrito = 0;
$totalResumenCarrito = 0.0;
$articulosCarrito = [];
$totalCarrito = 0.0;
$mensajeCarrito = null;
$csrfToken = $_SESSION['csrf_token'] ?? '';
$usaConexion = $logueado || in_array($vista, ['computadoras', 'componentes'], true);

$productos = [];
$gruposPorMarca = [];
$marcasCatalogo = [];
$categoriasCatalogo = [];
$etiquetasCategorias = [
    'almacenamiento' => 'Almacenamiento',
    'escritorio' => 'Escritorio',
    'fuente' => 'Fuente de poder',
    'gamer' => 'Gamer',
    'laptop' => 'Laptop',
    'memoria' => 'Memoria RAM',
    'procesador' => 'Procesador',
    'tarjeta-grafica' => 'Tarjeta gráfica',
    'workstation' => 'Workstation',
];

if ($usaConexion) {
    require_once __DIR__ . '/includes/conexion.php';
}

if ($logueado && (!isset($_SESSION['usuario_id']) || !isset($_SESSION['rol']))) {
    $consulta = $conexion->prepare('SELECT id, rol FROM usuarios WHERE usuario = ?');
    $consulta->bind_param('s', $_SESSION['usuario']);
    $consulta->execute();
    $consulta->bind_result($usuarioId, $rolUsuario);
    if ($consulta->fetch()) {
        $_SESSION['usuario_id'] = (int) $usuarioId;
        $_SESSION['rol'] = $rolUsuario;
    }
    $consulta->close();
}

if ($logueado && isset($_SESSION['usuario_id'])) {
    if (!isset($conexion)) {
        require_once __DIR__ . '/includes/conexion.php';
    }
    $consulta = $conexion->prepare(
        'SELECT COALESCE(SUM(cp.cantidad), 0), COALESCE(SUM(p.precio * cp.cantidad), 0)
         FROM carritos c
         LEFT JOIN carrito_productos cp ON cp.carrito_id = c.id
         LEFT JOIN productos p ON p.id = cp.producto_id
         WHERE c.usuario_id = ?'
    );
    $consulta->bind_param('i', $_SESSION['usuario_id']);
    $consulta->execute();
    $consulta->bind_result($cantidadCarrito, $totalResumenCarrito);
    $consulta->fetch();
    $consulta->close();
}

if ($vista === 'computadoras' || $vista === 'componentes') {

    $consulta = $conexion->prepare(
        'SELECT id, nombre, descripcion, precio, existencias, marca, categoria, imagen
         FROM productos
         WHERE tipo = ? AND activo = 1
         ORDER BY marca, nombre'
    );
    $consulta->bind_param('s', $vista);
    $consulta->execute();
    $consulta->bind_result($productoId, $nombre, $descripcion, $precio, $existencias, $marca, $categoria, $imagen);

    while ($consulta->fetch()) {
        $producto = [
            'id' => $productoId,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'precio' => $precio,
            'existencias' => $existencias,
            'marca' => $marca,
            'categoria' => $categoria,
            'imagen' => $imagen,
        ];
        $productos[] = $producto;

        $claveMarca = strtolower($marca);
        $gruposPorMarca[$claveMarca]['marca'] = $marca;
        $gruposPorMarca[$claveMarca]['productos'][] = $producto;
        $marcasCatalogo[$claveMarca] = $marca;
        $categoriasCatalogo[$categoria] = $categoria;
    }
    $consulta->close();

    asort($marcasCatalogo, SORT_FLAG_CASE | SORT_STRING);
    asort($categoriasCatalogo, SORT_FLAG_CASE | SORT_STRING);
}

if ($vista === 'carrito') {
    if ($logueado && isset($_SESSION['usuario_id'])) {
        $consulta = $conexion->prepare(
            'SELECT p.id, p.nombre, p.imagen, p.existencias, p.activo, p.precio, cp.cantidad,
                    (p.precio * cp.cantidad) AS subtotal
             FROM carritos c
             INNER JOIN carrito_productos cp ON cp.carrito_id = c.id
             INNER JOIN productos p ON p.id = cp.producto_id
             WHERE c.usuario_id = ?
             ORDER BY cp.agregado_en DESC'
        );
        $consulta->bind_param('i', $_SESSION['usuario_id']);
        $consulta->execute();
        $consulta->bind_result($productoId, $nombre, $imagen, $existencias, $activo, $precio, $cantidad, $subtotal);
        while ($consulta->fetch()) {
            $articulosCarrito[] = [
                'id' => $productoId,
                'nombre' => $nombre,
                'imagen' => $imagen,
                'existencias' => $existencias,
                'activo' => $activo,
                'precio' => $precio,
                'cantidad' => $cantidad,
                'subtotal' => $subtotal,
            ];
            $totalCarrito += (float) $subtotal;
        }
        $consulta->close();
    }
    $mensajeCarrito = $_SESSION['mensaje_carrito'] ?? null;
    unset($_SESSION['mensaje_carrito']);
}
if ($vista === 'gracias') {
    unset($_SESSION['contacto_enviado']);
}
$seoKey = $vista;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06392C">
    <?php require __DIR__ . '/includes/seo_head.php'; ?>
    <script>try { document.documentElement.dataset.tema = localStorage.getItem('tema') === 'claro' ? 'claro' : 'oscuro'; } catch (e) { document.documentElement.dataset.tema = 'oscuro'; }</script>
    <link rel="stylesheet" href="css/estilos.css?v=<?= filemtime(__DIR__ . '/css/estilos.css') ?>">
</head>
<body class="has-mobile-cta">
    <header class="site-header view-header">
        <div class="header-left">
            <a class="brand" href="index.php" aria-label="EnsamblaStore, inicio">
                <span class="brand-mark">e/</span>
                <span class="brand-name">ENSAMBLA<span>STORE</span></span>
            </a>
            <details class="site-menu">
                <summary class="menu-trigger" aria-label="Abrir menú de navegación">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </summary>
                <nav class="main-nav" aria-label="Navegación principal">
                    <a href="index.php">INICIO</a>
                    <a href="seccion.php?vista=computadoras">COMPUTADORAS</a>
                    <a href="seccion.php?vista=componentes">COMPONENTES</a>
                    <a href="seccion.php?vista=acerca">ACERCA DE</a>
                    <a href="seccion.php?vista=privacidad">PRIVACIDAD</a>
                    <button type="button" class="theme-switch" role="switch" aria-checked="false" aria-label="Cambiar entre tema oscuro y claro" data-theme-switch>
                        <span class="theme-track"><span class="theme-thumb"></span></span>
                        <span class="theme-text" data-theme-text>OSCURO</span>
                    </button>
                    <a class="nav-contact" <?= $enlaceContacto ?>>SOPORTE <span aria-hidden="true">↗</span></a>
                </nav>
            </details>
        </div>
        <div class="header-actions">
            <?php if ($logueado): ?>
                <details class="nav-dropdown account-dropdown">
                    <summary class="account-trigger" aria-label="Menú de cuenta de <?= $h($_SESSION['usuario']) ?>">
                        <span class="account-avatar" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        </span>
                        <span class="account-trigger-copy"><span>Hola,</span><strong><?= $h($_SESSION['usuario']) ?></strong></span>
                        <span class="dropdown-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="nav-dropdown-panel account-menu">
                        <p class="dropdown-eyebrow">SESIÓN ACTUAL</p>
                        <p class="account-menu-user"><?= $h($_SESSION['usuario']) ?></p>
                        <?php if (($_SESSION['rol'] ?? 'cliente') === 'admin'): ?>
                            <a class="dropdown-menu-link" href="admin.php">Administrar catálogo</a>
                        <?php endif; ?>
                        <form method="post" action="index.php" class="logout-form"><input type="hidden" name="accion" value="salir"><input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>"><button type="submit" class="dropdown-menu-link logout-button">Cerrar sesión</button></form>
                    </div>
                </details>
            <?php else: ?>
                <details class="nav-dropdown account-dropdown">
                    <summary class="account-trigger" aria-label="Iniciar sesión en EnsamblaStore">
                        <span class="account-avatar" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                        </span>
                        <span class="account-trigger-copy"><span>Hola,</span><strong>Inicia sesión</strong></span>
                        <span class="dropdown-chevron" aria-hidden="true"></span>
                    </summary>
                    <div class="nav-dropdown-panel account-menu">
                        <p class="dropdown-eyebrow">ENSAMBLASTORE</p>
                        <p>Inicia sesión para guardar tu carrito y continuar.</p>
                        <a class="dropdown-menu-link" href="index.php?login=1">Iniciar sesión</a>
                    </div>
                </details>
            <?php endif; ?>
            <details class="nav-dropdown cart-dropdown">
                <summary class="cart-trigger" aria-label="Carrito de compras: <?= (int) $cantidadCarrito ?> artículos, total $<?= number_format((float) $totalResumenCarrito, 2) ?> MXN">
                    <span class="cart-icon-wrap" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l2.2 11.2a2 2 0 0 0 2 1.6h8.9a2 2 0 0 0 2-1.6L22 7H6"/><circle cx="10" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                        <span class="cart-badge"><?= (int) $cantidadCarrito ?></span>
                    </span>
                    <span class="cart-summary-total">$<?= number_format((float) $totalResumenCarrito, 2) ?> MXN</span>
                    <span class="dropdown-chevron" aria-hidden="true"></span>
                </summary>
                <div class="nav-dropdown-panel cart-menu">
                    <p class="dropdown-eyebrow">MI CARRITO</p>
                    <p><?= (int) $cantidadCarrito > 0 ? 'Tienes ' . (int) $cantidadCarrito . ' artículo(s) en tu carrito.' : 'Tu carrito está vacío.' ?></p>
                    <a class="dropdown-menu-link" href="seccion.php?vista=carrito">Ver carrito</a>
                </div>
            </details>
        </div>
    </header>

    <main class="view-main">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <ol>
                <li><a href="index.php">Inicio</a></li>
                <li aria-current="page"><?= $h($secciones[$vista]['titulo']) ?></li>
            </ol>
        </nav>
        <?php include __DIR__ . '/secciones/' . $secciones[$vista]['archivo']; ?>
    </main>

    <?php if ($vista === 'computadoras' || $vista === 'componentes'): ?>
    <dialog class="product-preview-dialog" id="product-preview-dialog" aria-labelledby="preview-title">
        <div class="product-preview-panel">
            <button type="button" class="dialog-close" data-close-product-preview aria-label="Cerrar vista previa">×</button>
            <div class="product-preview-media">
                <span>Imagen no disponible</span>
                <img data-preview-image src="" alt="" hidden>
            </div>
            <div class="product-preview-details">
                <p class="eyebrow"><span></span> DETALLES DEL PRODUCTO</p>
                <h2 id="preview-title" data-preview-name></h2>
                <p class="product-preview-price" data-preview-price></p>
                <dl class="product-preview-meta">
                    <div><dt>Marca</dt><dd data-preview-brand></dd></div>
                    <div><dt>Tipo</dt><dd data-preview-category></dd></div>
                    <div><dt>Disponibilidad</dt><dd data-preview-stock></dd></div>
                </dl>
                <div class="product-preview-specs">
                    <h3>Especificaciones</h3>
                    <p data-preview-description></p>
                </div>
                <form action="index.php" method="post" class="product-preview-action" data-preview-action>
                    <input type="hidden" name="accion" value="agregar_carrito">
                    <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                    <input type="hidden" name="producto_id" value="" data-preview-product-id>
                    <button type="submit" data-preview-cart-button><?= $logueado ? 'Agregar al carrito' : 'Inicia sesión y agrega' ?></button>
                </form>
            </div>
        </div>
    </dialog>
    <?php endif; ?>

    <footer class="site-footer">
        <a class="brand footer-brand" href="index.php"><span class="brand-mark">e/</span><span class="brand-name">ENSAMBLA<span>STORE</span></span></a>
        <p>Tecnología a tu medida.</p>
        <a class="footer-link" <?= $enlaceContacto ?>>SOPORTE <span aria-hidden="true">↗</span></a>
        <nav class="footer-nav" aria-label="Enlaces del sitio">
            <a href="seccion.php?vista=computadoras">Computadoras</a>
            <a href="seccion.php?vista=componentes">Componentes</a>
            <a href="seccion.php?vista=acerca">Acerca de</a>
            <a href="seccion.php?vista=soporte">Contacto</a>
            <a href="seccion.php?vista=privacidad">Privacidad</a>
        </nav>
    </footer>

    <a class="mobile-sticky-cta" href="seccion.php?vista=computadoras">Explorar catálogo</a>
    <script src="js/secciones.js?v=<?= filemtime(__DIR__ . '/js/secciones.js') ?>" defer></script>
</body>
</html>
