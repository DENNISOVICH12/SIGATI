<script setup>
import { computed, ref } from 'vue'
import AssetQrCode from './AssetQrCode.vue'
import ExpandableHint from '@/components/common/ExpandableHint.vue'
import { buildPublicAssetUrl, isDevelopmentOnlyUrl } from '@/utils/publicAssetUrl'
const props = defineProps({ asset: { type: Object, required: true } })
const open = ref(false)
const publicUrl = computed(() => buildPublicAssetUrl(props.asset.public_path))
const isDevelopmentLabel = computed(() => isDevelopmentOnlyUrl(publicUrl.value))
const printLabel = () => window.print()
</script>
<template>
  <section class="qr-identification expandable-card" :class="{ 'expandable-card--open': open }">
    <button type="button" class="qr-trigger expandable-card__trigger" :aria-expanded="open" aria-controls="asset-qr-content" @click="open=!open"><span><strong>Identificación QR</strong><small>Identificación activa · Etiqueta disponible para impresión</small></span><ExpandableHint :open="open" /></button>
    <div id="asset-qr-content" class="qr-content" :class="{ open }" :aria-hidden="!open"><div class="overflow"><div class="qr-body">
      <div class="label-preview"><div class="institution">E.S.E. HOSPITAL PEDRO LEÓN<br>ÁLVAREZ DÍAZ</div><AssetQrCode :value="publicUrl" :label="`QR permanente del activo ${asset.code}`" /><strong class="label-code">{{ asset.code }}</strong><span class="label-category">{{ asset.category }}</span><p>Escanee para identificar<br>o reportar una falla</p><b>SIGATI</b></div>
      <div class="qr-copy"><span class="active">Identificación activa</span><h3>{{ asset.code }}</h3><p>{{ asset.category || 'Activo tecnológico' }}</p><p class="explanation">La etiqueta dirige a la información actual del activo. Editar sus datos no cambia este QR.</p><aside v-if="isDevelopmentLabel" class="development-warning"><strong>Prueba desde otro dispositivo no disponible mediante localhost.</strong><span>Abre SIGATI desde la dirección Network mostrada por Vite. Esta etiqueta es de desarrollo y no debe imprimirse como definitiva.</span></aside><button type="button" class="print-button" @click="printLabel">Imprimir etiqueta</button></div>
    </div></div></div>
  </section>
</template>
<style scoped>
.qr-identification{background:#fff;border:1px solid var(--color-border);border-radius:var(--radius-lg);overflow:hidden}.qr-trigger{width:100%;min-height:84px;padding:16px 20px;border:0;background:transparent;display:flex;align-items:center;justify-content:space-between;gap:18px;text-align:left}.qr-trigger>span:first-child{display:grid;gap:4px}.qr-trigger strong{font-size:14px;text-transform:uppercase;letter-spacing:.04em}.qr-trigger small{color:var(--color-text-muted);font-size:13px}.qr-content{display:grid;grid-template-rows:0fr;opacity:0;transform:translateY(-4px);visibility:hidden;transition:grid-template-rows 220ms ease,opacity 180ms ease,transform 200ms ease,visibility 220ms}.qr-content.open{grid-template-rows:1fr;opacity:1;transform:none;visibility:visible}.overflow{min-height:0;overflow:hidden}.qr-body{margin:0 20px;padding:20px 0;border-top:1px solid var(--color-border-subtle);display:flex;align-items:center;gap:28px}.label-preview{width:240px;flex:0 0 240px;padding:14px;border:2px solid #111;background:#fff;color:#000;text-align:center;font-family:Arial,sans-serif}.institution{font-size:10px;font-weight:700;line-height:1.25}.label-preview :deep(.qr-code){width:138px;margin:8px auto}.label-code{display:block;font:800 18px ui-monospace,monospace;overflow-wrap:anywhere}.label-category{display:block;font-size:11px;text-transform:uppercase}.label-preview p{margin:7px 0;font-size:10px;line-height:1.25}.label-preview>b{font-size:11px;letter-spacing:.1em}.qr-copy{min-width:0}.active{display:inline-block;padding:4px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:12px;font-weight:700}.qr-copy h3{margin:12px 0 2px;font:800 21px ui-monospace,monospace}.qr-copy>p{color:var(--color-text-muted)}.explanation{margin-top:15px;max-width:430px;line-height:1.6}.development-warning{max-width:470px;margin-top:16px;padding:10px 12px;border-left:3px solid #d97706;border-radius:6px;background:#fffbeb;color:#78350f;font-size:13px;line-height:1.45}.development-warning strong,.development-warning span{display:block}.development-warning strong{text-transform:none;letter-spacing:normal}.development-warning span{margin-top:3px}.print-button{margin-top:18px;padding:10px 16px;border:1px solid var(--color-primary);border-radius:9px;background:var(--color-primary);color:#fff;font-weight:700;cursor:pointer}@media(max-width:600px){.qr-trigger{padding-inline:16px;align-items:flex-start;flex-direction:column;gap:8px}.qr-body{margin-inline:16px;flex-direction:column;align-items:stretch}.label-preview{width:240px;max-width:100%;flex-basis:auto;margin:auto}.print-button{width:100%}}@media(prefers-reduced-motion:reduce){.qr-content{transition-duration:.01ms!important}}
@media print{.qr-identification{border:0!important;box-shadow:none!important;overflow:visible!important}.qr-trigger,.qr-copy{display:none!important}.qr-content,.qr-content.open{display:block!important;opacity:1!important;visibility:visible!important;transform:none!important}.overflow{overflow:visible!important}.qr-body{display:block!important;margin:0!important;padding:0!important;border:0!important}.label-preview{width:64mm!important;height:88mm!important;margin:0!important;padding:5mm!important;display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;break-inside:avoid}.label-preview :deep(.qr-code){width:38mm!important;margin:3mm auto!important}.institution{font-size:9pt!important}.label-code{font-size:15pt!important}.label-category,.label-preview p,.label-preview>b{font-size:8pt!important}}
</style>
<style>
@media print {
  @page { margin: 8mm; }
  body * { visibility: hidden !important; }
  .label-preview, .label-preview * { visibility: visible !important; }
  .label-preview { position: fixed !important; inset: 0 auto auto 0 !important; }
}
</style>
