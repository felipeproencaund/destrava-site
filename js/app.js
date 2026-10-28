/**
 * ============================================================
 * SITE DESTRAVA BUSINESS — links de WhatsApp
 * ============================================================
 * Monta os links "Falar no WhatsApp" a partir do número
 * centralizado em js/config.js, com uma mensagem específica
 * por botão (data-msg no HTML).
 * ============================================================
 */
(function () {
  'use strict';

  function buildWhatsAppLink(message) {
    var number = window.DESTRAVA_SITE_CONFIG.WHATSAPP_NUMBER;
    var text = encodeURIComponent(message);
    return 'https://wa.me/' + number + '?text=' + text;
  }


  // ---------------------------------------------------------------
  // Rastreamento de conversões
  // Envia eventos para window.dataLayer (e para o gtag, se existir).
  // Quando GA_MEASUREMENT_ID estiver preenchido em js/config.js,
  // o Google Analytics 4 é carregado e passa a receber os eventos.
  // ---------------------------------------------------------------
  window.dataLayer = window.dataLayer || [];

  function track(eventName, params) {
    params = params || {};
    params.page_path = location.pathname;
    var payload = { event: eventName };
    for (var k in params) payload[k] = params[k];
    window.dataLayer.push(payload);
    if (typeof window.gtag === 'function') window.gtag('event', eventName, params);
  }

  function loadAnalytics() {
    var id = window.DESTRAVA_SITE_CONFIG && window.DESTRAVA_SITE_CONFIG.GA_MEASUREMENT_ID;
    if (!id) return;
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', id);
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(s);
  }

  document.addEventListener('click', function (ev) {
    var a = ev.target.closest && ev.target.closest('a');
    if (!a) return;
    var href = a.getAttribute('href') || '';
    var label = (a.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 80);
    if (a.hasAttribute('data-whatsapp')) {
      track('whatsapp_click', { event_label: label });
    } else if (href.indexOf('diagnostico') !== -1) {
      track('diagnostico_click', { event_label: label });
    } else if (href.indexOf('instagram.com') !== -1) {
      track('instagram_click', { event_label: label });
    } else if (/(^|\/)contato\.html/.test(href) && a.classList.contains('btn')) {
      track('contato_click', { event_label: label });
    }
  });

  loadAnalytics();

  document.addEventListener('DOMContentLoaded', function () {
    var yearEl = document.getElementById('year');
    if (yearEl) yearEl.textContent = new Date().getFullYear();

    // menu mobile — roda primeiro e de forma independente, pra nunca
    // ficar refém de um erro no bloco do WhatsApp
    try {
      var toggle = document.getElementById('nav-toggle');
      var menu = document.getElementById('nav-menu');
      if (toggle && menu) {
        toggle.addEventListener('click', function () {
          menu.classList.toggle('is-open');
          toggle.classList.toggle('is-open');
        });
        menu.querySelectorAll('a').forEach(function (a) {
          a.addEventListener('click', function () {
            menu.classList.remove('is-open');
            toggle.classList.remove('is-open');
          });
        });
      }
    } catch (e) {
      console.error('Erro ao iniciar o menu mobile:', e);
    }

    // links de WhatsApp
    try {
      var links = document.querySelectorAll('[data-whatsapp]');
      links.forEach(function (link) {
        var msg = link.getAttribute('data-msg') || 'Olá! Quero saber mais sobre a Destrava Business.';
        link.setAttribute('href', buildWhatsAppLink(msg));
        link.setAttribute('target', '_blank');
        link.setAttribute('rel', 'noopener');
      });
    } catch (e) {
      console.error('Erro ao montar os links de WhatsApp (confira se js/config.js está carregando):', e);
    }
  });
})();
