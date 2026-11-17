<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/conexion.php';

$baseSitio = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');

// Cerrar sesión
if (isset($_GET['salir'])) {
    header('Location: index.php');
    exit;
}

$logueado = isset($_SESSION['usuario']);
$accion = $_POST['accion'] ?? '';
$vista = '';                       
$errorLogin = $errorRegistro = $okRegistro = '';
$mensajeOperacionCarrito = null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Cerrar sesion: solo por POST con token CSRF
if ($accion === 'salir') {
    $tokenSalida = $_POST['csrf_token'] ?? '';
    if (is_string($tokenSalida) && hash_equals($csrfToken, $tokenSalida)) {
        session_unset();
        session_destroy();
    }
    header('Location: index.php');
    exit;
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

// Formularios con sesión propia: login, registro y contacto exigen token CSRF
if (in_array($accion, ['login', 'registro', 'contacto'], true)) {
    $tokenFormulario = $_POST['csrf_token'] ?? '';
    if (!is_string($tokenFormulario) || !hash_equals($csrfToken, $tokenFormulario)) {
        http_response_code(400);
        exit('La solicitud expiró. Recarga la página e inténtalo de nuevo.');
    }
}

// Iniciar sesión
if ($accion === 'login' && !$logueado) {
    $usuario = $_POST['usuario'] ?? '';
    $usuario = is_string($usuario) ? trim($usuario) : '';
    $contrasena = $_POST['contrasena'] ?? '';
    $contrasena = is_string($contrasena) ? $contrasena : '';
    $ipCliente = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    // limite de intentos fallidos por IP: 8 cada 15 minutos
    $consulta = $conexion->prepare('SELECT COUNT(*) FROM intentos_login WHERE ip = ? AND creado_en > (NOW() - INTERVAL 15 MINUTE)');
    $consulta->bind_param('s', $ipCliente);
    $consulta->execute();
    $consulta->bind_result($intentosRecientes);
    $consulta->fetch();
    $consulta->close();
    $bloqueadoLogin = (int) $intentosRecientes >= 8;

    $consulta = $conexion->prepare('SELECT id, contrasena_hash, rol FROM usuarios WHERE usuario = ?');
    $consulta->bind_param('s', $usuario);
    $consulta->execute();
    $usuarioId = 0;
    $contrasenaHash = '';
    $rolUsuario = '';
    $consulta->bind_result($usuarioId, $contrasenaHash, $rolUsuario);

    if (
        !$bloqueadoLogin
        && $consulta->fetch()
        && is_string($contrasenaHash)
        && $contrasenaHash !== ''
        && is_string($rolUsuario)
        && password_verify($contrasena, $contrasenaHash)
    ) {
        $consulta->close();
        $consulta = $conexion->prepare('DELETE FROM intentos_login WHERE ip = ?');
        $consulta->bind_param('s', $ipCliente);
        $consulta->execute();
        $consulta->close();
        session_regenerate_id(true);
        $_SESSION['usuario'] = $usuario;
        $_SESSION['usuario_id'] = (int) $usuarioId;
        $_SESSION['rol'] = $rolUsuario;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $logueado = true;

        if (isset($_SESSION['producto_pendiente_carrito'])) {
            $accion = 'agregar_carrito';
            $_POST['producto_id'] = $_SESSION['producto_pendiente_carrito'];
            unset($_SESSION['producto_pendiente_carrito']);
        } else {
            header('Location: index.php');
            exit;
        }
    } else {
        $consulta->close();
        if ($bloqueadoLogin) {
            $errorLogin = 'Demasiados intentos fallidos. Espera 15 minutos e inténtalo de nuevo.';
        } else {
            $consulta = $conexion->prepare('INSERT INTO intentos_login (ip, usuario) VALUES (?, ?)');
            $usuarioIntento = mb_substr($usuario, 0, 50);
            $consulta->bind_param('ss', $ipCliente, $usuarioIntento);
            $consulta->execute();
            $consulta->close();
            $conexion->query('DELETE FROM intentos_login WHERE creado_en < (NOW() - INTERVAL 1 DAY)');
            $errorLogin = 'Usuario o contraseña incorrectos.';
        }
        $vista = 'login';
    }
}

// Crear cuenta
if ($accion === 'registro' && !$logueado) {
    $usuario = trim($_POST['usuario'] ?? '');
    $contrasena = $_POST['contrasena'] ?? '';
    $vista = 'registro';

    if ($usuario === '' || $contrasena === '') {
        $errorRegistro = 'Escribe un usuario y una contraseña.';
    } elseif (mb_strlen($usuario) > 50) {
        $errorRegistro = 'El usuario puede tener máximo 50 caracteres.';
    } elseif (mb_strlen($contrasena) < 8) {
        $errorRegistro = 'La contraseña debe tener al menos 8 caracteres.';
    } else {
        try {
            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
            $consulta = $conexion->prepare('INSERT INTO usuarios (usuario, contrasena_hash) VALUES (?, ?)');
            $consulta->bind_param('ss', $usuario, $hash);
            $consulta->execute();
            $okRegistro = 'Cuenta creada. Ya puedes iniciar sesión.';
            $vista = 'login';
        } catch (mysqli_sql_exception $e) {
            $errorRegistro = 'No se pudo crear la cuenta. Puede que ese usuario ya exista.';
        }
    }
}

if (in_array($accion, ['agregar_carrito', 'actualizar_carrito', 'quitar_carrito', 'crear_pedido'], true)) {
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    if (!is_string($tokenEnviado) || !hash_equals($csrfToken, $tokenEnviado)) {
        http_response_code(400);
        exit('La solicitud expiró. Recarga la página e inténtalo de nuevo.');
    }

    if (!$logueado || !isset($_SESSION['usuario_id'])) {
        if ($accion === 'agregar_carrito') {
            $productoIdEntrada = $_POST['producto_id'] ?? null;
            $productoId = is_scalar($productoIdEntrada)
                ? filter_var($productoIdEntrada, FILTER_VALIDATE_INT)
                : false;
            if ($productoId === false || $productoId < 1) {
                http_response_code(400);
                exit('El producto indicado no es válido.');
            }
            $_SESSION['producto_pendiente_carrito'] = (int) $productoId;
        }
        header('Location: index.php?login=1');
        exit;
    }

    $usuarioId = (int) $_SESSION['usuario_id'];
    $conexion->begin_transaction();

    try {
        $consulta = $conexion->prepare('INSERT IGNORE INTO carritos (usuario_id) VALUES (?)');
        $consulta->bind_param('i', $usuarioId);
        $consulta->execute();
        $consulta->close();

        $consulta = $conexion->prepare('SELECT id FROM carritos WHERE usuario_id = ? FOR UPDATE');
        $consulta->bind_param('i', $usuarioId);
        $consulta->execute();
        $consulta->bind_result($carritoId);
        if (!$consulta->fetch()) {
            throw new RuntimeException('No se pudo obtener el carrito del usuario.');
        }
        $consulta->close();
        $carritoId = (int) $carritoId;

        if ($accion === 'crear_pedido') {
            $nombreContacto = $_POST['nombre_contacto'] ?? '';
            $correo = $_POST['correo'] ?? '';
            $telefono = $_POST['telefono'] ?? '';
            $direccion = $_POST['direccion'] ?? '';

            if (!is_string($nombreContacto) || !is_string($correo) || !is_string($telefono) || !is_string($direccion)) {
                throw new InvalidArgumentException('Revisa los datos de contacto del pedido.');
            }

            $nombreContacto = trim($nombreContacto);
            $correo = trim($correo);
            $telefono = trim($telefono);
            $direccion = trim($direccion);

            if ($nombreContacto === '' || mb_strlen($nombreContacto) > 120) {
                throw new InvalidArgumentException('Escribe un nombre de contacto válido.');
            }
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 254) {
                throw new InvalidArgumentException('Escribe un correo electrónico válido.');
            }
            if (mb_strlen($telefono) > 30 || mb_strlen($direccion) > 255) {
                throw new InvalidArgumentException('El teléfono o la dirección son demasiado largos.');
            }

            $consulta = $conexion->prepare(
                'SELECT p.existencias, p.activo, cp.cantidad
                 FROM carrito_productos cp
                 INNER JOIN productos p ON p.id = cp.producto_id
                 WHERE cp.carrito_id = ?
                 FOR UPDATE'
            );
            $consulta->bind_param('i', $carritoId);
            $consulta->execute();
            $consulta->bind_result($existencias, $activo, $cantidad);
            $cantidadArticulos = 0;
            while ($consulta->fetch()) {
                $cantidadArticulos++;
                if ((int) $activo !== 1 || (int) $existencias < 1) {
                    $consulta->close();
                    throw new InvalidArgumentException('Hay un producto agotado o no disponible. Revisa tu carrito.');
                }
                if ((int) $cantidad > (int) $existencias) {
                    $consulta->close();
                    throw new InvalidArgumentException('No hay existencias suficientes para todos los productos. Ajusta tu carrito.');
                }
            }
            $consulta->close();

            if ($cantidadArticulos === 0) {
                throw new InvalidArgumentException('Tu carrito está vacío.');
            }

            $consulta = $conexion->prepare(
                'SELECT COALESCE(SUM(p.precio * cp.cantidad), 0)
                 FROM carrito_productos cp
                 INNER JOIN productos p ON p.id = cp.producto_id
                 WHERE cp.carrito_id = ?'
            );
            $consulta->bind_param('i', $carritoId);
            $consulta->execute();
            $consulta->bind_result($totalPedido);
            $consulta->fetch();
            $consulta->close();

            $estadoPedido = 'pendiente';
            $consulta = $conexion->prepare(
                'INSERT INTO ordenes (usuario_id, nombre_contacto, correo, telefono, direccion, total, estado)
                 VALUES (?, ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), ?, ?)'
            );
            $consulta->bind_param(
                'issssss',
                $usuarioId,
                $nombreContacto,
                $correo,
                $telefono,
                $direccion,
                $totalPedido,
                $estadoPedido
            );
            $consulta->execute();
            $ordenId = (int) $conexion->insert_id;
            $consulta->close();

            $consulta = $conexion->prepare(
                'INSERT INTO orden_productos (orden_id, producto_id, nombre_producto, precio_unitario, cantidad, subtotal)
                 SELECT ?, p.id, p.nombre, p.precio, cp.cantidad, p.precio * cp.cantidad
                 FROM carrito_productos cp
                 INNER JOIN productos p ON p.id = cp.producto_id
                 WHERE cp.carrito_id = ?'
            );
            $consulta->bind_param('ii', $ordenId, $carritoId);
            $consulta->execute();
            if ($consulta->affected_rows !== $cantidadArticulos) {
                $consulta->close();
                throw new RuntimeException('No se pudieron guardar todos los productos del pedido.');
            }
            $consulta->close();

            // descuenta las existencias en la misma transaccion (si algo falla, todo se revierte)
            $consulta = $conexion->prepare(
                'UPDATE productos p
                 INNER JOIN carrito_productos cp ON cp.producto_id = p.id
                 SET p.existencias = p.existencias - cp.cantidad
                 WHERE cp.carrito_id = ? AND p.existencias >= cp.cantidad'
            );
            $consulta->bind_param('i', $carritoId);
            $consulta->execute();
            if ($consulta->affected_rows !== $cantidadArticulos) {
                $consulta->close();
                throw new InvalidArgumentException('No hay existencias suficientes para todos los productos. Ajusta tu carrito.');
            }
            $consulta->close();

            $consulta = $conexion->prepare('DELETE FROM carrito_productos WHERE carrito_id = ?');
            $consulta->bind_param('i', $carritoId);
            $consulta->execute();
            $consulta->close();
        } elseif ($accion === 'quitar_carrito') {
            $productoIdEntrada = $_POST['producto_id'] ?? null;
            $productoId = is_scalar($productoIdEntrada)
                ? filter_var($productoIdEntrada, FILTER_VALIDATE_INT)
                : false;
            if ($productoId === false || $productoId < 1) {
                throw new InvalidArgumentException('El producto indicado no es válido.');
            }

            $consulta = $conexion->prepare('DELETE FROM carrito_productos WHERE carrito_id = ? AND producto_id = ?');
            $consulta->bind_param('ii', $carritoId, $productoId);
            $consulta->execute();
            $consulta->close();
        } else {
            $productoIdEntrada = $_POST['producto_id'] ?? null;
            $productoId = is_scalar($productoIdEntrada)
                ? filter_var($productoIdEntrada, FILTER_VALIDATE_INT)
                : false;
            if ($productoId === false || $productoId < 1) {
                throw new InvalidArgumentException('El producto indicado no es válido.');
            }

            $cantidadSolicitada = 1;
            if ($accion === 'actualizar_carrito') {
                $cantidadEntrada = $_POST['cantidad'] ?? null;
                $cantidadSolicitada = is_scalar($cantidadEntrada)
                    ? filter_var($cantidadEntrada, FILTER_VALIDATE_INT)
                    : false;
                if ($cantidadSolicitada === false || $cantidadSolicitada < 1) {
                    throw new InvalidArgumentException('La cantidad debe ser al menos uno.');
                }
            }

            $consulta = $conexion->prepare(
                'SELECT existencias FROM productos WHERE id = ? AND activo = 1 FOR UPDATE'
            );
            $consulta->bind_param('i', $productoId);
            $consulta->execute();
            $consulta->bind_result($existencias);
            if (!$consulta->fetch()) {
                $consulta->close();
                throw new InvalidArgumentException('Ese producto ya no está disponible.');
            }
            $consulta->close();
            $existencias = (int) $existencias;

            $consulta = $conexion->prepare(
                'SELECT cantidad FROM carrito_productos WHERE carrito_id = ? AND producto_id = ? FOR UPDATE'
            );
            $consulta->bind_param('ii', $carritoId, $productoId);
            $consulta->execute();
            $consulta->bind_result($cantidadActual);
            $existeEnCarrito = $consulta->fetch();
            $consulta->close();
            $cantidadActual = $existeEnCarrito ? (int) $cantidadActual : 0;
            $cantidadFinal = $accion === 'agregar_carrito'
                ? $cantidadActual + $cantidadSolicitada
                : $cantidadSolicitada;

            if ($existencias < 1 || $cantidadFinal > $existencias) {
                $mensajeOperacionCarrito = [
                    'tipo' => 'error',
                    'texto' => $existencias < 1
                        ? 'Este producto está agotado.'
                        : 'No hay existencias suficientes para esa cantidad.',
                ];
            } elseif ($existeEnCarrito) {
                $consulta = $conexion->prepare(
                    'UPDATE carrito_productos SET cantidad = ? WHERE carrito_id = ? AND producto_id = ?'
                );
                $consulta->bind_param('iii', $cantidadFinal, $carritoId, $productoId);
                $consulta->execute();
                $consulta->close();
            } else {
                $consulta = $conexion->prepare(
                    'INSERT INTO carrito_productos (carrito_id, producto_id, cantidad) VALUES (?, ?, ?)'
                );
                $consulta->bind_param('iii', $carritoId, $productoId, $cantidadFinal);
                $consulta->execute();
                $consulta->close();
            }
        }

        $conexion->commit();
    } catch (InvalidArgumentException $e) {
        $conexion->rollback();
        $_SESSION['mensaje_carrito'] = ['tipo' => 'error', 'texto' => $e->getMessage()];
        header('Location: seccion.php?vista=carrito');
        exit;
    } catch (Throwable $e) {
        $conexion->rollback();
        throw $e;
    }

    if ($mensajeOperacionCarrito === null) {
        if ($accion === 'crear_pedido') {
            $mensajeOperacionCarrito = [
                'tipo' => 'success',
                'texto' => 'Recibimos tu pedido #' . $ordenId . '. Está pendiente de confirmación; todavía no se realizó ningún cobro.',
            ];
        } else {
            $mensajeOperacionCarrito = [
                'tipo' => 'success',
                'texto' => $accion === 'quitar_carrito'
                    ? 'Producto quitado del carrito.'
                    : ($accion === 'actualizar_carrito' ? 'Carrito actualizado.' : 'Producto agregado al carrito.'),
            ];
        }
    }
    $_SESSION['mensaje_carrito'] = $mensajeOperacionCarrito;
    header('Location: seccion.php?vista=carrito');
    exit;
}

// Enviar mensaje de contacto (solo con sesión)
if ($accion === 'contacto') {
    if (!$logueado) {
        $vista = 'login';
    } else {
        $limitesContacto = ['nombre' => 100, 'correo' => 100, 'telefono' => 30, 'direccion' => 255, 'asunto' => 150, 'mensaje' => 5000];
        $campos = [];
        foreach ($limitesContacto as $campo => $limite) {
            $valor = $_POST[$campo] ?? '';
            $valor = is_string($valor) ? trim($valor) : '';
            $campos[$campo] = mb_strlen($valor) > $limite ? null : $valor;
        }
        if (in_array(null, $campos, true)
            || $campos['nombre'] === '' || $campos['asunto'] === '' || $campos['mensaje'] === ''
            || !filter_var($campos['correo'], FILTER_VALIDATE_EMAIL)) {
            header('Location: seccion.php?vista=soporte&error=1#soporte');
            exit;
        }

        $consulta = $conexion->prepare(
            'INSERT INTO mensajes_contacto (nombre, correo, telefono, direccion, asunto, mensaje) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $consulta->bind_param('ssssss',
            $campos['nombre'], $campos['correo'], $campos['telefono'],
            $campos['direccion'], $campos['asunto'], $campos['mensaje']
        );
        $consulta->execute();
        $_SESSION['contacto_enviado'] = true;
        header('Location: seccion.php?vista=gracias');
        exit;
    }
}

if (!$logueado && $vista === '' && isset($_GET['login'])) {
    $vista = 'login';
}

// Enlace a contacto: directo si hay sesión, si no abre el login
$enlaceContacto = $logueado ? 'href="seccion.php?vista=soporte#soporte"' : 'href="?login=1" data-open-login';
$cantidadCarrito = 0;
$totalResumenCarrito = 0.0;
if ($logueado && isset($_SESSION['usuario_id'])) {
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
// Cifras reales del catálogo (se leen de la base de datos)
$resumenCatalogo = ['computadoras' => 0, 'componentes' => 0, 'marcas' => 0];
$resultado = $conexion->query('select tipo, count(*) as total from productos where activo = 1 group by tipo');
while ($fila = $resultado->fetch_assoc()) {
    $resumenCatalogo[$fila['tipo']] = (int) $fila['total'];
}
$resultado = $conexion->query('select count(distinct marca) as total from productos where activo = 1');
$resumenCatalogo['marcas'] = (int) $resultado->fetch_assoc()['total'];
$h = fn($t) => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
$seoKey = 'inicio';
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
<body id="inicio" class="has-mobile-cta">

    <header class="site-header">
        <div class="header-left">
            <a class="brand" href="#inicio" aria-label="EnsamblaStore, inicio">
                <span class="brand-mark">e/</span>
                <span class="brand-name">ENSAMBLA<span>STORE</span></span>
            </a>
            <details class="site-menu">
                <summary class="menu-trigger" aria-label="Abrir menú de navegación">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </summary>
                <nav class="main-nav" aria-label="Navegación principal">
                    <a href="#inicio">INICIO</a>
                    <a href="seccion.php?vista=computadoras">COMPUTADORAS</a>
                    <a href="seccion.php?vista=componentes">COMPONENTES</a>
                    <a href="seccion.php?vista=acerca">ACERCA DE</a>
                    <button type="button" class="theme-switch" role="switch" aria-checked="false" aria-label="Cambiar entre tema oscuro y claro" data-theme-switch>
                        <span class="theme-track"><span class="theme-thumb"></span></span>
                        <span class="theme-text" data-theme-text>OSCURO</span>
                    </button>
                    <a class="nav-contact" <?= $enlaceContacto ?>>HABLEMOS <span aria-hidden="true">↗</span></a>
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
                        <a class="dropdown-menu-link" href="?login=1" data-open-login>Iniciar sesión</a>
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

    <?php include __DIR__ . '/secciones/hero.php'; ?>

    <main class="access-main" id="accesos">
        <header class="access-heading">
            <p class="eyebrow"><span></span> ENSAMBLASTORE</p>
            <h2>¿Qué estás buscando?</h2>
            <p>Elige una sección para explorar el catálogo o recibir ayuda.</p>
        </header>
        <div class="access-layout">
        <nav class="access-grid" aria-label="Accesos a las secciones">
            <a class="access-card" href="seccion.php?vista=computadoras">
                <span class="access-number">01</span><span class="access-arrow" aria-hidden="true">↗</span>
                <span class="access-icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="8" width="36" height="24" rx="3"/><path d="M18 40h12M24 32v8"/></svg></span>
                <h2>Computadoras</h2><p>Equipos nuevos y armados a tu medida.</p>
            </a>
            <a class="access-card" href="seccion.php?vista=componentes">
                <span class="access-number">02</span><span class="access-arrow" aria-hidden="true">↗</span>
                <span class="access-icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="13" y="13" width="22" height="22" rx="3"/><rect x="19" y="19" width="10" height="10" rx="1"/><path d="M19 7v6M29 7v6M19 35v6M29 35v6M7 19h6M7 29h6M35 19h6M35 29h6"/></svg></span>
                <h2>Componentes</h2><p>Piezas para actualizar o armar tu equipo.</p>
            </a>
            <a class="access-card" href="seccion.php?vista=soporte">
                <span class="access-number">03</span><span class="access-arrow" aria-hidden="true">↗</span>
                <span class="access-icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 28v-4a15 15 0 0 1 30 0v4"/><rect x="6" y="27" width="8" height="12" rx="3"/><rect x="34" y="27" width="8" height="12" rx="3"/><path d="M38 39c0 4-4 5-9 5h-3"/></svg></span>
                <h2>Soporte técnico</h2><p>Diagnóstico, reparación y asesoría.</p>
            </a>
            <a class="access-card" href="seccion.php?vista=acerca">
                <span class="access-number">04</span><span class="access-arrow" aria-hidden="true">↗</span>
                <span class="access-icon" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="17" r="6"/><path d="M6 40c0-7 5-12 12-12s12 5 12 12"/><circle cx="34" cy="19" r="5"/><path d="M33 29c6 0 10 4 10 10"/></svg></span>
                <h2>Acerca de</h2><p>Conoce al equipo de EnsamblaStore.</p>
            </a>
        </nav>
        <aside class="access-stats" aria-label="El catálogo en cifras">
            <p class="eyebrow"><span></span> EL CATÁLOGO</p>
            <dl>
                <div><dt>Computadoras</dt><dd><?= $resumenCatalogo['computadoras'] ?></dd></div>
                <div><dt>Componentes</dt><dd><?= $resumenCatalogo['componentes'] ?></dd></div>
                <div><dt>Marcas</dt><dd><?= $resumenCatalogo['marcas'] ?></dd></div>
            </dl>
        </aside>
        </div>

        <section class="brands" aria-label="Marcas que manejamos">
            <p class="eyebrow"><span></span> MARCAS QUE MANEJAMOS</p>
            <?php
            // archivo => [nombre, proporción ancho/alto del SVG]
            $marcas = [
                'asus' => ['ASUS', 4.84], 'acer' => ['Acer', 4.15], 'dell' => ['Dell', 1.00], 'hp' => ['HP', 1.00],
                'lenovo' => ['Lenovo', 3.00], 'msi' => ['MSI', 0.82], 'amd' => ['AMD', 4.19], 'intel' => ['Intel', 2.58],
                'nvidia' => ['NVIDIA', 1.51], 'corsair' => ['Corsair', 1.04], 'kingston' => ['Kingston', 0.72],
            ];
            ?>
            <div class="brands-track">
                <ul class="brands-move">
                    <?php foreach ([0, 1] as $copia): foreach ($marcas as $archivo => [$nombre, $ratio]): ?>
                        <li <?= $copia ? 'aria-hidden="true"' : '' ?>>
                            <span class="marca-logo" role="img" aria-label="<?= $h($nombre) ?>" style="--logo: url('<?= $baseSitio ?>/assets/marcas/<?= $archivo ?>.svg'); aspect-ratio: <?= $ratio ?>; height: <?= round(44 * ($ratio ** -0.35)) ?>px"></span>
                        </li>
                    <?php endforeach; endforeach; ?>
                </ul>
            </div>
        </section>

        <section class="faq-section" aria-labelledby="faq-title">
            <header class="faq-heading">
                <p class="eyebrow"><span></span> PREGUNTAS FRECUENTES</p>
                <h2 id="faq-title">Antes de elegir</h2>
                <p>Información clara para explorar el catálogo y enviar tu solicitud.</p>
            </header>
            <div class="faq-list">
                <details>
                    <summary>¿El catálogo incluye computadoras y componentes?</summary>
                    <p>Sí. Explora las <a href="seccion.php?vista=computadoras">computadoras</a> y los <a href="seccion.php?vista=componentes">componentes</a> disponibles.</p>
                </details>
                <details>
                    <summary>¿El pedido realiza un cobro automático?</summary>
                    <p>No. El pedido queda pendiente de confirmación y esta tienda no procesa cobros en línea.</p>
                </details>
                <details>
                    <summary>¿Cómo solicito ayuda técnica?</summary>
                    <p>Entra a <a href="seccion.php?vista=soporte">soporte técnico</a> e inicia sesión para enviar tu mensaje.</p>
                </details>
                <details>
                    <summary>¿La atención es presencial?</summary>
                    <p>No. EnsamblaStore es un proyecto escolar de demostración con atención en línea; no contamos con local para visitas.</p>
                </details>
            </div>
            <a class="faq-contact-link" href="seccion.php?vista=soporte">¿Tienes otra pregunta? Contacta a soporte <span aria-hidden="true">→</span></a>
        </section>
    </main>

    <footer class="site-footer">
        <a class="brand footer-brand" href="#inicio"><span class="brand-mark">e/</span><span class="brand-name">ENSAMBLA<span>STORE</span></span></a>
        <p>Tecnología a tu medida.</p>
        <a class="footer-link" <?= $enlaceContacto ?>>HABLEMOS <span aria-hidden="true">↗</span></a>
        <nav class="footer-nav" aria-label="Enlaces del sitio">
            <a href="seccion.php?vista=computadoras">Computadoras</a>
            <a href="seccion.php?vista=componentes">Componentes</a>
            <a href="seccion.php?vista=acerca">Acerca de</a>
            <a href="seccion.php?vista=soporte">Contacto</a>
            <a href="seccion.php?vista=privacidad">Privacidad</a>
        </nav>
    </footer>
    <p class="creditos">Modelo 3D "<a href="https://sketchfab.com/3d-models/asus-strix-b-550-f-gaming-motherboard-realistic-3eba5f45bed74fbeb2647de38047000f" target="_blank" rel="noopener">Asus Strix b-550-f Gaming Motherboard Realistic</a>" por <a href="https://sketchfab.com/MUSHROOM_BUILDS" target="_blank" rel="noopener">MUSHROOM_BUILDS</a>, licencia <a href="http://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC-BY 4.0</a>.</p>

    <?php if (!$logueado): ?>
    <!-- Login / crear cuenta (modal) -->
    <dialog class="login-dialog" id="login-dialog" <?= $vista !== '' ? 'open' : '' ?>>
        <section class="form-panel" id="panel-login" <?= $vista === 'registro' ? 'hidden' : '' ?>>
            <button type="button" class="dialog-close" data-close-login aria-label="Cerrar">×</button>
            <p class="eyebrow"><span></span> ENSAMBLASTORE</p>
            <h2>Iniciar sesión</h2>
            <p class="intro">Ingresa con tu usuario y contraseña para continuar.</p>

            <?php if ($errorLogin !== ''): ?><p class="notice error" role="alert"><?= $h($errorLogin) ?></p><?php endif; ?>
            <?php if ($okRegistro !== ''): ?><p class="notice success" role="status"><?= $h($okRegistro) ?></p><?php endif; ?>

            <form action="index.php" method="post">
                <input type="hidden" name="accion" value="login">
                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <label for="login-usuario">Usuario</label>
                <input type="text" id="login-usuario" name="usuario" autocomplete="username" required>
                <label for="login-contrasena">Contraseña</label>
                <input type="password" id="login-contrasena" name="contrasena" autocomplete="current-password" required>
                <button type="submit">Entrar</button>
            </form>
            <p class="form-footnote">¿Primera vez? <a href="#" data-vista="registro">Crear cuenta</a></p>
        </section>

        <section class="form-panel" id="panel-registro" <?= $vista === 'registro' ? '' : 'hidden' ?>>
            <button type="button" class="dialog-close" data-close-login aria-label="Cerrar">×</button>
            <p class="eyebrow"><span></span> ENSAMBLASTORE</p>
            <h2>Crear cuenta</h2>
            <p class="intro">Elige un usuario y una contraseña para iniciar sesión.</p>

            <?php if ($errorRegistro !== ''): ?><p class="notice error" role="alert"><?= $h($errorRegistro) ?></p><?php endif; ?>

            <form action="index.php" method="post">
                <input type="hidden" name="accion" value="registro">
                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <label for="reg-usuario">Usuario</label>
                <input type="text" id="reg-usuario" name="usuario" autocomplete="username" required>
                <label for="reg-contrasena">Contraseña</label>
                <input type="password" id="reg-contrasena" name="contrasena" autocomplete="new-password" minlength="8" required>
                <button type="submit">Crear cuenta</button>
            </form>
            <p class="form-footnote"><a href="#" data-vista="login">Volver al inicio de sesión</a></p>
        </section>
    </dialog>
    <?php endif; ?>

    <a class="mobile-sticky-cta" href="seccion.php?vista=computadoras">Explorar catálogo</a>
    <script>
        /* ---- Modal de login / registro ---- */
        const dialogo = document.getElementById('login-dialog');
        if (dialogo) {
            const paneles = { login: document.getElementById('panel-login'), registro: document.getElementById('panel-registro') };
            const mostrar = (vista) => { for (const k in paneles) paneles[k].hidden = k !== vista; };

            if (dialogo.hasAttribute('open')) { dialogo.removeAttribute('open'); dialogo.showModal(); }

            document.querySelectorAll('[data-open-login]').forEach((el) => el.addEventListener('click', (e) => {
                e.preventDefault();
                mostrar('login');
                if (!dialogo.open) dialogo.showModal();
            }));
            document.querySelectorAll('[data-vista]').forEach((el) => el.addEventListener('click', (e) => {
                e.preventDefault();
                mostrar(el.dataset.vista);
            }));
            dialogo.querySelectorAll('[data-close-login]').forEach((el) => el.addEventListener('click', () => dialogo.close()));
            dialogo.addEventListener('click', (e) => { if (e.target === dialogo) dialogo.close(); });
        }

    </script>
    <script type="module">
        async function initializeModelViewer() {
            let modelViewerModule;
            try {
                modelViewerModule = await import('./js/model-viewer.min.js');
            } catch (error) {
                console.error('No se pudo cargar el model-viewer local. Se intentará usar la copia CDN.', error);
                modelViewerModule = await import('https://cdn.jsdelivr.net/npm/@google/model-viewer@3.5.0/dist/model-viewer.min.js');
            }

            modelViewerModule.ModelViewerElement.meshoptDecoderLocation = 'js/meshopt_decoder.js';
            const viewer = document.querySelector('model-viewer[data-src]');
            if (viewer) {
                viewer.addEventListener('error', (error) => console.error('No se pudo cargar el modelo 3D.', error));
                viewer.src = viewer.dataset.src;
            }
        }

        initializeModelViewer().catch((error) => console.error('No se pudo inicializar el modelo 3D.', error));
    </script>
    <script src="js/secciones.js?v=<?= filemtime(__DIR__ . '/js/secciones.js') ?>" defer></script>
</body>
</html>
