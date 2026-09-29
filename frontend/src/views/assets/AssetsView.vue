<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import {
  getAssets,
  getAsset,
  getAreas,
  getAssetCategories,
} from '@/services/assetService'
import AssetTable from '@/components/assets/AssetTable.vue'
import AssetFormModal from '@/components/assets/AssetFormModal.vue'

const { can } = useAuth()
const route = useRoute()

// Estado de datos
const assets = ref([])
const areas = ref([])
const categories = ref([])
const loading = ref(false)
const error = ref('')

// Estado del Modal de Formulario (Creación y Edición)
const isModalOpen = ref(false)
const modalMode = ref('create')
const selectedAsset = ref(null)
const fetchingAsset = ref(false)

// Estado del feedback de éxito
const successMessage = ref('')
let successTimer = null

// Estado de paginación de Laravel
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0,
  from: 0,
  to: 0,
})

// Filtros y búsqueda
const searchInput = ref('')
const activeSearch = ref('')
const selectedStatus = ref('')
const selectedCategory = ref('')
const selectedAreaId = ref('')
const selectedLocationId = ref('')
const currentPage = ref(1)

// Control de debounce para búsqueda
let debounceTimer = null

/**
 * Ubicaciones disponibles según el área seleccionada actualmente.
 */
const availableLocations = computed(() => {
  if (!selectedAreaId.value) {
    return []
  }
  const area = areas.value.find(
    (a) => a.id === Number(selectedAreaId.value),
  )
  return area?.locations ?? []
})

/**
 * Determina si existe algún filtro o búsqueda activa.
 */
const hasActiveFilters = computed(() => {
  return Boolean(
    activeSearch.value ||
      selectedStatus.value ||
      selectedCategory.value ||
      selectedAreaId.value ||
      selectedLocationId.value,
  )
})

/**
 * Carga el catálogo de áreas y categorías dinámicamente desde el backend.
 */
const loadFilterCatalogs = async () => {
  try {
    const [areasData, categoriesData] = await Promise.all([
      getAreas(),
      getAssetCategories(),
    ])
    areas.value = areasData
    categories.value = categoriesData
  } catch (err) {
    console.error('Error al cargar catálogos de áreas o categorías:', err)
  }
}

/**
 * Consulta los activos al backend combinando búsqueda, filtros y paginación.
 */
const fetchAssets = async () => {
  loading.value = true
  error.value = ''

  try {
    const params = {
      search: activeSearch.value,
      status: selectedStatus.value,
      category: selectedCategory.value,
      area_id: selectedAreaId.value,
      location_id: selectedLocationId.value,
      page: currentPage.value,
    }

    const data = await getAssets(params)

    assets.value = data.data ?? []
    pagination.value = {
      current_page: data.current_page ?? 1,
      last_page: data.last_page ?? 1,
      total: data.total ?? 0,
      from: data.from ?? 0,
      to: data.to ?? 0,
    }
  } catch (err) {
    console.error('Error al consultar activos:', err)
    if (err.response?.status === 403) {
      error.value = 'No tienes permiso para consultar el inventario de activos.'
    } else if (err.response?.status === 422) {
      error.value =
        err.response?.data?.message ||
        'Los criterios de búsqueda o filtrado son inválidos.'
    } else if (!err.response) {
      error.value = 'No fue posible conectar con el servidor de SIGATI.'
    } else {
      error.value =
        err.response?.data?.message ||
        'Ocurrió un error inesperado al cargar los activos.'
    }
  } finally {
    loading.value = false
  }
}

/**
 * Manejador con debounce para el input de búsqueda (350 ms).
 */
const handleSearchInput = (e) => {
  const val = e.target.value
  searchInput.value = val

  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    activeSearch.value = val.trim()
    currentPage.value = 1
    fetchAssets()
  }, 350)
}

/**
 * Limpieza instantánea del término de búsqueda.
 */
const clearSearch = () => {
  clearTimeout(debounceTimer)
  searchInput.value = ''
  if (activeSearch.value !== '') {
    activeSearch.value = ''
    currentPage.value = 1
    fetchAssets()
  }
}

/**
 * Cambio de filtro por estado o categoría.
 */
const handleFilterChange = () => {
  currentPage.value = 1
  fetchAssets()
}

/**
 * Cambio de área: limpia la ubicación asociada y recarga.
 */
const handleAreaChange = () => {
  selectedLocationId.value = ''
  currentPage.value = 1
  fetchAssets()
}

/**
 * Cambio de ubicación dependiente.
 */
const handleLocationChange = () => {
  currentPage.value = 1
  fetchAssets()
}

/**
 * Navegación de página.
 */
const handlePageChange = (newPage) => {
  currentPage.value = newPage
  fetchAssets()
  // Scroll suave al inicio de la tabla
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

/**
 * Restablece todos los filtros y términos de búsqueda al estado inicial.
 */
const resetFilters = () => {
  clearTimeout(debounceTimer)
  searchInput.value = ''
  activeSearch.value = ''
  selectedStatus.value = ''
  selectedCategory.value = ''
  selectedAreaId.value = ''
  selectedLocationId.value = ''
  currentPage.value = 1
  fetchAssets()
}

/**
 * Despliega un mensaje de éxito temporal (toast) durante 4 segundos.
 */
const showSuccess = (msg) => {
  successMessage.value = msg
  clearTimeout(successTimer)
  successTimer = setTimeout(() => {
    successMessage.value = ''
  }, 4000)
}

/**
 * Abre el modal para registrar un nuevo activo.
 */
const openCreateModal = () => {
  modalMode.value = 'create'
  selectedAsset.value = null
  isModalOpen.value = true
}

/**
 * Consulta la versión más reciente del activo en el servidor y abre el modal de edición.
 */
const handleEditAsset = async (assetRow) => {
  fetchingAsset.value = true
  try {
    const freshAsset = await getAsset(assetRow.id)
    selectedAsset.value = freshAsset
    modalMode.value = 'edit'
    isModalOpen.value = true
  } catch (err) {
    console.error('Error al obtener activo para editar:', err)
    // Fallback: usar los datos de la fila de la tabla si falla la consulta detallada
    selectedAsset.value = assetRow
    modalMode.value = 'edit'
    isModalOpen.value = true
  } finally {
    fetchingAsset.value = false
  }
}

/**
 * Procesa la confirmación de guardado exitoso (creación o edición).
 */
const handleAssetSaved = async ({ asset, category, mode }) => {
  // Si la categoría es nueva, la incorporamos dinámicamente al catálogo
  if (category && !categories.value.includes(category)) {
    categories.value.push(category)
    categories.value.sort()
  }

  if (mode === 'create') {
    showSuccess('Activo registrado correctamente.')
    currentPage.value = 1
    await fetchAssets()
  } else {
    showSuccess('Activo actualizado correctamente.')
    await fetchAssets()
  }
}

onMounted(() => {
  loadFilterCatalogs()
  fetchAssets()

  if (route.query.action === 'create' && can('assets.create')) {
    openCreateModal()
  }
})

onUnmounted(() => {
  clearTimeout(debounceTimer)
  clearTimeout(successTimer)
})
</script>

<template>
  <div class="assets-view">
    <!-- Notificación accesible de éxito (Toast) -->
    <div
      v-if="successMessage"
      class="success-toast"
      role="status"
      aria-live="polite"
    >
      <div class="toast-content">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="toast-icon">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
          <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <span>{{ successMessage }}</span>
      </div>
      <button
        type="button"
        class="toast-close"
        title="Cerrar notificación"
        aria-label="Cerrar notificación"
        @click="successMessage = ''"
      >
        &times;
      </button>
    </div>

    <!-- Cabecera principal -->
    <header class="view-header">
      <div class="header-titles">
        <h1>Gestión de Activos</h1>
        <p class="header-subtitle">
          Inventario y control centralizado de los recursos tecnológicos de la institución.
        </p>
      </div>

      <!-- Botón Registrar Activo habilitado para usuarios con permiso assets.create -->
      <div v-if="can('assets.create')" class="header-actions">
        <button
          type="button"
          class="btn-new-asset"
          title="Registrar un nuevo activo en el inventario"
          @click="openCreateModal"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
          </svg>
          <span>Registrar activo</span>
        </button>
      </div>
    </header>

    <!-- Banner de error recuperable -->
    <div v-if="error" class="error-banner">
      <div class="error-content">
        <svg class="error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <span>{{ error }}</span>
      </div>
      <button type="button" class="btn-retry" @click="fetchAssets">
        Reintentar
      </button>
    </div>

    <!-- Barra de Búsqueda y Filtros -->
    <section class="filters-card" aria-label="Filtros de inventario">
      <!-- Fila superior: Búsqueda amplia y resumen de totales -->
      <div class="search-row">
        <div class="search-input-wrapper">
          <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
          </svg>
          <input
            id="asset-search"
            type="text"
            :value="searchInput"
            placeholder="Buscar por código, nombre, serie, marca, modelo, responsable, hostname o IP..."
            aria-label="Buscar activos"
            @input="handleSearchInput"
          />
          <button
            v-if="searchInput"
            type="button"
            class="clear-search-btn"
            title="Limpiar búsqueda"
            @click="clearSearch"
          >
            &times;
          </button>
        </div>

        <div class="summary-badge">
          <span v-if="loading" class="summary-loading">Actualizando...</span>
          <span v-else>
            <strong>{{ pagination.total }}</strong>
            {{ pagination.total === 1 ? 'activo encontrado' : 'activos encontrados' }}
          </span>
        </div>
      </div>

      <!-- Fila inferior: Controles selectores y botón de limpieza -->
      <div class="filters-row">
        <!-- Filtro Estado -->
        <div class="filter-group">
          <label for="filter-status">Estado</label>
          <select
            id="filter-status"
            v-model="selectedStatus"
            @change="handleFilterChange"
          >
            <option value="">Todos los estados</option>
            <option value="operational">Operativo</option>
            <option value="pending_review">Pendiente de revisión</option>
            <option value="faulty">Con falla</option>
            <option value="maintenance">En mantenimiento</option>
          </select>
        </div>

        <!-- Filtro Categoría -->
        <div class="filter-group">
          <label for="filter-category">Categoría</label>
          <select
            id="filter-category"
            v-model="selectedCategory"
            @change="handleFilterChange"
          >
            <option value="">Todas las categorías</option>
            <option
              v-for="cat in categories"
              :key="cat"
              :value="cat"
            >
              {{ cat }}
            </option>
          </select>
        </div>

        <!-- Filtro Área -->
        <div class="filter-group">
          <label for="filter-area">Área</label>
          <select
            id="filter-area"
            v-model="selectedAreaId"
            @change="handleAreaChange"
          >
            <option value="">Todas las áreas</option>
            <option
              v-for="area in areas"
              :key="area.id"
              :value="area.id"
            >
              {{ area.name }}
            </option>
          </select>
        </div>

        <!-- Filtro Ubicación (dependiente del Área) -->
        <div class="filter-group">
          <label for="filter-location">Ubicación</label>
          <select
            id="filter-location"
            v-model="selectedLocationId"
            :disabled="!selectedAreaId"
            @change="handleLocationChange"
          >
            <option value="">
              {{ selectedAreaId ? 'Todas las ubicaciones' : 'Selecciona un área primero' }}
            </option>
            <option
              v-for="loc in availableLocations"
              :key="loc.id"
              :value="loc.id"
            >
              {{ loc.name }}
            </option>
          </select>
        </div>

        <!-- Botón Limpiar Filtros -->
        <div class="filter-actions">
          <button
            type="button"
            class="btn-reset-filters"
            :disabled="!hasActiveFilters || loading"
            @click="resetFilters"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
              <path d="M3 3v5h5"></path>
            </svg>
            <span>Limpiar filtros</span>
          </button>
        </div>
      </div>
    </section>

    <!-- Tabla de Activos y Paginación -->
    <AssetTable
      :assets="assets"
      :loading="loading"
      :pagination="pagination"
      :has-active-filters="hasActiveFilters"
      @page-change="handlePageChange"
      @edit="handleEditAsset"
    />

    <!-- Modal de Registro y Edición de Activo -->
    <AssetFormModal
      :is-open="isModalOpen"
      :mode="modalMode"
      :asset="selectedAsset"
      :areas="areas"
      :categories="categories"
      @close="isModalOpen = false"
      @saved="handleAssetSaved"
    />
  </div>
</template>

<style scoped>
.assets-view {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

/* Cabecera */
.view-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  flex-wrap: wrap;
}

.header-titles h1 {
  font-size: 24px;
  font-weight: 700;
  color: var(--color-text-main);
  margin-bottom: 4px;
}

.header-subtitle {
  font-size: 14px;
  color: var(--color-text-muted);
}

.btn-new-asset {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background-color: var(--color-primary);
  color: #ffffff;
  border: 0;
  border-radius: var(--radius-md);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-new-asset svg {
  width: 16px;
  height: 16px;
}

.btn-new-asset.disabled {
  opacity: 0.6;
  cursor: not-allowed;
  background-color: #94a3b8;
}

.action-tag {
  font-size: 10px;
  background-color: rgba(255, 255, 255, 0.25);
  padding: 2px 6px;
  border-radius: 4px;
  text-transform: uppercase;
}

/* Error Banner */
.error-banner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 18px;
  background-color: var(--color-danger-bg);
  border: 1px solid var(--color-danger-border);
  border-radius: var(--radius-md);
  color: var(--color-danger);
  font-size: 14px;
}

.error-content {
  display: flex;
  align-items: center;
  gap: 10px;
}

.error-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.btn-retry {
  padding: 6px 14px;
  background-color: #ffffff;
  border: 1px solid var(--color-danger-border);
  border-radius: var(--radius-sm);
  color: var(--color-danger);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-retry:hover {
  background-color: #fee2e2;
}

/* Tarjeta de Filtros */
.filters-card {
  background: var(--color-bg-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 20px;
  box-shadow: var(--shadow-sm);
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* Fila de Búsqueda */
.search-row {
  display: flex;
  align-items: center;
  gap: 16px;
}

.search-input-wrapper {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 14px;
  width: 18px;
  height: 18px;
  color: var(--color-text-subtle);
  pointer-events: none;
}

.search-input-wrapper input {
  width: 100%;
  height: 44px;
  padding: 0 40px 0 42px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 14px;
  color: var(--color-text-main);
  background-color: #ffffff;
  outline: none;
  transition: all 0.15s ease;
}

.search-input-wrapper input:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.clear-search-btn {
  position: absolute;
  right: 12px;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: #f1f5f9;
  border: 0;
  border-radius: 50%;
  color: var(--color-text-muted);
  font-size: 16px;
  cursor: pointer;
  line-height: 1;
}

.clear-search-btn:hover {
  background-color: #e2e8f0;
  color: var(--color-text-main);
}

.summary-badge {
  flex-shrink: 0;
  font-size: 13px;
  color: var(--color-text-muted);
  background-color: #f8fafc;
  padding: 8px 14px;
  border-radius: var(--radius-md);
  border: 1px solid var(--color-border-subtle);
  white-space: nowrap;
}

.summary-badge strong {
  color: var(--color-text-main);
}

.summary-loading {
  color: var(--color-text-subtle);
  font-style: italic;
}

/* Fila de Filtros Desplegables */
.filters-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto;
  gap: 14px;
  align-items: flex-end;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.filter-group label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-muted);
}

.filter-group select {
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  font-size: 13px;
  color: var(--color-text-main);
  background-color: #ffffff;
  outline: none;
  cursor: pointer;
  transition: all 0.15s ease;
}

.filter-group select:focus {
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.filter-group select:disabled {
  background-color: #f8fafc;
  color: var(--color-text-subtle);
  cursor: not-allowed;
}

.filter-actions {
  display: flex;
  align-items: flex-end;
}

.btn-reset-filters {
  height: 40px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 0 14px;
  background-color: transparent;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  color: var(--color-text-muted);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-reset-filters svg {
  width: 14px;
  height: 14px;
}

.btn-reset-filters:hover:not(:disabled) {
  background-color: #f8fafc;
  color: var(--color-text-main);
  border-color: #cbd5e1;
}

.btn-reset-filters:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

@media (max-width: 900px) {
  .search-row {
    flex-direction: column;
    align-items: stretch;
  }

  .summary-badge {
    text-align: right;
  }

  .filters-row {
    grid-template-columns: 1fr 1fr;
  }

  .filter-actions {
    grid-column: span 2;
  }

  .btn-reset-filters {
    width: 100%;
    justify-content: center;
  }
}

@media (max-width: 600px) {
  .filters-row {
    grid-template-columns: 1fr;
  }

  .filter-actions {
    grid-column: span 1;
  }
}

/* Toast de éxito */
.success-toast {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 20px;
  background-color: var(--color-success-bg);
  border: 1px solid #bbf7d0;
  border-radius: var(--radius-md);
  color: var(--color-success);
  font-size: 14px;
  font-weight: 500;
  box-shadow: var(--shadow-md);
  animation: toastSlideDown 0.25s ease-out;
}

@keyframes toastSlideDown {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.toast-content {
  display: flex;
  align-items: center;
  gap: 10px;
}

.toast-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  color: var(--color-success);
}

.toast-close {
  background: transparent;
  border: 0;
  color: var(--color-success);
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
  padding: 0 4px;
  opacity: 0.7;
  transition: opacity 0.15s ease;
}

.toast-close:hover {
  opacity: 1;
}
</style>
