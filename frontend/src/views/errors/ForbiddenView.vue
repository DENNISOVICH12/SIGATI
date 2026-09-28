<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const goBack = () => {
  if (window.history.length > 2) {
    router.back()
  } else {
    router.push({ name: 'dashboard' })
  }
}
</script>

<template>
  <div class="error-container">
    <div class="error-card">
      <div class="error-badge">403</div>
      <h1>Acceso Denegado</h1>
      <p class="error-description">
        No cuentas con los permisos requeridos para acceder a este recurso o módulo en SIGATI.
      </p>
      <div class="actions">
        <button class="btn btn-secondary" @click="goBack">
          Volver atrás
        </button>
        <router-link :to="{ name: 'dashboard' }" class="btn btn-primary">
          Ir al Dashboard
        </router-link>
      </div>
    </div>
  </div>
</template>

<style scoped>
.error-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background-color: var(--color-bg-app);
}

.error-card {
  max-width: 480px;
  width: 100%;
  background: var(--color-bg-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  padding: 40px;
  text-align: center;
  box-shadow: var(--shadow-card);
}

.error-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px 14px;
  background-color: var(--color-warning-bg);
  color: var(--color-warning);
  font-weight: 700;
  font-size: 14px;
  border-radius: 9999px;
  margin-bottom: 16px;
}

h1 {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-text-main);
  margin-bottom: 10px;
}

.error-description {
  color: var(--color-text-muted);
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 28px;
}

.actions {
  display: flex;
  gap: 12px;
  justify-content: center;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 10px 18px;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 0.2s ease;
}

.btn-primary {
  background-color: var(--color-primary);
  color: #ffffff;
}

.btn-primary:hover {
  background-color: var(--color-primary-hover);
}

.btn-secondary {
  background-color: var(--color-bg-surface);
  border-color: var(--color-border);
  color: var(--color-text-main);
}

.btn-secondary:hover {
  background-color: var(--color-border-subtle);
}
</style>
