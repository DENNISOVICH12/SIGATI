<script setup>
import ExpandableHint from '@/components/common/ExpandableHint.vue'

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
  <section class="panel expandable-card" :class="[{ 'expandable-card--open': open }, `panel--${tone}`]">
    <button
      type="button"
      class="trigger expandable-card__trigger"
      :aria-expanded="open"
      :aria-controls="`${id}-content`"
      @click="$emit('toggle')"
    >
      <span class="heading"><span class="title">{{ title }}</span><span v-if="summary" class="summary">{{ summary }}</span></span>
      <ExpandableHint :open="open" />
    </button>
    <div :id="`${id}-content`" class="content" :class="{ expanded: open }" :aria-hidden="!open">
      <div class="overflow"><div class="inner"><slot /></div></div>
    </div>
  </section>
</template>

<style scoped>
.panel{min-width:0;background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm)}.panel--attention.expandable-card--open{border-color:#fde68a}.trigger{display:flex;align-items:center;justify-content:space-between;gap:18px;width:100%;min-height:88px;padding:18px 20px;border:0;border-radius:var(--radius-lg);background:transparent;color:inherit;text-align:left}.heading{display:grid;min-width:0;gap:4px}.title{font-size:15px;font-weight:750}.summary{color:var(--color-text-muted);font-size:13px;overflow-wrap:anywhere}.content{display:grid;grid-template-rows:0fr;opacity:0;visibility:hidden;transition:grid-template-rows 220ms ease,opacity 180ms ease,visibility 220ms}.content.expanded{grid-template-rows:1fr;opacity:1;visibility:visible}.overflow{min-height:0;overflow:hidden}.inner{margin:0 20px;padding:17px 0 20px;border-top:1px solid var(--color-border-subtle)}@media(max-width:520px){.trigger{align-items:flex-start;flex-direction:column;gap:10px;padding:16px}.inner{margin-inline:16px}}@media(prefers-reduced-motion:reduce){.content{transition-duration:.01ms!important}}
</style>
