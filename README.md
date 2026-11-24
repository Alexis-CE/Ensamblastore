# EnsamblaStore (form.mx)

Tienda de computadoras y componentes 

## Estructura
```
form.mx/
├── index.php            inicio, login/registro (modal), carrito y pedidos
├── seccion.php          catalogos, carrito, soporte y acerca de
├── admin.php            panel privado para administrar el catalogo
├── includes/conexion.php  conexion a la base de datos (base: dat)
├── includes/seo_head.php  títulos, metadatos sociales, canonical y GA4 opcional
├── 404.php              página de error personalizada
├── robots.txt           reglas de rastreo para buscadores
├── sitemap.xml          páginas públicas para indexación
├── favicon.svg          icono del sitio
├── database/
│   └── instalacion_completa.sql esquema, 30 productos y precios
├── secciones/           partes de cada pagina (hero, computadoras, componentes, carrito, soporte, acerca)
├── css/estilos.css
├── js/secciones.js
└── assets/
    ├── marcas/          logos SVG del slider
    ├── modelos/         modelo 3D del hero
    ├── seo/              imagen Open Graph
    └── productos/       fotos de productos (ver LEEME.txt)
```

