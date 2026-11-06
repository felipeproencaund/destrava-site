<?php
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/layout.php';
destrava_admin_require_login();

$modulos = [
    'financeiro' => ['Financeiro', 'Entradas, saídas, a receber e lucro do mês, por empresa do grupo.'],
    'contratos' => ['Contratos', 'Contrato gerado a partir do cliente e dos serviços, com assinatura online e registro.'],
    'planejamento' => ['Planejamento de marketing', 'Estudo do cliente, concorrência, canais, verba e metas dos próximos 90 dias.'],
];
$m = $_GET['m'] ?? '';
if (!isset($modulos[$m])) {
    header('Location: index.php');
    exit;
}
[$titulo, $texto] = $modulos[$m];
destrava_admin_head($titulo, $m);
?>
<div class="page-head"><h1><?= h($titulo) ?></h1></div>
<div class="em-breve">
  <span class="em-breve-selo">Em construção</span>
  <p><?= h($texto) ?></p>
  <a class="btn-primary" href="index.php">Voltar ao início</a>
</div>
<?php destrava_admin_foot();
