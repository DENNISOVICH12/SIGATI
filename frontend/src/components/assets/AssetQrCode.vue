<script setup>
import { computed } from 'vue'
import { qrMatrix } from '@/utils/qr'

const props = defineProps({ value: { type: String, required: true }, label: { type: String, default: 'Código QR' } })
const modules = computed(() => qrMatrix(props.value))
</script>

<template>
  <svg class="qr-code" viewBox="0 0 49 49" role="img" :aria-label="label" shape-rendering="crispEdges">
    <rect width="49" height="49" fill="#fff" />
    <template v-for="(row, y) in modules" :key="y">
      <rect v-for="(dark, x) in row" v-show="dark" :key="x" :x="x + 4" :y="y + 4" width="1" height="1" fill="#000" />
    </template>
  </svg>
</template>

<style scoped>.qr-code{display:block;width:100%;height:auto;aspect-ratio:1;background:#fff}</style>
