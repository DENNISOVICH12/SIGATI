<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true,
  },
})

const statusConfig = {
  operational: {
    label: 'Operativo',
    className: 'badge-operational',
  },
  pending_review: {
    label: 'Pendiente de revisión',
    className: 'badge-pending',
  },
  faulty: {
    label: 'Con falla',
    className: 'badge-faulty',
  },
  maintenance: {
    label: 'En mantenimiento',
    className: 'badge-maintenance',
  },
}

const currentStatus = computed(() => {
  return (
    statusConfig[props.status] || {
      label: props.status || 'Desconocido',
      className: 'badge-unknown',
    }
  )
})
</script>

<template>
  <span class="status-badge" :class="currentStatus.className">
    <span class="badge-dot" aria-hidden="true"></span>
    <span class="badge-text">{{ currentStatus.label }}</span>
  </span>
</template>

<style scoped>
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 9999px;
  font-size: 12px;
  font-weight: 600;
  line-height: 1.25;
  white-space: nowrap;
}

.badge-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* operational: verde institucional */
.badge-operational {
  background-color: var(--color-success-bg);
  color: var(--color-success);
}
.badge-operational .badge-dot {
  background-color: var(--color-success);
}

/* pending_review: ámbar / advertencia */
.badge-pending {
  background-color: var(--color-warning-bg);
  color: var(--color-warning);
}
.badge-pending .badge-dot {
  background-color: var(--color-warning);
}

/* faulty: rojo / falla crítica */
.badge-faulty {
  background-color: var(--color-danger-bg);
  color: var(--color-danger);
}
.badge-faulty .badge-dot {
  background-color: var(--color-danger);
}

/* maintenance: azul / técnico */
.badge-maintenance {
  background-color: #e0f2fe;
  color: #0369a1;
}
.badge-maintenance .badge-dot {
  background-color: #0284c7;
}

/* desconocido / fallback */
.badge-unknown {
  background-color: #f1f5f9;
  color: #64748b;
}
.badge-unknown .badge-dot {
  background-color: #94a3b8;
}
</style>
