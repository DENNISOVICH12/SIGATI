<script setup>
import { computed } from 'vue'
import { getTicketPriorityLabel } from '@/utils/tickets'
const props = defineProps({ priority: { type: String, default: '' } })
const tone = computed(() => ['low','medium','high','critical'].includes(props.priority) ? props.priority : 'unknown')
</script>
<template><span class="badge" :class="`badge--${tone}`">{{ getTicketPriorityLabel(priority) }}</span></template>
<style scoped>
.badge { display:inline-flex; padding:4px 9px; border-radius:999px; font-size:12px; font-weight:650; border:1px solid transparent; }
.badge--low { color:#047857; background:#ecfdf5; border-color:#a7f3d0; }
.badge--medium { color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }
.badge--high { color:#b45309; background:#fffbeb; border-color:#fde68a; }
.badge--critical { color:#b91c1c; background:#fef2f2; border-color:#fecaca; }
.badge--unknown { color:#475569; background:#f1f5f9; border-color:#e2e8f0; }
</style>
