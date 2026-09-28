<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const router = useRouter()
const route = useRoute()
const { signIn } = useAuth()

const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

const handleLogin = async () => {
  error.value = ''
  loading.value = true

  try {
    const currentUser = await signIn(
      email.value,
      password.value,
    )

    console.log(
      'Usuario autenticado:',
      currentUser,
    )

    /*
     * Si el usuario llegó al login porque intentó entrar
     * previamente a una ruta protegida, recuperamos
     * ese destino.
     *
     * En cualquier otro caso vamos al dashboard.
     */
    const redirect =
      typeof route.query.redirect === 'string' &&
      route.query.redirect.startsWith('/') &&
      !route.query.redirect.startsWith('//')
        ? route.query.redirect
        : '/dashboard'

    console.log(
      'Redirigiendo a:',
      redirect,
    )

    /*
     * replace() es preferible al login porque evita
     * dejar /login en el historial del navegador.
     */
    await router.replace(redirect)

    console.log(
      'Navegación completada:',
      router.currentRoute.value.fullPath,
    )
  } catch (err) {
    console.error(
      'Error durante el login:',
      err,
    )

    if (err.response?.status === 422) {
      error.value =
        err.response?.data?.message ||
        'Los datos ingresados no cumplen con el formato requerido.'
    } else if (err.response?.status === 401) {
      error.value =
        'Correo electrónico o contraseña incorrectos.'
    } else if (err.response?.status === 403) {
      error.value =
        'Tu cuenta no tiene autorización para acceder al sistema.'
    } else if (!err.response) {
      error.value =
        'No fue posible conectar con el servidor de SIGATI.'
    } else {
      error.value =
        err.response?.data?.message ||
        'Ocurrió un error inesperado al intentar iniciar sesión.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="login-wrapper">
    <div class="login-card">
      <div class="login-header">
        <div class="brand-badge">
          <div class="brand-icon">S</div>
          <span class="brand-title">SIGATI</span>
        </div>
        <h2>Bienvenido</h2>
        <p class="subtitle">
          Sistema Integral de Gestión de Activos, Soporte Técnico e Incidencias
        </p>
      </div>

      <div v-if="error" class="error-banner">
        <svg class="error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>{{ error }}</span>
      </div>

      <form class="login-form" @submit.prevent="handleLogin">
        <div class="form-group">
          <label for="email">Correo institucional</label>
          <div class="input-container">
            <input
              id="email"
              v-model.trim="email"
              type="email"
              autocomplete="username"
              placeholder="ejemplo@sigati.local"
              required
              :disabled="loading"
            />
          </div>
        </div>

        <div class="form-group">
          <label for="password">Contraseña</label>
          <div class="input-container">
            <input
              id="password"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              placeholder="••••••••"
              required
              :disabled="loading"
            />
            <button
              type="button"
              class="toggle-password-btn"
              tabindex="-1"
              @click="showPassword = !showPassword"
            >
              {{ showPassword ? 'Ocultar' : 'Ver' }}
            </button>
          </div>
        </div>

        <button type="submit" class="submit-btn" :disabled="loading">
          <span v-if="loading" class="spinner"></span>
          <span>{{ loading ? 'Autenticando...' : 'Iniciar Sesión' }}</span>
        </button>
      </form>

      <div class="login-footer">
        <p>SIGATI • Gestión Centralizada de Recursos IT</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: radial-gradient(circle at 10% 20%, rgba(37, 99, 235, 0.08), transparent 40%),
              radial-gradient(circle at 90% 80%, rgba(15, 23, 42, 0.05), transparent 40%),
              var(--color-bg-app);
}

.login-card {
  width: 100%;
  max-width: 440px;
  background-color: var(--color-bg-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  padding: 40px;
  box-shadow: var(--shadow-card);
}

.login-header {
  text-align: center;
  margin-bottom: 28px;
}

.brand-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
}

.brand-icon {
  width: 38px;
  height: 38px;
  background: var(--color-primary);
  color: #ffffff;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 19px;
  font-weight: 800;
  box-shadow: 0 4px 12px rgba(29, 78, 216, 0.3);
}

.brand-title {
  font-size: 20px;
  font-weight: 800;
  letter-spacing: 1px;
  color: var(--color-text-main);
}

.login-header h2 {
  font-size: 22px;
  font-weight: 700;
  color: var(--color-text-main);
  margin-bottom: 6px;
}

.subtitle {
  font-size: 13px;
  color: var(--color-text-muted);
  line-height: 1.45;
}

.error-banner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background-color: var(--color-danger-bg);
  border: 1px solid var(--color-danger-border);
  border-radius: var(--radius-md);
  color: var(--color-danger);
  font-size: 13px;
  margin-bottom: 20px;
  line-height: 1.4;
}

.error-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-main);
}

.input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.input-container input {
  width: 100%;
  height: 44px;
  padding: 0 14px;
  background-color: #ffffff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-text-main);
  outline: none;
  transition: all 0.15s ease;
}

.input-container input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}

.input-container input:disabled {
  background-color: #f8fafc;
  cursor: not-allowed;
}

.toggle-password-btn {
  position: absolute;
  right: 12px;
  background: transparent;
  border: 0;
  color: var(--color-text-subtle);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 4px;
}

.toggle-password-btn:hover {
  color: var(--color-text-main);
}

.submit-btn {
  height: 46px;
  margin-top: 6px;
  background-color: var(--color-primary);
  color: #ffffff;
  border: 0;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  transition: all 0.15s ease;
}

.submit-btn:hover:not(:disabled) {
  background-color: var(--color-primary-hover);
}

.submit-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 50%;
  border-top-color: #ffffff;
  animation: spin 0.7s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.login-footer {
  margin-top: 32px;
  text-align: center;
  border-top: 1px solid var(--color-border-subtle);
  padding-top: 18px;
}

.login-footer p {
  font-size: 12px;
  color: var(--color-text-subtle);
}
</style>
