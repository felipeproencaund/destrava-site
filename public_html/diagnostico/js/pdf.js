/**
 * ============================================================
 * GERAÇÃO DE PDF DO DIAGNÓSTICO — DESTRAVA
 * ============================================================
 * Usa a biblioteca jsPDF (carregada via CDN no index.html) para
 * montar, no navegador do próprio usuário, um PDF com o
 * diagnóstico completo — sem precisar de nenhum processamento
 * no servidor.
 * ============================================================
 */
(function () {
  'use strict';

  const NAVY = [24, 42, 58];
  const GOLD = [184, 148, 82];
  const GRAPHITE = [32, 32, 32];
  const MUTED = [92, 87, 80];

  function loadImageAsDataURL(url) {
    return fetch(url)
      .then((resp) => resp.blob())
      .then(
        (blob) =>
          new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
          })
      );
  }

  // O jsPDF às vezes não lida bem com a transparência de PNGs e acaba
  // preenchendo o fundo de preto. Para evitar isso, "achatamos" a imagem
  // num fundo branco antes de colocar no PDF.
  function loadImageFlattenedOnWhite(url) {
    return loadImageAsDataURL(url).then(
      (dataUrl) =>
        new Promise((resolve, reject) => {
          const img = new Image();
          img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);
            resolve(canvas.toDataURL('image/png'));
          };
          img.onerror = reject;
          img.src = dataUrl;
        })
    );
  }

  async function buildDiagnosisPDF(data) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit: 'pt', format: 'a4' });
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 48;
    const contentWidth = pageWidth - margin * 2;
    let y = 56;

    function ensureSpace(nextLineHeight) {
      if (y + nextLineHeight > pageHeight - 56) {
        doc.addPage();
        y = 56;
      }
    }

    // ---- Logo ----
    try {
      const logoData = await loadImageFlattenedOnWhite('assets/img/logo-destrava.png');
      const props = doc.getImageProperties(logoData);
      const logoWidth = 130;
      const logoHeight = (props.height / props.width) * logoWidth;
      doc.addImage(logoData, 'PNG', margin, y, logoWidth, logoHeight);
      y += logoHeight + 22;
    } catch (e) {
      y += 8;
    }

    // ---- Régua dourada ----
    doc.setDrawColor(GOLD[0], GOLD[1], GOLD[2]);
    doc.setLineWidth(1.1);
    doc.line(margin, y, pageWidth - margin, y);
    y += 26;

    // ---- Eyebrow ----
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10.5);
    doc.setTextColor(GOLD[0], GOLD[1], GOLD[2]);
    doc.text('SEU PRINCIPAL GARGALO HOJE', margin, y);
    y += 22;

    // ---- Título do diagnóstico ----
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(18);
    doc.setTextColor(NAVY[0], NAVY[1], NAVY[2]);
    const titleLines = doc.splitTextToSize(data.diagnostico_titulo || '', contentWidth);
    titleLines.forEach((line) => {
      ensureSpace(24);
      doc.text(line, margin, y);
      y += 24;
    });
    y += 4;

    // ---- Lead / empresa ----
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9.5);
    doc.setTextColor(MUTED[0], MUTED[1], MUTED[2]);
    doc.text(
      [data.empresa, data.nome, data.dataFormatada].filter(Boolean).join('   ·   '),
      margin,
      y
    );
    y += 26;

    function addParagraph(text, opts = {}) {
      const fontSize = opts.fontSize || 11;
      const color = opts.color || GRAPHITE;
      doc.setFont('helvetica', opts.bold ? 'bold' : 'normal');
      doc.setFontSize(fontSize);
      doc.setTextColor(color[0], color[1], color[2]);
      const lines = doc.splitTextToSize(text, contentWidth);
      lines.forEach((line) => {
        ensureSpace(fontSize * 1.5);
        doc.text(line, margin, y);
        y += fontSize * 1.5;
      });
    }

    function addSectionTitle(text) {
      y += 10;
      ensureSpace(22);
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(12.5);
      doc.setTextColor(NAVY[0], NAVY[1], NAVY[2]);
      doc.text(text, margin, y);
      y += 18;
    }

    // ---- Corpo do diagnóstico ----
    (data.diagnostico_texto || '').split('\n\n').forEach((para) => {
      if (!para.trim()) return;
      addParagraph(para.trim());
      y += 6;
    });

    // ---- Sinais ----
    if (data.sinais && data.sinais.length) {
      addSectionTitle('Por que chegamos a esse diagnóstico?');
      data.sinais.forEach((s) => addParagraph('•  ' + s, { fontSize: 10.5, color: MUTED }));
    }

    // ---- Objetivo ----
    if (data.objetivo_texto) {
      addSectionTitle('Você disse que gostaria de chegar a:');
      addParagraph(data.objetivo_texto, { bold: true });
    }

    // ---- Áreas ----
    if (data.areas_desbloqueio && data.areas_desbloqueio.length) {
      addSectionTitle('Áreas que provavelmente precisam ser destravadas');
      data.areas_desbloqueio.forEach((a, i) => addParagraph(`${i + 1}.  ${a}`, { bold: true }));
    }

    // ---- Rodapé ----
    y += 22;
    ensureSpace(70);
    doc.setDrawColor(GOLD[0], GOLD[1], GOLD[2]);
    doc.setLineWidth(0.8);
    doc.line(margin, y, pageWidth - margin, y);
    y += 24;

    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(GOLD[0], GOLD[1], GOLD[2]);
    doc.text('A gente entra. Entende. Resolve.', margin, y);
    y += 16;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(9);
    doc.setTextColor(MUTED[0], MUTED[1], MUTED[2]);
    doc.text('Diagnóstico gerado em ' + (data.dataFormatada || ''), margin, y);

    return doc;
  }

  window.destravaGeneratePDF = async function destravaGeneratePDF(data) {
    const doc = await buildDiagnosisPDF(data);
    const safeEmpresa = (data.empresa || 'diagnostico')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '');
    doc.save(`diagnostico-destrava-${safeEmpresa || 'empresa'}.pdf`);
  };
})();
