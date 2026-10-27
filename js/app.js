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

  document.addEventListener('DOMContentLoaded', function () {
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
