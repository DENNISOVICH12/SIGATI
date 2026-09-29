<template>
  <section class="ticket-shell">
    <p class="ticket-shell__eyebrow">Mesa de Ayuda</p>
    <h1>Detalle del ticket</h1>
    <p>
      El detalle y la línea de tiempo se habilitarán en una fase posterior.
    </p>
  </section>
</template>

<style scoped>
.ticket-shell {
  padding: 32px;
  background: var(--color-bg-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}

.ticket-shell__eyebrow {
  margin-bottom: 6px;
  color: var(--color-primary);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

h1 {
  margin-bottom: 8px;
  font-size: 24px;
}

.ticket-shell > p:last-child {
  color: var(--color-text-muted);
}
</style>
