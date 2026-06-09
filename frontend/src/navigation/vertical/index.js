export default [
  {
    title: "Escritorio",
    to: { name: "dashboard" },
    permission: "all",
    icon: { icon: "ri-pie-chart-box-line" },
  },

  {
    heading: "Inventario y Catálogo",
    permissions: ["listar_laboratorio", "listar_forma", "listar_producto", "listar_proveedor", "listar_compra"],
  },
  {
    title: "Ajustes de Catálogo",
    icon: { icon: "ri-list-settings-line" },
    children: [
      {
        title: "Laboratorios",
        to: { name: "laboratorios" },
        permission: "listar_laboratorio",
        icon: { icon: "ri-radio-button-line" },
      },
      {
        title: "Formas Farmacéuticas",
        to: { name: "formas" },
        permission: "listar_forma",
        icon: { icon: "ri-capsule-line" },
      },
      {
        title: "Dispensadores",
        to: { name: "dispensers" },
        permission: "all",
        icon: { icon: "ri-box-3-line" },
      },
      {
        title: "Productos",
        to: { path: "/productos" },
        permission: "listar_producto",
        icon: { icon: "ri-medicine-bottle-line" },
      },
    ],
  },
  {
    title: "Ingresos",
    icon: { icon: "ri-download-line" },
    children: [
      {
        title: "Proveedores",
        to: { name: "proveedores" },
        permission: "listar_proveedor",
        icon: { icon: "ri-truck-line" },
      },
      {
        title: "Compras",
        to: { name: "compras" },
        permission: "listar_compra",
        icon: { icon: "ri-shopping-bag-3-line" },
      },
    ],
  },
  {
    title: "Inventario",
    icon: { icon: "ri-archive-line" },
    children: [
      {
        title: "Control de Lotes / Vencimientos",
        to: { path: "/inventario/lotes" },
        permission: "all",
        icon: { icon: "ri-calendar-todo-line" },
      },
      {
        title: "Kárdex Valorizado (PEPS)",
        to: { path: "/inventario/kardex" },
        permission: "all",
        icon: { icon: "ri-exchange-line" },
      },
      {
        title: "Ajustes de Stock",
        to: { path: "/inventario/ajustes" },
        permission: "all",
        icon: { icon: "ri-equalizer-line" },
      },
    ],
  },

  {
    heading: "Ventas y POS",
    permissions: ["acceso_pos", "listar_venta", "listar_cliente"],
  },
  {
    title: "Ventas y POS",
    icon: { icon: "ri-shopping-cart-2-line" },
    children: [
      {
        title: "Punto de Venta (POS)",
        to: { name: "pos" },
        permission: "acceso_pos",
        icon: { icon: "ri-shopping-cart-line" },
      },
      {
        title: "Historial de Ventas",
        to: { name: "ventas" },
        permission: "listar_venta",
        icon: { icon: "ri-file-list-3-line" },
      },
      {
        title: "Clientes",
        to: { name: "clientes" },
        permission: "listar_cliente",
        icon: { icon: "ri-group-line" },
      },
    ],
  },

  {
    heading: "Operaciones",
    permissions: ["all"],
  },
  {
    title: "Cajas",
    icon: { icon: "ri-safe-2-line" },
    children: [
      {
        title: "Control / Arqueos",
        to: { path: "/cajas" },
        permission: "all",
        icon: { icon: "ri-safe-line" },
      },
      {
        title: "Ingresos a Caja",
        to: { path: "/cajas/ingresos" },
        permission: "all",
        icon: { icon: "ri-arrow-down-circle-line" },
      },
      {
        title: "Salidas de Caja",
        to: { path: "/cajas/salidas" },
        permission: "all",
        icon: { icon: "ri-arrow-up-circle-line" },
      },
      {
        title: "Registro de Cajas",
        to: { path: "/cajas/registro" },
        permission: "all",
        icon: { icon: "ri-history-line" },
      },
    ],
  },

  {
    heading: "SIAT",
    permissions: ["all", "listar_venta"],
  },
  {
    title: "SIAT",
    icon: { icon: "ri-government-line" },
    children: [
      {
        title: "Configuración",
        to: { name: "siat-config" },
        permission: "all",
        icon: { icon: "ri-settings-5-line" },
      },
      {
        title: "Sincronización de Catálogos",
        to: { path: "/siat/sincronizacion" },
        permission: "all",
        icon: { icon: "ri-refresh-line" },
      },
      {
        title: "Lista de Facturas",
        to: { name: "ventas", query: { type: "invoices" } },
        permission: "listar_venta",
        icon: { icon: "ri-file-list-line" },
      },
      {
        title: "Contingencias",
        to: { path: "/siat/contingencias" },
        permission: "all",
        icon: { icon: "ri-wifi-off-line" },
      },
    ],
  },

  {
    heading: "Reportes",
    permissions: ["reportes"],
  },
  {
    title: "Reportes y Cierres",
    to: { name: "reportes" },
    permission: "reportes",
    icon: { icon: "ri-bar-chart-box-line" },
  },

  {
    heading: "Accesos",
    permissions: ["listar_rol", "listar_usuario"],
  },
  {
    title: "Roles y Permisos",
    to: { name: "roles-permisos" },
    permission: "listar_rol",
    icon: { icon: "ri-lock-password-line" },
  },
  {
    title: "Usuarios",
    to: { name: "usuarios" },
    permission: "listar_usuario",
    icon: { icon: "ri-user-settings-line" },
  },
];
