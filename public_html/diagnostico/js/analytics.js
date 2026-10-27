/**
 * ============================================================
 * ANALYTICS — DESTRAVA
 * ============================================================
 * Empurra eventos para window.dataLayer (padrão Google Tag Manager).
 * Não inventa nenhum ID de GA4/GTM — quando você tiver um GTM
 * Container ID, adicione o snippet oficial do GTM no <head> e
 * <body> do index.html; os eventos abaixo já vão aparecer no
 * Preview do GTM automaticamente, sem precisar mudar este arquivo.
 *
 * Se preferir usar gtag.js diretamente (sem GTM), defina
 * GA_MEASUREMENT_ID em js/config.js e descomente o bloco no
 * final deste arquivo.
 * ============================================================
 */

window.dataLayer = window.dataLayer || [];

function trackEvent(eventName, params = {}) {
  const payload = {
    event: eventName,
    ...params,
    timestamp: new Date().toISOString(),
  };
  window.dataLayer.push(payload);

  if (DESTRAVA_CONFIG.APP_ENV_DEBUG) {
    // eslint-disable-next-line no-console
    console.log('[analytics]', payload);
  }
}

// Eventos usados pelo quiz (ver js/app.js):
//   quiz_started, question_answered, quiz_completed,
//   lead_form_started, lead_submitted, result_viewed,
//   cta_clicked, whatsapp_clicked

// ------------------------------------------------------------
// Carregamento opcional do gtag.js, somente se um GA_MEASUREMENT_ID
// real for configurado em js/config.js. Nunca inventamos um ID aqui.
// ------------------------------------------------------------
(function maybeLoadGtag() {
  if (!DESTRAVA_CONFIG.GA_MEASUREMENT_ID) return;

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${DESTRAVA_CONFIG.GA_MEASUREMENT_ID}`;
  document.head.appendChild(script);

  window.gtag = window.gtag || function gtag() { window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  window.gtag('config', DESTRAVA_CONFIG.GA_MEASUREMENT_ID);
})();
