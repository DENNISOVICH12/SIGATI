<script setup>
import { computed } from 'vue'
import { getTicketStatusLabel } from '@/utils/tickets'

const props = defineProps({ status: { type: String, default: '' } })
const known = ['new', 'assigned', 'in_progress', 'resolved', 'closed']
const tone = computed(() => known.includes(props.status) ? props.status : 'unknown')
</script>

<template><span class="badge" :class="`badge--${tone}`">{{ getTicketStatusLabel(status) }}</span></template>

<style scoped>
.badge { display:inline-flex; padding:4px 9px; border-radius:999px; font-size:12px; font-weight:650; white-space:nowrap; border:1px solid transparent; }
.badge--new { color:#1d4ed8; background:#eff6ff; border-color:#bfdbfe; }
.badge--assigned { color:#6d28d9; background:#f5f3ff; border-color:#ddd6fe; }
.badge--in_progress { color:#b45309; background:#fffbeb; border-color:#fde68a; }
.badge--resolved { color:#047857; background:#ecfdf5; border-color:#a7f3d0; }
.badge--closed, .badge--unknown { color:#475569; background:#f1f5f9; border-color:#e2e8f0; }
</style>
