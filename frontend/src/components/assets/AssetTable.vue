<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import AssetStatusBadge from './AssetStatusBadge.vue'

const { can } = useAuth()
const router = useRouter()

const props = defineProps({
  assets: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  pagination: {
    type: Object,
    default: () => ({
      current_page: 1,
      last_page: 1,
      total: 0,
      from: 0,
      to: 0,
    }),
  },
  hasActiveFilters: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['page-change', 'edit'])

/**
 * Calcula el rango de páginas a mostrar con elipsis para navegación amigable.
 */
const displayedPages = computed(() => {
  const current = props.pagination.current_page || 1
  const last = props.pagination.last_page || 1
  const delta = 1
  const range = []
  const rangeWithDots = []
  let l

  for (let i = 1; i <= last; i++) {
    if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
      range.push(i)
    }
  }

  for (const i of range) {
    if (l) {
      if (i - l === 2) {
        rangeWithDots.push(l + 1)
      } else if (i - l !== 1) {
        rangeWithDots.push('...')
      }
    }
    rangeWithDots.push(i)
    l = i
  }

  return rangeWithDots
})

const handlePageClick = (page) => {
  if (page === '...' || page === props.pagination.current_page || props.loading) {
    return
  }
  emit('page-change', page)
}
</script>

<template>
  <div class="table-wrapper">
    <div class="table-container">
      <table class="assets-table">
        <thead>
          <tr>
            <th scope="col" class="col-code">Código</th>
            <th scope="col" class="col-asset">Activo</th>
            <th scope="col" class="col-category">Categoría</th>
            <th scope="col" class="col-location">Área / Ubicación</th>
            <th scope="col" class="col-responsible">Responsable</th>
            <th scope="col" class="col-status">Estado</th>
            <th scope="col" class="col-actions text-right">Acciones</th>
          </tr>
        </thead>

        <!-- Estado de carga: Skeletons -->
        <tbody v-if="loading">
          <tr v-for="n in 5" :key="'skeleton-' + n" class="skeleton-row">
            <td><div class="skeleton-box code-skeleton"></div></td>
            <td>
              <div class="skeleton-box title-skeleton"></div>
              <div class="skeleton-box subtitle-skeleton"></div>
            </td>
            <td><div class="skeleton-box pill-skeleton"></div></td>
            <td>
              <div class="skeleton-box title-skeleton"></div>
              <div class="skeleton-box subtitle-skeleton"></div>
            </td>
            <td><div class="skeleton-box text-skeleton"></div></td>
            <td><div class="skeleton-box badge-skeleton"></div></td>
            <td><div class="skeleton-box action-skeleton"></div></td>
          </tr>
        </tbody>

        <!-- Estado sin datos (Empty state) -->
        <tbody v-else-if="assets.length === 0">
          <tr>
            <td colspan="7" class="empty-cell">
              <div class="empty-state">
                <div class="empty-icon">
                  <svg
                    v-if="hasActiveFilters"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    <line x1="8" y1="11" x2="14" y2="11"></line>
                  </svg>
                  <svg
                    v-else
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                  >
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                  </svg>
                </div>
                <h3 class="empty-title">
                  {{
                    hasActiveFilters
                      ? 'No se encontraron activos con los criterios seleccionados.'
                      : 'No hay activos registrados.'
                  }}
                </h3>
                <p class="empty-description">
                  {{
                    hasActiveFilters
                      ? 'Intenta modificando los términos de búsqueda o limpiando los filtros aplicados.'
                      : 'Aún no se han incorporado activos al sistema SIGATI.'
                  }}
                </p>
              </div>
            </td>
          </tr>
        </tbody>

        <!-- Listado de activos reales -->
        <tbody v-else>
          <tr v-for="asset in assets" :key="asset.id" class="asset-row">
            <!-- Código -->
            <td class="col-code">
              <span class="code-badge">{{ asset.code }}</span>
            </td>

            <!-- Activo: Nombre + Marca/Modelo -->
            <td class="col-asset">
              <div class="asset-name">{{ asset.name }}</div>
              <div
                v-if="asset.brand || asset.model"
                class="asset-meta"
              >
                <span>{{ [asset.brand, asset.model].filter(Boolean).join(' • ') }}</span>
              </div>
            </td>

            <!-- Categoría -->
            <td class="col-category">
              <span class="category-tag">{{ asset.category }}</span>
            </td>

            <!-- Área / Ubicación -->
            <td class="col-location">
              <div class="area-name">{{ asset.area?.name || 'Sin área asignada' }}</div>
              <div v-if="asset.location?.name" class="location-name">
                {{ asset.location.name }}
              </div>
            </td>

            <!-- Responsable -->
            <td class="col-responsible">
              <span
                :class="[
                  'responsible-text',
                  { 'unassigned': !asset.responsible_name }
                ]"
              >
                {{ asset.responsible_name || 'Sin asignar' }}
              </span>
            </td>

            <!-- Estado -->
            <td class="col-status">
              <AssetStatusBadge :status="asset.status" />
            </td>

            <!-- Acciones -->
            <!-- Acciones -->
<td class="col-actions text-right">
  <div class="table-actions">

    <!-- Ver ficha técnica -->
    <button
      v-if="can('assets.view')"
      type="button"
      class="btn-table-detail"
      title="Ver ficha técnica e historial del activo"
      aria-label="Ver detalle del activo"
      @click="router.push({ name: 'asset-detail', params: { id: asset.id } })"
    >
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        class="action-icon"
      >
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
        <circle cx="12" cy="12" r="3"></circle>
      </svg>

      <span>Ver detalle</span>
    </button>

    <!-- Editar -->
    <button
      v-if="can('assets.update')"
      type="button"
      class="btn-table-edit"
      title="Editar datos generales del activo"
      aria-label="Editar activo"
      @click="emit('edit', asset)"
    >
      <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="2"
        class="action-icon"
      >
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
      </svg>

      <span>Editar</span>
    </button>

    <span
      v-if="!can('assets.view') && !can('assets.update')"
      class="action-placeholder"
    >
      &mdash;
    </span>

  </div>
</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div v-if="pagination.total > 0 && !loading" class="pagination-footer">
      <div class="pagination-info">
        Mostrando del <strong>{{ pagination.from || 0 }}</strong> al
        <strong>{{ pagination.to || 0 }}</strong> de
        <strong>{{ pagination.total }}</strong> activos
      </div>

      <nav
        v-if="pagination.last_page > 1"
        class="pagination-nav"
        aria-label="Paginación de activos"
      >
        <button
          type="button"
          class="page-btn nav-btn"
          :disabled="pagination.current_page <= 1"
          @click="handlePageClick(pagination.current_page - 1)"
        >
          &larr; Anterior
        </button>

        <div class="page-numbers">
          <button
            v-for="(page, idx) in displayedPages"
            :key="idx"
            type="button"
            class="page-btn num-btn"
            :class="{
              'active': page === pagination.current_page,
              'ellipsis': page === '...'
            }"
            :disabled="page === '...'"
            @click="handlePageClick(page)"
          >
            {{ page }}
          </button>
        </div>

        <button
          type="button"
          class="page-btn nav-btn"
          :disabled="pagination.current_page >= pagination.last_page"
          @click="handlePageClick(pagination.current_page + 1)"
        >
          Siguiente &rarr;
        </button>
      </nav>
    </div>
  </div>
</template>

<style scoped>
.table-wrapper {
  background: var(--color-bg-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
}

.table-container {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.assets-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 14px;
}

.assets-table th {
  background-color: #f8fafc;
  color: var(--color-text-muted);
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--color-border);
  white-space: nowrap;
}

.assets-table td {
  padding: 16px 18px;
  border-bottom: 1px solid var(--color-border-subtle);
  vertical-align: middle;
}

.asset-row {
  transition: background-color 0.15s ease;
}

.asset-row:hover {
  background-color: #f8fafc;
}

.asset-row:last-child td {
  border-bottom: 0;
}

/* Columnas */
.col-code {
  width: 120px;
}

.code-badge {
  display: inline-block;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 13px;
  font-weight: 700;
  color: var(--color-primary);
  background-color: var(--color-primary-light);
  padding: 3px 8px;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-primary-border);
}

.col-asset {
  min-width: 200px;
}

.asset-name {
  font-weight: 600;
  color: var(--color-text-main);
  line-height: 1.35;
}

.asset-meta {
  font-size: 12px;
  color: var(--color-text-muted);
  margin-top: 3px;
}

.col-category {
  min-width: 130px;
}

.category-tag {
  display: inline-block;
  font-size: 12px;
  font-weight: 500;
  color: var(--color-text-muted);
  background-color: #f1f5f9;
  padding: 3px 8px;
  border-radius: var(--radius-sm);
}

.col-location {
  min-width: 180px;
}

.area-name {
  font-weight: 500;
  color: var(--color-text-main);
  line-height: 1.3;
}

.location-name {
  font-size: 12px;
  color: var(--color-text-subtle);
  margin-top: 2px;
}

.col-responsible {
  min-width: 160px;
}

.responsible-text {
  font-size: 13px;
  color: var(--color-text-main);
}

.responsible-text.unassigned {
  color: var(--color-text-subtle);
  font-style: italic;
}

.col-status {
  width: 150px;
}

.col-actions {
  width: 230px;
}

.table-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  white-space: nowrap;
}

.btn-table-detail,
.btn-table-edit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 6px 10px;
  background-color: #ffffff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-text-main);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-table-detail:hover,
.btn-table-edit:hover {
  background-color: var(--color-primary-light);
  border-color: var(--color-primary-border);
  color: var(--color-primary);
}

.text-right {
  text-align: right;
}

.btn-table-edit {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  background-color: #ffffff;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-text-main);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-table-edit:hover {
  background-color: var(--color-primary-light);
  border-color: var(--color-primary-border);
  color: var(--color-primary);
}

.action-icon {
  width: 14px;
  height: 14px;
}

.action-placeholder {
  color: var(--color-text-subtle);
  font-size: 14px;
}

/* Empty State */
.empty-cell {
  padding: 60px 24px;
  text-align: center;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  max-width: 420px;
  margin: 0 auto;
}

.empty-icon {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background-color: #f1f5f9;
  color: var(--color-text-subtle);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
}

.empty-icon svg {
  width: 26px;
  height: 26px;
}

.empty-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--color-text-main);
  margin-bottom: 6px;
}

.empty-description {
  font-size: 13px;
  color: var(--color-text-muted);
  line-height: 1.5;
}

/* Skeleton Loading */
.skeleton-row td {
  padding: 16px 18px;
}

.skeleton-box {
  background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
  border-radius: 4px;
}

.code-skeleton {
  width: 70px;
  height: 22px;
}

.title-skeleton {
  width: 140px;
  height: 16px;
  margin-bottom: 6px;
}

.subtitle-skeleton {
  width: 90px;
  height: 12px;
}

.pill-skeleton {
  width: 80px;
  height: 20px;
}

.text-skeleton {
  width: 110px;
  height: 14px;
}

.badge-skeleton {
  width: 90px;
  height: 24px;
  border-radius: 9999px;
}

.action-skeleton {
  width: 70px;
  height: 28px;
  border-radius: var(--radius-sm);
  margin-left: auto;
}

@keyframes shimmer {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

/* Paginación */
.pagination-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 20px;
  border-top: 1px solid var(--color-border);
  background-color: #ffffff;
  gap: 16px;
  flex-wrap: wrap;
}

.pagination-info {
  font-size: 13px;
  color: var(--color-text-muted);
}

.pagination-info strong {
  color: var(--color-text-main);
}

.pagination-nav {
  display: flex;
  align-items: center;
  gap: 6px;
}

.page-numbers {
  display: flex;
  align-items: center;
  gap: 4px;
}

.page-btn {
  height: 34px;
  padding: 0 10px;
  font-size: 13px;
  font-weight: 500;
  border: 1px solid var(--color-border);
  background-color: #ffffff;
  color: var(--color-text-main);
  border-radius: var(--radius-sm);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.page-btn:hover:not(:disabled):not(.ellipsis) {
  background-color: #f8fafc;
  border-color: #cbd5e1;
}

.page-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.num-btn {
  min-width: 34px;
  padding: 0 6px;
}

.num-btn.active {
  background-color: var(--color-primary);
  border-color: var(--color-primary);
  color: #ffffff;
  font-weight: 700;
}

.num-btn.ellipsis {
  border-color: transparent;
  background-color: transparent;
  cursor: default;
}

@media (max-width: 640px) {
  .pagination-footer {
    flex-direction: column;
    align-items: center;
  }
}
</style>
