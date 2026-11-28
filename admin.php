<?php
require_once __DIR__ . '/includes/sesion.php';
require_once __DIR__ . '/includes/conexion.php';

$h = static fn($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);

if ($usuarioId < 1) {
    header('Location: index.php?login=1');
    exit;
}

$consulta = $conexion->prepare('SELECT usuario, rol FROM usuarios WHERE id = ?');
$consulta->bind_param('i', $usuarioId);
$consulta->execute();
$consulta->bind_result($usuarioActual, $rolActual);
$usuarioExiste = $consulta->fetch();
$consulta->close();

if (!$usuarioExiste) {
    session_unset();
    session_destroy();
    header('Location: index.php?login=1');
    exit;
}

$_SESSION['usuario'] = $usuarioActual;
$_SESSION['rol'] = $rolActual;
if ($rolActual !== 'admin') {
    http_response_code(403);
    exit('No tienes permiso para administrar el catálogo.');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$error = '';
$mensaje = $_SESSION['mensaje_admin'] ?? '';
unset($_SESSION['mensaje_admin']);
$formProducto = [
    'id' => 0,
    'tipo' => 'computadoras',
    'nombre' => '',
    'descripcion' => '',
    'precio' => '',
    'existencias' => '0',
    'marca' => '',
    'categoria' => '',
    'imagen' => null,
    'activo' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenEnviado = $_POST['csrf_token'] ?? '';
    if (!is_string($tokenEnviado) || !hash_equals($csrfToken, $tokenEnviado)) {
        http_response_code(400);
        exit('La solicitud expiró. Recarga la página e inténtalo de nuevo.');
    }

    $accion = $_POST['accion'] ?? '';
    if ($accion === 'cambiar_estado') {
        $idEntrada = $_POST['id'] ?? '';
        $estadoEntrada = $_POST['estado'] ?? '';
        $id = is_string($idEntrada) ? filter_var($idEntrada, FILTER_VALIDATE_INT) : false;
        $estado = is_string($estadoEntrada) ? filter_var($estadoEntrada, FILTER_VALIDATE_INT) : false;
        if ($id === false || $id < 1 || !in_array($estado, [0, 1], true)) {
            $error = 'El producto indicado no es válido.';
        } else {
            $consulta = $conexion->prepare('UPDATE productos SET activo = ? WHERE id = ?');
            $consulta->bind_param('ii', $estado, $id);
            $consulta->execute();
            $actualizado = $consulta->affected_rows > 0;
            $consulta->close();
            $_SESSION['mensaje_admin'] = $actualizado
                ? ($estado === 1 ? 'Producto activado en el catálogo.' : 'Producto ocultado del catálogo.')
                : 'No se realizaron cambios en el producto.';
            header('Location: admin.php');
            exit;
        }
    } elseif ($accion === 'guardar_producto') {
        $idEntrada = $_POST['id'] ?? '0';
        $id = is_string($idEntrada) ? filter_var($idEntrada, FILTER_VALIDATE_INT) : false;
        $tipo = $_POST['tipo'] ?? '';
        $nombre = trim(is_string($_POST['nombre'] ?? null) ? $_POST['nombre'] : '');
        $descripcion = trim(is_string($_POST['descripcion'] ?? null) ? $_POST['descripcion'] : '');
        $precioEntrada = trim(is_string($_POST['precio'] ?? null) ? $_POST['precio'] : '');
        $existenciasEntrada = trim(is_string($_POST['existencias'] ?? null) ? $_POST['existencias'] : '');
        $marca = trim(is_string($_POST['marca'] ?? null) ? $_POST['marca'] : '');
        $categoria = trim(is_string($_POST['categoria'] ?? null) ? $_POST['categoria'] : '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        $formProducto = [
            'id' => $id === false ? 0 : $id,
            'tipo' => $tipo,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'precio' => $precioEntrada,
            'existencias' => $existenciasEntrada,
            'marca' => $marca,
            'categoria' => $categoria,
            'imagen' => null,
            'activo' => $activo,
        ];

        $precioValido = preg_match('/\A\d{1,8}(?:\.\d{1,2})?\z/', $precioEntrada) === 1
            && (float) $precioEntrada > 0;
        $existencias = filter_var($existenciasEntrada, FILTER_VALIDATE_INT);

        if ($id === false || $id < 0) {
            $error = 'El identificador del producto no es válido.';
        } elseif (!in_array($tipo, ['computadoras', 'componentes'], true)) {
            $error = 'Selecciona un tipo de producto válido.';
        } elseif ($nombre === '' || mb_strlen($nombre) > 150) {
            $error = 'El nombre es obligatorio y puede tener máximo 150 caracteres.';
        } elseif ($descripcion === '' || mb_strlen($descripcion) > 5000) {
            $error = 'La descripción es obligatoria y puede tener máximo 5,000 caracteres.';
        } elseif (!$precioValido) {
            $error = 'Escribe un precio válido mayor que cero y con máximo dos decimales.';
        } elseif ($existencias === false || $existencias < 0 || $existencias > 4294967295) {
            $error = 'Las existencias deben ser un número entero válido.';
        } elseif ($marca === '' || mb_strlen($marca) > 80) {
            $error = 'La marca es obligatoria y puede tener máximo 80 caracteres.';
        } elseif ($categoria === '' || mb_strlen($categoria) > 60) {
            $error = 'La categoría es obligatoria y puede tener máximo 60 caracteres.';
        } else {
            $imagen = null;
            $imagenAnterior = null;
            if ($id > 0) {
                $consulta = $conexion->prepare('SELECT imagen FROM productos WHERE id = ?');
                $consulta->bind_param('i', $id);
                $consulta->execute();
                $consulta->bind_result($imagenActual);
                if (!$consulta->fetch()) {
                    $error = 'No se encontró el producto que intentas editar.';
                } else {
                    $imagen = $imagenActual;
                    $imagenAnterior = $imagenActual;
                }
                $consulta->close();
            }

            if ($error === '') {
                if (isset($_POST['quitar_imagen'])) {
                    $imagen = null;
                }

                $imagenNueva = null;
                $archivo = $_FILES['imagen'] ?? null;
                if (is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        $error = 'No se pudo recibir la imagen. Intenta subirla de nuevo.';
                    } elseif ((int) ($archivo['size'] ?? 0) > 5 * 1024 * 1024) {
                        $error = 'La imagen no puede superar 5 MB.';
                    } elseif (!is_uploaded_file($archivo['tmp_name'] ?? '')) {
                        $error = 'El archivo de imagen no es válido.';
                    } else {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->file($archivo['tmp_name']);
                        $formatosImagen = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                        ];
                        $detallesImagen = getimagesize($archivo['tmp_name']);
                        if (!isset($formatosImagen[$mime]) || $detallesImagen === false || $detallesImagen['mime'] !== $mime) {
                            $error = 'Usa una imagen JPG, PNG o WebP válida.';
                        } else {
                            $directorioImagenes = __DIR__ . '/assets/productos';
                            $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $formatosImagen[$mime];
                            if (!is_dir($directorioImagenes) || !is_writable($directorioImagenes)) {
                                $error = 'La carpeta de imágenes no está disponible para escritura.';
                            } elseif (!move_uploaded_file($archivo['tmp_name'], $directorioImagenes . '/' . $nombreArchivo)) {
                                $error = 'No se pudo guardar la imagen en el servidor.';
                            } else {
                                $imagen = 'assets/productos/' . $nombreArchivo;
                                $imagenNueva = $directorioImagenes . '/' . $nombreArchivo;
                            }
                        }
                    }
                }
            }

            if ($error === '') {
                $precio = (float) $precioEntrada;
                $existencias = (int) $existencias;
                try {
                    if ($id === 0) {
                        $consulta = $conexion->prepare(
                            'INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                        );
                        $consulta->bind_param(
                            'sssdisssi',
                            $tipo,
                            $nombre,
                            $descripcion,
                            $precio,
                            $existencias,
                            $marca,
                            $categoria,
                            $imagen,
                            $activo
                        );
                    } else {
                        $consulta = $conexion->prepare(
                            'UPDATE productos
                             SET tipo = ?, nombre = ?, descripcion = ?, precio = ?, existencias = ?,
                                 marca = ?, categoria = ?, imagen = ?, activo = ?
                             WHERE id = ?'
                        );
                        $consulta->bind_param(
                            'sssdisssii',
                            $tipo,
                            $nombre,
                            $descripcion,
                            $precio,
                            $existencias,
                            $marca,
                            $categoria,
                            $imagen,
                            $activo,
                            $id
                        );
                    }
                    $consulta->execute();
                    $consulta->close();
                    if ($imagenAnterior !== null && $imagenAnterior !== $imagen
                        && preg_match('#^assets/productos/[a-f0-9]{32}\.(jpg|png|webp)$#', $imagenAnterior)) {
                        $rutaAnterior = __DIR__ . '/' . $imagenAnterior;
                        if (is_file($rutaAnterior)) {
                            unlink($rutaAnterior);
                        }
                    }
                    $_SESSION['mensaje_admin'] = $id === 0 ? 'Producto agregado al catálogo.' : 'Producto actualizado.';
                    header('Location: admin.php');
                    exit;
                } catch (mysqli_sql_exception $e) {
                    error_log('No se pudo guardar un producto desde admin.php: ' . $e->getMessage());
                    if ($imagenNueva !== null && is_file($imagenNueva)) {
                        unlink($imagenNueva);
                    }
                    $error = 'No se pudo guardar el producto. Revisa los datos e inténtalo de nuevo.';
                }
            }
        }
    } else {
        $error = 'La operación solicitada no es válida.';
    }
}

$idEditar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $idEditar !== null && $idEditar !== false && $idEditar > 0) {
    $consulta = $conexion->prepare(
        'SELECT id, tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo
         FROM productos WHERE id = ?'
    );
    $consulta->bind_param('i', $idEditar);
    $consulta->execute();
    $consulta->bind_result($idProducto, $tipoProducto, $nombreProducto, $descripcionProducto, $precioProducto, $existenciasProducto, $marcaProducto, $categoriaProducto, $imagenProducto, $activoProducto);
    if ($consulta->fetch()) {
        $formProducto = [
            'id' => $idProducto,
            'tipo' => $tipoProducto,
            'nombre' => $nombreProducto,
            'descripcion' => $descripcionProducto,
            'precio' => $precioProducto,
            'existencias' => $existenciasProducto,
            'marca' => $marcaProducto,
            'categoria' => $categoriaProducto,
            'imagen' => $imagenProducto,
            'activo' => $activoProducto,
        ];
    } else {
        $error = 'No se encontró el producto solicitado.';
    }
    $consulta->close();
}

$resultadoProductos = $conexion->query(
    'SELECT id, tipo, nombre, precio, existencias, marca, activo
     FROM productos
     ORDER BY activo DESC, tipo, nombre'
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#06392C">
    <title>Administrar catálogo | EnsamblaStore</title>
    <script>try { document.documentElement.dataset.tema = localStorage.getItem('tema') === 'claro' ? 'claro' : 'oscuro'; } catch (e) { document.documentElement.dataset.tema = 'oscuro'; }</script>
    <link rel="stylesheet" href="css/estilos.css?v=<?= filemtime(__DIR__ . '/css/estilos.css') ?>">
</head>
<body>
    <header class="admin-header">
        <a class="brand" href="index.php" aria-label="EnsamblaStore, volver a la tienda">
            <span class="brand-mark">e/</span>
            <span class="brand-name">ENSAMBLA<span>STORE</span></span>
        </a>
        <nav class="admin-nav" aria-label="Navegación de administración">
            <span>Admin: <?= $h($usuarioActual) ?></span>
            <a href="index.php">Ver tienda</a>
            <form method="post" action="index.php" class="logout-form"><input type="hidden" name="accion" value="salir"><input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>"><button type="submit" class="logout-button">Cerrar sesión</button></form>
        </nav>
    </header>

    <main class="admin-page">
        <div class="admin-heading">
            <p class="eyebrow">PANEL PRIVADO</p>
            <h1>Administrar catálogo</h1>
            <p>Agrega productos, actualiza precios e inventario, o escóndelos temporalmente de la tienda.</p>
        </div>

        <?php if ($mensaje !== ''): ?>
            <p class="admin-notice admin-notice-success" role="status"><?= $h($mensaje) ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="admin-notice admin-notice-error" role="alert"><?= $h($error) ?></p>
        <?php endif; ?>

        <section class="admin-panel" aria-labelledby="admin-form-title">
            <div class="admin-panel-heading">
                <div>
                    <p class="eyebrow"><?= (int) $formProducto['id'] > 0 ? 'EDITAR PRODUCTO' : 'NUEVO PRODUCTO' ?></p>
                    <h2 id="admin-form-title"><?= (int) $formProducto['id'] > 0 ? 'Actualizar información' : 'Agregar al catálogo' ?></h2>
                </div>
                <?php if ((int) $formProducto['id'] > 0): ?>
                    <a class="admin-secondary-button" href="admin.php">Cancelar edición</a>
                <?php endif; ?>
            </div>

            <form class="admin-form" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <input type="hidden" name="accion" value="guardar_producto">
                <input type="hidden" name="id" value="<?= (int) $formProducto['id'] ?>">
                <div class="admin-form-grid">
                    <label>Tipo
                        <select name="tipo" required>
                            <option value="computadoras" <?= $formProducto['tipo'] === 'computadoras' ? 'selected' : '' ?>>Computadora</option>
                            <option value="componentes" <?= $formProducto['tipo'] === 'componentes' ? 'selected' : '' ?>>Componente</option>
                        </select>
                    </label>
                    <label>Nombre
                        <input name="nombre" maxlength="150" value="<?= $h($formProducto['nombre']) ?>" required>
                    </label>
                    <label>Marca
                        <input name="marca" maxlength="80" value="<?= $h($formProducto['marca']) ?>" required>
                    </label>
                    <label>Categoría
                        <input name="categoria" maxlength="60" placeholder="Ej. laptop, procesador" value="<?= $h($formProducto['categoria']) ?>" required>
                    </label>
                    <label>Precio (MXN)
                        <input type="number" name="precio" min="0.01" max="99999999.99" step="0.01" value="<?= $h($formProducto['precio']) ?>" required>
                    </label>
                    <label>Existencias
                        <input type="number" name="existencias" min="0" max="4294967295" step="1" value="<?= $h($formProducto['existencias']) ?>" required>
                    </label>
                    <label class="admin-field-wide">Descripción
                        <textarea name="descripcion" maxlength="5000" rows="4" required><?= $h($formProducto['descripcion']) ?></textarea>
                    </label>
                    <label class="admin-field-wide">Imagen del producto (JPG, PNG o WebP; máximo 5 MB)
                        <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp">
                    </label>
                    <?php if (!empty($formProducto['imagen'])): ?>
                        <div class="admin-current-image admin-field-wide">
                            <img src="<?= $h($formProducto['imagen']) ?>" alt="Imagen actual de <?= $h($formProducto['nombre']) ?>">
                            <label class="admin-checkbox"><input type="checkbox" name="quitar_imagen" value="1"> Quitar imagen actual</label>
                        </div>
                    <?php endif; ?>
                    <label class="admin-checkbox admin-field-wide">
                        <input type="checkbox" name="activo" value="1" <?= (int) $formProducto['activo'] === 1 ? 'checked' : '' ?>>
                        Mostrar este producto en la tienda
                    </label>
                </div>
                <div class="admin-form-actions">
                    <button class="admin-primary-button" type="submit"><?= (int) $formProducto['id'] > 0 ? 'Guardar cambios' : 'Agregar producto' ?></button>
                </div>
            </form>
        </section>

        <section class="admin-panel" aria-labelledby="admin-list-title">
            <div class="admin-panel-heading">
                <div>
                    <p class="eyebrow">INVENTARIO</p>
                    <h2 id="admin-list-title">Productos registrados</h2>
                </div>
                <span class="admin-count"><?= $resultadoProductos->num_rows ?> producto(s)</span>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-products-table">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Precio</th>
                            <th>Existencias</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($producto = $resultadoProductos->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= $h($producto['nombre']) ?></strong><span><?= $h($producto['marca']) ?></span></td>
                                <td><?= $producto['tipo'] === 'computadoras' ? 'Computadora' : 'Componente' ?></td>
                                <td>$<?= number_format((float) $producto['precio'], 2) ?> MXN</td>
                                <td><?= (int) $producto['existencias'] ?></td>
                                <td><span class="admin-status <?= (int) $producto['activo'] === 1 ? 'is-active' : 'is-hidden' ?>"><?= (int) $producto['activo'] === 1 ? 'Visible' : 'Oculto' ?></span></td>
                                <td class="admin-row-actions">
                                    <a class="admin-secondary-button" href="admin.php?editar=<?= (int) $producto['id'] ?>">Editar</a>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                                        <input type="hidden" name="accion" value="cambiar_estado">
                                        <input type="hidden" name="id" value="<?= (int) $producto['id'] ?>">
                                        <input type="hidden" name="estado" value="<?= (int) $producto['activo'] === 1 ? 0 : 1 ?>">
                                        <button class="admin-secondary-button" type="submit"><?= (int) $producto['activo'] === 1 ? 'Ocultar' : 'Reactivar' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
