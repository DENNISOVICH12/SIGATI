<script setup>
defineProps({ label:String, value:[Number,String], detail:String, open:Boolean, id:String, tone:{type:String,default:'default'} })
defineEmits(['toggle'])
</script>
<template>
  <article class="metric" :class="[`metric--${tone}`, { open }]">
    <span class="label">{{ label }}</span><strong>{{ value }}</strong>
    <button type="button" :aria-expanded="open" :aria-controls="`${id}-detail`" @click="$emit('toggle')">{{ open ? 'Ver menos' : 'Ver detalles' }} <span :class="{ up:open }" aria-hidden="true">⌄</span></button>
    <div :id="`${id}-detail`" class="detail" :class="{ expanded:open }" :aria-hidden="!open"><div><p>{{ detail }}</p><slot /></div></div>
  </article>
</template>
<style scoped>
.metric{min-width:0;padding:17px 18px;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);transition:border-color 200ms ease}.metric.open{border-color:var(--color-primary-border)}.label{display:block;color:var(--color-text-muted);font-size:11px;font-weight:750;letter-spacing:.06em;text-transform:uppercase}.metric strong{display:block;margin:4px 0 7px;font-size:27px;line-height:1.2}.metric--danger strong{color:var(--color-danger)}button{display:flex;align-items:center;gap:5px;padding:2px 0;border:0;background:transparent;color:var(--color-primary);font-size:12px;font-weight:700;cursor:pointer}button span{font-size:17px;transition:transform 200ms ease}.up{transform:rotate(180deg)}button:focus-visible{outline:3px solid var(--color-primary-border);outline-offset:3px}.detail{display:grid;grid-template-rows:0fr;opacity:0;visibility:hidden;transition:grid-template-rows 220ms ease,opacity 180ms ease,visibility 220ms}.detail.expanded{grid-template-rows:1fr;opacity:1;visibility:visible}.detail>div{min-height:0;overflow:hidden}.detail p{margin:12px 0 0;padding-top:12px;border-top:1px solid var(--color-border-subtle);color:var(--color-text-muted);font-size:12px;line-height:1.5}@media(prefers-reduced-motion:reduce){.metric,button span,.detail{transition-duration:.01ms!important}}
</style>
