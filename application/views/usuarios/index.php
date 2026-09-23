<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Usuarios - Gestión de negocios</title><link rel="stylesheet" href="<?= html_escape(base_url('assets/app.css')) ?>"></head>
<body><header class="cabecera"><strong><?= html_escape($usuario->empresaNombre()) ?></strong><span><?= html_escape($usuario->nombre()) ?> (<?= html_escape($usuario->rolNombre()) ?>)</span></header>
<main class="pagina-prueba"><a href="<?= html_escape(site_url('inicio')) ?>">Volver al inicio</a>
<div class="titulo-acciones"><div><h1>Usuarios</h1><p>Estas cuentas pertenecen únicamente a tu empresa.</p></div>
<?php if ($usuario->puede('usuarios.gestionar')): ?><a class="boton-enlace" href="<?= html_escape(site_url('usuarios/crear')) ?>">Crear usuario</a><?php endif; ?></div>
<div class="tabla"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach ($usuarios as $cuenta): ?><tr>
<td><?= html_escape($cuenta['nombre']) ?><?= (int)$cuenta['id'] === $usuario->id() ? ' (tú)' : '' ?></td>
<td><?= html_escape($cuenta['correo']) ?></td><td><?= html_escape($cuenta['rol_nombre']) ?></td>
<td><span class="estado <?= $cuenta['activo'] ? 'activo' : 'inactivo' ?>"><?= $cuenta['activo'] ? 'Activo' : 'Inactivo' ?></span></td>
<td class="acciones"><?php if ($usuario->puede('usuarios.gestionar')): ?><a href="<?= html_escape(site_url('usuarios/'.$cuenta['id'].'/editar')) ?>">Editar</a>
<?php if ((int)$cuenta['id'] !== $usuario->id()): ?><?= form_open('usuarios/'.$cuenta['id'].'/estado') ?><button type="submit" class="boton-secundario"><?= $cuenta['activo'] ? 'Desactivar' : 'Activar' ?></button><?= form_close() ?><?php endif; ?><?php else: ?>Solo lectura<?php endif; ?></td>
</tr><?php endforeach; ?>
<?php if (!$usuarios): ?><tr><td colspan="5">No hay usuarios registrados.</td></tr><?php endif; ?>
</tbody></table></div></main></body></html>
