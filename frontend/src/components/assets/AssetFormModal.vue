<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { createAsset, updateAsset } from '@/services/assetService'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
  mode: {
    type: String,
    default: 'create', // 'create' | 'edit'
    validator: (v) => ['create', 'edit'].includes(v),
  },
  asset: {
    type: Object,
    default: null,
  },
  areas: {
    type: Array,
    default: () => [],
  },
  categories: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['close', 'saved'])

// Referencia al primer campo para accesibilidad de foco
const codeInputRef = ref(null)

// Estado del formulario
const form = ref({
  code: '',
  name: '',
  category: '',
  brand: '',
  model: '',
  serial_number: '',
  area_id: '',
  location_id: '',
  responsible_name: '',
  hostname: '',
  ip_address: '',
  mac_address: '',
  status: 'operational',
  notes: '',
})

// Control de envío y errores
const saving = ref(false)
const fieldErrors = ref({})
const generalError = ref('')

// Información visual no editable para modo edición
const readOnlyInfo = ref({
  areaName: '',
  locationName: '',
  status: '',
})

const isEditMode = computed(() => props.mode === 'edit')

/**
 * Ubicaciones dependientes del área seleccionada en el formulario
 */
const availableLocations = computed(() => {
  if (!form.value.area_id) return []
  const area = props.areas.find((a) => a.id === Number(form.value.area_id))
  return area?.locations ?? []
})

/**
 * Contador de caracteres para las notas
 */
const notesCharCount = computed(() => (form.value.notes ? form.value.notes.length : 0))

/**
 * Traduce el estado para visualización informativa en edición
 */
const statusLabel = computed(() => {
  const map = {
    operational: 'Operativo',
    pending_review: 'Pendiente de revisión',
    faulty: 'Con falla',
    maintenance: 'En mantenimiento',
  }
  return map[readOnlyInfo.value.status] || readOnlyInfo.value.status || 'No especificado'
})

/**
 * Limpia y reinicia el formulario
 */
const resetForm = () => {
  form.value = {
    code: '',
    name: '',
    category: '',
    brand: '',
    model: '',
    serial_number: '',
    area_id: '',
    location_id: '',
    responsible_name: '',
    hostname: '',
    ip_address: '',
    mac_address: '',
    status: 'operational',
    notes: '',
  }
  fieldErrors.value = {}
  generalError.value = ''
  readOnlyInfo.value = {
    areaName: '',
    locationName: '',
    status: '',
  }
}

/**
 * Carga los datos del activo al entrar en modo edición
 */
const populateForm = (data) => {
  if (!data) return
  form.value = {
    code: data.code || '',
    name: data.name || '',
    category: data.category || '',
    brand: data.brand || '',
    model: data.model || '',
    serial_number: data.serial_number || '',
    area_id: data.area_id || '',
    location_id: data.location_id || '',
    responsible_name: data.responsible_name || '',
    hostname: data.hostname || '',
    ip_address: data.ip_address || '',
    mac_address: data.mac_address || '',
    status: data.status || 'operational',
    notes: data.notes || '',
  }
  readOnlyInfo.value = {
    areaName: data.area?.name || 'Sin área asignada',
    locationName: data.location?.name || 'Sin ubicación específica',
    status: data.status || 'operational',
  }
  fieldErrors.value = {}
  generalError.value = ''
}

// Sincroniza al abrir el modal o cambiar el activo seleccionado
watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      if (isEditMode.value && props.asset) {
        populateForm(props.asset)
      } else {
        resetForm()
      }
      nextTick(() => {
        codeInputRef.value?.focus()
      })
    }
  },
  { immediate: true },
)

/**
 * Al cambiar de área en modo creación, se limpia la ubicación previa
 */
const handleAreaChange = () => {
  form.value.location_id = ''
  clearFieldError('area_id')
  clearFieldError('location_id')
}

/**
 * Limpia el error de validación de un campo cuando el usuario lo modifica
 */
const clearFieldError = (field) => {
  if (fieldErrors.value[field]) {
    delete fieldErrors.value[field]
  }
}

/**
 * Validación previa en cliente antes del envío (UX)
 */
const validateForm = () => {
  const errors = {}

  if (!form.value.code?.trim()) {
    errors.code = ['El código del activo es obligatorio.']
  } else if (form.value.code.length > 50) {
    errors.code = ['El código no puede superar los 50 caracteres.']
  }

  if (!form.value.name?.trim()) {
    errors.name = ['El nombre del activo es obligatorio.']
  } else if (form.value.name.length > 150) {
    errors.name = ['El nombre no puede superar los 150 caracteres.']
  }

  if (!form.value.category?.trim()) {
    errors.category = ['La categoría es obligatoria.']
  } else if (form.value.category.length > 80) {
    errors.category = ['La categoría no puede superar los 80 caracteres.']
  }

  if (form.value.serial_number && form.value.serial_number.length > 120) {
    errors.serial_number = ['El número de serie no puede superar los 120 caracteres.']
  }

  if (!isEditMode.value && !form.value.area_id) {
    errors.area_id = ['El área es obligatoria.']
  }

  if (form.value.notes && form.value.notes.length > 2000) {
    errors.notes = ['Las observaciones no pueden superar los 2000 caracteres.']
  }

  if (form.value.mac_address?.trim()) {
    const macRegex = /^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/
    if (!macRegex.test(form.value.mac_address.trim())) {
      errors.mac_address = ['El formato MAC debe ser XX:XX:XX:XX:XX:XX (ej. AA:BB:CC:11:22:33).']
    }
  }

  fieldErrors.value = errors
  return Object.keys(errors).length === 0
}

/**
 * Envío del formulario hacia el backend
 */
const handleSubmit = async () => {
  generalError.value = ''

  if (!validateForm()) {
    return
  }

  saving.value = true

  try {
    let result

    if (isEditMode.value) {
      // Modo Edición: Enviar ÚNICAMENTE los campos permitidos por PATCH /api/assets/{id}
      const payload = {
        code: form.value.code.trim(),
        name: form.value.name.trim(),
        category: form.value.category.trim(),
        brand: form.value.brand?.trim() || null,
        model: form.value.model?.trim() || null,
        serial_number: form.value.serial_number?.trim() || null,
        hostname: form.value.hostname?.trim() || null,
        ip_address: form.value.ip_address?.trim() || null,
        mac_address: form.value.mac_address?.trim() || null,
        notes: form.value.notes?.trim() || null,
      }

      result = await updateAsset(props.asset.id, payload)
    } else {
      // Modo Creación: Enviar contrato completo para POST /api/assets
      const payload = {
        code: form.value.code.trim(),
        name: form.value.name.trim(),
        category: form.value.category.trim(),
        brand: form.value.brand?.trim() || null,
        model: form.value.model?.trim() || null,
        serial_number: form.value.serial_number?.trim() || null,
        area_id: Number(form.value.area_id),
        location_id: form.value.location_id ? Number(form.value.location_id) : null,
        responsible_name: form.value.responsible_name?.trim() || null,
        hostname: form.value.hostname?.trim() || null,
        ip_address: form.value.ip_address?.trim() || null,
        mac_address: form.value.mac_address?.trim() || null,
        status: form.value.status || 'operational',
        notes: form.value.notes?.trim() || null,
      }

      result = await createAsset(payload)
    }

    emit('saved', {
      asset: result.asset ?? result,
      category: form.value.category.trim(),
      mode: props.mode,
    })
    emit('close')
  } catch (err) {
    console.error('Error al guardar activo:', err)
    if (err.response?.status === 422) {
      fieldErrors.value = err.response.data?.errors || {}
      generalError.value =
        err.response.data?.message || 'Por favor corrige los campos señalados en el formulario.'
    } else if (err.response?.status === 403) {
      generalError.value = 'No cuentas con autorización para ejecutar esta operación.'
    } else if (!err.response) {
      generalError.value = 'No fue posible conectar con el servidor de SIGATI.'
    } else {
      generalError.value =
        err.response.data?.message || 'Ocurrió un error inesperado al procesar el activo.'
    }
  } finally {
    saving.value = false
  }
}

/**
 * Cierre controlado del modal
 */
const handleClose = () => {
  if (saving.value) return // Evita cerrar durante proceso de guardado
  emit('close')
}

const handleKeydown = (e) => {
  if (e.key === 'Escape' && props.isOpen && !saving.value) {
    handleClose()
  }
}
</script>

<template>
  <div
    v-if="isOpen"
    class="modal-backdrop"
    @click.self="handleClose"
    @keydown="handleKeydown"
  >
    <div
      class="modal-container"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="'modal-title'"
    >
      <!-- Cabecera del Modal -->
      <header class="modal-header">
        <div class="header-text">
          <h2 id="modal-title">
            {{ isEditMode ? 'Editar Activo' : 'Registrar Nuevo Activo' }}
          </h2>
          <p class="header-subtitle">
            {{
              isEditMode
                ? 'Actualiza los datos descriptivos y técnicos del activo seleccionado.'
                : 'Ingresa los datos para incorporar un nuevo recurso al inventario institucional.'
            }}
          </p>
        </div>
        <button
          type="button"
          class="btn-close"
          :disabled="saving"
          title="Cerrar modal"
          aria-label="Cerrar modal"
          @click="handleClose"
        >
          &times;
        </button>
      </header>

      <!-- Mensaje de error general -->
      <div v-if="generalError" class="modal-alert-error" role="alert">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>{{ generalError }}</span>
      </div>

      <!-- Formulario con scroll interno -->
      <form class="modal-form" @submit.prevent="handleSubmit">
        <div class="form-body">
          <!-- SECCIÓN 1: IDENTIFICACIÓN -->
          <fieldset class="form-section">
            <legend class="section-title">1. Identificación Institucional</legend>
            <div class="fields-grid grid-3">
              <!-- Código -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.code }">
                <label for="asset-code">
                  Código institucional <span class="required">*</span>
                </label>
                <input
                  id="asset-code"
                  ref="codeInputRef"
                  v-model="form.code"
                  type="text"
                  maxlength="50"
                  placeholder="Ej: ACT-TI-001"
                  required
                  :disabled="saving"
                  @input="clearFieldError('code')"
                />
                <span v-if="fieldErrors.code" class="field-error-msg">
                  {{ fieldErrors.code[0] }}
                </span>
              </div>

              <!-- Nombre -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.name }">
                <label for="asset-name">
                  Nombre descriptivo <span class="required">*</span>
                </label>
                <input
                  id="asset-name"
                  v-model="form.name"
                  type="text"
                  maxlength="150"
                  placeholder="Ej: Laptop Dell Latitude 5420"
                  required
                  :disabled="saving"
                  @input="clearFieldError('name')"
                />
                <span v-if="fieldErrors.name" class="field-error-msg">
                  {{ fieldErrors.name[0] }}
                </span>
              </div>

              <!-- Categoría con Datalist (Sugerencias dinámicas + texto libre) -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.category }">
                <label for="asset-category">
                  Categoría <span class="required">*</span>
                </label>
                <input
                  id="asset-category"
                  v-model="form.category"
                  type="text"
                  list="categories-datalist"
                  maxlength="80"
                  placeholder="Ej: Cómputo, Impresoras, Redes..."
                  required
                  :disabled="saving"
                  @input="clearFieldError('category')"
                />
                <datalist id="categories-datalist">
                  <option
                    v-for="cat in categories"
                    :key="cat"
                    :value="cat"
                  ></option>
                </datalist>
                <span v-if="fieldErrors.category" class="field-error-msg">
                  {{ fieldErrors.category[0] }}
                </span>
              </div>
            </div>
          </fieldset>

          <!-- SECCIÓN 2: FABRICANTE -->
          <fieldset class="form-section">
            <legend class="section-title">2. Datos del Fabricante</legend>
            <div class="fields-grid grid-3">
              <!-- Marca -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.brand }">
                <label for="asset-brand">Marca</label>
                <input
                  id="asset-brand"
                  v-model="form.brand"
                  type="text"
                  maxlength="100"
                  placeholder="Ej: Dell, HP, Lenovo, Cisco..."
                  :disabled="saving"
                  @input="clearFieldError('brand')"
                />
                <span v-if="fieldErrors.brand" class="field-error-msg">
                  {{ fieldErrors.brand[0] }}
                </span>
              </div>

              <!-- Modelo -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.model }">
                <label for="asset-model">Modelo</label>
                <input
                  id="asset-model"
                  v-model="form.model"
                  type="text"
                  maxlength="100"
                  placeholder="Ej: Latitude 5420, ThinkPad E14..."
                  :disabled="saving"
                  @input="clearFieldError('model')"
                />
                <span v-if="fieldErrors.model" class="field-error-msg">
                  {{ fieldErrors.model[0] }}
                </span>
              </div>

              <!-- Serie -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.serial_number }">
                <label for="asset-serial">Número de serie</label>
                <input
                  id="asset-serial"
                  v-model="form.serial_number"
                  type="text"
                  maxlength="120"
                  placeholder="Ej: SN-9876543210"
                  :disabled="saving"
                  @input="clearFieldError('serial_number')"
                />
                <span v-if="fieldErrors.serial_number" class="field-error-msg">
                  {{ fieldErrors.serial_number[0] }}
                </span>
              </div>
            </div>
          </fieldset>

          <!-- SECCIÓN 3: ASIGNACIÓN Y UBICACIÓN -->
          <fieldset class="form-section">
            <legend class="section-title">3. Ubicación y Asignación</legend>

            <!-- Modo Creación: Campos editables -->
            <div v-if="!isEditMode" class="fields-grid grid-3">
              <!-- Área -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.area_id }">
                <label for="asset-area">
                  Área institucional <span class="required">*</span>
                </label>
                <select
                  id="asset-area"
                  v-model="form.area_id"
                  required
                  :disabled="saving"
                  @change="handleAreaChange"
                >
                  <option value="">Selecciona un área...</option>
                  <option
                    v-for="area in areas"
                    :key="area.id"
                    :value="area.id"
                  >
                    {{ area.name }}
                  </option>
                </select>
                <span v-if="fieldErrors.area_id" class="field-error-msg">
                  {{ fieldErrors.area_id[0] }}
                </span>
              </div>

              <!-- Ubicación -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.location_id }">
                <label for="asset-location">Ubicación específica</label>
                <select
                  id="asset-location"
                  v-model="form.location_id"
                  :disabled="!form.area_id || saving"
                  @change="clearFieldError('location_id')"
                >
                  <option value="">
                    {{ form.area_id ? 'Sin ubicación específica (opcional)' : 'Selecciona un área primero' }}
                  </option>
                  <option
                    v-for="loc in availableLocations"
                    :key="loc.id"
                    :value="loc.id"
                  >
                    {{ loc.name }}
                  </option>
                </select>
                <span v-if="fieldErrors.location_id" class="field-error-msg">
                  {{ fieldErrors.location_id[0] }}
                </span>
              </div>

              <!-- Responsable -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.responsible_name }">
                <label for="asset-responsible">Funcionario responsable</label>
                <input
                  id="asset-responsible"
                  v-model="form.responsible_name"
                  type="text"
                  maxlength="150"
                  placeholder="Nombre de la persona a cargo"
                  :disabled="saving"
                  @input="clearFieldError('responsible_name')"
                />
                <span v-if="fieldErrors.responsible_name" class="field-error-msg">
                  {{ fieldErrors.responsible_name[0] }}
                </span>
              </div>
            </div>

            <!-- Modo Edición: la asignación solo cambia mediante Trasladar -->
            <div v-else class="fields-grid grid-3">
              <div class="form-field readonly-field">
                <label>Área asignada</label>
                <div class="readonly-value">{{ readOnlyInfo.areaName }}</div>
                <small class="field-note">
                  La reubicación de área se realiza mediante la opción <em>Trasladar</em>.
                </small>
              </div>

              <div class="form-field readonly-field">
                <label>Ubicación física</label>
                <div class="readonly-value">{{ readOnlyInfo.locationName }}</div>
                <small class="field-note">
                  La reubicación se realiza mediante la opción <em>Trasladar</em>.
                </small>
              </div>

              <div class="form-field readonly-field">
                <label>Funcionario responsable</label>
                <div class="readonly-value">
                  {{ form.responsible_name || 'Sin responsable asignado' }}
                </div>
                <small class="field-note">
                  El responsable se modifica mediante la opción <em>Trasladar</em>.
                </small>
              </div>
            </div>
          </fieldset>

          <!-- SECCIÓN 4: INFORMACIÓN DE RED -->
          <fieldset class="form-section">
            <legend class="section-title">4. Parámetros de Red y Conectividad</legend>
            <div class="fields-grid grid-3">
              <!-- Hostname -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.hostname }">
                <label for="asset-hostname">Hostname / Nombre de red</label>
                <input
                  id="asset-hostname"
                  v-model="form.hostname"
                  type="text"
                  maxlength="120"
                  placeholder="Ej: PC-TI-01"
                  :disabled="saving"
                  @input="clearFieldError('hostname')"
                />
                <span v-if="fieldErrors.hostname" class="field-error-msg">
                  {{ fieldErrors.hostname[0] }}
                </span>
              </div>

              <!-- Dirección IP -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.ip_address }">
                <label for="asset-ip">Dirección IP</label>
                <input
                  id="asset-ip"
                  v-model="form.ip_address"
                  type="text"
                  maxlength="45"
                  placeholder="Ej: 192.168.1.100"
                  :disabled="saving"
                  @input="clearFieldError('ip_address')"
                />
                <span v-if="fieldErrors.ip_address" class="field-error-msg">
                  {{ fieldErrors.ip_address[0] }}
                </span>
              </div>

              <!-- Dirección MAC -->
              <div class="form-field" :class="{ 'has-error': fieldErrors.mac_address }">
                <label for="asset-mac">Dirección MAC</label>
                <input
                  id="asset-mac"
                  v-model="form.mac_address"
                  type="text"
                  maxlength="17"
                  placeholder="Ej: AA:BB:CC:11:22:33"
                  :disabled="saving"
                  @input="clearFieldError('mac_address')"
                />
                <span v-if="fieldErrors.mac_address" class="field-error-msg">
                  {{ fieldErrors.mac_address[0] }}
                </span>
              </div>
            </div>
          </fieldset>

          <!-- SECCIÓN 5: ESTADO OPERATIVO -->
          <fieldset class="form-section">
            <legend class="section-title">5. Estado Operativo</legend>

            <!-- Modo Creación: Selector de estado inicial -->
            <div v-if="!isEditMode" class="fields-grid grid-2">
              <div class="form-field" :class="{ 'has-error': fieldErrors.status }">
                <label for="asset-status">
                  Estado inicial del activo <span class="required">*</span>
                </label>
                <select
                  id="asset-status"
                  v-model="form.status"
                  required
                  :disabled="saving"
                  @change="clearFieldError('status')"
                >
                  <option value="operational">Operativo</option>
                  <option value="pending_review">Pendiente de revisión</option>
                  <option value="faulty">Con falla</option>
                  <option value="maintenance">En mantenimiento</option>
                </select>
                <span v-if="fieldErrors.status" class="field-error-msg">
                  {{ fieldErrors.status[0] }}
                </span>
              </div>
            </div>

            <!-- Modo Edición: Informativo -->
            <div v-else class="fields-grid grid-2">
              <div class="form-field readonly-field">
                <label>Estado operativo actual</label>
                <div class="readonly-value">{{ statusLabel }}</div>
                <small class="field-note">
                  El estado operativo se actualiza mediante la opción <em>Cambiar estado</em> con motivo de auditoría.
                </small>
              </div>
            </div>
          </fieldset>

          <!-- SECCIÓN 6: OBSERVACIONES -->
          <fieldset class="form-section">
            <legend class="section-title">6. Observaciones y Notas</legend>
            <div class="form-field" :class="{ 'has-error': fieldErrors.notes }">
              <div class="label-with-counter">
                <label for="asset-notes">Notas adicionales</label>
                <span
                  class="char-counter"
                  :class="{ 'limit-reached': notesCharCount > 2000 }"
                >
                  {{ notesCharCount }} / 2000
                </span>
              </div>
              <textarea
                id="asset-notes"
                v-model="form.notes"
                rows="3"
                maxlength="2000"
                placeholder="Detalles sobre garantías, accesorios, configuración o estado físico..."
                :disabled="saving"
                @input="clearFieldError('notes')"
              ></textarea>
              <span v-if="fieldErrors.notes" class="field-error-msg">
                {{ fieldErrors.notes[0] }}
              </span>
            </div>
          </fieldset>
        </div>

        <!-- Footer de acciones -->
        <footer class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="saving"
            @click="handleClose"
          >
            Cancelar
          </button>
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="saving"
          >
            <span v-if="saving" class="spinner-sm" aria-hidden="true"></span>
            <span>{{ saving ? 'Guardando...' : (isEditMode ? 'Guardar Cambios' : 'Registrar Activo') }}</span>
          </button>
        </footer>
      </form>
    </div>
  </div>
</template>

<style scoped>
/* Backdrop */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(2px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  z-index: 100;
  overflow-y: auto;
}

/* Contenedor del Modal */
.modal-container {
  background-color: var(--color-bg-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-xl);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  width: 100%;
  max-width: 860px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  animation: modalFadeIn 0.2s ease-out;
}

@keyframes modalFadeIn {
  from {
    opacity: 0;
    transform: scale(0.97) translateY(8px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

/* Header */
.modal-header {
  padding: 22px 28px;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  background-color: #ffffff;
}

.header-text h2 {
  font-size: 19px;
  font-weight: 700;
  color: var(--color-text-main);
  margin-bottom: 4px;
}

.header-subtitle {
  font-size: 13px;
  color: var(--color-text-muted);
}

.btn-close {
  background: transparent;
  border: 0;
  font-size: 26px;
  line-height: 1;
  color: var(--color-text-subtle);
  cursor: pointer;
  padding: 0 4px;
  border-radius: var(--radius-sm);
  transition: color 0.15s ease;
}

.btn-close:hover:not(:disabled) {
  color: var(--color-text-main);
}

/* Error Banner */
.modal-alert-error {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 16px 28px 0;
  padding: 12px 16px;
  background-color: var(--color-danger-bg);
  border: 1px solid var(--color-danger-border);
  border-radius: var(--radius-md);
  color: var(--color-danger);
  font-size: 13px;
  line-height: 1.4;
}

.modal-alert-error svg {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

/* Formulario */
.modal-form {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
}

.form-body {
  padding: 24px 28px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 22px;
}

/* Secciones */
.form-section {
  border: 1px solid var(--color-border-subtle);
  border-radius: var(--radius-md);
  padding: 18px 20px;
  background-color: #fafbfc;
}

.section-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.6px;
  padding: 0 8px;
}

.fields-grid {
  display: grid;
  gap: 14px;
  margin-top: 4px;
}

.grid-3 {
  grid-template-columns: repeat(3, 1fr);
}

.grid-2 {
  grid-template-columns: repeat(2, 1fr);
}

/* Campos */
.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.form-field label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-main);
}

.required {
  color: var(--color-danger);
}

.form-field input,
.form-field select,
.form-field textarea {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  font-size: 13px;
  color: var(--color-text-main);
  background-color: #ffffff;
  outline: none;
  transition: all 0.15s ease;
}

.form-field input:focus,
.form-field select:focus,
.form-field textarea:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-field input:disabled,
.form-field select:disabled,
.form-field textarea:disabled {
  background-color: #f1f5f9;
  cursor: not-allowed;
  color: var(--color-text-subtle);
}

.form-field textarea {
  resize: vertical;
  min-height: 70px;
}

.label-with-counter {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.char-counter {
  font-size: 11px;
  color: var(--color-text-subtle);
}

.char-counter.limit-reached {
  color: var(--color-danger);
  font-weight: 700;
}

/* Errores de campo */
.form-field.has-error input,
.form-field.has-error select,
.form-field.has-error textarea {
  border-color: var(--color-danger);
  background-color: #fffafb;
}

.form-field.has-error input:focus,
.form-field.has-error select:focus,
.form-field.has-error textarea:focus {
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
}

.field-error-msg {
  font-size: 11px;
  color: var(--color-danger);
  font-weight: 500;
  margin-top: 2px;
}

/* Campos de solo lectura informativos */
.readonly-field {
  background-color: #f8fafc;
  padding: 10px 12px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
}

.readonly-value {
  font-size: 13px;
  font-weight: 600;
  color: var(--color-text-main);
  margin-top: 2px;
}

.field-note {
  font-size: 11px;
  color: var(--color-text-subtle);
  margin-top: 4px;
  line-height: 1.35;
}

.field-note em {
  font-style: normal;
  font-weight: 600;
  color: var(--color-primary);
}

/* Footer de Acciones */
.modal-footer {
  padding: 16px 28px;
  border-top: 1px solid var(--color-border);
  background-color: #ffffff;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 12px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 9px 18px;
  font-size: 13px;
  font-weight: 600;
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-secondary {
  background-color: #ffffff;
  border-color: var(--color-border);
  color: var(--color-text-main);
}

.btn-secondary:hover:not(:disabled) {
  background-color: #f8fafc;
  border-color: #cbd5e1;
}

.btn-primary {
  background-color: var(--color-primary);
  color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
  background-color: var(--color-primary-hover);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinner-sm {
  width: 14px;
  height: 14px;
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

/* Responsive */
@media (max-width: 768px) {
  .grid-3 {
    grid-template-columns: 1fr;
  }
  .grid-2 {
    grid-template-columns: 1fr;
  }
  .modal-header,
  .form-body,
  .modal-footer {
    padding-left: 18px;
    padding-right: 18px;
  }
}
</style>
