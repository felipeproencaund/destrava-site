# Destrava: regras do projeto

Site da Destrava Business (vempradestrava.com.br) e quiz/CRM (diagnostico.vempradestrava.com.br).
Dono: Felipe Proença. Este arquivo não vai pro servidor (fica fora do deploy).

## Como trabalhar com o Felipe
- Português simples e direto. Sem metáfora forçada, sem termo enfeitado.
- Um passo de cada vez. Quando ele precisar clicar em algo, mandar o link direto.
- Só afirmar o que foi verificado. Dizer quando não deu pra conferir.
- Ele cria todas as senhas e credenciais. Nunca digitar senha, nem pedir pra colar senha no chat.
- Perguntar antes de mexer em contas dele (Hostinger, Google, GitHub, UptimeRobot).
- Mudança grande no site: mostrar prévia e esperar aprovação antes de publicar.
- Texto de cliente ou do site: perguntar antes qual serviço foi feito. Nunca inventar.
- Quando um projeto de cliente terminar, perguntar se ele quer atualizar a página Projetos.

## Como o site funciona
- HTML puro + PHP no quiz/CRM. Hospedagem Hostinger. A raiz do repositório é a pasta public_html.
- Publicar = commit + push na branch main. O GitHub Actions envia por FTP sozinho.
- Depois do push, conferir: `gh run list --limit 1`. Se falhar na etapa de envio, rodar de novo.
- O `diagnostico/php/config.php` é gerado no deploy a partir dos secrets. Nunca versionar.
- Commits: autor Felipe Proença, com as linhas Co-Authored-By e Claude-Session.
- Ao mudar CSS ou JS, subir o número da versão (?v=) em todas as páginas.

## Checklist ANTES de publicar (todo site novo ou grande mudança)
### Segurança
1. HTTPS com redirecionamento de HTTP.
2. Nenhuma senha, chave de API ou token no código público.
3. Todo formulário validado no servidor (tamanho, tipo, formato).
4. Proteção contra spam e abuso (limite por hora, bloqueio de tentativas de login).
5. Headers de segurança no .htaccess (CSP, HSTS, nosniff, X-Frame, Referrer, Permissions).
6. Dependências atualizadas.
7. Revisão final: dados sensíveis, páginas admin abertas, endpoints desnecessários, arquivos soltos (zip, sql, log).
8. Consultas ao banco sempre preparadas; tudo que o usuário digitou escapado ao mostrar na tela; senhas com hash; erros genéricos pro usuário; checar acesso a dados de outros usuários (IDOR); o servidor não faz requisições a endereços vindos de fora (SSRF).
- Cada site/subdomínio tem seu próprio .htaccess.

### SEO e técnico
1. Title e description únicos em cada página, com a oferta e o que a pessoa busca.
2. Um único H1 por página; H2 e H3 organizados.
3. JSON-LD do tipo certo (ex.: ProfessionalService).
4. canonical, og:url, og:image absoluto, robots.txt, sitemap.xml e llms.txt na raiz.
   - Imagem de compartilhamento (og:image) 1200x630 sempre igual ao visual e slogan atuais. Refazer sem perguntar a cada site novo ou mudança de topo, com nome de arquivo novo (o WhatsApp guarda a antiga).
5. Performance e celular: imagens leves, sem salto de layout, testar em tela pequena.
6. Rastreamento de conversões: envio de formulário, clique no WhatsApp, telefone, botões.
7. Google Search Console: verificar o domínio (registro TXT no DNS), enviar o sitemap, pedir indexação da home.
- Palavras-chave (meta keywords) não valem. O Google ignora.
- Página bonita não é o mesmo que página pronta tecnicamente.

### Monitoramento
1. Endereço /saude que confirma site e banco (no quiz: /php/saude.php).
2. Todo erro gravado em log com data, hora e o que a pessoa fazia. Cliente vê mensagem educada.
3. Monitor externo (UptimeRobot) para o site e para o /saude, com alerta por e-mail e no app. Testar o alerta uma vez.

## Castelo (área interna)
- Endereço: castelo.vempradestrava.com.br (pasta `castelo/`, subdomínio criado na Hostinger em 05/11/2026).
- Usa o mesmo banco e o mesmo config do quiz: `castelo/inc/auth.php` lê `diagnostico/php/config.php`.
- Telas: Central (central.php: cérebro do negócio, agentes, alertas, console de IA aguardando chave), Mensagens (mensagens.php: 1 a 1 pelo WhatsApp, limite 30/dia, tabelas pc_msg_*), Início (Intersect), CRM (leads.php, lead.php, novo.php), Financeiro (financeiro.php, fin-config.php, metas.php, comprovantes), Análises (analises.php, analise-painel.php = painel da reunião, analise-pc.php = detalhada, analise-produto.php, analise-importar.php), Erros, Minha conta. Contratos e Planejamento aparecem como "em breve" (modulo.php).
- Acesso: só usuário com `admin_users.castelo = 1` entra (hoje só felipeproenca). Usuários da equipe são da área da Destrava (diagnostico/admin), não do Castelo.
- Análises da Pede Coxinha: tabelas `pc_clientes`, `pc_pedidos`, `pc_itens`, `an_info`, `an_importacoes`. Dados vêm do banco do Consumer (Firebird 4, backup .fbconsumer no Drive da Pede Coxinha) via arquivo .json.gz importado em analise-importar.php. Próximo passo: envio automático pelo computador da loja.
- Sem script dentro do HTML (CSP): confirmações usam `data-confirm` e o JS fica em `castelo/assets/castelo.js`.
- diagnostico/admin NÃO redireciona para o Castelo: vai virar a área de entender os diagnósticos e acompanhar os planos de marketing (decisão do Felipe em 06/11/2026).

## Acessos (endereços e usuários; senha nunca fica aqui)
- Site: https://vempradestrava.com.br (pasta raiz do repositório = public_html)
- Quiz do diagnóstico: https://diagnostico.vempradestrava.com.br (pasta `diagnostico/`)
- Castelo (área interna): https://castelo.vempradestrava.com.br (pasta `castelo/`). Usuário do Felipe: `felipeproenca`. Usuários se criam em Minha conta; a pessoa cria a própria senha no primeiro acesso.
- Admin do diagnóstico: https://diagnostico.vempradestrava.com.br/admin (futura área de diagnósticos e planos de marketing).
- Repositório: https://github.com/felipeproencaund/destrava-site (deixar privado). Publicação automática pelo GitHub Actions (FTP).
- Hostinger: https://hpanel.hostinger.com, site vempradestrava.com.br, plano Single Web Hosting. DNS na própria Hostinger (dns-parking).
- Subdomínios: https://hpanel.hostinger.com/websites/vempradestrava.com.br/domains/subdomains
- Banco MySQL: `u283442319_destrava` (usuário `u283442319_felipeproenca`). phpMyAdmin: https://hpanel.hostinger.com/websites/vempradestrava.com.br/databases/php-my-admin
- Tabelas: `leads`, `lead_notas`, `admin_users` (login do Castelo), `fin_*` (Financeiro), `raffle_*` (ferramenta antiga de sorteio, fora do site).
- Resetar senha de um usuário do Castelo: no phpMyAdmin, deixar `password_hash` vazio em `admin_users`; no próximo login a pessoa cria a senha nova (tela criar-senha.php).
- Site da Pedra Angular: repositório `felipeproencaund/pedra-angular-site` (privado).

## Regras fixas
- LGPD: banner de cookies antes de ligar o Google Analytics (ID ainda pendente).
- Nada de "sorteio" no site (foi removido de vez).
- Grupo Undertake aparece só no rodapé e no card do fundador ("Quem está por trás da Destrava").
- A senha provisória do CRM deve ser trocada no primeiro acesso.
