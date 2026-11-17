-- instalacion completa de EnsamblaStore: esquema y catalogo de 30 productos
-- no recrea la base de datos; evita duplicar productos y limpia registros del catalogo legado.

-- esquema

CREATE DATABASE IF NOT EXISTS dat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dat;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    contrasena_hash VARCHAR(255) NOT NULL,
    rol ENUM('cliente', 'admin') NOT NULL DEFAULT 'cliente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @usuarios_rol_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE usuarios ADD COLUMN rol ENUM(''cliente'', ''admin'') NOT NULL DEFAULT ''cliente'' AFTER contrasena_hash',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'rol'
);
PREPARE usuarios_rol_stmt FROM @usuarios_rol_sql;
EXECUTE usuarios_rol_stmt;
DEALLOCATE PREPARE usuarios_rol_stmt;

CREATE TABLE IF NOT EXISTS mensajes_contacto (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(100) NOT NULL,
    telefono VARCHAR(30) NULL,
    direccion VARCHAR(255) NULL,
    asunto VARCHAR(150) NOT NULL,
    mensaje TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('computadoras', 'componentes') NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    precio DECIMAL(10, 2) UNSIGNED NOT NULL,
    existencias INT UNSIGNED NOT NULL DEFAULT 0,
    marca VARCHAR(80) NOT NULL,
    categoria VARCHAR(60) NOT NULL,
    imagen VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_productos_catalogo (tipo, activo, marca, categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carritos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL UNIQUE,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_carritos_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS carrito_productos (
    carrito_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL DEFAULT 1,
    agregado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (carrito_id, producto_id),
    CONSTRAINT fk_carrito_productos_carrito FOREIGN KEY (carrito_id)
        REFERENCES carritos (id) ON DELETE CASCADE,
    CONSTRAINT fk_carrito_productos_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ordenes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    nombre_contacto VARCHAR(120) NOT NULL,
    correo VARCHAR(254) NOT NULL,
    telefono VARCHAR(30) NULL,
    direccion VARCHAR(255) NULL,
    total DECIMAL(12, 2) UNSIGNED NOT NULL,
    estado ENUM('pendiente', 'confirmado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ordenes_usuario_fecha (usuario_id, creado_en),
    CONSTRAINT fk_ordenes_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orden_productos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    orden_id INT UNSIGNED NOT NULL,
    producto_id INT UNSIGNED NULL,
    nombre_producto VARCHAR(150) NOT NULL,
    precio_unitario DECIMAL(10, 2) UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    subtotal DECIMAL(12, 2) UNSIGNED NOT NULL,
    CONSTRAINT fk_orden_productos_orden FOREIGN KEY (orden_id)
        REFERENCES ordenes (id) ON DELETE CASCADE,
    CONSTRAINT fk_orden_productos_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    

CREATE TABLE IF NOT EXISTS intentos_login (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    usuario VARCHAR(50) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_intentos_ip_fecha (ip, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10 productos iniciales

USE dat;

-- Precios publicados por Cyberpuerta, consultados el 2026-10-27.
-- Son precios de referencia del articulo, no incluyen envio y pueden cambiar.
-- existencias = 5 es stock de prueba; ajustalo al inventario real desde admin.php.
-- imagen queda NULL para agregar fotos propias o con licencia.

START TRANSACTION;

-- Laptop Acer ANV16-72-933F, Intel Core 9 270H, RTX 5070, 32 GB, SSD 1 TB.
-- Precio: $44,429.00 MXN. Envio publicado: $158.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computadoras/Laptops/Laptop-Gamer-Acer-ANV16-72-933F-16-1920x1200-WUXGA-Intel-Core-9-270H-NVIDIA-GeForce-RTX-5070-32GB-1TB-SSD-Windows-11-Home-Ingles.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'computadoras', 'Laptop Gamer Acer ANV16-72-933F', 'Pantalla WUXGA de 16 pulgadas, Intel Core 9 270H, NVIDIA GeForce RTX 5070, 32 GB de RAM, SSD de 1 TB y Windows 11 Home. Teclado en ingles.', 44429.00, 5, 'Acer', 'laptop', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Laptop Gamer Acer ANV16-72-933F');

-- Laptop Acer Nitro V 15, Intel Core i5-13420H, RTX 5050, 32 GB, SSD 512 GB.
-- Precio: $26,239.00 MXN. Envio publicado: $158.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computadoras/Laptops/Laptop-Gamer-Acer-Nitro-V-15-15-6-1920x1080-Full-HD-Intel-Core-i5-13420H-NVIDIA-GeForce-RTX-5050-32GB-512GB-SSD-Windows-11-Home-Ingles-2.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'computadoras', 'Laptop Gamer Acer Nitro V 15 RTX 5050', 'Pantalla Full HD de 15.6 pulgadas, Intel Core i5-13420H, NVIDIA GeForce RTX 5050, 32 GB de RAM, SSD de 512 GB y Windows 11 Home. Teclado en ingles.', 26239.00, 5, 'Acer', 'laptop', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Laptop Gamer Acer Nitro V 15 RTX 5050');

-- Laptop Acer Nitro V 16, Ryzen AI 7 350, RTX 5060, 16 GB, SSD 1 TB.
-- Precio: $32,349.00 MXN. Envio publicado: $158.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computadoras/Laptops/Laptop-Gamer-Acer-IA-Nitro-V16-16-1920x1200-AMD-Ryzen-AI-7-350-NVIDIA-GeForce-RTX-5060-16GB-1TB-SSD-Windows-11-Home-Ingles.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'computadoras', 'Laptop Gamer Acer Nitro V 16 RTX 5060', 'Pantalla WUXGA de 16 pulgadas, AMD Ryzen AI 7 350, NVIDIA GeForce RTX 5060, 16 GB de RAM, SSD de 1 TB y Windows 11 Home. Teclado en ingles.', 32349.00, 5, 'Acer', 'laptop', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Laptop Gamer Acer Nitro V 16 RTX 5060');

-- Laptop Acer Nitro V 15, Intel Core i9-13900H, RTX 5060, 16 GB, SSD 512 GB.
-- Precio: $30,139.00 MXN. Envio publicado: $158.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computadoras/Laptops/Laptop-Gamer-Acer-Nitro-V-15-15-6-1920x1080-Full-HD-Intel-Core-i9-13900H-NVIDIA-GeForce-RTX-5060-16GB-512GB-SSD-Windows-11-Pro-Ingles.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'computadoras', 'Laptop Gamer Acer Nitro V 15 RTX 5060', 'Pantalla Full HD de 15.6 pulgadas, Intel Core i9-13900H, NVIDIA GeForce RTX 5060, 16 GB de RAM, SSD de 512 GB y Windows 11 Pro. Teclado en ingles.', 30139.00, 5, 'Acer', 'laptop', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Laptop Gamer Acer Nitro V 15 RTX 5060');

-- Laptop Acer Predator Helios Neo 16S, Core Ultra 9 275HX, RTX 5070 Ti, 32 GB, SSD 1 TB.
-- Precio: $57,809.00 MXN. Envio publicado: $158.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computadoras/Laptops/Laptop-Gamer-Acer-Predator-Helios-Neo-16S-16-2560x1600-Intel-Core-Ultra-9-275HX-NVIDIA-GeForce-RTX-5070-Ti-32GB-1TB-SSD-Windows-11-Home-Ingles.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'computadoras', 'Laptop Gamer Acer Predator Helios Neo 16S', 'Pantalla de 16 pulgadas 2560 x 1600, Intel Core Ultra 9 275HX, NVIDIA GeForce RTX 5070 Ti, 32 GB de RAM, SSD de 1 TB y Windows 11 Home. Teclado en ingles.', 57809.00, 5, 'Acer', 'laptop', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Laptop Gamer Acer Predator Helios Neo 16S');

-- Procesador AMD Ryzen 5 5500, socket AM4, 6 nucleos, hasta 4.4 GHz, cooler incluido.
-- Precio: $1,789.00 MXN. Envio publicado: $138.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computo-Hardware/Componentes/Procesadores/Procesadores-para-PC/Procesador-AMD-Ryzen-5-5500-Socket-AM4-4-40GHz-6-Nucleos-16MB-Cache-Incluye-Disipador-3.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'componentes', 'Procesador AMD Ryzen 5 5500', 'Socket AM4, 6 nucleos, frecuencia de hasta 4.4 GHz, cache de 16 MB e incluye disipador.', 1789.00, 5, 'AMD', 'procesador', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Procesador AMD Ryzen 5 5500');

-- Procesador AMD Ryzen 5 5600GT, socket AM4, 6 nucleos, hasta 4.6 GHz, cooler incluido.
-- Precio: $3,359.00 MXN. Envio publicado: $138.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computo-Hardware/Componentes/Procesadores/Procesadores-para-PC/Procesador-AMD-Ryzen-5-5600GT-con-AMD-Radeon-Graphics-Socket-AM4-4-60GHz-6-Nucleos-16MB-Cache-Incluye-Disipador.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'componentes', 'Procesador AMD Ryzen 5 5600GT', 'Socket AM4, 6 nucleos, frecuencia de hasta 4.6 GHz, graficos AMD Radeon, cache de 16 MB e incluye disipador.', 3359.00, 5, 'AMD', 'procesador', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Procesador AMD Ryzen 5 5600GT');

-- Procesador AMD Ryzen 7 5700, socket AM4, 8 nucleos, hasta 4.6 GHz, cooler incluido.
-- Precio: $2,689.00 MXN. Envio publicado: $138.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computo-Hardware/Componentes/Procesadores/Procesadores-para-PC/Procesador-AMD-Ryzen-7-5700-Socket-AM4-4-60GHz-8-Nucleos-16MB-Cache-Incluye-Disipador.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'componentes', 'Procesador AMD Ryzen 7 5700', 'Socket AM4, 8 nucleos, frecuencia de hasta 4.6 GHz, cache de 16 MB e incluye disipador.', 2689.00, 5, 'AMD', 'procesador', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Procesador AMD Ryzen 7 5700');

-- Procesador AMD Ryzen 5 8500G, socket AM5, 6 nucleos, hasta 5 GHz, cooler incluido.
-- Precio: $2,619.00 MXN. Envio publicado: $138.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computo-Hardware/Componentes/Procesadores/Procesadores-para-PC/Procesador-AMD-Ryzen-5-8500G-con-AMD-Radeon-Graphics-Socket-AM5-5GHz-6-Nucleos-16MB-Cache-Incluye-Disipador.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'componentes', 'Procesador AMD Ryzen 5 8500G', 'Socket AM5, 6 nucleos, frecuencia de hasta 5 GHz, graficos AMD Radeon, cache de 16 MB e incluye disipador.', 2619.00, 5, 'AMD', 'procesador', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Procesador AMD Ryzen 5 8500G');

-- Procesador AMD Ryzen 7 8700G, socket AM5, 8 nucleos, hasta 5.1 GHz, cooler incluido.
-- Precio: $4,499.00 MXN. Envio publicado: $128.00 MXN.
-- Fuente: https://www.cyberpuerta.mx/Computo-Hardware/Componentes/Procesadores/Procesadores-para-PC/Procesador-AMD-Ryzen-7-8700G-con-AMD-Radeon-Graphics-Socket-AM5-5-10GHz-8-Nucleos-16MB-Cache-Incluye-Disipador-2.html
INSERT INTO productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
SELECT 'componentes', 'Procesador AMD Ryzen 7 8700G', 'Socket AM5, 8 nucleos, frecuencia de hasta 5.1 GHz, graficos AMD Radeon, cache de 16 MB e incluye disipador.', 4499.00, 5, 'AMD', 'procesador', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'Procesador AMD Ryzen 7 8700G');

COMMIT;


-- 20 productos adicionales

use dat;

-- catalogo ampliado: 10 computadoras y 10 componentes nuevos.
-- precios aproximados de referencia, sin verificar contra tiendas.
-- la configuracion de referencia se ajusta a la version real que se vende en mexico.
-- existencias = 5 es stock de prueba para poder probar carrito y pedidos; ajustar al inventario real.
-- imagen: ya apunta a assets/productos/<archivo>. si el archivo no existe, la tarjeta muestra "Imagen no disponible".

start transaction;

-- ---------- computadoras ----------

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Laptop ASUS Vivobook 15 Core i5', 'pantalla full hd de 15.6 pulgadas, intel core i5-1235u, 16 gb de ram, ssd de 512 gb y windows 11 home.', 11990.00, 5, 'ASUS', 'laptop', 'assets/productos/asus-vivobook-15-i5.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Laptop ASUS Vivobook 15 Core i5');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Laptop Gamer ASUS TUF Gaming A15 RTX 4060', 'pantalla full hd de 15.6 pulgadas a 144 hz, amd ryzen 7 7735hs, nvidia geforce rtx 4060, 16 gb de ram y ssd de 512 gb.', 25990.00, 5, 'ASUS', 'laptop', 'assets/productos/asus-tuf-gaming-a15-rtx4060.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Laptop Gamer ASUS TUF Gaming A15 RTX 4060');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Laptop Dell Inspiron 15 Core i5', 'pantalla full hd de 15.6 pulgadas, intel core i5-1235u, 8 gb de ram, ssd de 512 gb y windows 11 home.', 12490.00, 5, 'Dell', 'laptop', 'assets/productos/dell-inspiron-15-i5.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Laptop Dell Inspiron 15 Core i5');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Workstation Dell Precision 3680 Tower', 'torre de trabajo con intel core i7-14700, 32 gb de ram, ssd de 1 tb y nvidia rtx a2000 de 12 gb, para diseño, ingeniería y renderizado.', 52990.00, 5, 'Dell', 'workstation', 'assets/productos/dell-precision-3680-tower.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Workstation Dell Precision 3680 Tower');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Computadora de escritorio HP Pavilion TP01 Ryzen 5', 'amd ryzen 5 5600g con gráficos radeon, 16 gb de ram, ssd de 512 gb y windows 11 home.', 13990.00, 5, 'HP', 'escritorio', 'assets/productos/hp-pavilion-tp01-ryzen5.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Computadora de escritorio HP Pavilion TP01 Ryzen 5');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Laptop Gamer Lenovo LOQ 15 RTX 3050', 'pantalla full hd de 15.6 pulgadas, intel core i5-12450hx, nvidia geforce rtx 3050 de 6 gb, 16 gb de ram y ssd de 512 gb.', 19990.00, 5, 'Lenovo', 'laptop', 'assets/productos/lenovo-loq-15-rtx3050.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Laptop Gamer Lenovo LOQ 15 RTX 3050');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Laptop Gamer MSI Thin 15 RTX 3050', 'pantalla full hd de 15.6 pulgadas a 144 hz, intel core i5-12450h, nvidia geforce rtx 3050 de 4 gb, 16 gb de ram y ssd de 512 gb.', 18990.00, 5, 'MSI', 'laptop', 'assets/productos/msi-thin-15-rtx3050.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Laptop Gamer MSI Thin 15 RTX 3050');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Computadora de escritorio Lenovo ThinkCentre M70s Gen 5', 'equipo de trabajo compacto con intel core i5-14400, 16 gb de ram, ssd de 512 gb y windows 11 pro.', 15990.00, 5, 'Lenovo', 'escritorio', 'assets/productos/lenovo-thinkcentre-m70s-gen5.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Computadora de escritorio Lenovo ThinkCentre M70s Gen 5');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Computadora de escritorio HP ProDesk 400 G9', 'equipo de oficina de formato compacto con intel core i5-12500, 16 gb de ram, ssd de 512 gb y windows 11 pro.', 14990.00, 5, 'HP', 'escritorio', 'assets/productos/hp-prodesk-400-g9.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Computadora de escritorio HP ProDesk 400 G9');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'computadoras', 'Workstation Lenovo ThinkStation P3 Tower', 'torre de trabajo con intel core i7-13700, 32 gb de ram, ssd de 1 tb y nvidia rtx a2000 de 12 gb, para diseño, ingeniería y renderizado.', 39990.00, 5, 'Lenovo', 'workstation', 'assets/productos/lenovo-thinkstation-p3-tower.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Workstation Lenovo ThinkStation P3 Tower');

-- ---------- componentes ----------

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'Procesador Intel Core i5-12400F', 'socket lga1700, 6 núcleos y 12 hilos, frecuencia de hasta 4.4 ghz, cache de 18 mb e incluye disipador. no tiene gráficos integrados.', 2390.00, 5, 'Intel', 'procesador', 'assets/productos/intel-core-i5-12400f.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Procesador Intel Core i5-12400F');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'Procesador Intel Core i7-14700K', 'socket lga1700, 20 núcleos (8 de rendimiento y 12 eficientes) y 28 hilos, frecuencia de hasta 5.6 ghz, gráficos intel uhd 770. no incluye disipador.', 9490.00, 5, 'Intel', 'procesador', 'assets/productos/intel-core-i7-14700k.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Procesador Intel Core i7-14700K');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'Tarjeta grafica ASUS Dual GeForce RTX 4060 8GB', 'nvidia geforce rtx 4060 con 8 gb de memoria gddr6, doble ventilador y conexión pcie 4.0.', 7490.00, 5, 'ASUS', 'tarjeta-grafica', 'assets/productos/asus-dual-rtx-4060-8gb.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Tarjeta grafica ASUS Dual GeForce RTX 4060 8GB');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'Tarjeta grafica MSI GeForce RTX 4060 Ventus 2X Black 8G OC', 'nvidia geforce rtx 4060 con 8 gb de memoria gddr6, doble ventilador y conexión pcie 4.0.', 7290.00, 5, 'MSI', 'tarjeta-grafica', 'assets/productos/msi-rtx-4060-ventus-2x-black-8g.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Tarjeta grafica MSI GeForce RTX 4060 Ventus 2X Black 8G OC');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'Memoria RAM Corsair Vengeance LPX 16GB (2x8GB) DDR4 3200', 'kit de 2 módulos de 8 gb ddr4 a 3200 mhz, latencia cl16 y disipador de aluminio.', 949.00, 5, 'Corsair', 'memoria', 'assets/productos/corsair-vengeance-lpx-16gb-ddr4.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Memoria RAM Corsair Vengeance LPX 16GB (2x8GB) DDR4 3200');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'Memoria RAM Corsair Vengeance 32GB (2x16GB) DDR5 6000', 'kit de 2 módulos de 16 gb ddr5 a 6000 mhz con perfil de overclock xmp.', 2890.00, 5, 'Corsair', 'memoria', 'assets/productos/corsair-vengeance-32gb-ddr5-6000.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Memoria RAM Corsair Vengeance 32GB (2x16GB) DDR5 6000');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'Memoria RAM Kingston Fury Beast 16GB DDR4 3200', 'módulo de 16 gb ddr4 a 3200 mhz, latencia cl16 y disipador.', 849.00, 5, 'Kingston', 'memoria', 'assets/productos/kingston-fury-beast-16gb-ddr4.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Memoria RAM Kingston Fury Beast 16GB DDR4 3200');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'SSD Kingston NV2 1TB NVMe PCIe 4.0', 'unidad m.2 nvme de 1 tb con velocidad de lectura de hasta 3,500 mb/s.', 1490.00, 5, 'Kingston', 'almacenamiento', 'assets/productos/kingston-nv2-1tb-nvme.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'SSD Kingston NV2 1TB NVMe PCIe 4.0');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'SSD Kingston A400 480GB SATA', 'unidad ssd de 480 gb en formato de 2.5 pulgadas con interfaz sata iii y lectura de hasta 500 mb/s.', 799.00, 5, 'Kingston', 'almacenamiento', 'assets/productos/kingston-a400-480gb-sata.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'SSD Kingston A400 480GB SATA');

insert into productos (tipo, nombre, descripcion, precio, existencias, marca, categoria, imagen, activo)
select 'componentes', 'Fuente de poder Corsair RM750e 750W 80 Plus Gold', 'fuente atx totalmente modular de 750 w con certificación 80 plus gold y conector para tarjetas gráficas modernas.', 2290.00, 5, 'Corsair', 'fuente', 'assets/productos/corsair-rm750e-750w.jpg', 1
from dual where not exists (select 1 from productos where nombre = 'Fuente de poder Corsair RM750e 750W 80 Plus Gold');

-- ---------- imagenes de los 10 productos originales ----------

update productos set imagen = 'assets/productos/acer-anv16-72-933f.jpg' where nombre = 'Laptop Gamer Acer ANV16-72-933F' and imagen is null;
update productos set imagen = 'assets/productos/acer-nitro-v15-rtx5050.jpg' where nombre = 'Laptop Gamer Acer Nitro V 15 RTX 5050' and imagen is null;
update productos set imagen = 'assets/productos/acer-nitro-v16-rtx5060.jpg' where nombre = 'Laptop Gamer Acer Nitro V 16 RTX 5060' and imagen is null;
update productos set imagen = 'assets/productos/acer-nitro-v15-rtx5060.jpg' where nombre = 'Laptop Gamer Acer Nitro V 15 RTX 5060' and imagen is null;
update productos set imagen = 'assets/productos/acer-predator-helios-neo-16s.jpg' where nombre = 'Laptop Gamer Acer Predator Helios Neo 16S' and imagen is null;
update productos set imagen = 'assets/productos/amd-ryzen-5-5500.jpg' where nombre = 'Procesador AMD Ryzen 5 5500' and imagen is null;
update productos set imagen = 'assets/productos/amd-ryzen-5-5600gt.jpg' where nombre = 'Procesador AMD Ryzen 5 5600GT' and imagen is null;
update productos set imagen = 'assets/productos/amd-ryzen-7-5700.jpg' where nombre = 'Procesador AMD Ryzen 7 5700' and imagen is null;
update productos set imagen = 'assets/productos/amd-ryzen-5-8500g.jpg' where nombre = 'Procesador AMD Ryzen 5 8500G' and imagen is null;
update productos set imagen = 'assets/productos/amd-ryzen-7-8700g.jpg' where nombre = 'Procesador AMD Ryzen 7 8700G' and imagen is null;

commit;


-- los precios de los 20 productos adicionales son referencias aproximadas (sin verificar contra tiendas).
-- confirmalos y ajustalos desde admin.php antes de publicar.
