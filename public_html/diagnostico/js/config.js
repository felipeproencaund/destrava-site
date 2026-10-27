/**
 * ============================================================
 * CONFIGURAÇÃO CENTRAL DO FRONTEND — DESTRAVA
 * ============================================================
 * Único lugar para alterar WhatsApp e IDs de analytics no lado
 * do navegador. Se precisar trocar o WhatsApp, troque AQUI e
 * também em /php/config.php (constante WHATSAPP_NUMBER).
 * ============================================================
 */
const DESTRAVA_CONFIG = Object.freeze({
  // Número de WhatsApp (apenas dígitos, com código do país)
  WHATSAPP_NUMBER: '5543999119533',

  // Mensagem pré-preenchida ao abrir o WhatsApp pelo CTA
  WHATSAPP_MESSAGE_TEMPLATE:
    'Olá! Acabei de fazer o Diagnóstico de Marketing da Destrava e quero conversar sobre os resultados. Meu diagnóstico: {diagnostico}',

  // Google Analytics 4 (measurement ID). Deixe null enquanto não tiver um.
  GA_MEASUREMENT_ID: null,

  // Google Tag Manager (container ID). Deixe null enquanto não tiver um.
  GTM_CONTAINER_ID: null,

  // Endpoint do backend que recebe o lead
  SUBMIT_ENDPOINT: '/php/submit.php',

  // Nome do site / marca
  SITE_NAME: 'Destrava',
});
