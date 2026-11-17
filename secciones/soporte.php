<section class="contact-section" id="soporte" aria-labelledby="support-title">
    <div class="support-intro">
        <p class="eyebrow"><span></span> SERVICIO TÉCNICO</p>
        <h1 id="support-title">Soporte técnico</h1>
        <p>¿Necesitas un diagnóstico, reparación o asesoría? Cuéntanos qué sucede con tu equipo.</p>
        <p class="response-commitment"><strong>Respondemos en menos de 24 horas hábiles.</strong> No laboramos los domingos. Atención exclusivamente en línea.</p>
    </div>

    <?php if ($logueado): ?>
        <div class="form-panel form-panel-wide" id="contacto">
            <header class="page-header">
                <p class="eyebrow"><span></span> HABLEMOS</p>
                <h3>Formulario de contacto</h3>
                <p class="intro">Hola, <strong><?= $h($_SESSION['usuario']) ?></strong>. Cuéntanos qué equipo o servicio necesitas.</p>
            </header>

            <?php if (isset($_GET['error'])): ?>
                <p class="notice error" role="alert">Revisa los datos: completa nombre, asunto y mensaje, y usa un correo válido. Los textos no pueden ser demasiado largos.</p>
            <?php endif; ?>

            <form action="index.php#soporte" method="post">
                <input type="hidden" name="accion" value="contacto">
                <input type="hidden" name="csrf_token" value="<?= $h($csrfToken) ?>">
                <div class="form-grid">
                    <div class="field">
                        <label for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" maxlength="100" required>
                    </div>
                    <div class="field">
                        <label for="email">Correo electrónico</label>
                        <input type="email" id="email" name="correo" maxlength="100" required>
                    </div>
                    <div class="field">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" maxlength="30">
                    </div>
                    <div class="field">
                        <label for="direccion">Dirección</label>
                        <input type="text" id="direccion" name="direccion" maxlength="255" autocomplete="street-address">
                    </div>
                    <div class="field field-full">
                        <label for="asunto">Asunto</label>
                        <input type="text" id="asunto" name="asunto" maxlength="150" required>
                    </div>
                    <div class="field field-full">
                        <label for="mensaje">Mensaje</label>
                        <textarea id="mensaje" name="mensaje" rows="5" maxlength="5000" required></textarea>
                    </div>
                </div>
                <button type="submit">Enviar mensaje</button>
            </form>
        </div>
    <?php else: ?>
        <div class="support-login">
            <p>Inicia sesión para enviar tu solicitud de soporte.</p>
            <a class="button button-light" href="index.php?login=1">INICIAR SESIÓN <span aria-hidden="true">↗</span></a>
        </div>
    <?php endif; ?>
</section>
