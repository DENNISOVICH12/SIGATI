<script setup>
import { computed, ref, watch } from 'vue'
import { transferAsset } from '@/services/assetService'
const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  asset: {
    type: Object,
    default: null,
  },
  areas: {
    type: Array,
    default: () => [],
  },
})
const emit = defineEmits([
  'close',
  'transferred',
])
const areaId = ref('')
const locationId = ref('')
const responsibleName = ref('')
const reason = ref('')
const submitting = ref(false)
const generalError = ref('')
const fieldErrors = ref({})
const selectedArea = computed(() => {
  return props.areas.find(
    (area) => String(area.id) === String(areaId.value),
  )
})
const locations = computed(() => {
  return selectedArea.value?.locations ?? []
})
const normalizeResponsibleName = (value) => {
  const normalized = String(value ?? '').trim()

  return normalized || null
}
const hasChanges = computed(() => {
  if (!props.asset) return false

  const normalizeId = (value) => {
    if (value === null || value === undefined || value === '') {
      return null
    }

    return Number(value)
  }

  const originalAreaId = normalizeId(
    props.asset.area_id ?? props.asset.area?.id
  )

  const originalLocationId = normalizeId(
    props.asset.location_id ?? props.asset.location?.id
  )

  const originalResponsible = normalizeResponsibleName(
    props.asset.responsible_name,
  )

  const newAreaId = normalizeId(areaId.value)
  const newLocationId = normalizeId(locationId.value)
  const newResponsible = normalizeResponsibleName(
    responsibleName.value,
  )

  return (
    originalAreaId !== newAreaId ||
    originalLocationId !== newLocationId ||
    originalResponsible !== newResponsible
  )
})
const resetForm = () => {
  areaId.value = props.asset?.area_id ?? props.asset?.area?.id ?? ''

  locationId.value =
    props.asset?.location_id ??
    props.asset?.location?.id ??
    ''

  responsibleName.value =
    props.asset?.responsible_name ?? ''

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
watch(areaId, (newAreaId, oldAreaId) => {
  if (
    oldAreaId !== undefined &&
    String(newAreaId) !== String(oldAreaId)
  ) {
    locationId.value = ''
  }
  fieldErrors.value.area_id = []
  fieldErrors.value.location_id = []
})
const close = () => {
  if (submitting.value) return
  emit('close')
}
const getFieldError = (field) => {
  return fieldErrors.value?.[field]?.[0] ?? ''
}
const submit = async () => {
  generalError.value = ''
  fieldErrors.value = {}

  if (!hasChanges.value) {
    generalError.value =
      'Debes modificar el área, la ubicación o el funcionario responsable para realizar un traslado.'

    return
  }

  if (!areaId.value) {
    fieldErrors.value.area_id = [
      'Debes seleccionar el área de destino.',
    ]
    return
  }

  if (!reason.value.trim()) {
    fieldErrors.value.reason = [
      'Debes indicar el motivo del traslado.',
    ]
    return
  }

  submitting.value = true

  try {
    const payload = {
      area_id: Number(areaId.value),
      location_id: locationId.value
        ? Number(locationId.value)
        : null,
      responsible_name: normalizeResponsibleName(
        responsibleName.value,
      ),
      reason: reason.value.trim(),
    }

    const response = await transferAsset(
      props.asset.id,
      payload,
    )

    emit('transferred', response)
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
      'No fue posible trasladar el activo.'
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
        aria-labelledby="transfer-title"
      >
        <header class="modal-header">
          <div>
            <h2 id="transfer-title">
              Trasladar activo
            </h2>
            <p>
              Modifica la ubicación institucional del activo
              conservando la trazabilidad del movimiento.
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
              <span>{{ asset?.code }}</span>
              <div>
                <strong>{{ asset?.name }}</strong>
                <small>
                  {{ asset?.area?.name || 'Sin área' }}
                  <template v-if="asset?.location?.name">
                    · {{ asset.location.name }}
                  </template>
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
            <div class="form-grid">
              <div class="field">
                <label for="transfer-area">
                  Área de destino
                  <span>*</span>
                </label>
                <select
                  id="transfer-area"
                  v-model="areaId"
                  :class="{ invalid: getFieldError('area_id') }"
                >
                  <option value="">
                    Selecciona un área...
                  </option>
                  <option
                    v-for="area in areas"
                    :key="area.id"
                    :value="area.id"
                  >
                    {{ area.name }}
                  </option>
                </select>
                <small
                  v-if="getFieldError('area_id')"
                  class="field-error"
                >
                  {{ getFieldError('area_id') }}
                </small>
              </div>
              <div class="field">
                <label for="transfer-location">
                  Ubicación específica
                </label>
                <select
                  id="transfer-location"
                  v-model="locationId"
                  :disabled="!areaId"
                  :class="{
                    invalid: getFieldError('location_id'),
                  }"
                >
                  <option value="">
                    Sin ubicación específica
                  </option>
                  <option
                    v-for="location in locations"
                    :key="location.id"
                    :value="location.id"
                  >
                    {{ location.name }}
                  </option>
                </select>
                <small
                  v-if="getFieldError('location_id')"
                  class="field-error"
                >
                  {{ getFieldError('location_id') }}
                </small>
              </div>
              <div class="field full">
                <label for="transfer-responsible">
                  Funcionario responsable
                </label>
                <input
                  id="transfer-responsible"
                  v-model="responsibleName"
                  type="text"
                  maxlength="150"
                  placeholder="Nombre de la persona responsable"
                  :class="{
                    invalid:
                      getFieldError('responsible_name'),
                  }"
                />
                <small
                  v-if="getFieldError('responsible_name')"
                  class="field-error"
                >
                  {{ getFieldError('responsible_name') }}
                </small>
              </div>
              <div class="field full">
                <label for="transfer-reason">
                  Motivo del traslado
                  <span>*</span>
                </label>
                <textarea
                  id="transfer-reason"
                  v-model="reason"
                  rows="4"
                  maxlength="1000"
                  placeholder="Ej: Reubicación del equipo a la oficina de facturación por requerimiento institucional."
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
  :disabled="submitting || !hasChanges"
>
              {{
                submitting
                  ? 'Trasladando...'
                  : 'Confirmar traslado'
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
  width: min(720px, 100%);
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
  padding: 14px;
  margin-bottom: 22px;
  background: #f8fafc;
  border: 1px solid var(--color-border-subtle);
  border-radius: 10px;
}
.asset-summary > span {
  font: 700 12px ui-monospace, monospace;
  color: var(--color-primary);
  background: var(--color-primary-light);
  padding: 5px 8px;
  border-radius: 6px;
}
.asset-summary strong,
.asset-summary small {
  display: block;
}
.asset-summary small {
  margin-top: 3px;
  color: var(--color-text-muted);
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
}
.field {
  display: flex;
  flex-direction: column;
  gap: 7px;
}
.field.full {
  grid-column: 1 / -1;
}
.field label {
  font-size: 13px;
  font-weight: 700;
  color: var(--color-text-main);
}
.field label span {
  color: #dc2626;
}
.field input,
.field select,
.field textarea {
  width: 100%;
  box-sizing: border-box;
  border: 1px solid var(--color-border);
  border-radius: 9px;
  padding: 11px 12px;
  background: #fff;
  color: var(--color-text-main);
  font: inherit;
}
.field textarea {
  resize: vertical;
}
.field input:focus,
.field select:focus,
.field textarea:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
}
.field .invalid {
  border-color: #dc2626;
}
.field-error {
  color: #dc2626;
  font-size: 11px;
}
.textarea-meta {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
.counter {
  margin-left: auto;
  color: var(--color-text-subtle);
}
.general-error {
  margin-bottom: 18px;
  padding: 11px 13px;
  color: #b91c1c;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 9px;
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
  border-radius: 9px;
  padding: 10px 16px;
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
  .form-grid {
    grid-template-columns: 1fr;
  }
  .field.full {
    grid-column: auto;
  }
  .modal-header,
  .modal-body {
    padding-left: 18px;
    padding-right: 18px;
  }
  .modal-footer {
    padding: 15px 18px;
  }
}
</style>
