export default [
  {
    title: 'Escritorio',
    to: { name: 'dashboard' },
    icon: { icon: 'ri-pie-chart-box-line' },
  },
  {
    title: 'Punto de Venta (POS)',
    to: { name: 'pos' },
    icon: { icon: 'ri-shopping-cart-2-line' },
  },
  {
    title: 'Catálogos',
    icon: { icon: 'ri-medicine-bottle-line' },
    children: [
      {
        title: 'Productos',
        to: { path: '/productos' },
        icon: { icon: 'ri-radio-button-line' },
      },
      {
        title: 'Laboratorios',
        to: { name: 'laboratorios' },
        icon: { icon: 'ri-radio-button-line' },
      },
      {
        title: 'Formas Farmacéuticas',
        to: { name: 'formas' },
        icon: { icon: 'ri-capsule-line' },
      },
      {
        title: 'Presentaciones',
        to: { name: 'presentaciones' },
        icon: { icon: 'ri-archive-line' },
      },
      {
        title: 'Proveedores',
        to: { name: 'proveedores' },
        icon: { icon: 'ri-truck-line' },
      },
    ],
  },
  {
    title: 'Transacciones',
    icon: { icon: 'ri-truck-line' },
    children: [
      {
        title: 'Mis Compras',
        to: { name: 'compras' },
        icon: { icon: 'ri-radio-button-line' },
      },
      {
        title: 'Historial de Ventas',
        to: { name: 'ventas' },
        icon: { icon: 'ri-radio-button-line' },
      },
      {
        title: 'Configuración SIAT',
        to: { name: 'siat-config' },
        icon: { icon: 'ri-government-line' },
      },
    ]
  },
  {
    title: 'Accesos',
    icon: { icon: 'ri-shield-user-line' },
    children: [
      {
        title: 'Roles y Permisos',
        to: { name: 'roles-permisos' },
        icon: { icon: 'ri-lock-password-line' },
      },
      {
        title: 'Usuarios',
        to: { name: 'usuarios' },
        icon: { icon: 'ri-user-settings-line' },
      },
    ],
  },
];
