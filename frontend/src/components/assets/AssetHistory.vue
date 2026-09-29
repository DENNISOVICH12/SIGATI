<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
  history: { type: Object, default: () => ({ data: [] }) },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['retry', 'page-change'])

const INITIAL_EVENT_COUNT = 3
const formatter = new Intl.DateTimeFormat('es-CO', {
  day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit',
})
const showAll = ref(false)
const expandedEntryId = ref(null)

const entries = computed(() => props.history?.data ?? [])
const visibleEntries = computed(() =>
  showAll.value ? entries.value : entries.value.slice(0, INITIAL_EVENT_COUNT),
)
const hasHiddenEntries = computed(() => entries.value.length > INITIAL_EVENT_COUNT)

const actionConfig = {
  created: { label: 'Activo registrado', tone: 'created' },
  updated: { label: 'Información actualizada', tone: 'updated' },
  transferred: { label: 'Activo trasladado', tone: 'transferred' },
  status_changed: { label: 'Estado operativo actualizado', tone: 'status' },
}

const fieldLabels = {
  code: 'Código', name: 'Nombre', category: 'Categoría', brand: 'Marca', model: 'Modelo',
  serial_number: 'Número de serie', responsible_name: 'Responsable', hostname: 'Hostname',
  ip_address: 'Dirección IP', mac_address: 'Dirección MAC', notes: 'Notas',
  area_id: 'Área', area_name: 'Área', location_id: 'Ubicación', location_name: 'Ubicación', status: 'Estado',
}

const statusLabels = {
  operational: 'Operativo', pending_review: 'Pendiente de revisión', faulty: 'Con falla', maintenance: 'En mantenimiento',
}

const formatDate = (value) => {
  if (!value) return 'Fecha no disponible'
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : formatter.format(date)
}

const displayValue = (field, value) => {
  if (field === 'status') return statusLabels[value] || value || 'Sin registrar'
  if (value === null || value === undefined || value === '') return 'Sin registrar'
  return String(value)
}

const meaningfulKeys = (entry) => {
  const oldValues = entry.old_values || {}
  const newValues = entry.new_values || {}
  const keys = [...new Set([...Object.keys(oldValues), ...Object.keys(newValues)])]

  // Los IDs tienen una versión legible en los eventos de traslado/creación.
  return keys.filter((key) => {
    if (key === 'area_id' && ('area_name' in oldValues || 'area_name' in newValues)) return false
    if (key === 'location_id' && ('location_name' in oldValues || 'location_name' in newValues)) return false
    return true
  })
}

const hasDetails = (entry) => Boolean(
  entry.description
  || entry.reason
  || meaningfulKeys(entry).length
  || entry.user,
)
const eventTitle = (action) => actionConfig[action]?.label || 'Movimiento registrado'
const eventTone = (action) => actionConfig[action]?.tone || 'default'
const detailId = (entry) => `asset-history-detail-${entry.id}`
const isExpanded = (entry) => expandedEntryId.value === entry.id

const toggleEntry = (entry) => {
  if (!hasDetails(entry)) return
  expandedEntryId.value = isExpanded(entry) ? null : entry.id
}

const toggleHistory = () => {
  if (showAll.value) {
    const initiallyVisibleIds = new Set(
      entries.value.slice(0, INITIAL_EVENT_COUNT).map((entry) => entry.id),
    )
    if (!initiallyVisibleIds.has(expandedEntryId.value)) expandedEntryId.value = null
  }
  showAll.value = !showAll.value
}

watch(
  () => entries.value.map((entry) => entry.id),
  (entryIds) => {
    if (expandedEntryId.value !== null && !entryIds.includes(expandedEntryId.value)) {
      expandedEntryId.value = null
    }
  },
)
</script>

<template>
  <section class="history-card" aria-labelledby="asset-history-title">
    <header class="history-heading">
      <h2 id="asset-history-title">Historial de trazabilidad</h2>
      <p>Movimientos y cambios registrados sobre este activo.</p>
    </header>

    <div v-if="loading" class="history-loading" aria-live="polite">
      <div v-for="n in 3" :key="n" class="history-skeleton">
        <span></span><div><i></i><i></i></div>
      </div>
    </div>

    <div v-else-if="error" class="history-error" role="alert">
      <strong>No fue posible cargar el historial.</strong>
      <span>{{ error }}</span>
      <button type="button" @click="emit('retry')">Reintentar</button>
    </div>

    <div v-else-if="entries.length === 0" class="history-empty">
      <strong>Aún no hay movimientos registrados para este activo.</strong>
      <span>Los cambios auditables aparecerán aquí.</span>
    </div>

    <template v-else>
      <TransitionGroup name="timeline" tag="ol" class="timeline">
        <li v-for="entry in visibleEntries" :key="entry.id" class="timeline-item">
          <span class="timeline-marker" :class="`tone-${eventTone(entry.action)}`" aria-hidden="true"></span>

          <article :class="{ expanded: isExpanded(entry), interactive: hasDetails(entry) }">
            <button
              v-if="hasDetails(entry)"
              class="event-summary"
              type="button"
              :aria-expanded="isExpanded(entry)"
              :aria-controls="detailId(entry)"
              @click="toggleEntry(entry)"
            >
              <span class="event-label">{{ eventTitle(entry.action) }}</span>
              <time :datetime="entry.created_at">{{ formatDate(entry.created_at) }}</time>
              <span class="chevron" aria-hidden="true">⌄</span>
            </button>

            <div v-else class="event-summary static">
              <span class="event-label">{{ eventTitle(entry.action) }}</span>
              <time :datetime="entry.created_at">{{ formatDate(entry.created_at) }}</time>
            </div>

            <div
              v-if="hasDetails(entry)"
              :id="detailId(entry)"
              class="detail-grid"
              :class="{ open: isExpanded(entry) }"
              :aria-hidden="!isExpanded(entry)"
            >
              <div class="detail-overflow">
                <div class="event-detail">
                  <p v-if="entry.description" class="event-description">{{ entry.description }}</p>
                  <p v-if="entry.reason" class="event-reason"><strong>Motivo:</strong> {{ entry.reason }}</p>

                  <div v-if="meaningfulKeys(entry).length" class="changes-grid">
                    <div v-for="field in meaningfulKeys(entry)" :key="field" class="change-row">
                      <span class="change-field">{{ fieldLabels[field] || field }}</span>
                      <div class="change-values">
                        <template v-if="entry.old_values && Object.prototype.hasOwnProperty.call(entry.old_values, field)">
                          <span class="old-value">{{ displayValue(field, entry.old_values[field]) }}</span>
                          <span class="arrow" aria-hidden="true">→</span>
                        </template>
                        <span class="new-value">{{ displayValue(field, entry.new_values?.[field]) }}</span>
                      </div>
                    </div>
                  </div>

                  <footer v-if="entry.user" class="event-actor">
                    <span>Realizado por</span>
                    <strong>{{ entry.user.name || 'Usuario no disponible' }}</strong>
                    <small v-if="entry.user.email">{{ entry.user.email }}</small>
                  </footer>
                </div>
              </div>
            </div>
          </article>
        </li>
      </TransitionGroup>

      <button
        v-if="hasHiddenEntries"
        class="timeline-toggle"
        type="button"
        :aria-expanded="showAll"
        @click="toggleHistory"
      >
        {{ showAll ? 'Ver menos' : 'Ver más' }}
        <span :class="{ up: showAll }" aria-hidden="true">⌄</span>
      </button>
    </template>

    <div v-if="!loading && !error && (history.last_page || 1) > 1" class="history-pagination">
      <button type="button" :disabled="history.current_page <= 1" @click="emit('page-change', history.current_page - 1)">← Anterior</button>
      <span>Página {{ history.current_page }} de {{ history.last_page }}</span>
      <button type="button" :disabled="history.current_page >= history.last_page" @click="emit('page-change', history.current_page + 1)">Siguiente →</button>
    </div>
  </section>
</template>

<style scoped>
.history-card{background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:24px}.history-heading h2{margin:0 0 5px;font-size:18px;color:var(--color-text-main)}.history-heading p{margin:0;color:var(--color-text-muted);font-size:13px}.timeline{list-style:none;margin:24px 0 0;padding:0}.timeline-item{position:relative;display:grid;grid-template-columns:22px minmax(0,1fr);gap:12px;padding-bottom:10px}.timeline-item:not(:last-child)::before{content:'';position:absolute;left:6px;top:14px;bottom:-1px;width:2px;background:#e2e8f0}.timeline-marker{z-index:1;margin-top:16px;width:13px;height:13px;border:3px solid #dbeafe;border-radius:50%;background:var(--color-primary)}article{min-width:0;border:1px solid transparent;border-radius:10px;background:#fbfdff;transition:background-color 200ms ease,border-color 200ms ease,box-shadow 200ms ease}.event-summary{display:grid;grid-template-columns:minmax(0,1fr) auto 18px;align-items:center;gap:14px;width:100%;min-height:44px;padding:9px 14px;border:0;border-radius:9px;background:transparent;color:inherit;text-align:left}.event-summary:not(.static){cursor:pointer}.event-summary:focus-visible,.timeline-toggle:focus-visible{outline:3px solid var(--color-primary-border);outline-offset:2px}.event-label{min-width:0;font-size:14px;font-weight:700;overflow-wrap:anywhere}.event-summary time{white-space:nowrap;color:var(--color-text-subtle);font-size:12px}.chevron{color:var(--color-text-subtle);font-size:18px;line-height:1;transition:transform 200ms ease,color 200ms ease}.expanded{border-color:#dbeafe;background:#fff}.expanded .chevron{transform:rotate(180deg);color:var(--color-primary)}.detail-grid{display:grid;grid-template-rows:0fr;opacity:0;visibility:hidden;transition:grid-template-rows 200ms ease,opacity 180ms ease,visibility 200ms}.detail-grid.open{grid-template-rows:1fr;opacity:1;visibility:visible}.detail-overflow{min-height:0;overflow:hidden}.event-detail{margin:0 14px;padding:4px 0 15px;border-top:1px solid #e8edf3}.event-description,.event-reason{margin:12px 0 0;color:var(--color-text-muted);font-size:13px;line-height:1.5;white-space:pre-wrap}.changes-grid{margin-top:13px;padding-top:12px;border-top:1px solid #e8edf3}.change-row{display:grid;grid-template-columns:minmax(100px,180px) minmax(0,1fr);gap:10px;padding:5px 0}.change-field{color:#64748b;font-size:11px;font-weight:700}.change-values{display:flex;align-items:center;flex-wrap:wrap;gap:7px;min-width:0;font-size:13px}.old-value{color:#b45309;text-decoration:line-through;overflow-wrap:anywhere}.new-value{color:var(--color-text-main);font-weight:600;overflow-wrap:anywhere}.arrow{color:var(--color-text-subtle)}.event-actor{display:flex;flex-wrap:wrap;gap:6px;margin-top:14px;color:#94a3b8;font-size:11px}.event-actor strong{color:#64748b}.timeline-toggle{display:flex;align-items:center;justify-content:center;gap:6px;min-height:40px;margin:6px auto 0;padding:7px 14px;border:0;border-radius:8px;background:transparent;color:var(--color-primary);font-size:13px;font-weight:750;cursor:pointer;transition:background-color 200ms ease,color 200ms ease}.timeline-toggle span{font-size:18px;line-height:1;transition:transform 200ms ease}.timeline-toggle span.up{transform:rotate(180deg)}.timeline-enter-active,.timeline-leave-active{transition:opacity 200ms ease,grid-template-rows 200ms ease}.timeline-enter-from,.timeline-leave-to{opacity:0}.timeline-leave-active{position:relative;width:100%}.history-empty,.history-error{min-height:150px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;text-align:center;color:var(--color-text-muted)}.history-empty strong,.history-error strong{color:var(--color-text-main)}.history-error button,.history-pagination button{border:1px solid var(--color-border);background:#fff;border-radius:8px;padding:8px 12px;cursor:pointer;color:var(--color-primary);font-weight:600}.history-error button{margin-top:6px}.history-pagination button:disabled{opacity:.45;cursor:not-allowed}.history-pagination{display:flex;justify-content:center;align-items:center;gap:14px;margin-top:10px;border-top:1px solid var(--color-border-subtle);padding-top:16px;font-size:12px;color:var(--color-text-muted)}.history-skeleton{display:grid;grid-template-columns:20px 1fr;gap:14px;margin:14px 0}.history-skeleton>span{width:14px;height:14px;border-radius:50%;background:#e2e8f0}.history-skeleton div{border:1px solid #eef2f7;border-radius:10px;padding:16px}.history-skeleton i{display:block;height:10px;background:#eef2f7;border-radius:5px;margin-bottom:10px}.history-skeleton i:last-child{width:55%;margin:0}@media(hover:hover){article.interactive:hover{border-color:var(--color-primary-border);background:#fff;box-shadow:var(--shadow-sm)}article.interactive:hover .chevron{color:var(--color-primary);transform:translateY(1px)}article.interactive.expanded:hover .chevron{transform:rotate(180deg) translateY(-1px)}.timeline-toggle:hover{background:var(--color-primary-light);color:var(--color-primary-hover)}}@media(max-width:560px){.history-card{padding:18px}.event-summary{grid-template-columns:minmax(0,1fr) 18px;gap:4px 10px;padding:9px 12px}.event-summary time{grid-column:1;grid-row:2;white-space:normal}.event-summary .chevron{grid-column:2;grid-row:1/3}.event-summary.static{grid-template-columns:1fr}.event-detail{margin-inline:12px}.change-row{grid-template-columns:1fr;gap:2px}.history-pagination{flex-wrap:wrap}}@media(prefers-reduced-motion:reduce){article,.chevron,.detail-grid,.timeline-toggle,.timeline-toggle span,.timeline-enter-active,.timeline-leave-active{transition-duration:.01ms!important}}
</style>
