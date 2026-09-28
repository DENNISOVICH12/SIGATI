<script setup>
import { computed, ref, watch } from 'vue'

import { changeAssetStatus } from '@/services/assetService'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },

  asset: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits([
  'close',
  'status-changed',
])

const status = ref('')
const reason = ref('')

const submitting = ref(false)
const generalError = ref('')
const fieldErrors = ref({})

const statusOptions = [
  {
    value: 'operational',
    label: 'Operativo',
    description: 'El activo funciona correctamente y está disponible para su uso.',
  },
  {
    value: 'pending_review',
    label: 'Pendiente de revisión',
    description: 'El activo requiere una revisión técnica antes de determinar su condición.',
  },
  {
    value: 'faulty',
    label: 'Con falla',
    description: 'El activo presenta una falla que afecta parcial o totalmente su funcionamiento.',
  },
  {
    value: 'maintenance',
    label: 'En mantenimiento',
    description: 'El activo se encuentra actualmente bajo intervención técnica.',
  },
]

const currentStatus = computed(() => {
  return props.asset?.status ?? ''
})

const currentStatusLabel = computed(() => {
  return (
    statusOptions.find(
      (option) => option.value === currentStatus.value,
    )?.label ?? currentStatus.value
  )
})

const selectedStatus = computed(() => {
  return statusOptions.find(
    (option) => option.value === status.value,
  )
})

const isSameStatus = computed(() => {
  return (
    Boolean(status.value) &&
    status.value === currentStatus.value
  )
})

const resetForm = () => {
  status.value = ''
  reason.value = ''

  generalError.value = ''
  fieldErrors.value = {}
}

watch(
  () => props.isOpen,
  (isOpen) => {
    if (isOpen) {
      resetForm()
    }
  },
)

watch(status, () => {
  generalError.value = ''

  if (fieldErrors.value.status) {
    fieldErrors.value.status = []
  }
})

watch(reason, () => {
  if (fieldErrors.value.reason) {
    fieldErrors.value.reason = []
  }
})

const close = () => {
  if (submitting.value) return

  emit('close')
}

const getFieldError = (field) => {
  return fieldErrors.value?.[field]?.[0] ?? ''
}

const validate = () => {
  fieldErrors.value = {}

  if (!status.value) {
    fieldErrors.value.status = [
      'Debes seleccionar el nuevo estado del activo.',
    ]
  }

  if (isSameStatus.value) {
    fieldErrors.value.status = [
      'Debes seleccionar un estado diferente al estado actual.',
    ]
  }

  if (!reason.value.trim()) {
    fieldErrors.value.reason = [
      'Debes indicar el motivo del cambio de estado.',
    ]
  }

  return Object.keys(fieldErrors.value).length === 0
}

const submit = async () => {
  generalError.value = ''

  if (!validate()) {
    return
  }

  submitting.value = true

  try {
    const payload = {
      status: status.value,
      reason: reason.value.trim(),
    }

    const response = await changeAssetStatus(
      props.asset.id,
      payload,
    )

    emit('status-changed', response)
  } catch (error) {
    if (
      error?.response?.status === 422 &&
      error?.response?.data?.errors
    ) {
      fieldErrors.value =
        error.response.data.errors

      return
    }

    generalError.value =
      error?.response?.data?.message ||
      'No fue posible cambiar el estado del activo.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="modal-backdrop"
      @mousedown.self="close"
    >
      <section
        class="modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="status-title"
      >
        <header class="modal-header">
          <div>
            <h2 id="status-title">
              Cambiar estado del activo
            </h2>

            <p>
              Registra un cambio en la condición operativa
              manteniendo la trazabilidad del activo.
            </p>
          </div>

          <button
            type="button"
            class="close-button"
            aria-label="Cerrar"
            :disabled="submitting"
            @click="close"
          >
            ×
          </button>
        </header>

        <form @submit.prevent="submit">
          <div class="modal-body">
            <div class="asset-summary">
              <span class="asset-code">
                {{ asset?.code }}
              </span>

              <div>
                <strong>{{ asset?.name }}</strong>

                <small>
                  Estado actual:
                  <b>{{ currentStatusLabel }}</b>
                </small>
              </div>
            </div>

            <div
              v-if="generalError"
              class="general-error"
              role="alert"
            >
              {{ generalError }}
            </div>

            <div class="field">
              <label>
                Nuevo estado
                <span class="required">*</span>
              </label>

              <div class="status-options">
                <label
                  v-for="option in statusOptions"
                  :key="option.value"
                  class="status-option"
                  :class="[
                    `status-${option.value}`,
                    {
                      selected: status === option.value,
                      current: currentStatus === option.value,
                    },
                  ]"
                >
                  <input
                    v-model="status"
                    type="radio"
                    name="asset-status"
                    :value="option.value"
                    :disabled="currentStatus === option.value"
                  />

                  <span
                    class="status-indicator"
                    aria-hidden="true"
                  ></span>

                  <span class="status-content">
                    <strong>
                      {{ option.label }}

                      <small
                        v-if="currentStatus === option.value"
                        class="current-label"
                      >
                        Estado actual
                      </small>
                    </strong>

                    <small>
                      {{ option.description }}
                    </small>
                  </span>
                </label>
              </div>

              <small
                v-if="getFieldError('status')"
                class="field-error"
              >
                {{ getFieldError('status') }}
              </small>
            </div>

            <div
              v-if="selectedStatus"
              class="selected-summary"
            >
              <span>Cambio solicitado</span>

              <strong>
                {{ currentStatusLabel }}
                <span aria-hidden="true">→</span>
                {{ selectedStatus.label }}
              </strong>
            </div>

            <div class="field reason-field">
              <label for="status-reason">
                Motivo del cambio
                <span class="required">*</span>
              </label>

              <textarea
                id="status-reason"
                v-model="reason"
                rows="4"
                maxlength="1000"
                placeholder="Ej: El equipo presenta fallas de encendido y requiere diagnóstico técnico."
                :class="{
                  invalid: getFieldError('reason'),
                }"
              ></textarea>

              <div class="textarea-meta">
                <small
                  v-if="getFieldError('reason')"
                  class="field-error"
                >
                  {{ getFieldError('reason') }}
                </small>

                <small class="counter">
                  {{ reason.length }} / 1000
                </small>
              </div>
            </div>
          </div>

          <footer class="modal-footer">
            <button
              type="button"
              class="btn-secondary"
              :disabled="submitting"
              @click="close"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="btn-primary"
              :disabled="
                submitting ||
                !status ||
                isSameStatus
              "
            >
              {{
                submitting
                  ? 'Actualizando...'
                  : 'Confirmar cambio'
              }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: grid;
  place-items: center;
  padding: 24px;
  background: rgba(15, 23, 42, .55);
}

.modal {
  width: min(680px, 100%);
  max-height: calc(100vh - 48px);
  overflow: auto;
  background: #fff;
  border-radius: 18px;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .25);
}

.modal-header {
  display: flex;
  justify-content: space-between;
  gap: 20px;
  padding: 24px 26px 20px;
  border-bottom: 1px solid var(--color-border-subtle);
}

.modal-header h2 {
  margin: 0 0 6px;
  font-size: 20px;
  color: var(--color-text-main);
}

.modal-header p {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 13px;
  line-height: 1.5;
}

.close-button {
  border: 0;
  background: transparent;
  color: var(--color-text-subtle);
  font-size: 26px;
  cursor: pointer;
}

.modal-body {
  padding: 24px 26px;
}

.asset-summary {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-bottom: 22px;
  padding: 14px;
  background: #f8fafc;
  border: 1px solid var(--color-border-subtle);
  border-radius: 10px;
}

.asset-code {
  flex-shrink: 0;
  padding: 5px 8px;
  border-radius: 6px;
  background: var(--color-primary-light);
  color: var(--color-primary);
  font: 700 12px ui-monospace, monospace;
}

.asset-summary strong,
.asset-summary small {
  display: block;
}

.asset-summary small {
  margin-top: 3px;
  color: var(--color-text-muted);
}

.asset-summary small b {
  color: var(--color-text-main);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 9px;
}

.field > label {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-main);
}

.required {
  color: #dc2626;
}

.status-options {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.status-option {
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 11px;
  padding: 14px;
  border: 1px solid var(--color-border);
  border-radius: 11px;
  background: #fff;
  cursor: pointer;
  transition:
    border-color .15s ease,
    box-shadow .15s ease,
    background .15s ease;
}

.status-option:hover:not(.current) {
  border-color: #94a3b8;
}

.status-option input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}

.status-option.selected {
  border-color: var(--color-primary);
  background: #f8fbff;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, .08);
}

.status-option.current {
  cursor: not-allowed;
  opacity: .65;
  background: #f8fafc;
}

.status-indicator {
  width: 10px;
  height: 10px;
  margin-top: 4px;
  flex: 0 0 10px;
  border-radius: 50%;
  background: #94a3b8;
}

.status-operational .status-indicator {
  background: #16a34a;
}

.status-pending_review .status-indicator {
  background: #d97706;
}

.status-faulty .status-indicator {
  background: #dc2626;
}

.status-maintenance .status-indicator {
  background: #2563eb;
}

.status-content {
  min-width: 0;
}

.status-content > strong {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  margin-bottom: 5px;
  color: var(--color-text-main);
  font-size: 13px;
}

.status-content > small {
  display: block;
  color: var(--color-text-muted);
  font-size: 11px;
  line-height: 1.45;
}

.current-label {
  padding: 2px 6px;
  border-radius: 999px;
  background: #e2e8f0;
  color: #64748b;
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
}

.selected-summary {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 15px;
  margin-top: 18px;
  padding: 12px 14px;
  border-radius: 9px;
  background: #f8fafc;
  border: 1px solid var(--color-border-subtle);
  font-size: 12px;
}

.selected-summary > span {
  color: var(--color-text-muted);
}

.selected-summary strong {
  display: flex;
  align-items: center;
  gap: 7px;
  color: var(--color-text-main);
}

.selected-summary strong span {
  color: var(--color-primary);
}

.reason-field {
  margin-top: 20px;
}

.field textarea {
  width: 100%;
  box-sizing: border-box;
  resize: vertical;
  border: 1px solid var(--color-border);
  border-radius: 9px;
  padding: 11px 12px;
  background: #fff;
  color: var(--color-text-main);
  font: inherit;
}

.field textarea:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
}

.field textarea.invalid {
  border-color: #dc2626;
}

.textarea-meta {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.field-error {
  color: #dc2626;
  font-size: 11px;
}

.counter {
  margin-left: auto;
  color: var(--color-text-subtle);
}

.general-error {
  margin-bottom: 18px;
  padding: 11px 13px;
  border: 1px solid #fecaca;
  border-radius: 9px;
  background: #fef2f2;
  color: #b91c1c;
  font-size: 13px;
}

.modal-footer {
  position: sticky;
  bottom: 0;
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 18px 26px;
  background: #fff;
  border-top: 1px solid var(--color-border-subtle);
}

.btn-primary,
.btn-secondary {
  padding: 10px 16px;
  border-radius: 9px;
  font-weight: 700;
  cursor: pointer;
}

.btn-primary {
  border: 1px solid var(--color-primary);
  background: var(--color-primary);
  color: #fff;
}

.btn-secondary {
  border: 1px solid var(--color-border);
  background: #fff;
  color: var(--color-text-main);
}

button:disabled {
  opacity: .6;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .modal-backdrop {
    padding: 12px;
  }

  .modal-header,
  .modal-body {
    padding-left: 18px;
    padding-right: 18px;
  }

  .status-options {
    grid-template-columns: 1fr;
  }

  .selected-summary {
    align-items: flex-start;
    flex-direction: column;
  }

  .modal-footer {
    padding: 15px 18px;
  }
}
</style>