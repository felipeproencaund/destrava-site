# Site Destrava (vempradestrava.com.br)

HTML puro + PHP (diagnostico e sorteio), hospedado na Hostinger.
A raiz deste repositorio e o `public_html` do servidor (a Hostinger instala direto nele).

## Estrutura
- `*.html` - site institucional (9 paginas, mesmo menu e rodape)
- `css`, `js`, `assets` - estilo, scripts, fontes e imagens
- `diagnostico/` - quiz de diagnostico + painel admin + PHP
- `sorteio/` - ferramenta de sorteios (com login por cliente)
- `sorteio-sebrae/` - sorteio da parceria com o Sebrae

## O que NAO vai para o GitHub (ver `.gitignore`)
- `diagnostico/php/config.php` (senha do banco). Use `config.example.php` como modelo.
- logs com dados de clientes, backups e `.zip`.

## Regras do padrao de sites da Destrava
Antes de publicar, conferir os 7 itens do checklist de seguranca (HTTPS, sem segredos no codigo, validacao de formularios, anti-spam, headers, dependencias, revisao final). Dados de clientes (CRM, quiz, sorteio) seguem a LGPD.
