<template>
  <teleport to="body">
    <div class="ses-reativado-overlay" role="dialog" aria-modal="true" aria-labelledby="reativado-titulo">
      <div class="ses-reativado-modal" ref="dialog" tabindex="-1">
        <button class="ses-reativado-close" @click="fechar" :aria-label="closeLabel">×</button>
        <div class="ses-reativado-body">
          <div class="ses-reativado-icon" aria-hidden="true">✓</div>
          <h3 id="reativado-titulo">Cadastro reativado!</h3>
          <p>Seu cadastro está ativo novamente. Agora você pode continuar utilizando o Portal de Empregabilidade Senac DF normalmente.</p>
          <div class="ses-reativado-actions">
            <button class="btn btn-primary" @click="fechar">Continuar</button>
          </div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { onMounted, onBeforeUnmount, ref, watch, nextTick } from 'vue';

const emit = defineEmits(['closed']);
const TIMER_KEY = 'ses_reativacao_sucesso';
const AUTO_CLOSE_MS = 5000;

const dialog = ref(null);
let timer = null;
const closeLabel = 'Fechar';

function startTimer() {
  clearTimer();
  timer = setTimeout(() => { fechar(); }, AUTO_CLOSE_MS);
}

function clearTimer() {
  if (timer) { clearTimeout(timer); timer = null; }
}

function fechar() {
  clearTimer();
  try { sessionStorage.removeItem(TIMER_KEY); } catch (e) {}
  emit('closed');
}

function onKey(e) {
  if (e.key === 'Escape' || e.key === 'Esc') {
    e.preventDefault();
    fechar();
  }
}

onMounted(async () => {
  // consume the one-time flag
  try { sessionStorage.removeItem(TIMER_KEY); } catch (e) {}
  await nextTick();
  // focus management
  const previouslyFocused = document.activeElement;
  dialog.value?.focus();
  window.addEventListener('keydown', onKey);
  startTimer();
  onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    clearTimer();
    if (previouslyFocused && typeof previouslyFocused.focus === 'function') previouslyFocused.focus();
  });
});

onBeforeUnmount(() => { clearTimer(); window.removeEventListener('keydown', onKey); });
</script>

<style scoped>
:root { --reativado-bg: var(--bs-body-bg, #fff); --reativado-text: var(--bs-body-color, #111); }
:global(html.dark) { --reativado-bg: #1f2937; --reativado-text: #f8fafc; }
.ses-reativado-overlay {
  position:fixed; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.36); z-index: 1080;
}
.ses-reativado-modal {
  background:var(--reativado-bg); color:var(--reativado-text); width:min(94vw,32rem); border-radius:12px; box-shadow:0 12px 36px rgba(2,6,23,0.32); padding:1.25rem; outline: none;
  transform:scale(.98); opacity:0; transition:opacity .2s ease, transform .2s ease;
}
.ses-reativado-modal:focus { box-shadow:0 0 0 0.25rem rgba(0,123,255,0.18); }
.ses-reativado-overlay .ses-reativado-modal{ opacity:1; transform:scale(1); }
.ses-reativado-close { position:absolute; right:0.5rem; top:0.25rem; background:transparent; border:0; font-size:1.25rem; color:var(--reativado-text); }
.ses-reativado-body { text-align:center; padding:0.5rem 1rem; }
.ses-reativado-icon { width:4rem; height:4rem; margin:0 auto 0.5rem; border-radius:50%; background:#1bbf51; color:white; display:flex; align-items:center; justify-content:center; font-size:1.75rem; box-shadow:0 6px 18px rgba(27,191,81,0.18); }
:global(html.dark) .ses-reativado-icon { background:#16a34a; }
.ses-reativado-actions { margin-top:1rem; }
@media (prefers-reduced-motion: reduce) { .ses-reativado-modal { transition: none; }
}
</style>
