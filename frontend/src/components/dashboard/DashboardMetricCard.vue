<script setup>
import ExpandableHint from '@/components/common/ExpandableHint.vue'

defineProps({ label:String, value:[Number,String], detail:String, open:Boolean, id:String, tone:{type:String,default:'default'} })
defineEmits(['toggle'])
</script>

<template>
  <article class="metric expandable-card" :class="[`metric--${tone}`, { 'expandable-card--open': open }]">
    <button class="metric-trigger expandable-card__trigger" type="button" :aria-expanded="open" :aria-controls="`${id}-detail`" @click="$emit('toggle')">
      <span class="label">{{ label }}</span>
      <strong>{{ value }}</strong>
      <ExpandableHint :open="open" />
    </button>
    <div :id="`${id}-detail`" class="detail" :class="{ expanded:open }" :aria-hidden="!open">
      <div><p>{{ detail }}</p><slot /></div>
    </div>
  </article>
</template>

<style scoped>
.metric{min-width:0;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm)}.metric-trigger{display:block;width:100%;padding:17px 18px;border:0;border-radius:var(--radius-lg);background:transparent;color:inherit;text-align:left}.label{display:block;color:var(--color-text-muted);font-size:11px;font-weight:750;letter-spacing:.06em;text-transform:uppercase}.metric strong{display:block;margin:4px 0 7px;font-size:27px;line-height:1.2}.metric--danger strong{color:var(--color-danger)}.detail{display:grid;grid-template-rows:0fr;opacity:0;visibility:hidden;transition:grid-template-rows 220ms ease,opacity 180ms ease,visibility 220ms}.detail.expanded{grid-template-rows:1fr;opacity:1;visibility:visible}.detail>div{min-height:0;margin:0 18px;overflow:hidden}.detail p{margin:0;padding:12px 0 0;border-top:1px solid var(--color-border-subtle);color:var(--color-text-muted);font-size:12px;line-height:1.5}.detail>div>:last-child{margin-bottom:17px}@media(prefers-reduced-motion:reduce){.detail{transition-duration:.01ms!important}}
</style>
