<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { getDashboard } from '@/services/dashboardService'
import DashboardCollapsible from '@/components/dashboard/DashboardCollapsible.vue'
import DashboardMetricCard from '@/components/dashboard/DashboardMetricCard.vue'
import DashboardRecentActivity from '@/components/dashboard/DashboardRecentActivity.vue'

const { user, can, hasRole } = useAuth()
const dashboard=ref(null), loading=ref(true), error=ref(''), openMetric=ref(null)
const sections=ref({attention:false,tickets:false,assets:false})
const isEngineer=computed(()=>hasRole('engineer'))
const roleLabel=computed(()=>isEngineer.value?'Ingeniero':'Técnico')
const statusLabels={new:'Nuevos',assigned:'Asignados',in_progress:'En proceso',resolved:'Resueltos',closed:'Cerrados'}
const assetLabels={operational:'Operativos',pending_review:'Pendientes de revisión',faulty:'Con falla',maintenance:'En mantenimiento'}
const openCount=computed(()=>dashboard.value?.tickets?.open ?? 0)
const attentionItems=computed(()=>{
 const a=dashboard.value?.attention ?? {}; const items=[]
 if(a.unassigned)items.push({label:`${a.unassigned} ${a.unassigned===1?'ticket sin asignar':'tickets sin asignar'}`,to:{name:'tickets',query:{unassigned:'1'}}})
 if(a.sla_breached)items.push({label:`${a.sla_breached} ${a.sla_breached===1?'ticket con SLA vencido':'tickets con SLA vencido'}`,to:{name:'tickets',query:{sla:'breached',...(isEngineer.value?{}:{assigned_to:String(user.value.id)})}}})
 if(a.resolved_pending_closure)items.push({label:`${a.resolved_pending_closure} ${a.resolved_pending_closure===1?'ticket resuelto pendiente':'tickets resueltos pendientes'} de cierre`,to:{name:'tickets',query:{status:'resolved'}}})
 if(a.problematic_assets)items.push({label:`${a.problematic_assets} ${a.problematic_assets===1?'activo requiere':'activos requieren'} revisión`,to:{name:'assets'}})
 return items
})
const attentionTotal=computed(()=>attentionItems.value.reduce((sum,item)=>sum+Number.parseInt(item.label,10),0))
const ticketQuery=computed(()=>isEngineer.value?{}:{assigned_to:String(user.value?.id ?? '')})
const load=async()=>{loading.value=true;error.value='';try{dashboard.value=await getDashboard()}catch(err){error.value=err.response?.status===403?'No tienes permisos para consultar el resumen operativo.':'No fue posible cargar el Dashboard. Las demás áreas de SIGATI continúan disponibles.'}finally{loading.value=false}}
const toggleMetric=(id)=>{openMetric.value=openMetric.value===id?null:id}
const toggleSection=(id)=>{sections.value[id]=!sections.value[id]}
onMounted(load)
</script>

<template>
 <div class="dashboard-view">
  <header class="welcome">
   <div><p class="eyebrow">Dashboard operativo</p><h1>Bienvenido, {{ user?.name ?? 'Usuario' }}</h1><p>Resumen operativo del sistema</p></div>
   <div class="meta"><span class="active"><i></i> Sesión activa</span><span class="role">{{ roleLabel }}</span></div>
  </header>

  <div v-if="error" class="error" role="alert"><div><strong>No se pudo actualizar el resumen</strong><p>{{ error }}</p></div><button type="button" @click="load">Reintentar</button></div>

  <template v-if="loading">
   <section class="metrics" aria-label="Cargando resumen"><div v-for="n in 4" :key="n" class="skeleton metric-skeleton"></div></section>
   <div class="main-grid"><div class="skeleton panel-skeleton"></div><div class="skeleton panel-skeleton"></div></div>
   <div class="skeleton activity-skeleton"></div>
  </template>

  <template v-else-if="dashboard">
   <section class="metrics" aria-label="Resumen operativo">
    <DashboardMetricCard v-if="dashboard.assets" id="metric-assets" label="Activos" :value="dashboard.assets.total" detail="Inventario tecnológico registrado en SIGATI." :open="openMetric==='assets'" @toggle="toggleMetric('assets')"><RouterLink class="detail-link" :to="{name:'assets'}">Ver activos →</RouterLink></DashboardMetricCard>
    <DashboardMetricCard id="metric-open" :label="isEngineer?'Tickets abiertos':'Mis tickets abiertos'" :value="openCount" :detail="isEngineer?'Solicitudes que todavía requieren atención.':'Solicitudes actualmente bajo tu responsabilidad.'" :open="openMetric==='open'" @toggle="toggleMetric('open')"><RouterLink class="detail-link" :to="{name:'tickets',query:ticketQuery}">Ver tickets →</RouterLink></DashboardMetricCard>
    <DashboardMetricCard id="metric-unassigned" label="Sin asignar" :value="dashboard.tickets.unassigned" detail="Tickets nuevos disponibles y aún sin técnico." :open="openMetric==='unassigned'" @toggle="toggleMetric('unassigned')"><RouterLink class="detail-link" :to="{name:'tickets',query:{unassigned:'1'}}">Ver tickets →</RouterLink></DashboardMetricCard>
    <DashboardMetricCard id="metric-sla" :label="isEngineer?'SLA vencido':'Mi SLA vencido'" :value="dashboard.tickets.sla_breached" detail="Tickets abiertos con al menos un compromiso SLA incumplido." tone="danger" :open="openMetric==='sla'" @toggle="toggleMetric('sla')"><RouterLink class="detail-link" :to="{name:'tickets',query:{...ticketQuery,sla:'breached'}}">Ver tickets →</RouterLink></DashboardMetricCard>
   </section>

   <DashboardCollapsible id="attention" title="Atención requerida" :summary="attentionTotal?`${attentionTotal} pendientes · Hay elementos que requieren revisión.`:'No hay pendientes críticos en este momento.'" tone="attention" :open="sections.attention" @toggle="toggleSection('attention')">
    <ul v-if="attentionItems.length" class="attention-list"><li v-for="item in attentionItems" :key="item.label"><span aria-hidden="true"></span><RouterLink :to="item.to">{{ item.label }} <b>→</b></RouterLink></li></ul><p v-else class="empty">Todo está al día.</p>
   </DashboardCollapsible>

   <div class="main-grid">
    <DashboardCollapsible id="helpdesk" title="Mesa de Ayuda" :summary="`${openCount} ${isEngineer?'tickets abiertos':'tickets abiertos asignados a ti'}`" :open="sections.tickets" @toggle="toggleSection('tickets')">
     <dl class="distribution"><div v-for="(label,status) in statusLabels" :key="status"><dt>{{ label }}</dt><dd>{{ dashboard.tickets.by_status[status] ?? 0 }}</dd></div></dl>
     <RouterLink class="section-link" :to="{name:'tickets',query:ticketQuery}">Ver Mesa de Ayuda →</RouterLink>
    </DashboardCollapsible>
    <DashboardCollapsible v-if="dashboard.assets" id="assets" title="Estado de activos" :summary="`${dashboard.assets.total} activos registrados`" :open="sections.assets" @toggle="toggleSection('assets')">
     <dl class="distribution"><div v-for="(label,status) in assetLabels" :key="status"><dt>{{ label }}</dt><dd>{{ dashboard.assets.by_status[status] ?? 0 }}</dd></div></dl>
     <RouterLink class="section-link" :to="{name:'assets'}">Ver activos →</RouterLink>
    </DashboardCollapsible>
   </div>

   <div class="lower-grid">
    <DashboardRecentActivity :activities="dashboard.recent_activity" />
    <section class="quick" aria-labelledby="quick-title"><h2 id="quick-title">Acciones rápidas</h2><p>Accesos frecuentes según tus permisos.</p><nav>
     <RouterLink v-if="can('tickets.create')" :to="{name:'ticket-create'}"><span>＋</span> Nuevo ticket</RouterLink>
     <RouterLink v-if="can('assets.create')" :to="{name:'assets',query:{action:'create'}}"><span>＋</span> Registrar activo</RouterLink>
     <RouterLink v-if="can('tickets.view')" :to="{name:'tickets',query:{unassigned:'1'}}"><span>→</span> Tickets sin asignar</RouterLink>
    </nav></section>
   </div>
  </template>
 </div>
</template>

<style scoped>
.dashboard-view{display:flex;flex-direction:column;gap:20px}.welcome{display:flex;justify-content:space-between;align-items:center;gap:20px;padding:23px 26px;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm)}.eyebrow{margin-bottom:3px;color:var(--color-primary);font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.welcome h1{font-size:23px;line-height:1.3}.welcome p:not(.eyebrow){margin-top:4px;color:var(--color-text-muted);font-size:13px}.meta{display:flex;gap:8px;flex-wrap:wrap}.meta>span{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:700}.active{background:var(--color-success-bg);color:var(--color-success)}.active i{width:7px;height:7px;border-radius:50%;background:currentColor}.role{border:1px solid var(--color-primary-border);background:var(--color-primary-light);color:var(--color-primary)}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}.detail-link,.section-link{display:inline-block;margin-top:9px;color:var(--color-primary);font-size:12px;font-weight:750}.main-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.distribution{display:grid;gap:9px;margin:0}.distribution div{display:flex;justify-content:space-between;gap:14px}.distribution dt{color:var(--color-text-muted);font-size:12px}.distribution dd{font-size:13px;font-weight:750}.attention-list{display:grid;gap:8px;list-style:none}.attention-list li{display:grid;grid-template-columns:8px minmax(0,1fr);align-items:center;gap:9px}.attention-list li>span{width:7px;height:7px;border-radius:50%;background:var(--color-warning)}.attention-list a{display:flex;justify-content:space-between;gap:10px;padding:5px 0;color:#475569;font-size:13px;font-weight:650}.attention-list b{color:var(--color-primary)}.empty{color:var(--color-text-muted);font-size:13px}.lower-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(240px,1fr);gap:15px;align-items:start}.quick{padding:21px;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm)}.quick h2{font-size:17px}.quick>p{margin-top:3px;color:var(--color-text-muted);font-size:12px}.quick nav{display:grid;gap:8px;margin-top:15px}.quick a{display:flex;align-items:center;gap:9px;padding:10px 11px;border:1px solid var(--color-border);border-radius:9px;font-size:12px;font-weight:700;transition:border-color 180ms ease,background 180ms ease}.quick a span{display:grid;place-items:center;width:22px;height:22px;border-radius:6px;background:var(--color-primary-light);color:var(--color-primary)}.quick a:hover{border-color:var(--color-primary-border);background:#fbfdff}.quick a:focus-visible,.detail-link:focus-visible,.section-link:focus-visible,.attention-list a:focus-visible{outline:3px solid var(--color-primary-border);outline-offset:2px}.error{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:15px 17px;border:1px solid var(--color-danger-border);border-radius:var(--radius-md);background:var(--color-danger-bg);color:var(--color-danger)}.error strong{font-size:13px}.error p{margin-top:2px;font-size:12px}.error button{padding:7px 12px;border:1px solid currentColor;border-radius:7px;background:#fff;color:inherit;font-weight:700;cursor:pointer}.skeleton{border-radius:var(--radius-lg);background:linear-gradient(90deg,#e8edf3,#f8fafc,#e8edf3);background-size:200%;animation:shine 1.3s infinite}.metric-skeleton{height:126px}.panel-skeleton{height:104px}.activity-skeleton{height:220px}@keyframes shine{to{background-position:-200% 0}}@media(max-width:1000px){.metrics{grid-template-columns:repeat(2,1fr)}.lower-grid{grid-template-columns:1fr}}@media(max-width:700px){.welcome{align-items:flex-start;flex-direction:column}.main-grid{grid-template-columns:1fr}}@media(max-width:480px){.dashboard-view{gap:14px}.welcome{padding:19px 17px}.welcome h1{font-size:20px}.metrics{grid-template-columns:1fr;gap:10px}.error{align-items:flex-start;flex-direction:column}.error button{width:100%}}@media(prefers-reduced-motion:reduce){.skeleton{animation:none}.quick a{transition-duration:.01ms!important}}
</style>
