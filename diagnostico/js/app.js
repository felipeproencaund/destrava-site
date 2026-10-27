/**
 * ============================================================
 * APP — DESTRAVA (lógica de navegação, estado e submissão)
 * ============================================================
 */
(function () {
  'use strict';

  // ------------------------------------------------------------
  // ESTADO
  // ------------------------------------------------------------
  const STORAGE_KEY = 'destrava_quiz_state_v1';

  const state = loadState() || {
    step: -1, // -1 = intro, 0..9 = perguntas, 10 = captura, 11 = resultado
    answers: {},
    submissionToken: null,
    leadId: null,
    startedTrackedSteps: {},
  };

  function saveState() {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch (e) { /* sessionStorage indisponível — segue sem persistir */ }
  }

  function loadState() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : null;
    } catch (e) {
      return null;
    }
  }

  function resetState() {
    sessionStorage.removeItem(STORAGE_KEY);
  }

  // ------------------------------------------------------------
  // UTM / ORIGEM
  // ------------------------------------------------------------
  function getUTMs() {
    const params = new URLSearchParams(window.location.search);
    return {
      utm_source: params.get('utm_source') || null,
      utm_medium: params.get('utm_medium') || null,
      utm_campaign: params.get('utm_campaign') || null,
      utm_content: params.get('utm_content') || null,
      utm_term: params.get('utm_term') || null,
    };
  }

  function getOrigem() {
    const utms = getUTMs();
    if (utms.utm_source) return utms.utm_source;
    if (document.referrer) {
      try {
        return new URL(document.referrer).hostname;
      } catch (e) {
        return 'referencia_desconhecida';
      }
    }
    return 'direto';
  }

  function generateToken() {
    if (window.crypto && window.crypto.randomUUID) {
      return window.crypto.randomUUID();
    }
    return 'tok_' + Date.now() + '_' + Math.random().toString(36).slice(2);
  }

  if (!state.submissionToken) {
    state.submissionToken = generateToken();
  }

  // ------------------------------------------------------------
  // ELEMENTOS
  // ------------------------------------------------------------
  const el = {
    header: document.getElementById('app-header'),
    progressFill: document.getElementById('progress-fill'),
    progressLabel: document.getElementById('progress-label'),

    screenIntro: document.getElementById('screen-intro'),
    screenQuestion: document.getElementById('screen-question'),
    screenCapture: document.getElementById('screen-capture'),
    screenResult: document.getElementById('screen-result'),

    btnStart: document.getElementById('btn-start'),
    btnBack: document.getElementById('btn-back'),
    btnBackCapture: document.getElementById('btn-back-capture'),

    questionText: document.getElementById('question-text'),
    optionsList: document.getElementById('options-list'),

    leadForm: document.getElementById('lead-form'),
    inputNome: document.getElementById('input-nome'),
    inputEmpresa: document.getElementById('input-empresa'),
    inputWhatsapp: document.getElementById('input-whatsapp'),
    inputInstagram: document.getElementById('input-instagram'),
    inputSite: document.getElementById('input-site'),
    inputConsent: document.getElementById('input-consent'),
    btnSubmitLead: document.getElementById('btn-submit-lead'),

    resultTitle: document.getElementById('result-title'),
    resultContext: document.getElementById('result-context'),
    resultDiagnosticText: document.getElementById('result-diagnostic-text'),
    signalList: document.getElementById('signal-list'),
    objectiveText: document.getElementById('objective-text'),
    areasList: document.getElementById('areas-list'),
    btnCta: document.getElementById('btn-cta'),
    btnPdf: document.getElementById('btn-pdf'),
  };

  const ALL_SCREENS = [el.screenIntro, el.screenQuestion, el.screenCapture, el.screenResult];

  function showScreen(screen) {
    ALL_SCREENS.forEach((s) => { s.hidden = s !== screen; });
    el.header.hidden = screen === el.screenIntro;
    window.scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' });
  }

  // ------------------------------------------------------------
  // NAVEGAÇÃO
  // ------------------------------------------------------------
  function totalQuestions() {
    return DESTRAVA_QUESTIONS.length;
  }

  function goToStep(step) {
    state.step = step;
    saveState();
    render();
  }

  function render() {
    if (state.step === -1) {
      showScreen(el.screenIntro);
      return;
    }
    if (state.step >= 0 && state.step < totalQuestions()) {
      renderQuestion(state.step);
      showScreen(el.screenQuestion);
      return;
    }
    if (state.step === totalQuestions()) {
      renderCapture();
      showScreen(el.screenCapture);
      return;
    }
    if (state.step === totalQuestions() + 1) {
      showScreen(el.screenResult);
      return;
    }
  }

  function renderQuestion(index) {
    const q = DESTRAVA_QUESTIONS[index];
    el.questionText.textContent = q.text;
    el.optionsList.innerHTML = '';

    q.options.forEach((opt) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'option-card';
      btn.setAttribute('role', 'option');
      btn.dataset.optionId = opt.id;
      if (state.answers[q.field] === opt.id) {
        btn.classList.add('is-selected');
        btn.setAttribute('aria-selected', 'true');
      } else {
        btn.setAttribute('aria-selected', 'false');
      }

      const letter = document.createElement('span');
      letter.className = 'option-letter';
      letter.textContent = opt.id;

      const text = document.createElement('span');
      text.textContent = opt.text;

      btn.appendChild(letter);
      btn.appendChild(text);
      btn.addEventListener('click', () => selectOption(q.field, opt.id, index));
      el.optionsList.appendChild(btn);
    });

    el.btnBack.hidden = index === 0;
    updateProgress(index);

    if (!state.startedTrackedSteps.quiz_started) {
      trackEvent('quiz_started');
      state.startedTrackedSteps.quiz_started = true;
      saveState();
    }
  }

  function updateProgress(index) {
    const total = totalQuestions();
    const current = index + 1;
    const pct = Math.round((current / total) * 100);
    el.progressFill.style.width = pct + '%';
    el.progressLabel.textContent = `${current} de ${total}`;
  }

  function selectOption(field, optionId, index) {
    state.answers[field] = optionId;
    saveState();

    trackEvent('question_answered', {
      question_field: field,
      question_index: index + 1,
      answer: optionId,
    });

    // pequena transição visual antes de avançar
    const selectedBtn = el.optionsList.querySelector(`[data-option-id="${optionId}"]`);
    if (selectedBtn) selectedBtn.classList.add('is-selected');

    setTimeout(() => {
      if (index < totalQuestions() - 1) {
        goToStep(index + 1);
      } else {
        trackEvent('quiz_completed', { answers: state.answers });
        goToStep(totalQuestions()); // vai para captura
      }
    }, 220);
  }

  // ------------------------------------------------------------
  // CAPTURA DE LEAD
  // ------------------------------------------------------------
  function renderCapture() {
    if (!state.startedTrackedSteps.lead_form_started) {
      trackEvent('lead_form_started');
      state.startedTrackedSteps.lead_form_started = true;
      saveState();
    }
  }

  function validateLeadForm() {
    let valid = true;
    clearFieldError(el.inputNome, 'error-nome');
    clearFieldError(el.inputEmpresa, 'error-empresa');
    clearFieldError(el.inputWhatsapp, 'error-whatsapp');
    document.getElementById('error-consent').textContent = '';

    if (!el.inputNome.value.trim()) {
      setFieldError(el.inputNome, 'error-nome', 'Informe seu nome.');
      valid = false;
    }
    if (!el.inputEmpresa.value.trim()) {
      setFieldError(el.inputEmpresa, 'error-empresa', 'Informe o nome da empresa.');
      valid = false;
    }
    const digits = el.inputWhatsapp.value.replace(/\D/g, '');
    if (digits.length < 10) {
      setFieldError(el.inputWhatsapp, 'error-whatsapp', 'Informe um WhatsApp válido com DDD.');
      valid = false;
    }
    if (!el.inputConsent.checked) {
      document.getElementById('error-consent').textContent = 'É necessário aceitar o contato para continuar.';
      valid = false;
    }
    return valid;
  }

  function setFieldError(input, errorId, message) {
    input.classList.add('has-error');
    document.getElementById(errorId).textContent = message;
  }
  function clearFieldError(input, errorId) {
    input.classList.remove('has-error');
    document.getElementById(errorId).textContent = '';
  }

  el.leadForm.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    if (!validateLeadForm()) return;
    if (el.btnSubmitLead.classList.contains('is-loading')) return; // trava contra duplo clique

    el.btnSubmitLead.classList.add('is-loading');
    el.btnSubmitLead.disabled = true;

    const diagnosis = computeDiagnosis(state.answers);
    state.diagnosis = diagnosis;
    state.leadNome = el.inputNome.value.trim();
    state.leadEmpresa = el.inputEmpresa.value.trim();

    const payload = {
      submission_token: state.submissionToken,
      nome: el.inputNome.value.trim(),
      empresa: el.inputEmpresa.value.trim(),
      whatsapp: el.inputWhatsapp.value.trim(),
      instagram: el.inputInstagram.value.trim(),
      site: el.inputSite.value.trim(),
      consentimento: el.inputConsent.checked,
      respostas: state.answers,
      diagnostico_id: diagnosis.diagnostico_id,
      diagnostico_titulo: diagnosis.diagnostico_titulo,
      gargalo_principal: diagnosis.gargalo_principal,
      objetivo: diagnosis.objetivo_texto,
      sinais: diagnosis.sinais,
      areas_desbloqueio: diagnosis.areas_desbloqueio,
      origem: getOrigem(),
      ...getUTMs(),
    };

    try {
      const resp = await fetch(DESTRAVA_CONFIG.SUBMIT_ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const data = await resp.json();
      if (!resp.ok || !data.ok) {
        throw new Error(data.error || 'Falha ao enviar o diagnóstico.');
      }
      state.leadId = data.lead_id || null;
      trackEvent('lead_submitted', { diagnostico_id: diagnosis.diagnostico_id });
      saveState();
      renderResult(diagnosis);
      goToStep(totalQuestions() + 1);
      trackEvent('result_viewed', { diagnostico_id: diagnosis.diagnostico_id });
    } catch (err) {
      alert('Não foi possível enviar seu diagnóstico agora. Tente novamente em instantes.');
      // eslint-disable-next-line no-console
      console.error(err);
    } finally {
      el.btnSubmitLead.classList.remove('is-loading');
      el.btnSubmitLead.disabled = false;
    }
  });

  // ------------------------------------------------------------
  // RESULTADO
  // ------------------------------------------------------------
  function renderResult(diagnosis) {
    el.resultTitle.textContent = diagnosis.diagnostico_titulo;

    const resumo = diagnosis.resposta_resumo
      ? `Você indicou que ${diagnosis.resposta_resumo}. Isso mostra que o ponto mais importante para sua empresa hoje parece estar em ${diagnosis.gargalo_principal}.`
      : `O ponto mais importante para sua empresa hoje parece estar em ${diagnosis.gargalo_principal}.`;
    el.resultContext.textContent = resumo;

    el.resultDiagnosticText.textContent = diagnosis.diagnostico_texto;

    el.signalList.innerHTML = '';
    diagnosis.sinais.forEach((s) => {
      const li = document.createElement('li');
      li.textContent = s;
      el.signalList.appendChild(li);
    });

    el.objectiveText.textContent = diagnosis.objetivo_texto;

    el.areasList.innerHTML = '';
    diagnosis.areas_desbloqueio.forEach((a) => {
      const li = document.createElement('li');
      li.textContent = a;
      el.areasList.appendChild(li);
    });
  }

  // ------------------------------------------------------------
  // CTA FINAL — WHATSAPP
  // ------------------------------------------------------------
  el.btnCta.addEventListener('click', async function () {
    const diagnosis = state.diagnosis || {};
    trackEvent('cta_clicked', { diagnostico_id: diagnosis.diagnostico_id });
    trackEvent('whatsapp_clicked', { diagnostico_id: diagnosis.diagnostico_id });

    // registra o clique no backend, associado ao lead
    fetch('php/cta.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ submission_token: state.submissionToken }),
    }).catch(() => { /* não bloqueia a experiência do usuário */ });

    const message = (DESTRAVA_CONFIG.WHATSAPP_MESSAGE_TEMPLATE || '')
      .replace('{diagnostico}', diagnosis.diagnostico_titulo || '');
    const url = `https://wa.me/${DESTRAVA_CONFIG.WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
    window.open(url, '_blank', 'noopener');
  });

  // ------------------------------------------------------------
  // PDF DO DIAGNÓSTICO
  // ------------------------------------------------------------
  el.btnPdf.addEventListener('click', async function () {
    const diagnosis = state.diagnosis;
    if (!diagnosis || el.btnPdf.classList.contains('is-loading')) return;

    el.btnPdf.classList.add('is-loading');
    el.btnPdf.disabled = true;

    try {
      const dataFormatada = new Date().toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
      });
      await window.destravaGeneratePDF({
        nome: state.leadNome || '',
        empresa: state.leadEmpresa || '',
        dataFormatada,
        diagnostico_titulo: diagnosis.diagnostico_titulo,
        diagnostico_texto: diagnosis.diagnostico_texto,
        sinais: diagnosis.sinais,
        objetivo_texto: diagnosis.objetivo_texto,
        areas_desbloqueio: diagnosis.areas_desbloqueio,
      });
      trackEvent('pdf_downloaded', { diagnostico_id: diagnosis.diagnostico_id });
    } catch (err) {
      alert('Não foi possível gerar o PDF agora. Tente novamente em instantes.');
      // eslint-disable-next-line no-console
      console.error(err);
    } finally {
      el.btnPdf.classList.remove('is-loading');
      el.btnPdf.disabled = false;
    }
  });

  // ------------------------------------------------------------
  // BOTÕES DE NAVEGAÇÃO
  // ------------------------------------------------------------
  el.btnStart.addEventListener('click', () => {
    goToStep(0);
  });

  el.btnBack.addEventListener('click', () => {
    if (state.step > 0) {
      goToStep(state.step - 1);
    } else {
      goToStep(-1);
    }
  });

  el.btnBackCapture.addEventListener('click', () => {
    goToStep(totalQuestions() - 1);
  });

  // ------------------------------------------------------------
  // RESTAURAR ESTADO (recarregou a página no meio do quiz)
  // ------------------------------------------------------------
  function restore() {
    if (state.step === totalQuestions() + 1 && state.diagnosis) {
      renderResult(state.diagnosis);
    }
    if (state.step >= totalQuestions()) {
      // já tinha respondido tudo; preenche campos se existirem
    }
    render();
  }

  restore();
})();
