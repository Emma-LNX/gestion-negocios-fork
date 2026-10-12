<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventario - Gestión de negocios</title>
    <link rel="stylesheet" href="<?= html_escape(base_url('assets/app.css')) ?>">
</head>

<body>
    <header class="cabecera">
        <div>
            <strong><?= html_escape($usuario->empresaNombre()) ?></strong>
            <p>Gestión de negocios</p>
        </div>

        <span>
            Usuario: <?= html_escape($usuario->nombre()) ?>
            (<?= html_escape($usuario->rolNombre()) ?>)
        </span>
    </header>

    <div class="estructura">

        <nav class="menu" aria-label="Menú principal">

            <a href="<?= html_escape(site_url('inicio')) ?>">
                Inicio
            </a>

            <?php if ($usuario->puede('productos.ver')): ?>

                <a href="<?= html_escape(site_url('productos')) ?>">
                    Productos de prueba
                </a>

            <?php endif; ?>


            <?php if ($usuario->puede('inventario.ver')): ?>

                <a
                    class="seleccionado"
                    href="<?= html_escape(site_url('inventario')) ?>"
                    aria-current="page">
                    Inventario
                </a>

            <?php endif; ?>


            <?php if ($usuario->puede('pedidos.ver')): ?>

                <a href="<?= html_escape(site_url('pedidos')) ?>">
                    Pedidos
                </a>

            <?php endif; ?>


            <?= form_open('salir') ?>

            <button
                type="submit"
                class="boton-salir">
                Cerrar sesión
            </button>

            <?= form_close() ?>

        </nav>

        <main class="contenido">

            <h1>Inventario</h1>

            <!-- Resumen general del inventario -->
            <div class="resumen-inventario">

                <div class="resumen-tarjeta">
                    <span>Total de insumos</span>

                    <strong>
                        <?= (int) $resumen['total'] ?>
                    </strong>
                </div>

                <div class="resumen-tarjeta">
                    <span>Stock normal</span>

                    <strong class="resumen-normal">
                        <?= (int) $resumen['normales'] ?>
                    </strong>
                </div>

                <div class="resumen-tarjeta">
                    <span>Stock bajo</span>

                    <strong class="resumen-bajo">
                        <?= (int) $resumen['bajos'] ?>
                    </strong>
                </div>

            </div>

            <!-- Búsqueda y filtros del inventario -->
            <form action="<?= html_escape(site_url('inventario')) ?>" method="get" class="filtros-inventario">
                <input
                    type="search"
                    id="busqueda"
                    name="busqueda"
                    maxlength="150"
                    placeholder="Buscar insumo..."
                    aria-label="Buscar insumo"
                    value="<?= html_escape($filtros['busqueda']) ?>">

                <select
                    id="estado"
                    name="estado"
                    aria-label="Filtrar por estado">
                    <option value="">Todos los estados</option>

                    <option
                        value="bajo"
                        <?= $filtros['estado'] === 'bajo'
                            ? 'selected' : '' ?>>
                        Stock bajo
                    </option>

                    <option
                        value="normal"
                        <?= $filtros['estado'] === 'normal'
                            ? 'selected' : '' ?>>
                        Stock normal
                    </option>
                </select>

                <select
                    id="unidad"
                    name="unidad"
                    aria-label="Filtrar por unidad">
                    <option value="">Todas las unidades</option>

                    <?php
                    $unidades = array(
                        'unidad' => 'Unidad',
                        'kg' => 'Kilogramos',
                        'g' => 'Gramos',
                        'mg' => 'Miligramos',
                        'l' => 'Litros',
                        'ml' => 'Mililitros'
                    );
                    ?>

                    <?php foreach ($unidades as $valor => $nombre): ?>

                        <option
                            value="<?= html_escape($valor) ?>"
                            <?= $filtros['unidad'] === $valor
                                ? 'selected' : '' ?>>
                            <?= html_escape($nombre) ?>
                        </option>

                    <?php endforeach; ?>
                </select>

                <select
                    id="orden"
                    name="orden"
                    aria-label="Ordenar inventario">
                    <option
                        value="nombre_asc"
                        <?= $filtros['orden'] === 'nombre_asc'
                            ? 'selected' : '' ?>>
                        Nombre A-Z
                    </option>

                    <option
                        value="nombre_desc"
                        <?= $filtros['orden'] === 'nombre_desc'
                            ? 'selected' : '' ?>>
                        Nombre Z-A
                    </option>

                    <option
                        value="cantidad_asc"
                        <?= $filtros['orden'] === 'cantidad_asc'
                            ? 'selected' : '' ?>>
                        Menor cantidad
                    </option>

                    <option
                        value="cantidad_desc"
                        <?= $filtros['orden'] === 'cantidad_desc'
                            ? 'selected' : '' ?>>
                        Mayor cantidad
                    </option>

                    <option
                        value="minimo_asc"
                        <?= $filtros['orden'] === 'minimo_asc'
                            ? 'selected' : '' ?>>
                        Menor stock mínimo
                    </option>

                    <option
                        value="minimo_desc"
                        <?= $filtros['orden'] === 'minimo_desc'
                            ? 'selected' : '' ?>>
                        Mayor stock mínimo
                    </option>

                    <option
                        value="stock_bajo"
                        <?= $filtros['orden'] === 'stock_bajo'
                            ? 'selected' : '' ?>>
                        Stock bajo primero
                    </option>
                </select>

                <button type="submit">
                    Filtrar
                </button>

                <a
                    href="<?= html_escape(site_url('inventario')) ?>"
                    class="limpiar-filtros">
                    Limpiar
                </a>

            </form>

            <?php if ($usuario->puede('inventario.gestionar')): ?>
                <p>
                    <a href="<?= html_escape(site_url('inventario/crear')) ?>">
                        Agregar insumo
                    </a>
                </p>
            <?php endif; ?>

            <div class="tabla">

                <table class="tabla-inventario">
                    <colgroup>
                        <col style="width: 32%;">
                        <col style="width: 15%;">
                        <col style="width: 15%;">
                        <col style="width: 10%;">
                        <col style="width: 16%;">
                        <col style="width: 12%;">
                    </colgroup>

                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Cantidad actual</th>
                            <th>Stock mínimo</th>
                            <th>Unidad</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($insumos as $item): ?>

                            <tr>
                                <td>
                                    <?= html_escape($item['nombre']) ?>
                                </td>

                                <td>
                                    <?= html_escape($item['cantidad_actual']) ?>
                                </td>

                                <td>
                                    <?= html_escape($item['stock_minimo']) ?>
                                </td>

                                <td>
                                    <?= html_escape($item['unidad_medida']) ?>
                                </td>

                                <?php $cantidadActual = (float) $item['cantidad_actual'];
                                $stockMinimo = (float) $item['stock_minimo'];
                                $stockBajo = $cantidadActual <= $stockMinimo;
                                ?>

                                <td>
                                    <?php if ($stockBajo): ?>

                                        <span class="estado-stock estado-bajo">
                                            Stock bajo
                                        </span>

                                    <?php else: ?>

                                        <span class="estado-stock estado-normal">
                                            Normal
                                        </span>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($usuario->puede('inventario.gestionar')): ?>

                                        <a href="<?= html_escape(
                                                        site_url(
                                                            'inventario/' .
                                                                $item['id'] .
                                                                '/editar'
                                                        )
                                                    ) ?>">
                                            Editar
                                        </a>

                                    <?php else: ?>

                                        Solo consulta

                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                        <?php if (empty($insumos)): ?>
                            <tr>
                                <td colspan="6">
                                    <?php if (
                                        $filtros['busqueda'] !== '' ||
                                        $filtros['estado'] !== '' ||
                                        $filtros['unidad'] !== ''
                                    ): ?>

                                        No se encontraron insumos que coincidan
                                        con los filtros seleccionados.

                                    <?php else: ?>

                                        Todavía no hay insumos registrados.

                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </main>

    </div>

    <footer>
        Proyecto de gestión de negocios
    </footer>

</body>

</html>