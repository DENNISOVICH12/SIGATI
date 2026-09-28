<script setup>
import { computed } from 'vue'
import { useAuth } from '@/composables/useAuth'

const { user } = useAuth()

const formattedRoles = computed(() => {
  if (!user.value?.roles?.length) return 'Sin rol asignado'

  const roleNames = {
    engineer: 'Ingeniero',
    technician: 'Técnico',
    admin: 'Administrador',
  }

  return user.value.roles
    .map((role) => roleNames[role] ?? role)
    .join(', ')
})
</script>

<template>
  <div class="dashboard-view">
    <!-- Encabezado de Bienvenida -->
    <header class="welcome-header">
      <div class="header-content">
        <h1>Bienvenido, {{ user?.name ?? 'Usuario' }}</h1>
        <p class="header-subtitle">
          Panel de Control de SIGATI — Sistema Integral de Gestión de Activos, Soporte Técnico e Incidencias
        </p>
      </div>

      <div class="header-meta">
        <span class="status-indicator">
          <span class="status-dot"></span>
          Sesión Activa
        </span>
        <span class="user-role-badge">{{ formattedRoles }}</span>
      </div>
    </header>

    <!-- Estado de Infraestructura Base -->
    <section class="overview-section">
      <div class="info-card">
        <div class="info-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          </svg>
        </div>
        <div class="info-body">
          <h3>Infraestructura Base SIGATI Lista</h3>
          <p>
            La plataforma ha inicializado correctamente su arquitectura frontend con soporte para enrutamiento protegido, gestión reactiva de sesión basada en tokens de Laravel Sanctum y autorización granular por roles y permisos.
          </p>
        </div>
      </div>
    </section>

    <!-- Módulos Próximos -->
    <section class="modules-overview">
      <div class="module-card">
        <div class="module-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
          </svg>
        </div>
        <h4>Gestión de Activos</h4>
        <p>Control de inventario, transferencias, cambios de estado e historial técnico.</p>
        <span class="module-status">Fase siguiente</span>
      </div>

      <div class="module-card">
        <div class="module-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
        </div>
        <h4>Soporte Técnico e Incidencias</h4>
        <p>Ciclo de vida de tickets: asignación, atención técnica, resolución y cierre.</p>
        <span class="module-status">Fase siguiente</span>
      </div>
    </section>
  </div>
</template>

<style scoped>
.dashboard-view {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.welcome-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 20px;
  background: var(--color-bg-surface);
  padding: 28px 32px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-sm);
}

.welcome-header h1 {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-text-main);
  margin-bottom: 6px;
}

.header-subtitle {
  font-size: 14px;
  color: var(--color-text-muted);
}

.header-meta {
  display: flex;
  align-items: center;
  gap: 12px;
}

.status-indicator {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 9999px;
  background-color: var(--color-success-bg);
  color: var(--color-success);
  font-size: 12px;
  font-weight: 600;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--color-success);
}

.user-role-badge {
  display: inline-flex;
  padding: 6px 12px;
  border-radius: 9999px;
  background-color: var(--color-primary-light);
  color: var(--color-primary);
  font-size: 12px;
  font-weight: 600;
  border: 1px solid var(--color-primary-border);
}

.info-card {
  display: flex;
  align-items: flex-start;
  gap: 18px;
  padding: 24px;
  background: var(--color-bg-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.info-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  background-color: var(--color-primary-light);
  color: var(--color-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.info-icon svg {
  width: 24px;
  height: 24px;
}

.info-body h3 {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text-main);
  margin-bottom: 6px;
}

.info-body p {
  font-size: 14px;
  color: var(--color-text-muted);
  line-height: 1.6;
}

.modules-overview {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
}

.module-card {
  background: var(--color-bg-surface);
  padding: 24px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--color-border);
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
}

.module-icon {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  background-color: #f1f5f9;
  color: var(--color-text-muted);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}

.module-icon svg {
  width: 20px;
  height: 20px;
}

.module-card h4 {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text-main);
  margin-bottom: 8px;
}

.module-card p {
  font-size: 13px;
  color: var(--color-text-muted);
  line-height: 1.5;
  margin-bottom: 20px;
  flex: 1;
}

.module-status {
  align-self: flex-start;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 4px 8px;
  border-radius: 4px;
  background-color: #f1f5f9;
  color: var(--color-text-muted);
}

@media (max-width: 768px) {
  .welcome-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
