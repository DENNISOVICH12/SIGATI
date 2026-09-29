<script setup>
defineProps({ open: Boolean })
</script>

<template>
  <span class="expandable-hint">{{ open ? 'Ocultar detalles' : 'Más detalles' }}</span>
</template>
