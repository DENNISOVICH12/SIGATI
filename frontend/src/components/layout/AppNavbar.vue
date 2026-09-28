<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const emit = defineEmits(['toggle-sidebar'])

const router = useRouter()
const { user, signOut, loading } = useAuth()

const formattedRole = computed(() => {
  if (!user.value?.roles?.length) return 'Sin rol'
  
  const roleNames = {
    engineer: 'Ingeniero',
    technician: 'Técnico',
    admin: 'Administrador',
  }

  return user.value.roles
    .map((role) => roleNames[role] ?? role)
    .join(', ')
})

const handleLogout = async () => {
  try {
    await signOut()
    router.push({ name: 'login' })
  } catch (error) {
    console.error('Error al cerrar sesión:', error)
    router.push({ name: 'login' })
  }
}
</script>

<template>
  <header class="app-navbar">
    <div class="navbar-left">
      <button
        class="toggle-btn"
        type="button"
        title="Alternar menú"
        @click="emit('toggle-sidebar')"
      >
        <span class="bar"></span>
        <span class="bar"></span>
        <span class="bar"></span>
      </button>

      <span class="system-tag">SIGATI Web</span>
    </div>

    <div class="navbar-right">
      <div v-if="user" class="user-profile">
        <div class="user-avatar">
          {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
        </div>
        <div class="user-details">
          <span class="user-name">{{ user.name ?? 'Usuario' }}</span>
          <span class="user-role">{{ formattedRole }}</span>
        </div>
      </div>

      <button
        class="logout-btn"
        type="button"
        :disabled="loading"
        @click="handleLogout"
      >
        <svg
          class="logout-icon"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="2"
          stroke-linecap="round"
          stroke-linejoin="round"
        >
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
          <polyline points="16 17 21 12 16 7"></polyline>
          <line x1="21" y1="12" x2="9" y2="12"></line>
        </svg>
        <span>{{ loading ? 'Saliendo...' : 'Cerrar sesión' }}</span>
      </button>
    </div>
  </header>
</template>

<style scoped>
.app-navbar {
  height: 64px;
  background-color: var(--color-bg-surface);
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  position: sticky;
  top: 0;
  z-index: 20;
}

.navbar-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.toggle-btn {
  display: none;
  flex-direction: column;
  justify-content: space-between;
  width: 24px;
  height: 18px;
  background: transparent;
  border: 0;
  cursor: pointer;
  padding: 0;
}

.toggle-btn .bar {
  width: 100%;
  height: 2px;
  background-color: var(--color-text-main);
  border-radius: 2px;
}

.system-tag {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-subtle);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.navbar-right {
  display: flex;
  align-items: center;
  gap: 20px;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: var(--color-primary-light);
  color: var(--color-primary);
  font-weight: 700;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--color-primary-border);
}

.user-details {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}

.user-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-main);
}

.user-role {
  font-size: 12px;
  color: var(--color-text-muted);
}

.logout-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  background-color: transparent;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
}

.logout-btn:hover:not(:disabled) {
  background-color: var(--color-danger-bg);
  border-color: var(--color-danger-border);
  color: var(--color-danger);
}

.logout-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.logout-icon {
  width: 16px;
  height: 16px;
}

@media (max-width: 768px) {
  .toggle-btn {
    display: flex;
  }

  .user-details {
    display: none;
  }

  .logout-btn span {
    display: none;
  }

  .logout-btn {
    padding: 8px;
  }
}
</style>
