<section class="catalog-section" id="computadoras" data-catalog="computadoras" aria-labelledby="computadoras-title">
    <header class="catalog-heading">
        <p class="eyebrow"><span></span> CATÁLOGO</p>
        <h1 id="computadoras-title">Computadoras</h1>
        <p>Encuentra el equipo ideal para trabajar, crear o jugar.</p>
    </header>

    <div class="catalog-filters" data-catalog-filter="computadoras">
        <label class="catalog-search">Buscar producto
            <input type="search" data-product-search placeholder="Escribe nombre, marca o característica" autocomplete="off">
        </label>
        <label>Marca
            <select data-filter-brand>
                <option value="todas">Todas las marcas</option>
                <?php foreach ($marcasCatalogo as $clave => $nombreMarca): ?>
                    <option value="<?= $h($clave) ?>"><?= $h($nombreMarca) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo de equipo
            <select data-filter-category>
                <option value="todas">Todos los tipos</option>
                <?php foreach ($categoriasCatalogo as $categoria): ?>
                    <option value="<?= $h($categoria) ?>"><?= $h($etiquetasCategorias[$categoria] ?? ucfirst(str_replace('-', ' ', $categoria))) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <form class="recommendation-quiz" data-recommendation-quiz>
        <div class="quiz-heading">
            <h3>Encuentra tu equipo ideal</h3>
            <p>Cuéntanos qué necesitas y te mostramos opciones del catálogo.</p>
        </div>
        <label>Presupuesto máximo
            <select data-quiz-budget>
                <option value="">Sin límite</option>
                <option value="10000">Hasta $10,000</option>
                <option value="20000">Hasta $20,000</option>
                <option value="35000">Hasta $35,000</option>
                <option value="60000">Hasta $60,000</option>
            </select>
        </label>
        <label>¿Para qué lo usarás?
            <select data-quiz-use>
                <option value="">Uso general</option>
                <option value="estudio">Estudio u oficina</option>
                <option value="gaming">Gaming</option>
                <option value="creacion">Diseño y creación</option>
            </select>
        </label>
        <label>¿Qué priorizas?
            <select data-quiz-priority>
                <option value="">Equilibrio</option>
                <option value="potencia">Potencia y rendimiento</option>
                <option value="almacenamiento">Almacenamiento</option>
            </select>
        </label>
        <div class="quiz-actions">
            <button type="submit">Ver recomendaciones</button>
            <button type="reset" class="quiz-reset">Limpiar</button>
        </div>
    </form>

    <section class="recommendation-results" data-recommendation-results hidden aria-live="polite">
        <h3>Recomendaciones para ti</h3>
        <ol data-recommendation-list></ol>
        <p data-recommendation-message></p>
    </section>

    <div class="brand-groups">
        <?php if ($productos === []): ?>
            <p class="catalog-empty">Todavía no hay computadoras publicadas en el catálogo.</p>
        <?php endif; ?>
        <p class="catalog-empty" data-filter-empty hidden>No encontramos productos con esos criterios. Prueba ajustar la búsqueda o tus respuestas.</p>
        <?php foreach ($gruposPorMarca as $claveMarca => $grupo): ?>
            <section class="brand-group" data-brand-group="<?= $h($claveMarca) ?>" aria-label="Computadoras <?= $h($grupo['marca']) ?>">
                <h3><?= $h($grupo['marca']) ?></h3>
                <div class="product-grid">
                    <?php foreach ($grupo['productos'] as $producto): ?>
                        <article id="producto-<?= (int) $producto['id'] ?>" class="product-card" data-product-category="<?= $h($producto['categoria']) ?>" data-product-price="<?= $h((string) $producto['precio']) ?>">
                            <div class="product-image">
                                <span>Imagen no disponible</span>
                                <?php
                                $imagenValida = is_string($producto['imagen'])
                                    && preg_match('~\Aassets/productos/[A-Za-z0-9_-]+\.(?:avif|gif|jpe?g|png|webp)\z~i', $producto['imagen']);
                                ?>
                                <?php if ($imagenValida): ?>
                                    <img src="<?= $h($producto['imagen']) ?>" alt="<?= $h($producto['nombre']) ?>" loading="lazy" onerror="this.remove()">
                                <?php endif; ?>
                            </div>
                            <h4><?= $h($producto['nombre']) ?></h4>
                            <p><?= $h($producto['descripcion']) ?></p>
                            <p class="product-price">$<?= number_format((float) $producto['precio'], 2) ?> MXN</p>
                            <p class="product-stock"><?= (int) $producto['existencias'] > 0 ? 'Disponible' : 'Agotado' ?></p>
                            <button type="button" class="product-preview-button" data-product-preview
                                data-product-id="<?= (int) $producto['id'] ?>"
                                data-product-name="<?= $h($producto['nombre']) ?>"
                                data-product-description="<?= $h($producto['descripcion']) ?>"
                                data-product-price="<?= $h((string) $producto['precio']) ?>"
                                data-product-brand="<?= $h($producto['marca']) ?>"
                                data-product-category="<?= $h($etiquetasCategorias[$producto['categoria']] ?? ucfirst(str_replace('-', ' ', $producto['categoria']))) ?>"
                                data-product-stock="<?= (int) $producto['existencias'] ?>"
                                data-product-image="<?= $h($imagenValida ? $producto['imagen'] : '') ?>">
                                Ver detalles
                            </button>
                            <?php if ((int) $producto['existencias'] > 0): ?>
                                <form action="index.php" method="post" class="cart-action">
                                    <input type="hidden" name="accion" value="agregar_carrito">
                                    <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                                    <input type="hidden" name="producto_id" value="<?= (int) $producto['id'] ?>">
                                    <button type="submit"><?= $logueado ? 'Agregar al carrito' : 'Inicia sesión y agrega' ?></button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>
