import { setupLayouts } from 'virtual:generated-layouts'
import { createRouter, createWebHistory } from 'vue-router/auto'
import { setupGuards } from './guards'

function recursiveLayouts(route) {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])

    return route
  }

  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
  extendRoutes: pages => [
    ...[
      {
        name: 'index',
        redirect: to => {
          const userData = localStorage.getItem("user"); //useCookie('userData')
          if (userData)
            return { name: 'dashboard' }

          return { name: 'login', query: to.query }
        },
      },
    ],
    ...[...pages, ...[
      {
        path: '/roles-y-permisos',
        name: 'roles-permisos',
        component: () => import('@/pages/roles-permisos/index.vue'),
        meta: {
          permission: 'listar_rol'
        }
      },
      {
        path: '/facturas',
        name: 'facturas',
        component: () => import('@/pages/facturas.vue'),
        meta: {
          permission: 'listar_venta'
        }
      }
    ]].map(route => recursiveLayouts(route)),
  ],
})

setupGuards(router)
export { router }
export default function (app) {
  app.use(router)
}
