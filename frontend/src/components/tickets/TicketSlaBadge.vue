<script setup>
import { computed } from 'vue'
const props = defineProps({ status: { type: String, default: '' } })
const labels = { on_time:'En tiempo', warning:'Próximo a vencer', breached:'Vencido', met:'Cumplido', not_configured:'Sin SLA' }
const label = computed(() => labels[props.status] ?? 'Sin información')
const tone = computed(() => Object.hasOwn(labels, props.status) ? props.status : 'unknown')
</script>
<template><span class="sla" :class="`sla--${tone}`"><span aria-hidden="true" class="dot"></span>{{ label }}</span></template>
<style scoped>
.sla { display:inline-flex; align-items:center; gap:6px; color:#475569; font-size:12px; font-weight:600; white-space:nowrap; }
.dot { width:7px; height:7px; border-radius:50%; background:#94a3b8; }
.sla--on_time .dot, .sla--met .dot { background:#16a34a; }.sla--warning .dot { background:#d97706; }.sla--breached { color:#b91c1c; }.sla--breached .dot { background:#dc2626; }
</style>
