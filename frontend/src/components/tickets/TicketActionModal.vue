<script setup>
import { computed, reactive, watch } from 'vue'

const props = defineProps({ open:Boolean, action:{type:String,default:''}, technicians:{type:Array,default:()=>[]}, ticket:{type:Object,default:null}, busy:Boolean, errors:{type:Object,default:()=>({})} })
const emit = defineEmits(['close','submit'])
const form = reactive({ technician_id:'', reason:'', note:'', diagnosis:'', solution:'', notes:'' })
const config = computed(() => ({
  release:{ title:'Liberar ticket', submit:'Liberar ticket' }, assign:{ title:props.ticket?.assigned_to ? 'Reasignar técnico' : 'Asignar técnico', submit:'Guardar asignación' },
  start:{ title:'Iniciar atención', submit:'Iniciar atención' }, resolve:{ title:'Resolver ticket', submit:'Registrar resolución' }, close:{ title:'Cerrar ticket', submit:'Cerrar ticket' },
}[props.action] || {}))
const reasonRequired = computed(() => ['release','close'].includes(props.action) || (props.action === 'assign' && props.ticket?.status === 'in_progress'))

watch(() => props.open, value => { if (value) Object.assign(form,{ technician_id:props.ticket?.assigned_to || '',reason:'',note:'',diagnosis:'',solution:'',notes:'' }) })
const submit = () => {
  const fields = props.action === 'assign' ? ['technician_id','reason'] : props.action === 'start' ? ['note'] : props.action === 'resolve' ? ['diagnosis','solution','notes'] : ['reason']
  emit('submit', Object.fromEntries(fields.map(key => [key, form[key]]).filter(([,value]) => value !== '')))
}
</script>

<template>
  <Teleport to="body"><div v-if="open" class="backdrop" @mousedown.self="!busy && emit('close')"><section class="modal" role="dialog" aria-modal="true" :aria-labelledby="`action-${action}`">
    <header><div><p>Mesa de Ayuda</p><h2 :id="`action-${action}`">{{ config.title }}</h2></div><button type="button" :disabled="busy" aria-label="Cerrar" @click="emit('close')">×</button></header>
    <form @submit.prevent="submit">
      <label v-if="action==='assign'">Técnico activo <strong>*</strong><select v-model="form.technician_id" required :disabled="busy"><option value="" disabled>Selecciona un técnico</option><option v-for="technician in technicians" :key="technician.id" :value="technician.id">{{ technician.name }} · {{ technician.email }}</option></select><small v-if="errors.technician_id">{{ errors.technician_id[0] }}</small></label>
      <label v-if="['release','assign','close'].includes(action)">Motivo <strong v-if="reasonRequired">*</strong><textarea v-model="form.reason" :required="reasonRequired" :maxlength="action==='close'?2000:1000" rows="4" :disabled="busy"/><small v-if="errors.reason">{{ errors.reason[0] }}</small></label>
      <label v-if="action==='start'">Nota de inicio <span>Opcional</span><textarea v-model="form.note" maxlength="2000" rows="4" :disabled="busy"/><small v-if="errors.note">{{ errors.note[0] }}</small></label>
      <template v-if="action==='resolve'"><label>Diagnóstico <strong>*</strong><textarea v-model="form.diagnosis" required maxlength="3000" rows="4" :disabled="busy"/><small v-if="errors.diagnosis">{{ errors.diagnosis[0] }}</small></label><label>Solución <strong>*</strong><textarea v-model="form.solution" required maxlength="5000" rows="5" :disabled="busy"/><small v-if="errors.solution">{{ errors.solution[0] }}</small></label><label>Notas <span>Opcional</span><textarea v-model="form.notes" maxlength="3000" rows="3" :disabled="busy"/><small v-if="errors.notes">{{ errors.notes[0] }}</small></label></template>
      <footer><button type="button" class="secondary" :disabled="busy" @click="emit('close')">Cancelar</button><button type="submit" class="primary" :disabled="busy">{{ busy ? 'Procesando…' : config.submit }}</button></footer>
    </form>
  </section></div></Teleport>
</template>

<style scoped>
.backdrop{position:fixed;inset:0;z-index:1100;background:rgba(15,23,42,.55);display:grid;place-items:center;padding:20px}.modal{width:min(560px,100%);max-height:calc(100dvh - 32px);overflow:auto;background:#fff;border-radius:var(--radius-lg);box-shadow:0 24px 60px rgba(15,23,42,.3)}header{display:flex;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--color-border)}header p{margin:0 0 4px;color:var(--color-primary);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}h2{margin:0;font-size:20px}header button{border:0;background:transparent;font-size:25px;color:#64748b;cursor:pointer}form{display:grid;gap:18px;padding:24px}label{display:grid;gap:7px;color:#475569;font-size:13px;font-weight:700}label span{font-weight:400;color:#94a3b8}label strong{color:#dc2626}textarea,select{width:100%;box-sizing:border-box;border:1px solid var(--color-border);border-radius:9px;padding:10px 12px;background:#fff;color:var(--color-text-main);font:inherit;font-weight:400;resize:vertical}select{height:44px}textarea:focus,select:focus{outline:3px solid rgba(37,99,235,.14);border-color:var(--color-primary)}small{color:#b91c1c;font-weight:500}footer{display:flex;justify-content:flex-end;gap:10px;padding-top:4px}footer button{min-height:42px;padding:0 16px;border-radius:9px;font-weight:700;cursor:pointer}.secondary{background:#fff;border:1px solid var(--color-border)}.primary{background:var(--color-primary);border:1px solid var(--color-primary);color:#fff}button:disabled{opacity:.6;cursor:not-allowed}@media(max-width:560px){.backdrop{padding:8px;align-items:end}.modal{max-height:92dvh;border-radius:14px 14px 0 0}header,form{padding:18px}footer{flex-direction:column-reverse}footer button{width:100%}}
</style>
