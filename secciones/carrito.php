<section class="catalog-section cart-section" aria-labelledby="cart-title">
    <header class="catalog-heading">
        <p class="eyebrow"><span></span> ENSAMBLASTORE</p>
        <h1 id="cart-title">Mi carrito</h1>
        <p>Revisa los productos y envía tu solicitud de pedido. La tienda confirmará la disponibilidad antes de cobrar.</p>
    </header>

    <?php if ($mensajeCarrito !== null): ?>
        <p class="notice <?= $mensajeCarrito['tipo'] === 'error' ? 'error' : 'success' ?>" role="<?= $mensajeCarrito['tipo'] === 'error' ? 'alert' : 'status' ?>">
            <?= $h($mensajeCarrito['texto']) ?>
        </p>
    <?php endif; ?>

    <?php if (!$logueado): ?>
        <div class="support-login">
            <p>Inicia sesión para ver y guardar tu carrito.</p>
            <a class="button button-light" href="index.php?login=1">INICIAR SESIÓN <span aria-hidden="true">↗</span></a>
        </div>
    <?php elseif ($articulosCarrito === []): ?>
        <div class="support-login">
            <p>Tu carrito está vacío. Agrega productos del catálogo para verlos aquí.</p>
            <a class="button button-light" href="seccion.php?vista=computadoras">VER COMPUTADORAS <span aria-hidden="true">↗</span></a>
            <a class="button button-light" href="seccion.php?vista=componentes">VER COMPONENTES <span aria-hidden="true">↗</span></a>
        </div>
    <?php else: ?>
        <div class="cart-items">
            <?php foreach ($articulosCarrito as $articulo): ?>
                <?php
                $imagenValida = is_string($articulo['imagen'])
                    && preg_match('~\Aassets/productos/[A-Za-z0-9_-]+\.(?:avif|gif|jpe?g|png|webp)\z~i', $articulo['imagen']);
                $disponible = (int) $articulo['activo'] === 1 && (int) $articulo['existencias'] > 0;
                ?>
                <article class="cart-item">
                    <div class="cart-item-image">
                        <span>Imagen no disponible</span>
                        <?php if ($imagenValida): ?>
                            <img src="<?= $h($articulo['imagen']) ?>" alt="<?= $h($articulo['nombre']) ?>" loading="lazy" onerror="this.remove()">
                        <?php endif; ?>
                    </div>
                    <div class="cart-item-details">
                        <h3><?= $h($articulo['nombre']) ?></h3>
                        <p>$<?= number_format((float) $articulo['precio'], 2) ?> MXN por unidad</p>
                        <?php if (!$disponible): ?>
                            <p class="cart-unavailable">Este producto ya no está disponible.</p>
                        <?php elseif ((int) $articulo['cantidad'] > (int) $articulo['existencias']): ?>
                            <p class="cart-unavailable">Existencias actuales: <?= (int) $articulo['existencias'] ?>. Ajusta la cantidad.</p>
                        <?php endif; ?>
                        <p class="cart-item-subtotal">Subtotal: $<?= number_format((float) $articulo['subtotal'], 2) ?> MXN</p>
                    </div>
                    <div class="cart-item-actions">
                        <?php if ($disponible): ?>
                            <form action="index.php" method="post" class="cart-quantity">
                                <input type="hidden" name="accion" value="actualizar_carrito">
                                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                                <input type="hidden" name="producto_id" value="<?= (int) $articulo['id'] ?>">
                                <label for="cantidad-<?= (int) $articulo['id'] ?>">Cantidad</label>
                                <input type="number" id="cantidad-<?= (int) $articulo['id'] ?>" name="cantidad" min="1" max="<?= (int) $articulo['existencias'] ?>" value="<?= min((int) $articulo['cantidad'], (int) $articulo['existencias']) ?>" required>
                                <button type="submit">Actualizar</button>
                            </form>
                        <?php endif; ?>
                        <form action="index.php" method="post">
                            <input type="hidden" name="accion" value="quitar_carrito">
                            <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                            <input type="hidden" name="producto_id" value="<?= (int) $articulo['id'] ?>">
                            <button type="submit" class="button-remove">Quitar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="cart-total">
            <strong>Total actual</strong>
            <span>$<?= number_format($totalCarrito, 2) ?> MXN</span>
            <p>Enviar el pedido no realiza ningún cobro; al confirmarlo se descuentan las existencias.</p>
        </div>
        <section class="checkout-panel" aria-labelledby="checkout-title">
            <header>
                <p class="eyebrow"><span></span> SOLICITUD DE PEDIDO</p>
                <h3 id="checkout-title">Datos de contacto</h3>
                <p>El pedido quedará pendiente hasta que confirmemos existencias y detalles contigo.</p>
            </header>
            <form action="index.php" method="post">
                <input type="hidden" name="accion" value="crear_pedido">
                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label for="pedido-nombre">Nombre</label>
                        <input type="text" id="pedido-nombre" name="nombre_contacto" maxlength="120" autocomplete="name" required>
                    </div>
                    <div class="field">
                        <label for="pedido-correo">Correo electrónico</label>
                        <input type="email" id="pedido-correo" name="correo" maxlength="254" autocomplete="email" required>
                    </div>
                    <div class="field">
                        <label for="pedido-telefono">Teléfono (opcional)</label>
                        <input type="tel" id="pedido-telefono" name="telefono" maxlength="30" autocomplete="tel">
                    </div>
                    <div class="field">
                        <label for="pedido-direccion">Dirección de entrega (opcional)</label>
                        <input type="text" id="pedido-direccion" name="direccion" maxlength="255" autocomplete="street-address">
                    </div>
                </div>
                <button type="submit">Enviar pedido pendiente</button>
            </form>
        </section>
    <?php endif; ?>
</section>
