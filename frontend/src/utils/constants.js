export const COOKIE_MAX_AGE_1_YEAR = 365 * 24 * 60 * 60;

export const PERMISOS = [
    {
        'name': 'Dashboard',
        'permisos': [
            { name: 'Escritorio', permiso: 'escritorio' }
        ]
    },
    {
        'name': 'Punto de Venta',
        'permisos': [
            { name: 'Acceso a POS', permiso: 'acceso_pos' },
            { name: 'Listado Múltiple', permiso: 'listar_venta' },
            { name: 'Anular Venta', permiso: 'anular_venta' },
        ]
    },
    {
        'name': 'Compras e Ingresos',
        'permisos': [
            { name: 'Listado', permiso: 'listar_compra' },
            { name: 'Registrar', permiso: 'registrar_compra' },
            { name: 'Anular Compra', permiso: 'anular_compra' },
        ]
    },
    {
        'name': 'Catálogo Múltiple',
        'permisos': [
            { name: 'Productos', permiso: 'listar_producto' },
            { name: 'Laboratorios', permiso: 'listar_laboratorio' },
            { name: 'Proveedores', permiso: 'listar_proveedor' },
            { name: 'Formas Farmacéuticas', permiso: 'listar_forma' },
            { name: 'Presentaciones', permiso: 'listar_presentacion' },
        ]
    },
    {
        'name': 'Clientes',
        'permisos': [
            { name: 'Listado', permiso: 'listar_cliente' },
            { name: 'Registrar', permiso: 'registrar_cliente' },
            { name: 'Editar', permiso: 'editar_cliente' },
            { name: 'Eliminar', permiso: 'eliminar_cliente' }
        ]
    },
    {
        'name': 'Reportes',
        'permisos': [
            { name: 'Reportes Financieros', permiso: 'reportes' }
        ]
    },
    {
        'name': 'Roles',
        'permisos': [
            { name: 'Listado', permiso: 'listar_rol' },
            { name: 'Registrar', permiso: 'registrar_rol' },
            { name: 'Editar', permiso: 'editar_rol' },
            { name: 'Eliminar', permiso: 'eliminar_rol' }
        ]
    },
    {
        'name': 'Usuarios',
        'permisos': [
            { name: 'Listado', permiso: 'listar_usuario' },
            { name: 'Registrar', permiso: 'registrar_usuario' },
            { name: 'Editar', permiso: 'editar_usuario' },
            { name: 'Eliminar', permiso: 'eliminar_usuario' }
        ]
    }
];