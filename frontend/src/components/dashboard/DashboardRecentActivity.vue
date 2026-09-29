<script setup>
import { computed, ref } from 'vue'
import ExpandableHint from '@/components/common/ExpandableHint.vue'
import { getTicketEventLabel, getTicketStatusLabel } from '@/utils/tickets'

const props = defineProps({ activities:{type:Array,default:()=>[]} })
const showAll=ref(false), expanded=ref(null)
const visible=computed(()=>showAll.value?props.activities:props.activities.slice(0,4))
const formatter=new Intl.DateTimeFormat('es-CO',{dateStyle:'medium',timeStyle:'short'})
const label=(item)=>`${item.resource_type==='ticket'?'Ticket':'Activo'} ${item.resource_code ?? ''} · ${item.resource_type==='ticket'?getTicketEventLabel(item.event_type):item.description}`
const date=(value)=>{const parsed=new Date(value);return Number.isNaN(parsed.getTime())?'Fecha no disponible':formatter.format(parsed)}
const toggle=(id)=>{expanded.value=expanded.value===id?null:id}
</script>
<template>
 <section class="activity" aria-labelledby="activity-title">
  <header><div><h2 id="activity-title">Actividad reciente</h2><p>Últimos movimientos relevantes del sistema.</p></div></header>
  <p v-if="!activities.length" class="empty">Todavía no hay actividad registrada.</p>
  <ol v-else>
   <li v-for="item in visible" :key="item.id">
    <span class="dot" aria-hidden="true"></span>
    <article class="expandable-card" :class="{'expandable-card--open':expanded===item.id}">
     <button class="expandable-card__trigger" type="button" :aria-expanded="expanded===item.id" :aria-controls="`${item.id}-detail`" @click="toggle(item.id)"><span class="event">{{ label(item) }}</span><time :datetime="item.created_at">{{ date(item.created_at) }}</time><ExpandableHint :open="expanded===item.id" /></button>
     <div :id="`${item.id}-detail`" class="detail" :class="{expanded:expanded===item.id}" :aria-hidden="expanded!==item.id"><div><p v-if="item.description">{{ item.description }}</p><p v-if="item.old_status||item.new_status"><b>Estado:</b> {{ item.old_status?getTicketStatusLabel(item.old_status):'Sin estado' }} → {{ item.new_status?getTicketStatusLabel(item.new_status):'Sin cambio' }}</p><p v-if="item.reason"><b>Motivo:</b> {{ item.reason }}</p><small v-if="item.actor">Realizado por {{ item.actor }}</small><RouterLink :to="{name:item.resource_type==='ticket'?'ticket-detail':'asset-detail',params:{id:item.resource_id}}">Ver recurso →</RouterLink></div></div>
    </article>
   </li>
  </ol>
  <button v-if="activities.length>4" type="button" class="more" :aria-expanded="showAll" @click="showAll=!showAll">{{ showAll?'Ver menos actividad':'Ver más actividad' }}</button>
 </section>
</template>
<style scoped>
.activity{padding:21px 22px;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm)}h2{font-size:17px}header p,.empty{margin-top:3px;color:var(--color-text-muted);font-size:12px}.empty{padding:28px 0;text-align:center}ol{list-style:none;margin-top:16px}li{display:grid;grid-template-columns:14px minmax(0,1fr);gap:9px}.dot{width:8px;height:8px;margin-top:18px;border-radius:50%;background:var(--color-primary)}article{margin-bottom:5px;border:1px solid transparent;border-radius:9px;background:#fbfdff}article>button{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:3px 12px;width:100%;min-height:43px;padding:8px 11px;border:0;border-radius:9px;background:transparent;text-align:left}.event{font-size:13px;font-weight:700;overflow-wrap:anywhere}time{color:var(--color-text-subtle);font-size:11px;white-space:nowrap}.expandable-hint{grid-column:1/-1}.detail{display:grid;grid-template-rows:0fr;opacity:0;visibility:hidden;transition:grid-template-rows 200ms ease,opacity 180ms ease,visibility 200ms}.detail.expanded{grid-template-rows:1fr;opacity:1;visibility:visible}.detail>div{min-height:0;margin:0 11px;overflow:hidden;border-top:1px solid var(--color-border-subtle)}.detail p{margin-top:9px;color:var(--color-text-muted);font-size:12px;white-space:pre-wrap}.detail small{display:block;margin-top:9px;color:var(--color-text-subtle)}.detail a{display:inline-block;margin:9px 0 12px;color:var(--color-primary);font-size:12px;font-weight:700}.more{display:flex;align-items:center;margin:10px auto 0;padding:7px 12px;border:0;border-radius:8px;background:transparent;color:var(--color-primary);font-size:12px;font-weight:750;cursor:pointer}.more:hover{background:var(--color-primary-light)}button:focus-visible,a:focus-visible{outline:3px solid var(--color-primary-border);outline-offset:2px}@media(max-width:560px){.activity{padding:18px 15px}article>button{grid-template-columns:1fr}time{grid-row:2}}@media(prefers-reduced-motion:reduce){.detail{transition-duration:.01ms!important}}
</style>
