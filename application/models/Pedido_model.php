<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pedido_model extends MY_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function crear($referencia, $usuario_id)
    {
        $empresa_id = $this->empresa_id();

        $consulta = $this->db
            ->where('id', $usuario_id)
            ->where('empresa_id', $empresa_id)
            ->where('activo', 1)
            ->get('usuarios');

        if (!$consulta) {
            show_error('No se pudo verificar el usuario.', 503);
        }

        if (!$consulta->row_array()) {
            show_error('Usuario no autorizado.', 403);
        }

        // El número visible y el pedido se guardan en la misma transacción.
        $debug_anterior = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        try {
            if (!$this->db->trans_begin()) {
                throw new RuntimeException('No se pudo iniciar el registro.');
            }

            // UPDATE bloquea el contador de esta empresa hasta terminar.
            // Otra empresa puede seguir creando sus propios pedidos.
            $actualizado = $this->db->query(
                'UPDATE empresas
                 SET ultimo_numero_pedido = ultimo_numero_pedido + 1
                 WHERE id = ?',
                array($empresa_id)
            );

            if (!$actualizado || $this->db->affected_rows() !== 1) {
                throw new RuntimeException('No se pudo actualizar el contador.');
            }

            $consulta = $this->db
                ->select('ultimo_numero_pedido')
                ->where('id', $empresa_id)
                ->get('empresas');

            $empresa = $consulta ? $consulta->row_array() : NULL;

            if (!$empresa) {
                throw new RuntimeException('No se pudo consultar el contador.');
            }

            $guardado = $this->db->insert('pedidos', array(
                'empresa_id' => $empresa_id,
                'numero' => (int) $empresa['ultimo_numero_pedido'],
                'usuario_id' => $usuario_id,
                'referencia' => $referencia,
                'estado' => 'borrador',
                'estado_pago' => 'pendiente',
                'total' => '0.00'
            ));

            if (!$guardado) {
                throw new RuntimeException('No se pudo guardar el pedido.');
            }

            // Las rutas y los detalles siguen utilizando el ID interno.
            $pedido_id = (int) $this->db->insert_id();

            if (!$this->db->trans_status() || !$this->db->trans_commit()) {
                throw new RuntimeException('No se pudo completar el registro.');
            }

            return $pedido_id;
        } catch (Exception $error) {
            $this->db->trans_rollback();
            log_message('error', 'Fallo al crear el pedido con numeración por empresa.');
        } finally {
            $this->db->db_debug = $debug_anterior;
        }

        show_error('No se pudo guardar el pedido. Revisa la actualización de la base de datos e intenta nuevamente.', 503);
        return 0;
    }

    public function listar()
    {
        $consulta = $this->db
            ->where('empresa_id', $this->empresa_id())
            ->order_by('id', 'DESC')
            ->limit(50)
            ->get('pedidos');

        if (!$consulta) {
            show_error('No se pudieron consultar los pedidos.', 503);
        }

        return $consulta->result_array();
    }
        
    public function buscar($id)
    {
        $consulta = $this->db
            ->where('id', (int) $id)
            ->where('empresa_id', $this->empresa_id())
            ->get('pedidos');

        if (!$consulta) {
            show_error('No se pudo consultar el pedido.', 503);
        }

        return $consulta->row_array();
    }

    public function detalles($pedido_id)
    {
        $consulta = $this->db
            ->select('id, producto_id, producto_nombre, cantidad, precio_unitario')
            ->where('pedido_id', (int) $pedido_id)
            ->where('empresa_id', $this->empresa_id())
            ->order_by('id', 'ASC')
            ->get('pedido_detalles');

        if (!$consulta) {
            show_error('No se pudieron consultar los productos del pedido.', 503);
        }

        return $consulta->result_array();
    }

        public function agregar_producto($pedido_id, $producto_id, $cantidad)
    {
        if ($cantidad < 1 || $cantidad > 999) {
            return 'La cantidad debe estar entre 1 y 999.';
        }

        $empresa_id = $this->empresa_id();
        $debug_anterior = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        // Ejecuta consultas y detecta fallos para deshacer la operación.
        $ejecutar = function ($sql, $valores) {
            $resultado = $this->db->query($sql, $valores);

            if ($resultado === FALSE) {
                throw new RuntimeException('Error de base de datos.');
            }

            return $resultado;
        };

        try {
            if (!$this->db->trans_begin()) {
                throw new RuntimeException('No se pudo iniciar la operación.');
            }

            // Bloquear el pedido mientras se modifican sus productos.
            $pedido = $ejecutar(
                'SELECT id, estado
                 FROM pedidos
                 WHERE id = ? AND empresa_id = ?
                 FOR UPDATE',
                array($pedido_id, $empresa_id)
            )->row_array();

            if (!$pedido) {
                throw new InvalidArgumentException('Pedido no encontrado.');
            }

            if ($pedido['estado'] !== 'borrador') {
                throw new InvalidArgumentException(
                    'Solo puedes modificar pedidos en borrador.'
                );
            }

            $producto = $ejecutar(
                'SELECT id, nombre, precio
                 FROM productos
                 WHERE id = ? AND empresa_id = ?',
                array($producto_id, $empresa_id)
            )->row_array();

            if (!$producto) {
                throw new InvalidArgumentException(
                    'El producto no está disponible para tu empresa.'
                );
            }

            $detalle = $ejecutar(
                'SELECT id, cantidad
                 FROM pedido_detalles
                 WHERE pedido_id = ?
                   AND empresa_id = ?
                   AND producto_id = ?',
                array($pedido_id, $empresa_id, $producto_id)
            )->row_array();

            if ($detalle) {
                $nueva_cantidad = (int) $detalle['cantidad'] + $cantidad;

                if ($nueva_cantidad > 999) {
                    throw new InvalidArgumentException(
                        'El máximo por producto es de 999 unidades.'
                    );
                }

                // Mantener el precio con el que se agregó originalmente.
                $ejecutar(
                    'UPDATE pedido_detalles
                     SET cantidad = ?
                     WHERE id = ? AND empresa_id = ?',
                    array($nueva_cantidad, $detalle['id'], $empresa_id)
                );
            } else {
                $ejecutar(
                    'INSERT INTO pedido_detalles
                     (empresa_id, pedido_id, producto_id,
                      producto_nombre, cantidad, precio_unitario)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    array(
                        $empresa_id,
                        $pedido_id,
                        $producto_id,
                        $producto['nombre'],
                        $cantidad,
                        $producto['precio']
                    )
                );
            }

            // Calcular con DECIMAL en la base de datos.
            $resumen = $ejecutar(
                'SELECT
                    COALESCE(SUM(cantidad * precio_unitario), 0) AS total,
                    COALESCE(SUM(cantidad * precio_unitario), 0)
                        > 9999999999.99 AS excede
                 FROM pedido_detalles
                 WHERE pedido_id = ? AND empresa_id = ?',
                array($pedido_id, $empresa_id)
            )->row_array();

            if ($resumen['excede']) {
                throw new InvalidArgumentException(
                    'El total supera el límite permitido para un pedido.'
                );
            }

            $ejecutar(
                'UPDATE pedidos
                 SET total = ?
                 WHERE id = ? AND empresa_id = ?',
                array($resumen['total'], $pedido_id, $empresa_id)
            );

            if (!$this->db->trans_status() || !$this->db->trans_commit()) {
                throw new RuntimeException('No se pudo completar la operación.');
            }

            return NULL;
        } catch (InvalidArgumentException $error) {
            $this->db->trans_rollback();
            return $error->getMessage();
        } catch (Exception $error) {
            $this->db->trans_rollback();
            log_message('error', 'Fallo al agregar un producto al pedido.');

            return 'No se pudo guardar el producto. Intenta nuevamente.';
        } finally {
            $this->db->db_debug = $debug_anterior;
        }
    }

        public function modificar_detalle($pedido_id, $detalle_id, $cantidad)
    {
        // NULL significa quitar el producto del pedido.
        if ($cantidad !== NULL && ($cantidad < 1 || $cantidad > 999)) {
            return 'La cantidad debe estar entre 1 y 999.';
        }

        $empresa_id = $this->empresa_id();
        $debug_anterior = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        $ejecutar = function ($sql, $valores) {
            $resultado = $this->db->query($sql, $valores);

            if ($resultado === FALSE) {
                throw new RuntimeException('Error de base de datos.');
            }

            return $resultado;
        };

        try {
            if (!$this->db->trans_begin()) {
                throw new RuntimeException('No se pudo iniciar la operación.');
            }

            $pedido = $ejecutar(
                'SELECT id, estado
                 FROM pedidos
                 WHERE id = ? AND empresa_id = ?
                 FOR UPDATE',
                array($pedido_id, $empresa_id)
            )->row_array();

            if (!$pedido) {
                throw new InvalidArgumentException('Pedido no encontrado.');
            }

            if ($pedido['estado'] !== 'borrador') {
                throw new InvalidArgumentException(
                    'Solo puedes modificar pedidos en borrador.'
                );
            }

            $detalle = $ejecutar(
                'SELECT id
                 FROM pedido_detalles
                 WHERE id = ?
                   AND pedido_id = ?
                   AND empresa_id = ?',
                array($detalle_id, $pedido_id, $empresa_id)
            )->row_array();

            if (!$detalle) {
                throw new InvalidArgumentException(
                    'El producto no se encuentra en este pedido.'
                );
            }

            if ($cantidad === NULL) {
                $ejecutar(
                    'DELETE FROM pedido_detalles
                     WHERE id = ?
                       AND pedido_id = ?
                       AND empresa_id = ?',
                    array($detalle_id, $pedido_id, $empresa_id)
                );
            } else {
                $ejecutar(
                    'UPDATE pedido_detalles
                     SET cantidad = ?
                     WHERE id = ?
                       AND pedido_id = ?
                       AND empresa_id = ?',
                    array($cantidad, $detalle_id, $pedido_id, $empresa_id)
                );
            }

            $resumen = $ejecutar(
                'SELECT
                    COALESCE(SUM(cantidad * precio_unitario), 0) AS total,
                    COALESCE(SUM(cantidad * precio_unitario), 0)
                        > 9999999999.99 AS excede
                 FROM pedido_detalles
                 WHERE pedido_id = ? AND empresa_id = ?',
                array($pedido_id, $empresa_id)
            )->row_array();

            if ($resumen['excede']) {
                throw new InvalidArgumentException(
                    'El total supera el límite permitido para un pedido.'
                );
            }

            $ejecutar(
                'UPDATE pedidos
                 SET total = ?
                 WHERE id = ? AND empresa_id = ?',
                array($resumen['total'], $pedido_id, $empresa_id)
            );

            if (!$this->db->trans_status() || !$this->db->trans_commit()) {
                throw new RuntimeException('No se pudo completar la operación.');
            }

            return NULL;
        } catch (InvalidArgumentException $error) {
            $this->db->trans_rollback();
            return $error->getMessage();
        } catch (Exception $error) {
            $this->db->trans_rollback();
            log_message('error', 'Fallo al modificar el detalle del pedido.');

            return 'No se pudo modificar el pedido. Intenta nuevamente.';
        } finally {
            $this->db->db_debug = $debug_anterior;
        }
    }
        public function confirmar($pedido_id)
    {
        $empresa_id = $this->empresa_id();
        $debug_anterior = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        $ejecutar = function ($sql, $valores) {
            $resultado = $this->db->query($sql, $valores);

            if ($resultado === FALSE) {
                throw new RuntimeException('Error de base de datos.');
            }

            return $resultado;
        };

        try {
            if (!$this->db->trans_begin()) {
                throw new RuntimeException('No se pudo iniciar la operación.');
            }

            // Usa el mismo bloqueo que agregar y modificar productos.
            $pedido = $ejecutar(
                'SELECT id, estado
                 FROM pedidos
                 WHERE id = ? AND empresa_id = ?
                 FOR UPDATE',
                array($pedido_id, $empresa_id)
            )->row_array();

            if (!$pedido) {
                throw new InvalidArgumentException('Pedido no encontrado.');
            }

            if ($pedido['estado'] !== 'borrador') {
                throw new InvalidArgumentException(
                    'Solo puedes confirmar un pedido en borrador.'
                );
            }

            $resumen = $ejecutar(
                'SELECT
                    COUNT(*) AS productos,
                    COALESCE(SUM(cantidad * precio_unitario), 0) AS total,
                    COALESCE(SUM(cantidad * precio_unitario), 0)
                        > 9999999999.99 AS excede,
                    COALESCE(SUM(cantidad < 1 OR cantidad > 999), 0)
                        AS invalidos
                 FROM pedido_detalles
                 WHERE pedido_id = ? AND empresa_id = ?',
                array($pedido_id, $empresa_id)
            )->row_array();

            if ((int) $resumen['productos'] === 0) {
                throw new InvalidArgumentException(
                    'Agrega al menos un producto antes de confirmar.'
                );
            }

            if ((int) $resumen['invalidos'] > 0) {
                throw new InvalidArgumentException(
                    'Las cantidades deben estar entre 1 y 999.'
                );
            }

            if ($resumen['excede']) {
                throw new InvalidArgumentException(
                    'El total supera el límite permitido.'
                );
            }

            // "pendiente" significa pendiente de preparación.
            // El estado de pago no cambia.
            $ejecutar(
                'UPDATE pedidos
                 SET estado = ?,
                     total = ?,
                     confirmado_en = CURRENT_TIMESTAMP
                 WHERE id = ? AND empresa_id = ?',
                array(
                    'pendiente',
                    $resumen['total'],
                    $pedido_id,
                    $empresa_id
                )
            );

            if (!$this->db->trans_status() || !$this->db->trans_commit()) {
                throw new RuntimeException('No se pudo completar la operación.');
            }

            return NULL;
        } catch (InvalidArgumentException $error) {
            $this->db->trans_rollback();
            return $error->getMessage();
        } catch (Exception $error) {
            $this->db->trans_rollback();
            log_message('error', 'Fallo al confirmar el pedido.');

            return 'No se pudo confirmar el pedido. Intenta nuevamente.';
        } finally {
            $this->db->db_debug = $debug_anterior;
        }
    }
        public function cancelar($pedido_id, $motivo)
    {
        // Validar el motivo también en el servidor.
        if (
            !is_string($motivo)
            || trim($motivo) === ''
            || mb_strlen(trim($motivo), 'UTF-8') > 500
        ) {
            return 'Escribe un motivo de entre 1 y 500 caracteres.';
        }

        $motivo = trim($motivo);

        // Obtener la identidad desde la sesión, no desde el formulario.
        $ci =& get_instance();
        $ci->load->library('Autenticacion');
        $usuario = $ci->autenticacion->usuario_actual();

        if (!$usuario || !$usuario->puede('pedidos.cancelar')) {
            return 'No tienes permiso para cancelar pedidos.';
        }

        $empresa_id = $this->empresa_id();
        $debug_anterior = $this->db->db_debug;
        $this->db->db_debug = FALSE;

        $ejecutar = function ($sql, $valores) {
            $resultado = $this->db->query($sql, $valores);

            if ($resultado === FALSE) {
                throw new RuntimeException('Error de base de datos.');
            }

            return $resultado;
        };

        try {
            if (!$this->db->trans_begin()) {
                throw new RuntimeException('No se pudo iniciar la operación.');
            }

            // Bloquear el pedido mientras se procesa la cancelación.
            $pedido = $ejecutar(
                'SELECT id, estado, estado_pago
                 FROM pedidos
                 WHERE id = ? AND empresa_id = ?
                 FOR UPDATE',
                array($pedido_id, $empresa_id)
            )->row_array();

            if (!$pedido) {
                throw new InvalidArgumentException('Pedido no encontrado.');
            }

            // Revalidar que el usuario sigue autorizado.
            $autorizado = $ejecutar(
                'SELECT u.id
                 FROM usuarios u
                 JOIN empresas e ON e.id = u.empresa_id
                 JOIN rol_permisos rp ON rp.rol_id = u.rol_id
                 JOIN permisos p ON p.id = rp.permiso_id
                 WHERE u.id = ?
                   AND u.empresa_id = ?
                   AND u.activo = 1
                   AND e.activa = 1
                   AND p.codigo = ?
                 LIMIT 1',
                array(
                    $usuario->id(),
                    $empresa_id,
                    'pedidos.cancelar'
                )
            )->row_array();

            if (!$autorizado) {
                throw new InvalidArgumentException(
                    'No tienes permiso para cancelar pedidos.'
                );
            }

            $estados_permitidos = array(
                'borrador',
                'pendiente',
                'en_preparacion',
                'listo'
            );

            if (!in_array($pedido['estado'], $estados_permitidos, TRUE)) {
                throw new InvalidArgumentException(
                    'Este pedido ya fue cancelado, entregado o no admite cancelación.'
                );
            }

            if ($pedido['estado_pago'] !== 'pendiente') {
                throw new InvalidArgumentException(
                    'Un pedido con pagos requiere gestionar la devolución antes de cancelarlo.'
                );
            }

            // Registrar la autorización y el motivo.
            $ejecutar(
                'INSERT INTO pedido_cancelaciones
                 (pedido_id, empresa_id, usuario_id, motivo, estado_anterior)
                 VALUES (?, ?, ?, ?, ?)',
                array(
                    $pedido_id,
                    $empresa_id,
                    $usuario->id(),
                    $motivo,
                    $pedido['estado']
                )
            );

            // Conservar los productos y el total como registro histórico.
            $ejecutar(
                'UPDATE pedidos
                 SET estado = ?
                 WHERE id = ? AND empresa_id = ?',
                array('cancelado', $pedido_id, $empresa_id)
            );

            if (!$this->db->trans_status() || !$this->db->trans_commit()) {
                throw new RuntimeException('No se pudo completar la operación.');
            }

            return NULL;
        } catch (InvalidArgumentException $error) {
            $this->db->trans_rollback();
            return $error->getMessage();
        } catch (Exception $error) {
            $this->db->trans_rollback();
            log_message('error', 'Fallo al cancelar el pedido.');

            return 'No se pudo cancelar el pedido. Revisa la migración 004.';
        } finally {
            $this->db->db_debug = $debug_anterior;
        }
    }
        public function cancelacion($pedido_id)
    {
        $consulta = $this->db
            ->select('c.*, u.nombre AS autorizado_por')
            ->from('pedido_cancelaciones c')
            ->join(
                'usuarios u',
                'u.id = c.usuario_id AND u.empresa_id = c.empresa_id',
                'left'
            )
            ->where('c.pedido_id', (int) $pedido_id)
            ->where('c.empresa_id', $this->empresa_id())
            ->get();

        if (!$consulta) {
            show_error('No se pudo consultar la cancelación.', 503);
        }

        return $consulta->row_array();
    }
}
