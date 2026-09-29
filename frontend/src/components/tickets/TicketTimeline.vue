<script setup>
import { computed, ref, watch } from 'vue'
import { getTicketEventLabel, getTicketStatusLabel } from '@/utils/tickets'

const props = defineProps({
  events: { type: Array, default: () => [] },
})

const INITIAL_EVENT_COUNT = 3
const formatter = new Intl.DateTimeFormat('es-CO', { dateStyle: 'medium', timeStyle: 'short' })
const showAll = ref(false)
const expandedEventId = ref(null)

const visibleEvents = computed(() =>
  showAll.value ? props.events : props.events.slice(0, INITIAL_EVENT_COUNT),
)
const hasHiddenEvents = computed(() => props.events.length > INITIAL_EVENT_COUNT)

const date = (value) => {
  const parsed = new Date(value)
  return value && !Number.isNaN(parsed.getTime())
    ? formatter.format(parsed)
    : 'Fecha no disponible'
}

const metadataRows = (event) => {
  const metadata = event.metadata || {}
  const labels = {
    diagnosis: 'Diagnóstico',
    solution: 'Solución',
    notes: 'Notas',
    source: 'Origen',
    priority: 'Prioridad',
  }

  return Object.entries(labels)
    .filter(([key]) => metadata[key])
    .map(([key, label]) => ({ label, value: metadata[key] }))
}

const hasDetails = (event) => Boolean(
  event.description
  || event.old_status
  || event.new_status
  || event.reason
  || metadataRows(event).length
  || event.old_assigned_technician
  || event.new_assigned_technician
  || event.user,
)

const detailId = (event) => `ticket-event-detail-${event.id}`
const isExpanded = (event) => expandedEventId.value === event.id

const toggleEvent = (event) => {
  if (!hasDetails(event)) return
  expandedEventId.value = isExpanded(event) ? null : event.id
}

const toggleTimeline = () => {
  if (showAll.value) {
    const initiallyVisibleIds = new Set(
      props.events.slice(0, INITIAL_EVENT_COUNT).map((event) => event.id),
    )
    if (!initiallyVisibleIds.has(expandedEventId.value)) expandedEventId.value = null
  }
  showAll.value = !showAll.value
}

watch(
  () => props.events.map((event) => event.id),
  (eventIds) => {
    if (expandedEventId.value !== null && !eventIds.includes(expandedEventId.value)) {
      expandedEventId.value = null
    }
  },
)
</script>

<template>
  <section class="timeline-card">
    <header>
      <h2>Línea de tiempo</h2>
      <p>Historial persistido de acciones sobre el ticket.</p>
    </header>

    <div v-if="!events.length" class="empty">No hay eventos registrados.</div>

    <TransitionGroup v-else name="timeline" tag="ol">
      <li v-for="event in visibleEvents" :key="event.id">
        <span class="marker" aria-hidden="true"></span>

        <article :class="{ expanded: isExpanded(event), interactive: hasDetails(event) }">
          <button
            v-if="hasDetails(event)"
            class="event-summary"
            type="button"
            :aria-expanded="isExpanded(event)"
            :aria-controls="detailId(event)"
            @click="toggleEvent(event)"
          >
            <span class="event-label">{{ getTicketEventLabel(event.event_type) }}</span>
            <time :datetime="event.created_at">{{ date(event.created_at) }}</time>
            <span class="chevron" aria-hidden="true">⌄</span>
          </button>

          <div v-else class="event-summary static">
            <span class="event-label">{{ getTicketEventLabel(event.event_type) }}</span>
            <time :datetime="event.created_at">{{ date(event.created_at) }}</time>
          </div>

          <div
            v-if="hasDetails(event)"
            :id="detailId(event)"
            class="detail-grid"
            :class="{ open: isExpanded(event) }"
            :aria-hidden="!isExpanded(event)"
          >
            <div class="detail-overflow">
              <div class="event-detail">
                <p v-if="event.description" class="description">{{ event.description }}</p>

                <div v-if="event.old_status || event.new_status" class="change">
                  <span v-if="event.old_status">{{ getTicketStatusLabel(event.old_status) }}</span>
                  <b v-if="event.old_status && event.new_status">→</b>
                  <strong v-if="event.new_status">{{ getTicketStatusLabel(event.new_status) }}</strong>
                </div>

                <p v-if="event.reason" class="reason"><b>Motivo:</b> {{ event.reason }}</p>

                <dl v-if="metadataRows(event).length">
                  <div v-for="row in metadataRows(event)" :key="row.label">
                    <dt>{{ row.label }}</dt>
                    <dd>{{ row.value }}</dd>
                  </div>
                </dl>

                <dl v-if="event.old_assigned_technician || event.new_assigned_technician" class="assignment-change">
                  <div v-if="event.old_assigned_technician">
                    <dt>Técnico anterior</dt>
                    <dd>{{ event.old_assigned_technician.name }}</dd>
                  </div>
                  <div v-if="event.new_assigned_technician">
                    <dt>Técnico nuevo</dt>
                    <dd>{{ event.new_assigned_technician.name }}</dd>
                  </div>
                </dl>

                <footer v-if="event.user">
                  <span>Realizado por</span>
                  <strong>{{ event.user.name || 'Usuario no disponible' }}</strong>
                  <small v-if="event.user.email">{{ event.user.email }}</small>
                </footer>
              </div>
            </div>
          </div>
        </article>
      </li>
    </TransitionGroup>

    <button
      v-if="hasHiddenEvents"
      class="timeline-toggle"
      type="button"
      :aria-expanded="showAll"
      @click="toggleTimeline"
    >
      {{ showAll ? 'Ver menos' : 'Ver más' }}
      <span :class="{ up: showAll }" aria-hidden="true">⌄</span>
    </button>
  </section>
</template>

<style scoped>
.timeline-card{background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);padding:24px}.timeline-card>header h2{margin:0 0 5px;font-size:18px}.timeline-card>header p{margin:0;color:var(--color-text-muted);font-size:13px}.empty{padding:42px 0;text-align:center;color:var(--color-text-muted)}ol{list-style:none;margin:24px 0 0;padding:0}li{position:relative;display:grid;grid-template-columns:22px minmax(0,1fr);gap:12px;padding-bottom:10px}li:not(:last-child)::before{content:'';position:absolute;left:6px;top:14px;bottom:-1px;width:2px;background:#e2e8f0}.marker{z-index:1;margin-top:16px;width:13px;height:13px;border:3px solid #dbeafe;border-radius:50%;background:var(--color-primary)}article{min-width:0;border:1px solid transparent;border-radius:10px;background:#fbfdff;transition:background-color 200ms ease,border-color 200ms ease,box-shadow 200ms ease}.event-summary{display:grid;grid-template-columns:minmax(0,1fr) auto 18px;align-items:center;gap:14px;width:100%;min-height:44px;padding:9px 14px;border:0;border-radius:9px;background:transparent;color:inherit;text-align:left}.event-summary:not(.static){cursor:pointer}.event-summary:focus-visible,.timeline-toggle:focus-visible{outline:3px solid var(--color-primary-border);outline-offset:2px}.event-label{min-width:0;font-size:14px;font-weight:700;overflow-wrap:anywhere}.event-summary time{white-space:nowrap;color:var(--color-text-subtle);font-size:12px}.chevron{color:var(--color-text-subtle);font-size:18px;line-height:1;transition:transform 200ms ease,color 200ms ease}.expanded{border-color:#dbeafe;background:#fff}.expanded .chevron{transform:rotate(180deg);color:var(--color-primary)}.detail-grid{display:grid;grid-template-rows:0fr;opacity:0;visibility:hidden;transition:grid-template-rows 200ms ease,opacity 180ms ease,visibility 200ms}.detail-grid.open{grid-template-rows:1fr;opacity:1;visibility:visible}.detail-overflow{min-height:0;overflow:hidden}.event-detail{margin:0 14px;padding:4px 0 15px;border-top:1px solid #e8edf3}.description{margin:12px 0 0;color:var(--color-text-muted);font-size:13px;white-space:pre-wrap}.change{display:flex;align-items:center;gap:8px;margin-top:12px;font-size:12px;color:#64748b}.change strong{color:var(--color-primary)}.reason{margin:12px 0 0;font-size:13px;color:#475569;white-space:pre-wrap}dl{display:grid;gap:8px;margin:13px 0 0;padding-top:12px;border-top:1px solid #e8edf3}dl div{display:grid;grid-template-columns:100px minmax(0,1fr);gap:10px}dt{font-size:11px;font-weight:700;color:#64748b}dd{margin:0;font-size:13px;white-space:pre-wrap;overflow-wrap:anywhere}.assignment-change{grid-template-columns:repeat(2,minmax(0,1fr))}.assignment-change div{grid-template-columns:1fr;gap:2px}article footer{display:flex;flex-wrap:wrap;gap:6px;margin-top:14px;color:#94a3b8;font-size:11px}article footer strong{color:#64748b}.timeline-toggle{display:flex;align-items:center;justify-content:center;gap:6px;min-height:40px;margin:6px auto 0;padding:7px 14px;border:0;border-radius:8px;background:transparent;color:var(--color-primary);font-size:13px;font-weight:750;cursor:pointer;transition:background-color 200ms ease,color 200ms ease}.timeline-toggle span{font-size:18px;line-height:1;transition:transform 200ms ease}.timeline-toggle span.up{transform:rotate(180deg)}.timeline-enter-active,.timeline-leave-active{transition:opacity 200ms ease,grid-template-rows 200ms ease}.timeline-enter-from,.timeline-leave-to{opacity:0}.timeline-leave-active{position:relative;width:100%}@media(hover:hover){article.interactive:hover{border-color:var(--color-primary-border);background:#fff;box-shadow:var(--shadow-sm)}article.interactive:hover .chevron{color:var(--color-primary);transform:translateY(1px)}article.interactive.expanded:hover .chevron{transform:rotate(180deg) translateY(-1px)}.timeline-toggle:hover{background:var(--color-primary-light);color:var(--color-primary-hover)}}@media(max-width:560px){.timeline-card{padding:18px}.event-summary{grid-template-columns:minmax(0,1fr) 18px;gap:4px 10px;padding:9px 12px}.event-summary time{grid-column:1;grid-row:2;white-space:normal}.event-summary .chevron{grid-column:2;grid-row:1/3}.event-summary.static{grid-template-columns:1fr}.event-detail{margin-inline:12px}.assignment-change{grid-template-columns:1fr}dl div{grid-template-columns:1fr;gap:2px}}@media(prefers-reduced-motion:reduce){article,.chevron,.detail-grid,.timeline-toggle,.timeline-toggle span,.timeline-enter-active,.timeline-leave-active{transition-duration:0.01ms!important}}
</style>
