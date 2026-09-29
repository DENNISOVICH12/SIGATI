<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { createPublicAssetReport, getPublicAsset } from '@/services/publicAssetService'

const route = useRoute()
const asset = ref(null)
const loading = ref(true)
const invalid = ref(false)
const error = ref('')
const showForm = ref(false)
const submitting = ref(false)
const successCode = ref('')
const errors = ref({})
const form = ref({ problem: '', description: '', reporter_name: '', reporter_email: '', reporter_phone: '' })
const statuses = { operational: 'Operativo', pending_review: 'Pendiente de revisión', faulty: 'Con falla', maintenance: 'En mantenimiento' }

const load = async () => {
  loading.value = true; invalid.value = false; error.value = ''
  try { asset.value = await getPublicAsset(route.params.token) }
  catch (requestError) {
    if (requestError.response?.status === 404) invalid.value = true
    else error.value = 'No fue posible consultar la identificación. Intenta nuevamente.'
  } finally { loading.value = false }
}

const submit = async () => {
  if (submitting.value || successCode.value) return
  submitting.value = true; errors.value = {}; error.value = ''
  try {
    const response = await createPublicAssetReport(route.params.token, form.value)
    successCode.value = response.data.ticket_code
  } catch (requestError) {
    if (requestError.response?.status === 422) errors.value = requestError.response.data.errors ?? {}
    else if (requestError.response?.status === 429) error.value = 'Se alcanzó el límite de reportes. Espera antes de intentarlo nuevamente.'
    else error.value = 'No fue posible enviar el reporte. Intenta nuevamente.'
  } finally { submitting.value = false }
}

onMounted(load)
</script>

<template>
  <main class="public-page">
    <header class="brand"><strong>SIGATI</strong><span>E.S.E. Hospital Pedro León Álvarez Díaz</span></header>
    <section v-if="loading" class="public-card" aria-live="polite"><div class="loader"></div><p>Consultando identificación…</p></section>
    <section v-else-if="invalid" class="public-card state" role="alert"><h1>Identificación no disponible</h1><p>No fue posible encontrar una identificación activa asociada a este código.</p><small>Comuníquese con el área de Sistemas.</small></section>
    <section v-else-if="!asset" class="public-card state" role="alert"><h1>No fue posible consultar</h1><p>{{ error }}</p><button class="secondary" @click="load">Reintentar</button></section>
    <template v-else>
      <section class="public-card asset-card">
        <p class="eyebrow">Activo tecnológico</p><h1>{{ asset.code }}</h1><h2>{{ asset.name }}</h2><p class="category">{{ asset.category }}</p>
        <dl><div><dt>Área</dt><dd>{{ asset.area || 'No registrada' }}</dd></div><div><dt>Ubicación</dt><dd>{{ asset.location || 'No registrada' }}</dd></div><div><dt>Estado</dt><dd>{{ statuses[asset.status] || 'No disponible' }}</dd></div></dl>
        <button v-if="!showForm && !successCode" class="primary" type="button" @click="showForm=true">Reportar una falla</button>
      </section>
      <section v-if="showForm" class="public-card report-card">
        <div v-if="successCode" class="success" role="status"><h2>Reporte enviado correctamente</h2><p>Código del ticket: <strong>{{ successCode }}</strong></p><p>El área de Sistemas ha recibido su solicitud.</p></div>
        <form v-else novalidate @submit.prevent="submit">
          <h2>Reportar una falla</h2><p>Está reportando una incidencia sobre <strong>{{ asset.code }}</strong>.</p>
          <div v-if="error" class="alert" role="alert">{{ error }}</div>
          <label>Problema <span>*</span><input v-model="form.problem" maxlength="100" placeholder="Ej. No enciende" :aria-invalid="!!errors.problem"></label><small v-if="errors.problem" class="field-error">{{ errors.problem[0] }}</small>
          <label>Descripción <span>*</span><textarea v-model="form.description" rows="5" maxlength="5000" placeholder="Describe qué ocurrió" :aria-invalid="!!errors.description"></textarea></label><small v-if="errors.description" class="field-error">{{ errors.description[0] }}</small>
          <label>Nombre del solicitante <span>*</span><input v-model="form.reporter_name" maxlength="150" autocomplete="name" :aria-invalid="!!errors.reporter_name"></label><small v-if="errors.reporter_name" class="field-error">{{ errors.reporter_name[0] }}</small>
          <label>Correo <em>Opcional</em><input v-model="form.reporter_email" type="email" maxlength="254" autocomplete="email" :aria-invalid="!!errors.reporter_email"></label><small v-if="errors.reporter_email" class="field-error">{{ errors.reporter_email[0] }}</small>
          <label>Teléfono <em>Opcional</em><input v-model="form.reporter_phone" type="tel" maxlength="30" autocomplete="tel" :aria-invalid="!!errors.reporter_phone"></label><small v-if="errors.reporter_phone" class="field-error">{{ errors.reporter_phone[0] }}</small>
          <button class="primary" :disabled="submitting">{{ submitting ? 'Enviando reporte...' : 'Enviar reporte' }}</button>
        </form>
      </section>
    </template>
    <footer>Identificación institucional · SIGATI</footer>
  </main>
</template>

<style scoped>
.public-page{width:100%;min-height:100vh;padding:24px 14px 32px;background:#f3f6fa;color:#172033}.brand{max-width:560px;margin:0 auto 18px;display:flex;flex-direction:column;align-items:center;text-align:center}.brand strong{color:#174b83;font-size:24px;letter-spacing:.08em}.brand span{color:#5f6f82;font-size:13px}.public-card{width:100%;max-width:560px;margin:0 auto 14px;padding:24px;background:#fff;border:1px solid #dce4ed;border-radius:16px;box-shadow:0 8px 28px rgba(15,23,42,.06)}.eyebrow{color:#68798c;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em}.asset-card h1{margin:5px 0 0;font-size:30px;overflow-wrap:anywhere}.asset-card h2{font-size:18px;margin-top:2px}.category{color:#607086;margin-top:3px}.asset-card dl{margin:22px 0;display:grid;gap:0;border-block:1px solid #e6ebf1}.asset-card dl div{display:grid;grid-template-columns:100px minmax(0,1fr);gap:12px;padding:12px 0;border-bottom:1px solid #eef2f6}.asset-card dl div:last-child{border:0}.asset-card dt{color:#68798c}.asset-card dd{font-weight:650;overflow-wrap:anywhere}.primary,.secondary{width:100%;min-height:46px;border-radius:9px;font-weight:750;cursor:pointer}.primary{border:1px solid #174b83;background:#174b83;color:#fff}.primary:disabled{opacity:.6;cursor:wait}.secondary{margin-top:18px;border:1px solid #cbd5e1;background:#fff;color:#334155}.report-card h2,.state h1{font-size:21px}.report-card form>p,.state p{margin:5px 0 20px;color:#607086}.report-card label{display:grid;gap:6px;margin-top:15px;font-size:13px;font-weight:700}.report-card label span{color:#b42318}.report-card label em{color:#718096;font-weight:400}.report-card input,.report-card textarea{width:100%;padding:11px 12px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;color:#172033}.report-card textarea{resize:vertical}.report-card input:focus,.report-card textarea:focus{outline:3px solid #d7e8f8;border-color:#174b83}.report-card button{margin-top:22px}.field-error{display:block;margin-top:4px;color:#b42318}.alert{margin-top:14px;padding:10px;background:#fff4f3;border:1px solid #fecdca;border-radius:8px;color:#b42318}.success{padding:12px 0;text-align:center}.success h2{color:#166534}.success strong{display:block;margin:8px 0;font:800 19px ui-monospace,monospace}.state{text-align:center;padding-block:42px}.state small{color:#68798c}.loader{width:34px;height:34px;margin:10px auto;border:3px solid #dbe4ee;border-top-color:#174b83;border-radius:50%;animation:spin .8s linear infinite}.public-card>p{text-align:center;color:#607086}footer{text-align:center;color:#7d8998;font-size:12px;margin-top:20px}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:374px){.public-page{padding-inline:10px}.public-card{padding:19px}.asset-card h1{font-size:25px}}@media(prefers-reduced-motion:reduce){.loader{animation:none}}
</style>
