import {
  createRouter,
  createWebHistory,
} from 'vue-router'

import { useAuth } from '@/composables/useAuth'
import { onSessionInvalidated } from '@/services/session'

const routes = [
  {
    path: '/a/:token',
    name: 'public-asset',
    component: () => import('@/views/assets/PublicAssetView.vue'),
    meta: { public: true },
  },
  {
    path: '/login',
    name: 'login',

    component: () =>
      import(
        '@/views/auth/LoginView.vue'
      ),

    meta: {
      guestOnly: true,
    },
  },

  {
    path: '/',

    component: () =>
      import(
        '@/components/layout/AppLayout.vue'
      ),

    meta: {
      requiresAuth: true,
    },

    children: [
      {
        path: '',

        redirect: {
          name: 'dashboard',
        },
      },

      {
        path: 'dashboard',

        name: 'dashboard',

        component: () =>
          import(
            '@/views/dashboard/DashboardView.vue'
          ),

        meta: {
          requiresAuth: true,
        },
      },

      {
        path: 'activos',

        name: 'assets',

        component: () =>
          import(
            '@/views/assets/AssetsView.vue'
          ),

        meta: {
          requiresAuth: true,
          permission: 'assets.view',
        },
      },

      {
        path: 'activos/:id',

        name: 'asset-detail',

        component: () =>
          import(
            '@/views/assets/AssetDetailView.vue'
          ),

        meta: {
          requiresAuth: true,
          permission: 'assets.view',
        },
      },

      {
        path: 'tickets',
        name: 'tickets',
        component: () =>
          import(
            '@/views/tickets/TicketsView.vue'
          ),
        meta: {
          requiresAuth: true,
          permission: 'tickets.view',
        },
      },

      {
        path: 'tickets/nuevo',
        name: 'ticket-create',
        component: () =>
          import(
            '@/views/tickets/TicketCreateView.vue'
          ),
        meta: {
          requiresAuth: true,
          permission: 'tickets.create',
        },
      },

      {
        path: 'tickets/:id',
        name: 'ticket-detail',
        component: () =>
          import(
            '@/views/tickets/TicketDetailView.vue'
          ),
        meta: {
          requiresAuth: true,
          permission: 'tickets.view',
        },
      },
    ],
  },

  {
    path: '/403',

    name: 'forbidden',

    component: () =>
      import(
        '@/views/errors/ForbiddenView.vue'
      ),
  },

  {
    path: '/:pathMatch(.*)*',

    name: 'not-found',

    component: () =>
      import(
        '@/views/errors/NotFoundView.vue'
      ),
  },
]

const router = createRouter({
  history: createWebHistory(),

  routes,
})

onSessionInvalidated(() => {
  const currentRoute = router.currentRoute.value

  if (currentRoute.name && currentRoute.name !== 'login') {
    router.replace({
      name: 'login',
      query: {
        redirect: currentRoute.fullPath,
      },
    })
  }
})

/*
 * =========================================================
 * GUARD GLOBAL DE NAVEGACIÓN
 * =========================================================
 */
router.beforeEach(
  async (to) => {
    const {
      isAuthenticated,
      initialized,
      initAuth,
      can,
    } = useAuth()

    /*
     * Si todavía no conocemos el estado de la sesión,
     * intentamos restaurarla.
     */
    if (!initialized.value) {
      await initAuth()
    }

    /*
     * Determinamos si cualquiera de las rutas
     * coincidentes requiere autenticación.
     */
    const requiresAuth =
      to.matched.some(
        (record) =>
          record.meta.requiresAuth,
      )

    /*
     * Determinamos si es una ruta exclusiva
     * para usuarios no autenticados.
     */
    const guestOnly =
      to.matched.some(
        (record) =>
          record.meta.guestOnly,
      )

    /*
     * =====================================================
     * RUTA PROTEGIDA
     * =====================================================
     */
    if (
      requiresAuth &&
      !isAuthenticated.value
    ) {
      return {
        name: 'login',

        query: {
          redirect:
            to.fullPath !== '/'
              ? to.fullPath
              : '/dashboard',
        },
      }
    }

    /*
     * =====================================================
     * LOGIN CON SESIÓN ACTIVA
     * =====================================================
     */
    if (
      guestOnly &&
      isAuthenticated.value
    ) {
      return {
        name: 'dashboard',
      }
    }

    /*
     * =====================================================
     * CONTROL DE PERMISOS
     * =====================================================
     */
    const requiredPermission =
      to.matched
        .map(
          (record) =>
            record.meta.permission,
        )
        .find(Boolean)

    if (
      requiredPermission &&
      !can(requiredPermission)
    ) {
      return {
        name: 'forbidden',
      }
    }

    /*
     * No retornamos nada cuando la navegación
     * está permitida.
     *
     * Esta es la sintaxis moderna recomendada
     * por Vue Router.
     */
    return true
  },
)

export default router
