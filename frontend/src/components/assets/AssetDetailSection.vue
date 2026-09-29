<script setup>
defineProps({
  id: { type: String, required: true },
  title: { type: String, required: true },
  summary: { type: String, required: true },
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['toggle'])
</script>

<template>
  <section class="detail-section" :class="{ open }">
    <button
      type="button"
      class="section-trigger"
      :aria-expanded="open"
      :aria-controls="id"
      @click="emit('toggle')"
    >
      <span class="heading-copy">
        <span class="section-title">{{ title }}</span>
        <span v-if="!open" class="section-summary"><slot name="summary">{{ summary }}</slot></span>
      </span>
      <span class="chevron" aria-hidden="true">⌄</span>
    </button>

    <div
      :id="id"
      class="section-content"
      :class="{ expanded: open }"
      :aria-hidden="!open"
    >
      <div class="content-overflow">
        <div class="content-inner">
          <slot />
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.detail-section{min-width:0;background:var(--color-bg-surface);border:1px solid var(--color-border);border-radius:var(--radius-lg);transition:border-color 200ms ease,box-shadow 200ms ease}.detail-section.open{border-color:var(--color-primary-border);box-shadow:var(--shadow-sm)}
.section-trigger{display:grid;grid-template-columns:minmax(0,1fr) 24px;align-items:center;gap:16px;width:100%;min-height:76px;padding:15px 20px;border:0;border-radius:var(--radius-lg);background:transparent;color:inherit;text-align:left;cursor:pointer;transition:background-color 200ms ease}.section-trigger:focus-visible{outline:3px solid var(--color-primary-border);outline-offset:3px}.heading-copy{display:flex;min-width:0;flex-direction:column;gap:4px}.section-title{color:var(--color-text-main);font-size:14px;font-weight:750;letter-spacing:.04em;text-transform:uppercase}.section-summary{color:var(--color-text-muted);font-size:13px;line-height:1.4;overflow-wrap:anywhere}.chevron{justify-self:center;color:var(--color-text-subtle);font-size:21px;line-height:1;transition:transform 200ms ease,color 200ms ease}.open .chevron{transform:rotate(180deg);color:var(--color-primary)}
.section-content{display:grid;grid-template-rows:0fr;opacity:0;visibility:hidden;transition:grid-template-rows 220ms ease,opacity 180ms ease,visibility 220ms}.section-content.expanded{grid-template-rows:1fr;opacity:1;visibility:visible}.content-overflow{min-height:0;overflow:hidden}.content-inner{margin:0 20px;padding:18px 0 20px;border-top:1px solid var(--color-border-subtle)}
@media(hover:hover){.section-trigger:hover{background:var(--color-primary-light)}.section-trigger:hover .chevron{color:var(--color-primary);transform:translateX(2px)}.open .section-trigger:hover .chevron{transform:rotate(180deg) translateY(-1px)}}
@media(max-width:560px){.section-trigger{min-height:72px;padding:14px 16px}.content-inner{margin-inline:16px;padding-bottom:18px}.section-title{font-size:12px}}
@media(prefers-reduced-motion:reduce){.detail-section,.section-trigger,.chevron,.section-content{transition-duration:.01ms!important}}
</style>
