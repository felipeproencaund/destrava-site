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
5. Performance e celular: imagens leves, sem salto de layout, testar em tela pequena.
6. Rastreamento de conversões: envio de formulário, clique no WhatsApp, telefone, botões.
7. Google Search Console: verificar o domínio (registro TXT no DNS), enviar o sitemap, pedir indexação da home.
- Palavras-chave (meta keywords) não valem. O Google ignora.
- Página bonita não é o mesmo que página pronta tecnicamente.

### Monitoramento
1. Endereço /saude que confirma site e banco (no quiz: /php/saude.php).
2. Todo erro gravado em log com data, hora e o que a pessoa fazia. Cliente vê mensagem educada.
3. Monitor externo (UptimeRobot) para o site e para o /saude, com alerta por e-mail e no app. Testar o alerta uma vez.

## Regras fixas
- LGPD: banner de cookies antes de ligar o Google Analytics (ID ainda pendente).
- Nada de "sorteio" no site (foi removido de vez).
- Grupo Undertake aparece só no rodapé.
- A senha provisória do CRM deve ser trocada no primeiro acesso.
