<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { getAssets } from '@/services/assetService'
import { createTicket } from '@/services/ticketService'
import { TICKET_PRIORITY_LABELS } from '@/utils/tickets'

const router = useRouter()
const priorities = Object.entries(TICKET_PRIORITY_LABELS).map(([value, label]) => ({ value, label }))
const form = ref({
  reporter_name: '',
  reporter_email: '',
  reporter_phone: '',
  title: '',
  description: '',
  category: '',
  priority: 'medium',
  asset_id: null,
})
const fieldErrors = ref({})
const generalError = ref('')
const forbidden = ref(false)
const submitting = ref(false)

const assets = ref([])
const assetSearch = ref('')
const activeAssetSearch = ref('')
const assetsLoading = ref(false)
const assetError = ref('')
const assetPage = ref(1)
const assetLastPage = ref(1)
const selectedAsset = ref(null)
let searchTimer
let assetRequestId = 0

const selectedAssetId = computed(() => {
  const value = Number(form.value.asset_id)
  return Number.isInteger(value) && value > 0 ? value : null
})

const clearFieldError = (field) => {
  if (fieldErrors.value[field]) delete fieldErrors.value[field]
  if (Object.keys(fieldErrors.value).length === 0) generalError.value = ''
}

const phoneIsValid = (phone) => {
  if (!/^\+?[0-9 ()-]+$/.test(phone)) return false
  const digitCount = phone.replace(/\D/g, '').length
  return digitCount >= 7 && digitCount <= 15
}

const validate = () => {
  const errors = {}
  const name = form.value.reporter_name.trim()
  const title = form.value.title.trim()
  const description = form.value.description.trim()
  const category = form.value.category.trim()

  if (!name) errors.reporter_name = ['El nombre del solicitante es obligatorio.']
  else if (name.length < 2 || !/\p{L}/u.test(name)) errors.reporter_name = ['El nombre del solicitante no es válido.']
  else if (name.length > 150) errors.reporter_name = ['El nombre del solicitante no puede superar los 150 caracteres.']
  if (!title) errors.title = ['El título es obligatorio.']
  else if (title.length < 5) errors.title = ['El título debe tener al menos 5 caracteres.']
  else if (title.length > 150) errors.title = ['El título no puede superar los 150 caracteres.']
  if (!description) errors.description = ['La descripción es obligatoria.']
  else if (description.length < 10) errors.description = ['La descripción debe tener al menos 10 caracteres.']
  else if (description.length > 5000) errors.description = ['La descripción no puede superar los 5000 caracteres.']
  if (!category) errors.category = ['La categoría es obligatoria.']
  else if (category.length < 2) errors.category = ['La categoría debe tener al menos 2 caracteres.']
  else if (category.length > 100) errors.category = ['La categoría no puede superar los 100 caracteres.']

  const email = form.value.reporter_email.trim()
  if (email.length > 254) errors.reporter_email = ['El correo no puede superar los 254 caracteres.']
  else if (email && !/^[^\s@]+@[^\s@]+$/.test(email)) errors.reporter_email = ['Ingresa un correo electrónico válido.']
  const phone = form.value.reporter_phone.trim()
  if (phone.length > 30) errors.reporter_phone = ['El teléfono no puede superar los 30 caracteres.']
  else if (phone && !phoneIsValid(phone)) errors.reporter_phone = ['Ingresa un número de teléfono válido.']
  if (!priorities.some(({ value }) => value === form.value.priority)) errors.priority = ['Selecciona una prioridad válida.']
  if (form.value.asset_id !== null && selectedAssetId.value === null) errors.asset_id = ['Selecciona un activo válido o la opción sin activo.']

  fieldErrors.value = errors
  return Object.keys(errors).length === 0
}

const assetLabel = (asset) => [asset.code, asset.name, asset.category].filter(Boolean).join(' · ')
const assetArea = (asset) => asset.area?.name || 'Sin área registrada'

const loadAssets = async (page = 1) => {
  const requestId = ++assetRequestId
  assetsLoading.value = true
  assetError.value = ''
  try {
    const data = await getAssets({ search: activeAssetSearch.value, page })
    if (requestId !== assetRequestId) return
    assets.value = Array.isArray(data.data) ? data.data : []
    assetPage.value = data.current_page ?? 1
    assetLastPage.value = data.last_page ?? 1
  } catch (error) {
    if (requestId !== assetRequestId) return
    const status = error.response?.status
    if (status === 403) assetError.value = 'No tienes permiso para consultar los activos. Puedes crear el ticket sin asociar uno.'
    else if (status === 404) assetError.value = 'El catálogo de activos no está disponible. Puedes crear el ticket sin asociar uno.'
    else if (status !== 401) assetError.value = 'No fue posible cargar los activos. Puedes crear el ticket sin asociar uno.'
  } finally {
    if (requestId === assetRequestId) assetsLoading.value = false
  }
}

const searchAssets = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    activeAssetSearch.value = assetSearch.value.trim().slice(0, 100)
    loadAssets(1)
  }, 350)
}

const chooseAsset = (asset) => {
  selectedAsset.value = asset
  form.value.asset_id = asset.id
  clearFieldError('asset_id')
}

const clearAsset = () => {
  selectedAsset.value = null
  form.value.asset_id = null
  clearFieldError('asset_id')
}

const handleSubmit = async () => {
  if (submitting.value) return
  generalError.value = ''
  forbidden.value = false
  if (!validate()) return

  submitting.value = true
  const optional = (value) => value.trim() || null
  const payload = {
    asset_id: selectedAssetId.value,
    reporter_name: form.value.reporter_name.trim(),
    reporter_email: optional(form.value.reporter_email),
    reporter_phone: optional(form.value.reporter_phone),
    title: form.value.title.trim(),
    description: form.value.description.trim(),
    category: form.value.category.trim(),
    priority: form.value.priority,
  }

  try {
    const response = await createTicket(payload)
    const id = response.data?.ticket?.id
    if (!id) throw new Error('La respuesta no contiene el identificador del ticket.')
    await router.push({ name: 'ticket-detail', params: { id } })
  } catch (error) {
    const status = error.response?.status
    if (status === 422) {
      const backendErrors = error.response?.data?.errors ?? {}
      fieldErrors.value = Object.fromEntries(
        Object.entries(backendErrors).map(([field, messages]) => [
          field,
          [Array.isArray(messages) && typeof messages[0] === 'string' ? messages[0] : 'Revisa este campo.'],
        ]),
      )
      generalError.value = 'Revisa los campos indicados.'
    } else if (status === 403) {
      forbidden.value = true
      generalError.value = 'No tienes permiso para crear tickets.'
    } else if (status === 409) {
      generalError.value = error.response?.data?.message || 'No fue posible crear el ticket por un conflicto. Revisa la información e intenta nuevamente.'
    } else if (status !== 401) {
      generalError.value = status
        ? 'Ocurrió un problema en el servidor. Intenta nuevamente.'
        : 'No fue posible conectar con el servidor. Verifica tu conexión e intenta nuevamente.'
    }
  } finally {
    submitting.value = false
  }
}

onMounted(() => loadAssets())
onBeforeUnmount(() => clearTimeout(searchTimer))
</script>

<template>
  <div class="ticket-create">
    <header class="page-header">
      <div>
        <p class="eyebrow">Mesa de Ayuda</p>
        <h1>Nuevo ticket</h1>
        <p class="subtitle">Registra una nueva solicitud de soporte técnico.</p>
      </div>
      <RouterLink class="back-link" :to="{ name: 'tickets' }">← Volver a Mesa de Ayuda</RouterLink>
    </header>

    <section v-if="forbidden" class="restricted" role="alert">
      <span aria-hidden="true">!</span>
      <div><h2>Acceso restringido</h2><p>{{ generalError }}</p></div>
      <button type="button" @click="router.push({ name: 'tickets' })">Volver</button>
    </section>

    <form v-else class="form-card" novalidate @submit.prevent="handleSubmit">
      <div v-if="generalError" class="alert" role="alert">{{ generalError }}</div>

      <section class="form-section" aria-labelledby="request-heading">
        <div class="section-heading">
          <span>1</span><div><h2 id="request-heading">Solicitud</h2><p>Describe el requerimiento y su nivel de atención.</p></div>
        </div>
        <div class="fields-grid">
          <div class="field field--wide">
            <label for="title">Título <b>*</b></label>
            <input id="title" v-model="form.title" maxlength="150" autocomplete="off" :aria-invalid="Boolean(fieldErrors.title)" :aria-describedby="fieldErrors.title ? 'title-error' : undefined" @input="clearFieldError('title')">
            <p v-if="fieldErrors.title" id="title-error" class="field-error">{{ fieldErrors.title[0] }}</p>
          </div>
          <div class="field">
            <label for="category">Categoría <b>*</b></label>
            <input id="category" v-model="form.category" maxlength="100" autocomplete="off" placeholder="Ej. Redes, impresoras" :aria-invalid="Boolean(fieldErrors.category)" @input="clearFieldError('category')">
            <p v-if="fieldErrors.category" class="field-error">{{ fieldErrors.category[0] }}</p>
          </div>
          <div class="field">
            <label for="priority">Prioridad <b>*</b></label>
            <select id="priority" v-model="form.priority" :aria-invalid="Boolean(fieldErrors.priority)" @change="clearFieldError('priority')">
              <option v-for="priority in priorities" :key="priority.value" :value="priority.value">{{ priority.label }}</option>
            </select>
            <p v-if="fieldErrors.priority" class="field-error">{{ fieldErrors.priority[0] }}</p>
          </div>
          <div class="field field--wide">
            <div class="label-row"><label for="description">Descripción <b>*</b></label><small>{{ form.description.length }}/5000</small></div>
            <textarea id="description" v-model="form.description" rows="6" maxlength="5000" :aria-invalid="Boolean(fieldErrors.description)" @input="clearFieldError('description')"></textarea>
            <p v-if="fieldErrors.description" class="field-error">{{ fieldErrors.description[0] }}</p>
          </div>
        </div>
      </section>

      <section class="form-section" aria-labelledby="reporter-heading">
        <div class="section-heading">
          <span>2</span><div><h2 id="reporter-heading">Datos del solicitante</h2><p>Información de contacto de quien reporta la solicitud.</p></div>
        </div>
        <div class="fields-grid fields-grid--three">
          <div class="field">
            <label for="reporter_name">Nombre <b>*</b></label>
            <input id="reporter_name" v-model="form.reporter_name" maxlength="150" autocomplete="name" :aria-invalid="Boolean(fieldErrors.reporter_name)" @input="clearFieldError('reporter_name')">
            <p v-if="fieldErrors.reporter_name" class="field-error">{{ fieldErrors.reporter_name[0] }}</p>
          </div>
          <div class="field">
            <label for="reporter_email">Correo electrónico <em>Opcional</em></label>
            <input id="reporter_email" v-model="form.reporter_email" type="email" maxlength="254" autocomplete="email" :aria-invalid="Boolean(fieldErrors.reporter_email)" @input="clearFieldError('reporter_email')">
            <p v-if="fieldErrors.reporter_email" class="field-error">{{ fieldErrors.reporter_email[0] }}</p>
          </div>
          <div class="field">
            <label for="reporter_phone">Teléfono <em>Opcional</em></label>
            <input id="reporter_phone" v-model="form.reporter_phone" type="tel" inputmode="tel" maxlength="30" autocomplete="tel" :aria-invalid="Boolean(fieldErrors.reporter_phone)" @input="clearFieldError('reporter_phone')">
            <p v-if="fieldErrors.reporter_phone" class="field-error">{{ fieldErrors.reporter_phone[0] }}</p>
          </div>
        </div>
      </section>

      <section class="form-section" aria-labelledby="asset-heading">
        <div class="section-heading">
          <span>3</span><div><h2 id="asset-heading">Activo asociado</h2><p>La solicitud puede registrarse sin vincular un activo.</p></div>
        </div>
        <div class="asset-picker">
          <button type="button" class="no-asset" :class="{ selected: !selectedAssetId }" :aria-pressed="!selectedAssetId" @click="clearAsset">
            <strong>Sin activo asociado</strong><small>Registrar una solicitud general</small>
          </button>
          <div v-if="selectedAsset" class="selected-asset">
            <div><small>Activo seleccionado</small><strong>{{ assetLabel(selectedAsset) }}</strong><span>{{ assetArea(selectedAsset) }}</span></div>
            <button type="button" @click="clearAsset">Quitar</button>
          </div>
          <div class="asset-search">
            <label for="asset-search">Buscar activo por código, nombre, categoría u otros datos</label>
            <input id="asset-search" v-model="assetSearch" maxlength="100" placeholder="Escribe para buscar…" @input="searchAssets">
          </div>
          <p v-if="fieldErrors.asset_id" class="field-error">{{ fieldErrors.asset_id[0] }}</p>
          <div v-if="assetError" class="asset-message" role="status">{{ assetError }} <button type="button" @click="loadAssets(assetPage)">Reintentar</button></div>
          <div v-else-if="assetsLoading" class="asset-message" aria-live="polite">Buscando activos…</div>
          <div v-else-if="assets.length" class="asset-results">
            <button v-for="asset in assets" :key="asset.id" type="button" :class="{ selected: selectedAssetId === Number(asset.id) }" @click="chooseAsset(asset)">
              <strong>{{ asset.code }} · {{ asset.name }}</strong><span>{{ asset.category || 'Sin categoría' }} · {{ assetArea(asset) }}</span>
            </button>
          </div>
          <p v-else class="asset-message">No se encontraron activos con este criterio.</p>
          <nav v-if="!assetsLoading && !assetError && assetLastPage > 1" class="asset-pagination" aria-label="Páginas de activos">
            <button type="button" :disabled="assetPage <= 1" @click="loadAssets(assetPage - 1)">Anterior</button>
            <span>Página {{ assetPage }} de {{ assetLastPage }}</span>
            <button type="button" :disabled="assetPage >= assetLastPage" @click="loadAssets(assetPage + 1)">Siguiente</button>
          </nav>
        </div>
      </section>

      <footer class="actions">
        <button class="secondary" type="button" :disabled="submitting" @click="router.push({ name: 'tickets' })">Cancelar</button>
        <button class="primary" type="submit" :disabled="submitting">{{ submitting ? 'Creando ticket…' : 'Crear ticket' }}</button>
      </footer>
    </form>
  </div>
</template>

<style scoped>
.ticket-create{display:flex;flex-direction:column;gap:22px;max-width:1100px;margin:0 auto}.page-header{display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}.eyebrow{color:var(--color-primary);font-size:11px;font-weight:750;letter-spacing:.1em;text-transform:uppercase;margin-bottom:3px}h1{font-size:26px;line-height:1.25}.subtitle{margin-top:5px;color:var(--color-text-muted);font-size:14px}.back-link{color:var(--color-primary);font-size:13px;font-weight:700}.back-link:hover{text-decoration:underline}.form-card{background:var(--color-bg-surface);border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);overflow:hidden}.alert{margin:22px 24px 0;padding:13px 15px;background:var(--color-danger-bg);border:1px solid var(--color-danger-border);border-radius:var(--radius-md);color:var(--color-danger);font-size:13px}.form-section{padding:25px 28px;border-bottom:1px solid var(--color-border-subtle)}.section-heading{display:flex;gap:12px;align-items:flex-start;margin-bottom:20px}.section-heading>span{display:grid;place-items:center;width:28px;height:28px;flex:none;border-radius:50%;background:var(--color-primary-light);color:var(--color-primary);font-size:12px;font-weight:800}.section-heading h2{font-size:16px}.section-heading p{margin-top:2px;color:var(--color-text-muted);font-size:12px}.fields-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.fields-grid--three{grid-template-columns:1.2fr 1fr .8fr}.field{display:flex;flex-direction:column;min-width:0;gap:6px}.field--wide{grid-column:1/-1}.field label,.asset-search label{color:#334155;font-size:12px;font-weight:700}.field label b{color:var(--color-danger)}.field label em{margin-left:5px;color:var(--color-text-subtle);font-size:10px;font-style:normal;font-weight:600}.label-row{display:flex;justify-content:space-between;align-items:center}.label-row small{color:var(--color-text-subtle);font-size:10px}.field input,.field select,.field textarea,.asset-search input{width:100%;padding:10px 12px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;color:var(--color-text-main);font-size:13px;outline:none;transition:border-color .15s,box-shadow .15s}.field textarea{resize:vertical;min-height:125px}.field input:focus,.field select:focus,.field textarea:focus,.asset-search input:focus{border-color:var(--color-primary);box-shadow:0 0 0 3px rgba(37,99,235,.12)}[aria-invalid=true]{border-color:var(--color-danger)!important}.field-error{color:var(--color-danger);font-size:11px}.asset-picker{display:flex;flex-direction:column;gap:12px}.no-asset{padding:12px 14px;text-align:left;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-md);cursor:pointer}.no-asset strong,.no-asset small{display:block}.no-asset strong{font-size:13px}.no-asset small{margin-top:2px;color:var(--color-text-muted)}.no-asset.selected{background:var(--color-primary-light);border-color:var(--color-primary-border);box-shadow:0 0 0 1px var(--color-primary-border)}.selected-asset{display:flex;justify-content:space-between;gap:15px;align-items:center;padding:13px 15px;background:#f8fafc;border:1px solid var(--color-border);border-radius:var(--radius-md)}.selected-asset div>*{display:block}.selected-asset small{color:var(--color-primary);font-weight:700}.selected-asset strong{margin:2px 0;font-size:13px}.selected-asset span{color:var(--color-text-muted);font-size:11px}.selected-asset button,.asset-message button{border:0;background:transparent;color:var(--color-primary);font-weight:700;cursor:pointer}.asset-search{display:flex;flex-direction:column;gap:6px;margin-top:3px}.asset-results{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;max-height:240px;overflow:auto}.asset-results button{padding:11px 12px;text-align:left;background:#fff;border:1px solid var(--color-border);border-radius:8px;cursor:pointer;min-width:0}.asset-results button:hover,.asset-results button.selected{background:var(--color-primary-light);border-color:var(--color-primary-border)}.asset-results strong,.asset-results span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.asset-results strong{font-size:12px}.asset-results span{margin-top:3px;color:var(--color-text-muted);font-size:11px}.asset-message{padding:11px;color:var(--color-text-muted);font-size:12px;text-align:center}.asset-pagination{display:flex;justify-content:center;align-items:center;gap:13px;color:var(--color-text-muted);font-size:11px}.asset-pagination button{padding:6px 10px;background:#fff;border:1px solid var(--color-border);border-radius:6px;color:var(--color-primary);font-weight:700;cursor:pointer}.asset-pagination button:disabled{color:var(--color-text-subtle);cursor:not-allowed}.actions{display:flex;justify-content:flex-end;gap:10px;padding:20px 28px;background:#f8fafc}.actions button,.restricted button{padding:10px 17px;border-radius:8px;font-size:12px;font-weight:750;cursor:pointer}.actions button:disabled{opacity:.65;cursor:not-allowed}.secondary{background:#fff;border:1px solid #cbd5e1;color:#334155}.primary{min-width:130px;background:var(--color-primary);border:1px solid var(--color-primary);color:#fff}.primary:hover:not(:disabled){background:var(--color-primary-hover)}.restricted{display:flex;align-items:center;gap:15px;padding:24px;background:#fff;border:1px solid var(--color-danger-border);border-radius:var(--radius-lg)}.restricted>span{display:grid;place-items:center;width:38px;height:38px;border-radius:50%;background:var(--color-danger-bg);color:var(--color-danger);font-weight:800}.restricted div{flex:1}.restricted h2{font-size:17px}.restricted p{color:var(--color-text-muted);font-size:13px}.restricted button{background:#fff;border:1px solid var(--color-border)}button:focus-visible,.back-link:focus-visible{outline:3px solid rgba(37,99,235,.25);outline-offset:2px}@media(max-width:800px){.fields-grid--three{grid-template-columns:1fr 1fr}.fields-grid--three .field:first-child{grid-column:1/-1}}@media(max-width:600px){.ticket-create{gap:17px}.page-header{align-items:flex-start;flex-direction:column}.form-section{padding:21px 16px}.alert{margin:16px 16px 0}.fields-grid,.fields-grid--three{grid-template-columns:1fr;gap:15px}.fields-grid--three .field:first-child{grid-column:auto}.asset-results{grid-template-columns:1fr}.actions{padding:16px;flex-direction:column-reverse}.actions button{width:100%}.restricted{align-items:flex-start;flex-wrap:wrap}.restricted button{width:100%}}
</style>
