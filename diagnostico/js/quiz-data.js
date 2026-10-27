/**
 * ============================================================
 * DADOS E LÓGICA DO QUIZ — DESTRAVA
 * ============================================================
 * Para alterar PERGUNTAS/TEXTOS: edite QUESTIONS abaixo.
 * Para alterar os DIAGNÓSTICOS: edite DIAGNOSTICS abaixo.
 * Para alterar a LÓGICA (quando cada diagnóstico/sinal/área
 * aparece): edite as funções no final do arquivo.
 * ============================================================
 */

// ------------------------------------------------------------
// 1. PERGUNTAS
// ------------------------------------------------------------
const QUESTIONS = [
  {
    field: 'responsavel_marketing',
    text: 'Hoje, quem é responsável pelo marketing da sua empresa?',
    options: [
      { id: 'A', text: 'Eu mesmo' },
      { id: 'B', text: 'Alguém da equipe acumula essa função' },
      { id: 'C', text: 'Tenho uma agência ou profissional externo' },
      { id: 'D', text: 'Cada hora alguém faz um pouco' },
      { id: 'E', text: 'Na prática, ninguém é realmente responsável' },
    ],
  },
  {
    field: 'consistencia_conteudo',
    text: 'Com que frequência sua empresa consegue produzir conteúdo?',
    options: [
      { id: 'A', text: 'Temos planejamento e seguimos uma rotina' },
      { id: 'B', text: 'Publicamos, mas sem muita regularidade' },
      { id: 'C', text: 'Postamos quando sobra tempo' },
      { id: 'D', text: 'Passamos períodos sem publicar' },
      { id: 'E', text: 'Praticamente não produzimos conteúdo' },
    ],
  },
  {
    field: 'percepcao_marketing',
    text: 'Quando você pensa no marketing da sua empresa, qual dessas situações parece mais familiar?',
    options: [
      { id: 'A', text: 'Sei exatamente o que precisamos fazer' },
      { id: 'B', text: 'Tenho várias ideias, mas não consigo executar' },
      { id: 'C', text: 'Faço algumas coisas, mas não sei se estão funcionando' },
      { id: 'D', text: 'Já tentei algumas estratégias e parei' },
      { id: 'E', text: 'Sinto que estamos fazendo marketing sem uma direção clara' },
    ],
  },
  {
    field: 'posicionamento',
    text: 'Você consegue responder claramente por que um cliente deveria escolher sua empresa em vez de outra?',
    options: [
      { id: 'A', text: 'Sim, e isso está claro na nossa comunicação' },
      { id: 'B', text: 'Sim, mas acho que não conseguimos transmitir isso' },
      { id: 'C', text: 'Mais ou menos' },
      { id: 'D', text: 'Não sei dizer' },
      { id: 'E', text: 'Nunca parei para pensar nisso' },
    ],
  },
  {
    field: 'presenca_digital',
    text: 'Quando alguém encontra sua empresa no Instagram ou Google, o que acontece?',
    options: [
      { id: 'A', text: 'Encontra tudo organizado e entende rapidamente o que fazemos' },
      { id: 'B', text: 'Encontra as informações, mas nossa apresentação poderia ser melhor' },
      { id: 'C', text: 'Nosso perfil existe, mas não transmite tudo que somos' },
      { id: 'D', text: 'Temos pouca informação' },
      { id: 'E', text: 'Nunca analisamos isso' },
    ],
  },
  {
    field: 'mensuracao',
    text: 'Você sabe de onde estão vindo seus clientes?',
    options: [
      { id: 'A', text: 'Sim, acompanhamos isso' },
      { id: 'B', text: 'Tenho uma boa noção' },
      { id: 'C', text: 'Algumas vezes consigo identificar' },
      { id: 'D', text: 'Não acompanhamos' },
      { id: 'E', text: 'Nunca parei para medir' },
    ],
  },
  {
    field: 'tempo_disponivel',
    text: 'Quanto tempo você consegue dedicar pessoalmente ao marketing toda semana?',
    options: [
      { id: 'A', text: 'Mais de 5 horas' },
      { id: 'B', text: '3 a 5 horas' },
      { id: 'C', text: '1 a 3 horas' },
      { id: 'D', text: 'Menos de 1 hora' },
      { id: 'E', text: 'Praticamente nenhum' },
    ],
  },
  {
    field: 'principal_obstaculo',
    text: 'O que mais costuma impedir o marketing de acontecer?',
    options: [
      { id: 'A', text: 'Falta de tempo' },
      { id: 'B', text: 'Falta de planejamento' },
      { id: 'C', text: 'Não saber exatamente o que fazer' },
      { id: 'D', text: 'Falta de alguém responsável' },
      { id: 'E', text: 'Começamos e não conseguimos manter' },
      { id: 'F', text: 'Não sabemos se o investimento está trazendo retorno' },
    ],
  },
  {
    field: 'dor_principal',
    text: 'Se você pudesse tirar uma coisa da sua cabeça hoje, qual seria?',
    options: [
      { id: 'A', text: '"Preciso postar mais"' },
      { id: 'B', text: '"Preciso melhorar minha presença digital"' },
      { id: 'C', text: '"Preciso conseguir mais clientes"' },
      { id: 'D', text: '"Preciso organizar meu marketing"' },
      { id: 'E', text: '"Preciso descobrir o que realmente está funcionando"' },
      { id: 'F', text: '"Quero simplesmente parar de ter que pensar nisso"' },
    ],
  },
  {
    field: 'objetivo',
    text: 'Se o seu marketing estivesse funcionando exatamente como você gostaria hoje, o que estaria acontecendo na sua empresa?',
    options: [
      { id: 'A', text: 'Mais pessoas conhecendo minha empresa' },
      { id: 'B', text: 'Mais pedidos de orçamento' },
      { id: 'C', text: 'Mais vendas' },
      { id: 'D', text: 'Mais clientes recorrentes' },
      { id: 'E', text: 'Minha empresa sendo mais valorizada' },
      { id: 'F', text: 'Eu não precisaria mais me preocupar com marketing' },
    ],
  },
];

// ------------------------------------------------------------
// 2. DIAGNÓSTICOS
// ------------------------------------------------------------
const DIAGNOSTICS = {
  A: {
    id: 'A',
    titulo: 'Seu marketing existe, mas não está trabalhando como deveria.',
    gargalo: 'baixa integração entre as ações de marketing',
    texto: `Você não está começando do zero.

Sua empresa já faz marketing, mas existe uma diferença entre estar presente e ter uma estrutura que transforma presença em oportunidade.

Você provavelmente já produz conteúdo, divulga sua empresa ou até investe em tráfego.

O problema é que essas ações podem estar acontecendo de forma isolada.

O próximo passo não é simplesmente fazer mais marketing.

É descobrir onde sua estrutura está perdendo força.`,
  },
  B: {
    id: 'B',
    titulo: 'Você está fazendo marketing no improviso.',
    gargalo: 'falta de estrutura e rotina no marketing',
    texto: `Você já percebeu que precisa fazer marketing e até tenta manter uma rotina.

O problema é que ele acaba dependendo de quando sobra tempo, quando aparece uma ideia ou quando alguém lembra de fazer.

Isso faz a empresa alternar entre períodos de movimento e períodos de silêncio.

O gargalo provavelmente não é falta de vontade.

É falta de estrutura.

Marketing precisa continuar funcionando mesmo quando você está ocupado cuidando da empresa.`,
  },
  C: {
    id: 'C',
    titulo: 'Sua empresa está melhor do que a imagem que apresenta.',
    gargalo: 'distância entre o que a empresa é e o que ela comunica',
    texto: `Sua empresa já tem história, experiência, clientes e coisas boas para mostrar.

Mas existe uma diferença entre o que sua empresa é e o que um potencial cliente consegue enxergar quando pesquisa por ela.

O problema não necessariamente está no seu negócio.

Pode estar na forma como ele está sendo apresentado, encontrado e percebido.

Você não precisa inventar autoridade.

Precisa fazer a autoridade que já existe aparecer.`,
  },
  D: {
    id: 'D',
    titulo: 'Você está tentando fazer uma função que não deveria depender de você.',
    gargalo: 'marketing acumulado com a rotina de administrar a empresa',
    texto: `Você tem um negócio para administrar.

Precisa cuidar de cliente, equipe, financeiro, operação, fornecedor, problema e venda.

E ainda precisa pensar em Instagram, conteúdo, anúncio, Google e estratégia.

Não é estranho que o marketing fique para depois.

O problema não é você não saber fazer marketing.

É que marketing exige planejamento, execução, acompanhamento e consistência.

Tudo isso precisa acontecer enquanto você toca a empresa.

Seu papel é cuidar do negócio.

O marketing precisa ter quem cuide dele.`,
  },
};

// ------------------------------------------------------------
// 3. REGRAS DE SINAIS (por que chegamos a esse diagnóstico)
// Cada regra só aparece se a resposta realmente sustenta.
// A ordem da lista é a ordem de prioridade de exibição.
// ------------------------------------------------------------
const SIGNAL_RULES = [
  {
    id: 'tempo_baixo',
    test: (a) => ['D', 'E'].includes(a.tempo_disponivel),
    text: 'Seu marketing depende de um tempo que praticamente não existe.',
  },
  {
    id: 'responsavel_proprio',
    test: (a) => a.responsavel_marketing === 'A',
    text: 'O marketing está diretamente ligado à sua disponibilidade pessoal.',
  },
  {
    id: 'consistencia_irregular',
    test: (a) => ['B', 'C', 'D', 'E'].includes(a.consistencia_conteudo),
    text: 'Existe dificuldade em manter uma rotina de comunicação.',
  },
  {
    id: 'sem_mensuracao',
    test: (a) => ['D', 'E'].includes(a.mensuracao),
    text: 'Você não possui clareza suficiente sobre quais ações estão trazendo resultado.',
  },
  {
    id: 'posicionamento_fraco',
    test: (a) => ['C', 'D', 'E'].includes(a.posicionamento),
    text: 'Existe espaço para deixar mais evidente por que o cliente deveria escolher sua empresa.',
  },
  {
    id: 'presenca_fraca',
    test: (a) => ['B', 'C', 'D', 'E'].includes(a.presenca_digital),
    text: 'Sua presença digital ainda não transmite completamente a estrutura da empresa.',
  },
  {
    id: 'sem_direcao',
    test: (a) => ['D', 'E'].includes(a.percepcao_marketing),
    text: 'Falta uma direção clara orientando as ações de marketing.',
  },
  {
    id: 'depende_externo_sem_integracao',
    test: (a) => a.responsavel_marketing === 'C' && ['C', 'D', 'E'].includes(a.mensuracao),
    text: 'Mesmo com apoio externo, não há clareza sobre o retorno das ações.',
  },
];

// ------------------------------------------------------------
// 4. REGRAS DE ÁREAS A DESTRAVAR
// ------------------------------------------------------------
const AREA_RULES = [
  {
    area: 'Planejamento',
    test: (a) => ['B', 'C', 'D', 'E'].includes(a.percepcao_marketing) || ['C', 'D', 'E'].includes(a.consistencia_conteudo),
  },
  {
    area: 'Consistência',
    test: (a) => ['C', 'D', 'E'].includes(a.consistencia_conteudo),
  },
  {
    area: 'Mensuração',
    test: (a) => ['C', 'D', 'E'].includes(a.mensuracao) || a.principal_obstaculo === 'F',
  },
  {
    area: 'Posicionamento',
    test: (a) => ['C', 'D', 'E'].includes(a.posicionamento),
  },
  {
    area: 'Presença digital',
    test: (a) => ['B', 'C', 'D', 'E'].includes(a.presenca_digital),
  },
  {
    area: 'Conteúdo',
    test: (a) => ['D', 'E'].includes(a.consistencia_conteudo) || a.principal_obstaculo === 'C',
  },
  {
    area: 'Execução',
    test: (a) => ['B', 'D'].includes(a.percepcao_marketing) || a.principal_obstaculo === 'E',
  },
  {
    area: 'Processo comercial',
    test: (a) => ['B', 'C', 'D'].includes(a.objetivo) && ['C', 'D', 'E'].includes(a.mensuracao),
  },
  {
    area: 'Aquisição',
    test: (a) => ['A', 'C'].includes(a.objetivo) && ['B', 'C', 'D', 'E'].includes(a.presenca_digital),
  },
  {
    area: 'Conversão',
    test: (a) => ['B', 'C'].includes(a.objetivo) && ['C', 'D', 'E'].includes(a.posicionamento),
  },
];

// ------------------------------------------------------------
// 5. LÓGICA DE DECISÃO DO DIAGNÓSTICO PRINCIPAL
// Sistema de pontuação: cada diagnóstico acumula pontos com
// base nas respostas que sustentam aquele cenário. Vence o
// diagnóstico com maior pontuação; em empate, usa-se a ordem
// de prioridade (D > B > C > A), por serem, nessa ordem, os
// cenários mais específicos e mais comuns entre pequenos
// empresários que acumulam funções.
// ------------------------------------------------------------
function scoreDiagnostics(a) {
  const scores = { A: 0, B: 0, C: 0, D: 0 };

  // --- Diagnóstico A: marketing existe, mas não integrado ---
  if (a.responsavel_marketing === 'C') scores.A += 2; // agência/profissional externo
  if (['A', 'B'].includes(a.consistencia_conteudo)) scores.A += 2; // já produz com alguma regularidade
  if (['C', 'D', 'E'].includes(a.mensuracao)) scores.A += 1; // mas não mede direito
  if (a.percepcao_marketing === 'C') scores.A += 1; // faz coisas, não sabe se funcionam

  // --- Diagnóstico B: marketing no improviso ---
  if (['C', 'D'].includes(a.consistencia_conteudo)) scores.B += 2;
  if (['C', 'D', 'E'].includes(a.tempo_disponivel)) scores.B += 1;
  if (['B', 'E'].includes(a.principal_obstaculo)) scores.B += 2; // falta de planejamento / começa e não mantém
  if (['B', 'D'].includes(a.percepcao_marketing)) scores.B += 1;

  // --- Diagnóstico C: empresa melhor que a imagem que apresenta ---
  if (['B', 'C', 'D'].includes(a.presenca_digital)) scores.C += 2;
  if (['A', 'B'].includes(a.posicionamento)) scores.C += 2; // já sabe seu diferencial
  if (a.percepcao_marketing === 'A') scores.C += 1; // sabe o que fazer, mas isso não aparece fora

  // --- Diagnóstico D: função que não deveria depender do empresário ---
  if (a.responsavel_marketing === 'A') scores.D += 3;
  if (['D', 'E'].includes(a.tempo_disponivel)) scores.D += 3;
  if (['A', 'D'].includes(a.principal_obstaculo)) scores.D += 1; // falta de tempo / falta de responsável
  if (a.dor_principal === 'F') scores.D += 1; // "quero parar de pensar nisso"

  return scores;
}

function pickDiagnostic(scores) {
  const priority = ['D', 'B', 'C', 'A'];
  let best = priority[0];
  let bestScore = scores[best];
  for (const key of priority) {
    if (scores[key] > bestScore) {
      best = key;
      bestScore = scores[key];
    }
  }
  return best;
}

function getSignals(a, max = 3) {
  return SIGNAL_RULES.filter((rule) => rule.test(a))
    .slice(0, max)
    .map((rule) => rule.text);
}

function getAreas(a, max = 3, min = 2) {
  const matched = AREA_RULES.filter((rule) => rule.test(a)).map((rule) => rule.area);
  // remove duplicates preserving order
  const unique = [...new Set(matched)];
  return unique.slice(0, max).length >= min ? unique.slice(0, max) : unique;
}

/**
 * Resumo legível da resposta relevante para o gargalo principal,
 * usado no bloco "Seu principal gargalo hoje".
 */
function getRespostaResumo(diagnosticId, a) {
  const question = QUESTIONS.reduce((acc, q) => {
    acc[q.field] = q;
    return acc;
  }, {});

  const resumos = {
    A: () => {
      const opt = question.mensuracao.options.find((o) => o.id === a.mensuracao);
      return `sobre mensuração de resultados: "${opt ? opt.text.toLowerCase() : ''}"`;
    },
    B: () => {
      const opt = question.consistencia_conteudo.options.find((o) => o.id === a.consistencia_conteudo);
      return `sobre a frequência de produção de conteúdo: "${opt ? opt.text.toLowerCase() : ''}"`;
    },
    C: () => {
      const opt = question.presenca_digital.options.find((o) => o.id === a.presenca_digital);
      return `sobre como sua empresa aparece no Instagram ou Google: "${opt ? opt.text.toLowerCase() : ''}"`;
    },
    D: () => {
      const opt = question.tempo_disponivel.options.find((o) => o.id === a.tempo_disponivel);
      return `sobre o tempo disponível para cuidar do marketing: "${opt ? opt.text.toLowerCase() : ''}"`;
    },
  };

  return resumos[diagnosticId] ? resumos[diagnosticId]() : '';
}

/**
 * Função principal: recebe o objeto de respostas ({ field: 'A'|'B'... })
 * e devolve o diagnóstico completo já personalizado.
 */
function computeDiagnosis(answers) {
  const scores = scoreDiagnostics(answers);
  const diagnosticId = pickDiagnostic(scores);
  const diagnostic = DIAGNOSTICS[diagnosticId];
  const sinais = getSignals(answers);
  const areas = getAreas(answers);
  const respostaResumo = getRespostaResumo(diagnosticId, answers);

  const objetivoOption = QUESTIONS.find((q) => q.field === 'objetivo').options.find(
    (o) => o.id === answers.objetivo
  );

  return {
    diagnostico_id: diagnosticId,
    diagnostico_titulo: diagnostic.titulo,
    diagnostico_texto: diagnostic.texto,
    gargalo_principal: diagnostic.gargalo,
    resposta_resumo: respostaResumo,
    sinais,
    areas_desbloqueio: areas,
    objetivo_texto: objetivoOption ? objetivoOption.text : '',
    scores,
  };
}

// Exporta para uso no restante do app (script global, sem bundler)
window.DESTRAVA_QUESTIONS = QUESTIONS;
window.DESTRAVA_DIAGNOSTICS = DIAGNOSTICS;
window.computeDiagnosis = computeDiagnosis;
