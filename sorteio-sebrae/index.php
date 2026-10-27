<?php
/**
 * DESTRAVA — Sorteio simples por número de inscrição
 * - Sem banco de dados.
 * - O sorteio acontece no servidor (random_int, gerador seguro do PHP).
 * - Cada sorteio fica registrado em registro/sorteios.log (pasta bloqueada ao público).
 */
date_default_timezone_set('America/Sao_Paulo');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

const MAX_PARTICIPANTES = 100000;
const MAX_GANHADORES = 100;
$logDir = __DIR__ . '/registro';
$logFile = $logDir . '/sorteios.log';

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$titulo = '';
$organizador = '';
$produto = '';
$descricao = '';
$participantes = '';
$ganhadores = '1';
$erros = [];
$resultado = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $limpa = fn($v, $max) => mb_substr(trim(preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', ' ', strip_tags((string) $v))), 0, $max);
    $titulo = $limpa($_POST['titulo'] ?? '', 150);
    $organizador = $limpa($_POST['organizador'] ?? '', 150);
    $produto = $limpa($_POST['produto'] ?? '', 150);
    $descricao = $limpa($_POST['descricao'] ?? '', 600);
    $participantes = trim((string) ($_POST['participantes'] ?? ''));
    $ganhadores = trim((string) ($_POST['ganhadores'] ?? ''));

    $n = filter_var($participantes, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => MAX_PARTICIPANTES]]);
    $k = filter_var($ganhadores, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => MAX_GANHADORES]]);

    if ($produto === '') $erros[] = 'Informe o produto ou serviço sorteado.';
    if ($n === false) $erros[] = 'Informe o número de participantes (de 1 a ' . number_format(MAX_PARTICIPANTES, 0, ',', '.') . ').';
    if ($k === false) $erros[] = 'Informe o número de ganhadores (de 1 a ' . MAX_GANHADORES . ').';
    if ($n !== false && $k !== false && $k > $n) $erros[] = 'O número de ganhadores não pode ser maior que o de participantes.';

    if (!$erros) {
        // Sorteio sem repetição entre 1 e N (Fisher–Yates parcial, sem montar a lista inteira)
        $trocas = [];
        $numeros = [];
        for ($i = 0; $i < $k; $i++) {
            $j = random_int($i, $n - 1);
            $vi = $trocas[$i] ?? $i;
            $vj = $trocas[$j] ?? $j;
            $trocas[$j] = $vi;
            $numeros[] = $vj + 1;
        }

        // Número do sorteio = próximo número no registro
        $numeroSorteio = 1;
        if (is_file($logFile)) {
            $numeroSorteio = count(file($logFile, FILE_SKIP_EMPTY_LINES)) + 1;
        }
        $codigo = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $quando = date('d/m/Y H:i:s');

        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $linha = json_encode([
            'sorteio' => $numeroSorteio,
            'codigo' => $codigo,
            'data' => $quando,
            'titulo' => $titulo,
            'organizador' => $organizador,
            'produto' => $produto,
            'descricao' => $descricao,
            'participantes' => $n,
            'ganhadores' => $k,
            'resultado' => $numeros,
            'ip' => hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|sorteio'),
        ], JSON_UNESCAPED_UNICODE) . "\n";
        $registrado = @file_put_contents($logFile, $linha, FILE_APPEND | LOCK_EX) !== false;

        $resultado = [
            'numeros' => $numeros,
            'numero_sorteio' => $numeroSorteio,
            'codigo' => $codigo,
            'quando' => $quando,
            'n' => $n,
            'k' => $k,
            'registrado' => $registrado,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Sorteio | Destrava</title>
<meta name="robots" content="noindex, nofollow">
<style>
  :root {
    --navy: #182A3A; --gold: #B89452; --gold-soft: #F1E6D2; --ivory: #F5F0E7;
    --text: #202020; --muted: #5C5750; --border: #E3DAC7; --err: #A33A2B; --err-soft: #F8E5E1;
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--ivory); color: var(--text); font: 16px/1.5 system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; -webkit-font-smoothing: antialiased; }
  .wrap { max-width: 560px; margin: 0 auto; padding: 28px 16px 40px; }
  .logo { display: block; height: 30px; width: auto; margin: 0 auto 24px; }
  .card { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 22px; margin-bottom: 16px; }
  h1 { color: var(--navy); font-size: 24px; line-height: 1.2; margin: 0 0 6px; }
  .sub { color: var(--muted); margin: 0 0 18px; font-size: 15px; }
  label { display: block; font-weight: 700; font-size: 14px; margin-bottom: 14px; }
  label span { display: block; margin-bottom: 5px; }
  input, textarea { width: 100%; padding: 12px; border: 1.5px solid var(--border); border-radius: 10px; font: inherit; font-size: 16px; background: #fff; color: var(--text); }
  textarea { resize: vertical; }
  input:focus, textarea:focus { outline: none; border-color: var(--gold); }
  .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  .btn { display: block; width: 100%; padding: 15px; border: none; border-radius: 12px; font: inherit; font-weight: 800; font-size: 17px; cursor: pointer; text-align: center; text-decoration: none; }
  .btn-gold { background: var(--gold); color: var(--navy); }
  .btn-outline { background: transparent; color: var(--navy); border: 1.5px solid var(--border); font-size: 15px; padding: 12px; }
  .erro { background: var(--err-soft); color: var(--err); font-weight: 600; font-size: 14px; padding: 10px 12px; border-radius: 10px; margin: 0 0 14px; }

  .result { background: var(--navy); color: #fff; border: none; text-align: center; padding: 28px 20px; }
  .result .label { color: var(--gold); font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 12px; margin: 0 0 6px; }
  .result .rtitle { color: #fff; font-size: 24px; line-height: 1.2; margin: 0 0 8px; }
  .result .prod { font-size: 18px; font-weight: 700; color: var(--gold-soft); margin: 0 0 6px; }
  .result .desc { font-size: 14px; color: rgba(255,255,255,.7); margin: 0 0 22px; }
  .result .prod + .label { margin-top: 20px; }
  .nums { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin: 0 0 20px; padding: 0; list-style: none; }
  .nums li { background: rgba(255,255,255,.08); border: 2px solid var(--gold); border-radius: 14px; padding: 12px 18px 10px; min-width: 110px; }
  .nums .pos { display: block; font-size: 12px; color: var(--gold-soft); font-weight: 700; }
  .nums .n { display: block; font-size: 44px; line-height: 1.1; font-weight: 800; color: var(--gold); }
  .single .nums .n { font-size: 72px; }
  .meta { font-size: 13px; color: rgba(255,255,255,.65); margin: 0; }
  .meta strong { color: #fff; }
  .warn { font-size: 13px; color: #FFD9A0; margin: 10px 0 0; }
  .hint { color: var(--muted); font-size: 13px; text-align: center; margin: 14px 0 0; }
  .foot { text-align: center; color: var(--muted); font-size: 12.5px; margin-top: 8px; }
  @media print { .no-print { display: none !important; } body { background: #fff; } }
  @media (max-width: 380px) { .row { grid-template-columns: 1fr; } }
</style>
</head>
<body>
<main class="wrap">
  <img class="logo" src="logo-destrava.png" alt="Destrava">

<?php if ($resultado): ?>
  <section class="card result <?= $resultado['k'] === 1 ? 'single' : '' ?>">
    <?php if ($organizador): ?><p class="label"><?= h($organizador) ?></p><?php endif; ?>
    <?php if ($titulo): ?><h2 class="rtitle"><?= h($titulo) ?></h2><?php endif; ?>
    <p class="prod">Prêmio: <?= h($produto) ?></p>
    <?php if ($descricao): ?><p class="desc"><?= nl2br(h($descricao)) ?></p><?php endif; ?>
    <p class="label"><?= $resultado['k'] === 1 ? 'Número sorteado' : $resultado['k'] . ' números sorteados' ?></p>
    <ol class="nums">
      <?php foreach ($resultado['numeros'] as $i => $num): ?>
        <li><?php if ($resultado['k'] > 1): ?><span class="pos"><?= $i + 1 ?>º</span><?php endif; ?><span class="n"><?= $num ?></span></li>
      <?php endforeach; ?>
    </ol>
    <p class="meta">Sorteio nº <strong><?= $resultado['numero_sorteio'] ?></strong> · código <strong><?= h($resultado['codigo']) ?></strong><br>
      <?= h($resultado['quando']) ?> · entre os números 1 e <?= number_format($resultado['n'], 0, ',', '.') ?></p>
    <?php if (!$resultado['registrado']): ?>
      <p class="warn">Atenção: o resultado não pôde ser gravado no registro. Tire um print desta tela.</p>
    <?php endif; ?>
  </section>
  <p class="hint">Confira os números na lista de inscrição. Dica: tire um print desta tela.</p>
  <div class="no-print" style="display:grid;gap:10px;margin-top:14px">
    <a class="btn btn-outline" href="./">Fazer outro sorteio</a>
  </div>

<?php else: ?>
  <section class="card">
    <h1>Sorteio</h1>
    <p class="sub">O resultado sai pelo número de inscrição. Cada número sorteado corresponde a uma pessoa da lista.</p>
    <?php foreach ($erros as $e): ?><p class="erro"><?= h($e) ?></p><?php endforeach; ?>
    <form method="post" id="form">
      <label><span>Nome do sorteio</span>
        <input type="text" name="titulo" value="<?= h($titulo) ?>" maxlength="150" placeholder="Ex.: Sorteio Sebrae Itapeva"></label>
      <label><span>Quem está sorteando</span>
        <input type="text" name="organizador" value="<?= h($organizador) ?>" maxlength="150" placeholder="Ex.: Sebrae + Destrava"></label>
      <label><span>Produto ou serviço sorteado *</span>
        <input type="text" name="produto" value="<?= h($produto) ?>" maxlength="150" placeholder="Ex.: Consultoria de marketing" required></label>
      <label><span>Descrição do prêmio</span>
        <textarea name="descricao" rows="2" maxlength="600" placeholder="Detalhes, validade, como retirar..."><?= h($descricao) ?></textarea></label>
      <div class="row">
        <label><span>Participantes *</span>
          <input type="number" name="participantes" value="<?= h($participantes) ?>" min="1" max="<?= MAX_PARTICIPANTES ?>" inputmode="numeric" placeholder="Ex.: 120" required></label>
        <label><span>Ganhadores *</span>
          <input type="number" name="ganhadores" value="<?= h($ganhadores) ?>" min="1" max="<?= MAX_GANHADORES ?>" inputmode="numeric" required></label>
      </div>
      <button class="btn btn-gold" type="submit" id="btn">Sortear</button>
    </form>
    <p class="hint">Serão sorteados números de 1 até o total de participantes, sem repetição.</p>
  </section>
<?php endif; ?>

  <p class="foot">Sorteio realizado no servidor e registrado · <strong>Destrava</strong></p>
</main>
<script>
  // Evita clique duplo (o sorteio em si acontece no servidor)
  var f = document.getElementById('form');
  if (f) f.addEventListener('submit', function () { var b = document.getElementById('btn'); b.disabled = true; b.textContent = 'Sorteando...'; });
</script>
</body>
</html>
