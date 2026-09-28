<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { getAsset, getAssetHistory, getAreas, getAssetCategories } from '@/services/assetService'
import AssetStatusBadge from '@/components/assets/AssetStatusBadge.vue'
import AssetHistory from '@/components/assets/AssetHistory.vue'
import AssetFormModal from '@/components/assets/AssetFormModal.vue'
import AssetTransferModal from '@/components/assets/AssetTransferModal.vue'
import AssetStatusModal from '@/components/assets/AssetStatusModal.vue'

const route = useRoute()
const router = useRouter()
const { can } = useAuth()

const asset = ref(null)
const loading = ref(true)
const pageError = ref(null)
const history = ref({ data: [], current_page: 1, last_page: 1 })
const historyLoading = ref(false)
const historyError = ref('')
const areas = ref([])
const categories = ref([])
const isModalOpen = ref(false)
const isTransferModalOpen = ref(false)
const isStatusModalOpen = ref(false)

const successMessage = ref('')
let successTimer = null

const assetId = computed(() => route.params.id)

const placeholder = (value, fallback = 'No registrado') => value === null || value === undefined || value === '' ? fallback : value

const formatDate = (value) => {
  if (!value) return 'No registrada'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('es-CO', { day:'numeric', month:'short', year:'numeric', hour:'numeric', minute:'2-digit' }).format(date)
}

const classifyError = (err) => {
  const status = err?.response?.status
  if (status === 404) return { status, title:'Activo no encontrado', message:'El activo solicitado no existe o ya no está disponible.' }
  if (status === 403) return { status, title:'Acceso restringido', message:'No tienes permisos para consultar este activo.' }
  return { status: status || 0, title:'No fue posible cargar el activo', message:'Ocurrió un problema al consultar la información. Puedes intentarlo nuevamente.' }
}

const loadAsset = async () => {
  loading.value = true
  pageError.value = null
  try { asset.value = await getAsset(assetId.value) }
  catch (err) { asset.value = null; pageError.value = classifyError(err) }
  finally { loading.value = false }
}

const loadHistory = async (page = 1) => {
  historyLoading.value = true
  historyError.value = ''
  try {
    const response = await getAssetHistory(assetId.value, page)
    history.value = response.history ?? { data: [], current_page:1, last_page:1 }
  } catch (err) {
    console.error('Error al cargar historial del activo:', err)
    historyError.value = err?.response?.status === 403 ? 'No tienes permisos para consultar estos movimientos.' : 'Intenta nuevamente en unos momentos.'
  } finally { historyLoading.value = false }
}

const loadCatalogs = async () => {
  try { [areas.value, categories.value] = await Promise.all([getAreas(), getAssetCategories()]) }
  catch (err) { console.error('No fue posible cargar catálogos para edición:', err) }
}

const openEdit = async () => {
  await loadCatalogs()
  isModalOpen.value = true
}
const openTransfer = async () => {
  await loadCatalogs()
  isTransferModalOpen.value = true
}

const showSuccess = (message) => {
  successMessage.value = message
  clearTimeout(successTimer)
  successTimer = setTimeout(() => { successMessage.value = '' }, 4000)
}

const handleSaved = async () => {
  isModalOpen.value = false
  showSuccess('Activo actualizado correctamente.')
  await Promise.all([loadAsset(), loadHistory(history.value.current_page || 1)])
}
const handleTransferred = async () => {
  isTransferModalOpen.value = false

  showSuccess('Activo trasladado correctamente.')

  await Promise.all([
    loadAsset(),
    loadHistory(1),
  ])
}

const handleStatusChanged = async () => {
  isStatusModalOpen.value = false

  showSuccess('Estado del activo actualizado correctamente.')

  await Promise.all([
    loadAsset(),
    loadHistory(1),
  ])
}

const retryAll = async () => {
  await loadAsset()
  if (asset.value) await loadHistory()
}

onMounted(async () => {
  await loadAsset()
  if (asset.value) await loadHistory()
})
onUnmounted(() => clearTimeout(successTimer))
</script>

<template>
  <div class="asset-detail-view">
    <div v-if="successMessage" class="success-toast" role="status" aria-live="polite">
      <span>✓ {{ successMessage }}</span><button type="button" aria-label="Cerrar notificación" @click="successMessage=''">×</button>
    </div>

    <div v-if="loading" class="detail-loading" aria-live="polite">
      <div class="skeleton sk-back"></div><div class="skeleton sk-title"></div><div class="skeleton sk-subtitle"></div>
      <div class="skeleton-grid"><div v-for="n in 4" :key="n" class="skeleton sk-card"></div></div>
    </div>

    <section v-else-if="pageError" class="page-state" role="alert">
      <div class="state-icon">!</div><h1>{{ pageError.title }}</h1><p>{{ pageError.message }}</p>
      <div class="state-actions"><button type="button" class="btn-secondary" @click="router.push({name:'assets'})">Volver a Activos</button><button v-if="pageError.status !== 404 && pageError.status !== 403" type="button" class="btn-primary" @click="retryAll">Reintentar</button></div>
    </section>

    <template v-else-if="asset">
      <button type="button" class="back-link" @click="router.push({name:'assets'})">← Volver a Activos</button>

      <header class="detail-header">
        <div class="identity">
          <div class="code-line"><span class="asset-code">{{ asset.code }}</span><AssetStatusBadge :status="asset.status" /></div>
          <h1>{{ asset.name }}</h1>
          <p>{{ asset.category }}<template v-if="asset.brand || asset.model"> · {{ [asset.brand, asset.model].filter(Boolean).join(' ') }}</template></p>
        </div>
        <div class="header-actions">
  <button
    v-if="can('assets.transfer')"
    type="button"
    class="btn-secondary"
    @click="openTransfer"
  >
    Trasladar
  </button>

  <button
    v-if="can('assets.change_status')"
    type="button"
    class="btn-secondary"
    @click="isStatusModalOpen = true"
  >
    Cambiar estado
  </button>

  <button
    v-if="can('assets.update')"
    type="button"
    class="btn-primary"
    @click="openEdit"
  >
    Editar activo
  </button>
</div>
      </header>

      <div class="detail-grid">
        <section class="info-card">
          <h2>Identificación</h2>
          <dl><div><dt>Código institucional</dt><dd>{{ asset.code }}</dd></div><div><dt>Nombre</dt><dd>{{ asset.name }}</dd></div><div><dt>Categoría</dt><dd>{{ asset.category }}</dd></div><div><dt>Número de serie</dt><dd>{{ placeholder(asset.serial_number) }}</dd></div></dl>
        </section>
        <section class="info-card">
          <h2>Fabricante</h2>
          <dl><div><dt>Marca</dt><dd>{{ placeholder(asset.brand) }}</dd></div><div><dt>Modelo</dt><dd>{{ placeholder(asset.model) }}</dd></div></dl>
        </section>
        <section class="info-card">
          <h2>Ubicación y asignación</h2>
          <dl><div><dt>Área</dt><dd>{{ placeholder(asset.area?.name, 'Sin área asignada') }}</dd></div><div><dt>Ubicación</dt><dd>{{ placeholder(asset.location?.name, 'Sin ubicación específica') }}</dd></div><div><dt>Funcionario responsable</dt><dd>{{ placeholder(asset.responsible_name, 'Sin asignar') }}</dd></div></dl>
        </section>
        <section class="info-card">
          <h2>Red y conectividad</h2>
          <dl><div><dt>Hostname</dt><dd class="technical">{{ placeholder(asset.hostname) }}</dd></div><div><dt>Dirección IP</dt><dd class="technical">{{ placeholder(asset.ip_address) }}</dd></div><div><dt>Dirección MAC</dt><dd class="technical">{{ placeholder(asset.mac_address) }}</dd></div></dl>
        </section>
        <section class="info-card wide-card">
          <h2>Observaciones</h2><p class="notes">{{ placeholder(asset.notes, 'Sin observaciones registradas.') }}</p>
        </section>
        <section class="info-card wide-card registry-card">
          <h2>Información del registro</h2><dl><div><dt>Fecha de registro</dt><dd>{{ formatDate(asset.created_at) }}</dd></div><div><dt>Última actualización</dt><dd>{{ formatDate(asset.updated_at) }}</dd></div></dl>
        </section>
      </div>

      <AssetHistory :history="history" :loading="historyLoading" :error="historyError" @retry="loadHistory(history.current_page || 1)" @page-change="loadHistory" />

      <AssetFormModal :is-open="isModalOpen" mode="edit" :asset="asset" :areas="areas" :categories="categories" @close="isModalOpen=false" @saved="handleSaved" />
      <AssetTransferModal
  :is-open="isTransferModalOpen"
  :asset="asset"
  :areas="areas"
  @close="isTransferModalOpen = false"
  @transferred="handleTransferred"
/>
      <AssetStatusModal
  :is-open="isStatusModalOpen"
  :asset="asset"
  @close="isStatusModalOpen = false"
  @status-changed="handleStatusChanged"
/> 
    </template>
  </div>
</template>

<style scoped>
.asset-detail-view{display:flex;flex-direction:column;gap:22px}.back-link{align-self:flex-start;border:0;background:transparent;color:var(--color-text-muted);font-weight:600;cursor:pointer;padding:2px 0}.back-link:hover{color:var(--color-primary)}
.detail-header{display:flex;justify-content:space-between;align-items:flex-start;gap:24px}.code-line{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.asset-code{font:700 13px ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--color-primary);background:var(--color-primary-light);border:1px solid var(--color-primary-border);padding:5px 9px;border-radius:7px}.identity h1{font-size:28px;margin:10px 0 6px;color:var(--color-text-main)}.identity p{margin:0;color:var(--color-text-muted);font-size:14px}.btn-primary,.btn-secondary{border-radius:9px;padding:10px 15px;font-weight:700;cursor:pointer}.btn-primary{background:var(--color-primary);color:#fff;border:1px solid var(--color-primary)}.btn-secondary{background:#fff;color:var(--color-text-main);border:1px solid var(--color-border)}
.detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.info-card{background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:22px}.info-card h2{margin:0 0 18px;font-size:15px;color:var(--color-text-main)}.info-card dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;margin:0}.info-card dl>div{min-width:0}.info-card dt{font-size:11px;text-transform:uppercase;letter-spacing:.45px;font-weight:700;color:var(--color-text-subtle);margin-bottom:5px}.info-card dd{margin:0;color:var(--color-text-main);font-size:14px;font-weight:600;overflow-wrap:anywhere}.technical{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px!important}.wide-card{grid-column:1/-1}.notes{white-space:pre-wrap;margin:0;color:var(--color-text-muted);font-size:14px;line-height:1.65}.registry-card dl{grid-template-columns:repeat(2,minmax(0,260px))}
.page-state{background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:70px 24px;text-align:center}.state-icon{width:52px;height:52px;border-radius:50%;display:grid;place-items:center;margin:0 auto 15px;background:#fef2f2;color:#dc2626;font-size:24px;font-weight:800}.page-state h1{margin:0 0 8px;font-size:22px}.page-state p{margin:0 auto 20px;max-width:520px;color:var(--color-text-muted)}.state-actions{display:flex;justify-content:center;gap:10px}.success-toast{position:fixed;top:88px;right:28px;z-index:1000;display:flex;gap:18px;align-items:center;background:#ecfdf5;color:#166534;border:1px solid #bbf7d0;border-radius:10px;padding:12px 15px;box-shadow:0 8px 24px rgba(15,23,42,.12);font-weight:600}.success-toast button{border:0;background:transparent;color:inherit;font-size:20px;cursor:pointer}
.detail-loading{display:flex;flex-direction:column;gap:14px}.skeleton{background:linear-gradient(90deg,#eef2f7 25%,#e2e8f0 50%,#eef2f7 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:8px}.sk-back{width:130px;height:18px}.sk-title{width:360px;max-width:70%;height:34px}.sk-subtitle{width:250px;height:18px}.skeleton-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:10px}.sk-card{height:190px}@keyframes shimmer{to{background-position:-200% 0}}
.header-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  flex-wrap: wrap;
} 
@media (max-width: 850px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .wide-card {
    grid-column: auto;
  }

  .detail-header {
    flex-direction: column;
  }

  .header-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .info-card dl,
  .registry-card dl {
    grid-template-columns: 1fr 1fr;
  }
}

@media (max-width: 560px) {
  .info-card dl,
  .registry-card dl {
    grid-template-columns: 1fr;
  }

  .identity h1 {
    font-size: 23px;
  }

  .info-card {
    padding: 18px;
  }

  .header-actions {
    width: 100%;
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions .btn-primary,
  .header-actions .btn-secondary {
    width: 100%;
    text-align: center;
  }

  .success-toast {
    left: 16px;
    right: 16px;
    top: 76px;
  }
}</style>
