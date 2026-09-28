<script setup>
import { computed } from 'vue'

const props = defineProps({
  history: { type: Object, default: () => ({ data: [] }) },
  loading: { type: Boolean, default: false },
  error: { type: String, default: '' },
})

const emit = defineEmits(['retry', 'page-change'])

const entries = computed(() => props.history?.data ?? [])

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
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('es-CO', {
    day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit',
  }).format(date)
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

const eventTitle = (action) => actionConfig[action]?.label || 'Movimiento registrado'
const eventTone = (action) => actionConfig[action]?.tone || 'default'
</script>

<template>
  <section class="history-card" aria-labelledby="asset-history-title">
    <div class="history-heading">
      <div>
        <h2 id="asset-history-title">Historial de trazabilidad</h2>
        <p>Movimientos y cambios registrados sobre este activo.</p>
      </div>
    </div>

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

    <ol v-else class="timeline">
      <li v-for="entry in entries" :key="entry.id" class="timeline-item">
        <div class="timeline-marker" :class="`tone-${eventTone(entry.action)}`" aria-hidden="true"></div>
        <article class="timeline-content">
          <div class="event-topline">
            <div>
              <h3>{{ eventTitle(entry.action) }}</h3>
              <time :datetime="entry.created_at">{{ formatDate(entry.created_at) }}</time>
            </div>
            <span class="event-action">{{ entry.action }}</span>
          </div>

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

          <div class="event-actor">
            <span>Realizado por</span>
            <strong>{{ entry.user?.name || 'Usuario no disponible' }}</strong>
            <small v-if="entry.user?.email">{{ entry.user.email }}</small>
          </div>
        </article>
      </li>
    </ol>

    <div v-if="!loading && !error && (history.last_page || 1) > 1" class="history-pagination">
      <button type="button" :disabled="history.current_page <= 1" @click="emit('page-change', history.current_page - 1)">← Anterior</button>
      <span>Página {{ history.current_page }} de {{ history.last_page }}</span>
      <button type="button" :disabled="history.current_page >= history.last_page" @click="emit('page-change', history.current_page + 1)">Siguiente →</button>
    </div>
  </section>
</template>

<style scoped>
.history-card { background:#fff; border:1px solid var(--color-border); border-radius:var(--radius-lg); padding:24px; }
.history-heading { margin-bottom:22px; }
.history-heading h2 { margin:0 0 5px; font-size:18px; color:var(--color-text-main); }
.history-heading p { margin:0; color:var(--color-text-muted); font-size:13px; }
.timeline { list-style:none; margin:0; padding:0; }
.timeline-item { position:relative; display:grid; grid-template-columns:24px 1fr; gap:14px; padding-bottom:24px; }
.timeline-item:not(:last-child)::before { content:''; position:absolute; left:6px; top:14px; bottom:0; width:2px; background:var(--color-border-subtle); }
.timeline-marker { width:14px; height:14px; border-radius:50%; margin-top:5px; border:3px solid #fff; box-shadow:0 0 0 2px #94a3b8; z-index:1; background:#94a3b8; }
.tone-created { background:#16a34a; box-shadow:0 0 0 2px #86efac; }
.tone-updated { background:#2563eb; box-shadow:0 0 0 2px #93c5fd; }
.tone-transferred { background:#7c3aed; box-shadow:0 0 0 2px #c4b5fd; }
.tone-status { background:#d97706; box-shadow:0 0 0 2px #fcd34d; }
.timeline-content { border:1px solid var(--color-border-subtle); border-radius:var(--radius-md); padding:16px 18px; background:#fbfdff; min-width:0; }
.event-topline { display:flex; justify-content:space-between; gap:12px; }
.event-topline h3 { margin:0 0 3px; font-size:14px; color:var(--color-text-main); }
.event-topline time { color:var(--color-text-muted); font-size:12px; }
.event-action { font:600 10px ui-monospace,monospace; color:var(--color-text-subtle); background:#eef2f7; border-radius:6px; padding:4px 7px; height:max-content; }
.event-description,.event-reason { margin:12px 0 0; font-size:13px; color:var(--color-text-muted); line-height:1.5; }
.changes-grid { margin-top:14px; border-top:1px solid var(--color-border-subtle); padding-top:10px; }
.change-row { display:grid; grid-template-columns:minmax(120px,180px) 1fr; gap:12px; padding:6px 0; font-size:12px; }
.change-field { color:var(--color-text-muted); font-weight:600; }
.change-values { display:flex; align-items:center; flex-wrap:wrap; gap:7px; min-width:0; }
.old-value { color:#b45309; text-decoration:line-through; overflow-wrap:anywhere; }.new-value { color:var(--color-text-main); font-weight:600; overflow-wrap:anywhere; }.arrow{color:var(--color-text-subtle)}
.event-actor { display:flex; align-items:baseline; flex-wrap:wrap; gap:6px; margin-top:14px; font-size:11px; color:var(--color-text-subtle); }.event-actor strong{color:var(--color-text-muted)}
.history-empty,.history-error { min-height:150px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:7px; text-align:center; color:var(--color-text-muted); }.history-empty strong,.history-error strong{color:var(--color-text-main)}
.history-error button,.history-pagination button { border:1px solid var(--color-border); background:#fff; border-radius:8px; padding:8px 12px; cursor:pointer; color:var(--color-primary); font-weight:600; }.history-error button{margin-top:6px}.history-pagination button:disabled{opacity:.45;cursor:not-allowed}
.history-pagination { display:flex; justify-content:center; align-items:center; gap:14px; border-top:1px solid var(--color-border-subtle); padding-top:16px; font-size:12px; color:var(--color-text-muted); }
.history-skeleton { display:grid;grid-template-columns:20px 1fr;gap:14px;margin:14px 0}.history-skeleton>span{width:14px;height:14px;border-radius:50%;background:#e2e8f0}.history-skeleton div{border:1px solid #eef2f7;border-radius:10px;padding:16px}.history-skeleton i{display:block;height:10px;background:#eef2f7;border-radius:5px;margin-bottom:10px}.history-skeleton i:last-child{width:55%;margin:0}
@media(max-width:640px){.history-card{padding:18px}.event-topline{flex-direction:column}.event-action{align-self:flex-start}.change-row{grid-template-columns:1fr;gap:3px}}
</style>
