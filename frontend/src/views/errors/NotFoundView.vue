<script setup>
</script>

<template>
  <div class="error-container">
    <div class="error-card">
      <div class="error-badge">404</div>
      <h1>Página no encontrada</h1>
      <p class="error-description">
        La ruta solicitada no existe o fue movida dentro del sistema SIGATI.
      </p>
      <div class="actions">
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
  background-color: var(--color-danger-bg);
  color: var(--color-danger);
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
  justify-content: center;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 10px 22px;
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
</style>
