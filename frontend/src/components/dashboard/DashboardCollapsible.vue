<script setup>
defineProps({
  id: { type: String, required: true },
  title: { type: String, required: true },
  summary: { type: String, default: '' },
  open: Boolean,
  tone: { type: String, default: 'default' },
})

defineEmits(['toggle'])
</script>

<template>
  <section class="panel" :class="[{ open }, `panel--${tone}`]">
    <button
      type="button"
      class="trigger"
      :aria-expanded="open"
      :aria-controls="`${id}-content`"
      @click="$emit('toggle')"
    >
      <span class="heading"><span class="title">{{ title }}</span><span v-if="summary" class="summary">{{ summary }}</span></span>
      <span class="toggle-copy">{{ open ? 'Ver menos' : 'Ver detalles' }} <span class="chevron" aria-hidden="true">⌄</span></span>
    </button>
    <div :id="`${id}-content`" class="content" :class="{ expanded: open }" :aria-hidden="!open">
      <div class="overflow"><div class="inner"><slot /></div></div>
    </div>
  </section>
</template>

<style scoped>
.panel{min-width:0;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);transition:border-color 200ms ease,box-shadow 200ms ease}.panel.open{border-color:var(--color-primary-border)}.panel--attention.open{border-color:#fde68a}.trigger{display:flex;align-items:center;justify-content:space-between;gap:18px;width:100%;min-height:88px;padding:18px 20px;border:0;border-radius:var(--radius-lg);background:transparent;color:inherit;text-align:left;cursor:pointer;transition:background-color 180ms ease}.heading{display:grid;min-width:0;gap:4px}.title{font-size:15px;font-weight:750}.summary{color:var(--color-text-muted);font-size:13px;overflow-wrap:anywhere}.toggle-copy{display:flex;align-items:center;gap:6px;flex:none;color:var(--color-primary);font-size:12px;font-weight:750}.chevron{font-size:18px;transition:transform 200ms ease}.open .chevron{transform:rotate(180deg)}.content{display:grid;grid-template-rows:0fr;opacity:0;visibility:hidden;transition:grid-template-rows 220ms ease,opacity 180ms ease,visibility 220ms}.content.expanded{grid-template-rows:1fr;opacity:1;visibility:visible}.overflow{min-height:0;overflow:hidden}.inner{margin:0 20px;padding:17px 0 20px;border-top:1px solid var(--color-border-subtle)}.trigger:focus-visible{outline:3px solid var(--color-primary-border);outline-offset:3px}@media(hover:hover){.trigger:hover{background:#fbfdff}}@media(max-width:520px){.trigger{align-items:flex-start;flex-direction:column;gap:10px;padding:16px}.inner{margin-inline:16px}.toggle-copy{align-self:flex-start}}@media(prefers-reduced-motion:reduce){.panel,.trigger,.chevron,.content{transition-duration:.01ms!important}}
</style>
