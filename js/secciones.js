document.querySelectorAll('[data-catalog-filter]').forEach((filtros) => {
    const catalogo = filtros.dataset.catalogFilter;
    const marca = filtros.querySelector('[data-filter-brand]');
    const categoria = filtros.querySelector('[data-filter-category]');
    const raizCatalogo = document.querySelector(`[data-catalog="${catalogo}"]`);
    const grupos = raizCatalogo.querySelectorAll('[data-brand-group]');
    const productos = [...raizCatalogo.querySelectorAll('[data-product-category]')];
    const busqueda = filtros.querySelector('[data-product-search]');
    const cuestionario = raizCatalogo.querySelector('[data-recommendation-quiz]');
    const resultados = raizCatalogo.querySelector('[data-recommendation-results]');
    const listaRecomendaciones = resultados?.querySelector('[data-recommendation-list]');
    const mensajeRecomendaciones = resultados?.querySelector('[data-recommendation-message]');
    const sinResultados = raizCatalogo.querySelector('[data-filter-empty]');
    let recomendacionesActivas = false;

    const normalizar = (texto) => texto
        .toLocaleLowerCase('es')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');

    const puntuar = (producto, uso, prioridad) => {
        const texto = normalizar(`${producto.innerText} ${producto.dataset.productCategory}`);
        const contieneAlguno = (terminos) => terminos.some((termino) => texto.includes(termino));
        const categoriaProducto = producto.dataset.productCategory;
        let puntaje = 0;

        if (uso === 'estudio') {
            puntaje += contieneAlguno(['oficina', 'workstation', 'thinkcentre', 'prodesk', 'pavilion', 'vivobook', 'inspiron']) ? 4 : 0;
            puntaje += contieneAlguno(['i5', 'ryzen 5', 'ssd']) ? 2 : 0;
        } else if (uso === 'gaming') {
            puntaje += contieneAlguno(['gamer', 'gaming', 'rtx', 'geforce', 'tarjeta grafica']) ? 5 : 0;
            puntaje += contieneAlguno(['ryzen 7', 'core i7', '32 gb', 'ddr5']) ? 2 : 0;
            puntaje += categoriaProducto === 'tarjeta-grafica' ? 6 : categoriaProducto === 'procesador' ? 3 : categoriaProducto === 'memoria' ? 2 : 0;
        } else if (uso === 'creacion') {
            puntaje += contieneAlguno(['workstation', 'rtx', 'geforce', 'core i7', 'ryzen 7']) ? 4 : 0;
            puntaje += contieneAlguno(['32 gb', 'ddr5', '1 tb']) ? 2 : 0;
            puntaje += categoriaProducto === 'workstation' ? 4 : categoriaProducto === 'tarjeta-grafica' || categoriaProducto === 'memoria' ? 2 : 0;
        } else if (uso === 'actualizar') {
            puntaje += contieneAlguno(['procesador', 'tarjeta grafica', 'memoria', 'ssd', 'fuente', 'almacenamiento']) ? 4 : 0;
            puntaje += categoriaProducto ? 1 : 0;
        }

        if (prioridad === 'potencia') {
            puntaje += contieneAlguno(['rtx', 'geforce', 'core i7', 'core 9', 'ryzen 7', '32 gb', 'ddr5']) ? 3 : 0;
            puntaje += categoriaProducto === 'tarjeta-grafica' ? 2 : categoriaProducto === 'procesador' ? 1 : 0;
        } else if (prioridad === 'almacenamiento') {
            puntaje += contieneAlguno(['1 tb', '1tb', 'nvme', 'ssd', '480 gb', '512 gb']) ? 3 : 0;
            puntaje += categoriaProducto === 'almacenamiento' ? 2 : 0;
        }

        return puntaje;
    };

    const filtrar = () => {
        const textoBusqueda = normalizar(busqueda?.value.trim() || '');
        const textoUso = cuestionario?.querySelector('[data-quiz-use]').value || '';
        const textoPrioridad = cuestionario?.querySelector('[data-quiz-priority]').value || '';
        const presupuesto = Number(cuestionario?.querySelector('[data-quiz-budget]').value || 0);
        const elegibles = productos.filter((producto) => {
            const coincideMarca = marca.value === 'todas'
                || producto.closest('[data-brand-group]').dataset.brandGroup === marca.value;
            const coincideCategoria = categoria.value === 'todas'
                || producto.dataset.productCategory === categoria.value;
            const coincideBusqueda = !textoBusqueda
                || normalizar(producto.innerText).includes(textoBusqueda);
            const coincidePresupuesto = !recomendacionesActivas
                || !presupuesto
                || Number(producto.dataset.productPrice) <= presupuesto;
            return coincideMarca && coincideCategoria && coincideBusqueda && coincidePresupuesto;
        });
        const clasificadas = recomendacionesActivas
            ? elegibles
                .map((producto, indice) => ({
                    producto,
                    indice,
                    puntaje: puntuar(producto, textoUso, textoPrioridad),
                    precio: Number(producto.dataset.productPrice),
                }))
                .sort((a, b) => b.puntaje - a.puntaje || a.precio - b.precio || a.indice - b.indice)
            : [];
        const recomendadas = new Set(clasificadas.slice(0, 3).map(({ producto }) => producto));
        let visibles = 0;

        grupos.forEach((grupo) => {
            const coincideMarca = marca.value === 'todas' || grupo.dataset.brandGroup === marca.value;
            grupo.querySelectorAll('[data-product-category]').forEach((producto) => {
                const mostrar = coincideMarca
                    && elegibles.includes(producto)
                    && (!recomendacionesActivas || recomendadas.has(producto));
                producto.hidden = !mostrar;
                producto.classList.toggle('recommended-product', mostrar && recomendacionesActivas);
                if (mostrar) visibles++;
            });
            grupo.hidden = !coincideMarca || ![...grupo.querySelectorAll('[data-product-category]')].some((producto) => !producto.hidden);
        });

        if (sinResultados) sinResultados.hidden = visibles > 0;
        if (resultados) resultados.hidden = !recomendacionesActivas;
        if (listaRecomendaciones) {
            listaRecomendaciones.replaceChildren();
            clasificadas.slice(0, 3).forEach(({ producto }) => {
                const elemento = document.createElement('li');
                const enlace = document.createElement('a');
                enlace.href = `#${producto.id}`;
                enlace.textContent = `${producto.querySelector('h4').textContent} — ${new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(producto.dataset.productPrice))}`;
                elemento.append(enlace);
                listaRecomendaciones.append(elemento);
            });
        }
        if (mensajeRecomendaciones) {
            mensajeRecomendaciones.textContent = visibles
                ? 'Mostramos hasta tres opciones que mejor coinciden con tus respuestas.'
                : 'No encontramos productos con esos criterios. Ajusta tu presupuesto, búsqueda o respuestas.';
        }
    };

    filtros.addEventListener('input', filtrar);
    filtros.addEventListener('change', filtrar);
    cuestionario?.addEventListener('submit', (evento) => {
        evento.preventDefault();
        recomendacionesActivas = true;
        filtrar();
    });
    cuestionario?.addEventListener('change', () => {
        if (recomendacionesActivas) filtrar();
    });
    cuestionario?.addEventListener('reset', () => {
        recomendacionesActivas = false;
        requestAnimationFrame(filtrar);
    });
    filtrar();
});

const menusNavegacion = document.querySelectorAll('.site-header details');
menusNavegacion.forEach((menu) => {
    menu.addEventListener('toggle', () => {
        if (menu.open) {
            menusNavegacion.forEach((otroMenu) => {
                if (otroMenu !== menu) otroMenu.open = false;
            });
        }

        const botonMenu = menu.querySelector('.menu-trigger');
        if (botonMenu) {
            botonMenu.setAttribute('aria-label', menu.open ? 'Cerrar menú de navegación' : 'Abrir menú de navegación');
        }
    });
});

document.addEventListener('click', (evento) => {
    menusNavegacion.forEach((menu) => {
        if (menu.open && !menu.contains(evento.target)) menu.open = false;
    });
});

/* ---- Switch de tema (toda la página) ---- */
const interruptorTema = document.querySelector('[data-theme-switch]');
if (interruptorTema) {
    const textoTema = interruptorTema.querySelector('[data-theme-text]');
    const sincronizar = () => {
        const claro = document.documentElement.dataset.tema === 'claro';
        interruptorTema.setAttribute('aria-checked', String(claro));
        if (textoTema) textoTema.textContent = claro ? 'CLARO' : 'OSCURO';
    };
    sincronizar();
    interruptorTema.addEventListener('click', () => {
        const nuevo = document.documentElement.dataset.tema === 'claro' ? 'oscuro' : 'claro';
        document.documentElement.dataset.tema = nuevo;
        try { localStorage.setItem('tema', nuevo); } catch (e) {}
        sincronizar();
    });
}
