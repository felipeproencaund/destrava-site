# Site Destrava (vempradestrava.com.br)

HTML puro + PHP (diagnostico e sorteio), hospedado na Hostinger.
Tudo que vai para o ar fica em `public_html/`.

## Estrutura
- `public_html/*.html` - site institucional (9 paginas, mesmo menu e rodape)
- `public_html/css`, `js`, `assets` - estilo, scripts, fontes e imagens
- `public_html/diagnostico/` - quiz de diagnostico + painel admin + PHP
- `public_html/sorteio/` - ferramenta de sorteios (com login por cliente)
- `public_html/sorteio-sebrae/` - sorteio da parceria com o Sebrae

## O que NAO vai para o GitHub (ver `.gitignore`)
- `diagnostico/php/config.php` (senha do banco). Use `config.example.php` como modelo.
- logs com dados de clientes, backups e `.zip`.

## Regras do padrao de sites da Destrava
Antes de publicar, conferir os 7 itens do checklist de seguranca (HTTPS, sem segredos no codigo, validacao de formularios, anti-spam, headers, dependencias, revisao final). Dados de clientes (CRM, quiz, sorteio) seguem a LGPD.
