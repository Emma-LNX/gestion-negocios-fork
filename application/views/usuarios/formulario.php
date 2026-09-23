<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= html_escape($titulo) ?> - Gestión de negocios</title><link rel="stylesheet" href="<?= html_escape(base_url('assets/app.css')) ?>"></head>
<body><header class="cabecera"><strong><?= html_escape($usuario->empresaNombre()) ?></strong><span><?= html_escape($usuario->nombre()) ?> (<?= html_escape($usuario->rolNombre()) ?>)</span></header>
<main class="pagina-formulario"><a href="<?= html_escape(site_url('usuarios')) ?>">Volver a usuarios</a><h1><?= html_escape($titulo) ?></h1>
<?php if ($datos['error'] !== ''): ?><p class="error" role="alert"><?= html_escape($datos['error']) ?></p><?php endif; ?>
<?= form_open($accion, array('class' => 'formulario recuadro')) ?>
<label for="nombre">Nombre</label><input id="nombre" name="nombre" maxlength="100" required value="<?= html_escape($datos['nombre']) ?>">
<label for="correo">Correo</label><input id="correo" name="correo" type="email" maxlength="190" required value="<?= html_escape($datos['correo']) ?>" <?= $creando ? '' : 'readonly' ?>>
<label for="rol_id">Rol</label><select id="rol_id" name="rol_id" required><option value="">Selecciona un rol</option>
<?php foreach ($roles as $rol): ?><option value="<?= (int)$rol['id'] ?>" <?= (string)$datos['rol_id'] === (string)$rol['id'] ? 'selected' : '' ?>><?= html_escape($rol['nombre']) ?></option><?php endforeach; ?></select>
<?php if ($creando): ?><label for="password">Contraseña temporal</label><input id="password" name="password" type="password" minlength="8" maxlength="72" required autocomplete="new-password"><p class="nota">Compártela por un medio seguro. El sistema guarda únicamente su hash.</p><?php endif; ?>
<button type="submit"><?= $creando ? 'Crear usuario' : 'Guardar cambios' ?></button><?= form_close() ?></main></body></html>
