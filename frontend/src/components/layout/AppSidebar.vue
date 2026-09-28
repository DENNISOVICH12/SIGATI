<script setup>
import { useAuth } from '@/composables/useAuth'

defineProps({
  isOpen: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['close'])
const { can } = useAuth()
</script>

<template>
  <div>
    <!-- Backdrop para móviles -->
    <div
      v-if="isOpen"
      class="sidebar-backdrop"
      @click="emit('close')"
    ></div>

    <aside class="app-sidebar" :class="{ 'is-open': isOpen }">
      <div class="sidebar-brand">
        <div class="brand-logo">S</div>
        <div class="brand-info">
          <span class="brand-name">SIGATI</span>
          <span class="brand-sub">Gestión & Soporte</span>
        </div>
      </div>

      <nav class="sidebar-nav">
        <div class="nav-section-title">Módulos Principales</div>

        <!-- Dashboard (Activo) -->
        <router-link
          :to="{ name: 'dashboard' }"
          class="nav-item"
          active-class="active"
          @click="emit('close')"
        >
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7"></rect>
            <rect x="14" y="3" width="7" height="7"></rect>
            <rect x="14" y="14" width="7" height="7"></rect>
            <rect x="3" y="14" width="7" height="7"></rect>
          </svg>
          <span class="nav-label">Dashboard</span>
        </router-link>

        <!-- Activos (Habilitado con permiso assets.view) -->
        <router-link
          v-if="can('assets.view')"
          :to="{ name: 'assets' }"
          class="nav-item"
          active-class="active"
          @click="emit('close')"
        >
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
          <span class="nav-label">Activos</span>
        </router-link>

        <!-- Tickets (Deshabilitado temporalmente) -->
        <div class="nav-item disabled" title="Módulo disponible en la siguiente fase">
          <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          <span class="nav-label">Tickets</span>
          <span class="coming-soon-badge">Próx.</span>
        </div>
      </nav>

      <div class="sidebar-footer">
        <span class="version-label">SIGATI v1.0 • Fase 1</span>
      </div>
    </aside>
  </div>
</template>

<style scoped>
.app-sidebar {
  width: 260px;
  height: 100vh;
  background-color: var(--color-bg-sidebar);
  color: #ffffff;
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  left: 0;
  bottom: 0;
  z-index: 30;
  transition: transform 0.25s ease;
}

.sidebar-brand {
  height: 64px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.brand-logo {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-sm);
  background: var(--color-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 800;
  color: #ffffff;
}

.brand-info {
  display: flex;
  flex-direction: column;
}

.brand-name {
  font-size: 16px;
  font-weight: 700;
  letter-spacing: 0.5px;
}

.brand-sub {
  font-size: 11px;
  color: var(--color-text-subtle);
}

.sidebar-nav {
  flex: 1;
  padding: 20px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  overflow-y: auto;
}

.nav-section-title {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.75px;
  padding: 8px 12px 4px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  border-radius: var(--radius-md);
  color: #94a3b8;
  font-size: 14px;
  font-weight: 500;
  transition: all 0.15s ease;
  user-select: none;
}

.nav-item:not(.disabled):hover {
  background-color: var(--color-bg-sidebar-hover);
  color: #ffffff;
}

.nav-item.active {
  background-color: var(--color-bg-sidebar-active);
  color: #ffffff;
  font-weight: 600;
}

.nav-item.disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.nav-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.nav-label {
  flex: 1;
}

.coming-soon-badge {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 4px;
  background-color: rgba(255, 255, 255, 0.1);
  color: #cbd5e1;
  font-weight: 600;
  text-transform: uppercase;
}

.sidebar-footer {
  padding: 16px 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.version-label {
  font-size: 11px;
  color: #64748b;
}

.sidebar-backdrop {
  display: none;
}

@media (max-width: 768px) {
  .app-sidebar {
    transform: translateX(-100%);
  }

  .app-sidebar.is-open {
    transform: translateX(0);
  }

  .sidebar-backdrop {
    display: block;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 25;
  }
}
</style>
