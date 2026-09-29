<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
import { getTickets, getTicketStats } from '@/services/ticketService'
import TicketFilters from '@/components/tickets/TicketFilters.vue'
import TicketTable from '@/components/tickets/TicketTable.vue'

const route=useRoute(), router=useRouter(), { can }=useAuth()
const tickets=ref([]), stats=ref(null), loading=ref(false), statsLoading=ref(false), error=ref(''), statsError=ref('')
const pagination=ref({current_page:1,last_page:1,total:0,from:0,to:0})
const searchInput=ref(''), lastLoadedAt=ref(0)
const allowed={status:['new','assigned','in_progress','resolved','closed'],priority:['low','medium','high','critical'],sla:['on_time','warning','breached','met']}
let debounceTimer, requestId=0
const value=(key)=>typeof route.query[key]==='string'?route.query[key]:''
const valid=(key)=>allowed[key].includes(value(key))?value(key):''
const filters=computed(()=>({search:value('search').slice(0,150),status:valid('status'),priority:valid('priority'),assignment:value('unassigned')==='1'?'unassigned':'',sla:valid('sla'),assigned_to:/^\d+$/.test(value('assigned_to'))?value('assigned_to'):''}))
const page=computed(()=>{const number=Number.parseInt(value('page'),10);return Number.isInteger(number)&&number>0?number:1})
const hasActiveFilters=computed(()=>Object.values(filters.value).some(Boolean))
const params=computed(()=>({search:filters.value.search,status:filters.value.status,priority:filters.value.priority,unassigned:filters.value.assignment==='unassigned'?1:'',sla:filters.value.sla,assigned_to:filters.value.assigned_to,page:page.value}))
const cleanQuery=(changes={}, resetPage=true)=>{const next={...route.query,...changes};if(resetPage) delete next.page;Object.keys(next).forEach(k=>{if(next[k]===''||next[k]==null||next[k]===false||(k==='page'&&String(next[k])==='1'))delete next[k]});return next}
const updateFilter=(key,val)=>router.push({query:cleanQuery({[key]:val})})
const updateSearch=val=>{searchInput.value=val;clearTimeout(debounceTimer);debounceTimer=setTimeout(()=>updateFilter('search',val.trim()),350)}
const clearFilters=()=>{clearTimeout(debounceTimer);searchInput.value='';router.push({query:{}})}
const applyQuickFilter=changes=>router.push({query:cleanQuery({status:'',unassigned:'',sla:'',...changes})})
const errorMessage=err=>{if(!err.response)return 'No fue posible conectar con el servidor.';if(err.response.status===403)return 'No tienes permiso para consultar la bandeja de tickets.';if(err.response.status===422)return 'Los filtros seleccionados no son válidos. Limpia los filtros e intenta nuevamente.';return 'No fue posible cargar los tickets.'}
const loadTickets=async()=>{const id=++requestId;loading.value=true;error.value='';try{const {data}=await getTickets(params.value);if(id!==requestId)return;tickets.value=Array.isArray(data.data)?data.data:[];pagination.value={current_page:data.current_page??1,last_page:data.last_page??1,total:data.total??0,from:data.from??0,to:data.to??0};lastLoadedAt.value=Date.now()}catch(err){if(id===requestId)error.value=errorMessage(err)}finally{if(id===requestId)loading.value=false}}
const loadStats=async()=>{statsLoading.value=true;statsError.value='';try{const {data}=await getTicketStats();stats.value=data}catch{statsError.value='No fue posible actualizar el resumen.'}finally{statsLoading.value=false}}
const retry=()=>{loadTickets();if(statsError.value)loadStats()}
const changePage=next=>router.push({query:cleanQuery({page:String(next)},false)}).then(()=>window.scrollTo({top:0,behavior:'smooth'}))
const refreshIfStale=()=>{if(document.visibilityState==='visible'&&Date.now()-lastLoadedAt.value>=45000){loadTickets();loadStats()}}
watch(()=>route.query,()=>{searchInput.value=filters.value.search;loadTickets()},{deep:true})
onMounted(()=>{searchInput.value=filters.value.search;loadTickets();loadStats();window.addEventListener('focus',refreshIfStale);document.addEventListener('visibilitychange',refreshIfStale)})
onBeforeUnmount(()=>{clearTimeout(debounceTimer);window.removeEventListener('focus',refreshIfStale);document.removeEventListener('visibilitychange',refreshIfStale)})
const cards=computed(()=>[
 {label:'Nuevos',value:stats.value?.tickets?.by_status?.new,action:{status:'new'}},
 {label:'Sin asignar',value:stats.value?.tickets?.unassigned,action:{unassigned:'1'}},
 {label:'En proceso',value:stats.value?.tickets?.by_status?.in_progress,action:{status:'in_progress'}},
 {label:'SLA vencido',value:stats.value?.sla?.breached,action:{sla:'breached'},danger:true},
])
</script>
<template>
 <div class="tickets-view">
  <header><div><p class="eyebrow">Mesa de Ayuda</p><h1>Tickets</h1><p class="subtitle">Consulta y seguimiento de solicitudes de soporte técnico.</p></div><RouterLink v-if="can('tickets.create')" class="new" :to="{name:'ticket-create'}"><span aria-hidden="true">＋</span> Nuevo ticket</RouterLink></header>
  <section class="stats" aria-label="Resumen operativo">
   <template v-if="statsLoading"><div v-for="n in 4" :key="n" class="stat skeleton"></div></template>
   <template v-else-if="!statsError"><button v-for="card in cards" :key="card.label" type="button" class="stat" :class="{danger:card.danger}" @click="applyQuickFilter(card.action)"><span>{{ card.label }}</span><strong>{{ card.value ?? 0 }}</strong><small>Aplicar filtro →</small></button></template>
   <div v-else class="stats-error" role="status"><span>{{ statsError }}</span><button type="button" @click="loadStats">Reintentar</button></div>
  </section>
  <div v-if="error" class="error" role="alert"><span>{{ error }}</span><button type="button" @click="retry">Reintentar</button></div>
  <TicketFilters :search="searchInput" :status="filters.status" :priority="filters.priority" :assignment="filters.assignment" :sla="filters.sla" :has-active-filters="hasActiveFilters" :loading="loading" :total="pagination.total" @update:search="updateSearch" @update:status="updateFilter('status',$event)" @update:priority="updateFilter('priority',$event)" @update:assignment="updateFilter('unassigned',$event==='unassigned'?'1':'')" @update:sla="updateFilter('sla',$event)" @clear="clearFilters" />
  <TicketTable :tickets="tickets" :loading="loading" :pagination="pagination" :has-active-filters="hasActiveFilters" :can-create="can('tickets.create')" @page-change="changePage" @clear="clearFilters" @create="router.push({name:'ticket-create'})" />
 </div>
</template>
<style scoped>
.tickets-view{display:flex;flex-direction:column;gap:22px}header{display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap}.eyebrow{color:var(--color-primary);font-size:11px;font-weight:750;letter-spacing:.1em;text-transform:uppercase;margin-bottom:3px}h1{font-size:26px;line-height:1.25}.subtitle{margin-top:5px;color:#64748b;font-size:14px}.new{display:inline-flex;align-items:center;gap:7px;padding:10px 15px;border-radius:var(--radius-md);background:var(--color-primary);color:#fff;font-size:13px;font-weight:700}.new:focus-visible,.stat:focus-visible,.error button:focus-visible{outline:3px solid rgba(37,99,235,.25);outline-offset:2px}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}.stat{min-height:104px;padding:16px;text-align:left;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);cursor:pointer}.stat span{display:block;color:#64748b;font-size:12px;font-weight:650}.stat strong{display:block;margin:4px 0;font-size:26px;color:#0f172a}.stat small{color:var(--color-primary);font-weight:600}.stat.danger strong{color:#b91c1c}.stat.skeleton{border:0;background:linear-gradient(90deg,#e2e8f0,#f8fafc,#e2e8f0);background-size:200%;animation:pulse 1.3s infinite;cursor:default}@keyframes pulse{to{background-position:-200% 0}}.stats-error,.error{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 17px;border-radius:var(--radius-md)}.stats-error{grid-column:1/-1;background:#fff;border:1px solid var(--color-border);color:#64748b}.error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c}.stats-error button,.error button{padding:7px 12px;border:1px solid currentColor;border-radius:7px;background:#fff;color:inherit;font-weight:650;cursor:pointer}@media(max-width:850px){.stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){header{align-items:stretch}.new{justify-content:center}.stats{gap:10px}.stat{min-height:94px;padding:13px}.stat strong{font-size:22px}.error{align-items:flex-start;flex-direction:column}.error button{width:100%}}
</style>
