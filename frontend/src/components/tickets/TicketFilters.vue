<script setup>
defineProps({ search: { type:String, default:'' }, status:{type:String,default:''}, priority:{type:String,default:''}, assignment:{type:String,default:''}, sla:{type:String,default:''}, hasActiveFilters:Boolean, loading:Boolean, total:{type:Number,default:0} })
const emit = defineEmits(['update:search','update:status','update:priority','update:assignment','update:sla','clear'])
</script>
<template>
  <section class="filters" aria-label="Filtros de tickets">
    <div class="search-row">
      <div class="search"><span aria-hidden="true">⌕</span><label class="sr-only" for="ticket-search">Buscar tickets</label><input id="ticket-search" :value="search" type="search" maxlength="150" placeholder="Buscar por código, título o solicitante..." @input="emit('update:search', $event.target.value)"></div>
      <span class="count"><template v-if="loading">Actualizando…</template><template v-else><strong>{{ total }}</strong> {{ total === 1 ? 'ticket' : 'tickets' }}</template></span>
    </div>
    <div class="controls">
      <label>Estado<select :value="status" @change="emit('update:status',$event.target.value)"><option value="">Todos</option><option value="new">Nuevo</option><option value="assigned">Asignado</option><option value="in_progress">En proceso</option><option value="resolved">Resuelto</option><option value="closed">Cerrado</option></select></label>
      <label>Prioridad<select :value="priority" @change="emit('update:priority',$event.target.value)"><option value="">Todas</option><option value="low">Baja</option><option value="medium">Media</option><option value="high">Alta</option><option value="critical">Crítica</option></select></label>
      <label>Asignación<select :value="assignment" @change="emit('update:assignment',$event.target.value)"><option value="">Todos</option><option value="unassigned">Sin asignar</option></select></label>
      <label>SLA<select :value="sla" @change="emit('update:sla',$event.target.value)"><option value="">Todos</option><option value="on_time">En tiempo</option><option value="warning">Próximo a vencer</option><option value="breached">Vencido</option><option value="met">Cumplido</option></select></label>
      <button v-if="hasActiveFilters" type="button" class="clear" :disabled="loading" @click="emit('clear')">Limpiar filtros</button>
    </div>
  </section>
</template>
<style scoped>
.filters{padding:20px;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);display:grid;gap:18px}.search-row{display:flex;gap:16px;align-items:center}.search{position:relative;flex:1}.search span{position:absolute;left:14px;top:9px;color:#94a3b8;font-size:22px}.search input{width:100%;height:44px;padding:0 14px 0 42px;border:1px solid var(--color-border);border-radius:var(--radius-md);font-size:14px}.search input:focus,select:focus,.clear:focus-visible{outline:3px solid rgba(37,99,235,.15);border-color:var(--color-primary)}.count{white-space:nowrap;padding:8px 12px;background:#f8fafc;border-radius:8px;color:#64748b;font-size:13px}.controls{display:grid;grid-template-columns:repeat(4,minmax(130px,1fr)) auto;gap:14px;align-items:end}.controls label{display:grid;gap:6px;color:#64748b;font-size:12px;font-weight:650}.controls select{height:40px;padding:0 10px;border:1px solid var(--color-border);border-radius:var(--radius-md);background:#fff;color:#0f172a}.clear{height:40px;padding:0 14px;border:1px solid var(--color-border);border-radius:var(--radius-md);background:#fff;color:var(--color-primary);font-weight:650;cursor:pointer}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}@media(max-width:900px){.controls{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.filters{padding:16px}.search-row{align-items:stretch;flex-direction:column}.count{align-self:flex-start}.controls{grid-template-columns:1fr}.clear{width:100%}}
</style>
