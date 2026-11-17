<?php
$seoBaseUrl = 'https://tudominio.com';
$seoPages = [
    'inicio' => [
        'title' => 'Computadoras y componentes para armar tu PC | EnsamblaStore',
        'description' => 'Compra computadoras y componentes, arma tu PC a medida y encuentra tecnología para trabajar, crear o jugar en EnsamblaStore.',
        'path' => '/',
        'indexable' => true,
    ],
    'computadoras' => [
        'title' => 'Computadoras y laptops para trabajo y gaming | EnsamblaStore',
        'description' => 'Explora laptops, computadoras de escritorio y workstations para estudiar, trabajar, crear contenido o jugar.',
        'path' => '/seccion.php?vista=computadoras',
        'indexable' => true,
    ],
    'componentes' => [
        'title' => 'Componentes para armar y mejorar tu PC | EnsamblaStore',
        'description' => 'Encuentra procesadores, tarjetas gráficas, memoria RAM, SSD y otros componentes para actualizar tu computadora.',
        'path' => '/seccion.php?vista=componentes',
        'indexable' => true,
    ],
    'acerca' => [
        'title' => 'Conoce EnsamblaStore | Tecnología a tu medida',
        'description' => 'Conoce al equipo de EnsamblaStore y nuestra propuesta de computadoras, componentes y tecnología a tu medida.',
        'path' => '/seccion.php?vista=acerca',
        'indexable' => true,
    ],
    'soporte' => [
        'title' => 'Soporte técnico para computadoras | EnsamblaStore',
        'description' => 'Solicita diagnóstico, reparación o asesoría técnica para tu computadora con el equipo de EnsamblaStore.',
        'path' => '/seccion.php?vista=soporte',
        'indexable' => true,
    ],
    'carrito' => [
        'title' => 'Mi carrito | EnsamblaStore',
        'description' => 'Revisa los productos de tu carrito de EnsamblaStore.',
        'path' => '/seccion.php?vista=carrito',
        'indexable' => false,
    ],
    'privacidad' => [
        'title' => 'Aviso de privacidad | EnsamblaStore',
        'description' => 'Consulta cómo EnsamblaStore trata los datos de cuenta, contacto y pedidos en este proyecto escolar de demostración.',
        'path' => '/privacidad',
        'indexable' => true,
    ],
    'gracias' => [
        'title' => 'Gracias por contactarnos | EnsamblaStore',
        'description' => 'Recibimos tu mensaje. El equipo de EnsamblaStore responderá dentro del plazo de atención indicado.',
        'path' => '/gracias',
        'indexable' => false,
    ],
];

$seoKey = isset($seoKey, $seoPages[$seoKey]) ? $seoKey : 'inicio';
$seoPage = $seoPages[$seoKey];
$seoTitle = $seoPage['title'];
$seoDescription = $seoPage['description'];
$seoCanonical = $seoBaseUrl . $seoPage['path'];
$seoImage = $seoBaseUrl . '/assets/seo/ensamblastore-og.svg';
$seoRobots = $seoPage['indexable'] ? 'index, follow' : 'noindex, nofollow';
$gaMeasurementId = 'G-XXXXXXXXXX';
?>
<title><?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
<meta name="robots" content="<?= $seoRobots ?>">
<link rel="canonical" href="<?= htmlspecialchars($seoCanonical, ENT_QUOTES, 'UTF-8') ?>">
<link rel="sitemap" type="application/xml" href="<?= $seoBaseUrl ?>/sitemap.xml">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta property="og:type" content="website">
<meta property="og:site_name" content="EnsamblaStore">
<meta property="og:locale" content="es_MX">
<meta property="og:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:url" content="<?= htmlspecialchars($seoCanonical, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:image" content="<?= htmlspecialchars($seoImage, ENT_QUOTES, 'UTF-8') ?>">
<meta property="og:image:type" content="image/svg+xml">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="EnsamblaStore: computadoras y componentes para armar tu PC">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($seoTitle, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($seoDescription, ENT_QUOTES, 'UTF-8') ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($seoImage, ENT_QUOTES, 'UTF-8') ?>">
<?php if (preg_match('/\AG-[A-Z0-9]+\z/', $gaMeasurementId) && $gaMeasurementId !== 'G-XXXXXXXXXX'): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaMeasurementId, ENT_QUOTES, 'UTF-8') ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?= htmlspecialchars($gaMeasurementId, ENT_QUOTES, 'UTF-8') ?>');
    </script>
<?php endif; ?>
<?php if ($seoKey === 'inicio'): ?>
    <script type="application/ld+json"><?= json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        '@id' => $seoBaseUrl . '/#organization',
        'name' => 'EnsamblaStore',
        'url' => $seoBaseUrl . '/',
        'logo' => $seoImage,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
