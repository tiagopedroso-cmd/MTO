# M.T.O — Marido de Aluguel

Landing page responsiva com WhatsApp, galeria com setas e painel do proprietário para publicar e excluir fotos. WhatsApp configurado: **+55 11 97018-3640**.

## Publicar na Hostinger

Este projeto utiliza hospedagem **Web/Cloud com PHP 8.1 ou superior**, HTTPS e sessões PHP. Não utiliza banco de dados, Node.js, Composer ou processo de build. GitHub Pages e o Criador de Sites da Hostinger não executam este painel PHP.

1. Faça backup do site que estiver no domínio.
2. No hPanel, use a integração Git com este repositório ou envie os arquivos para a pasta `public_html` do domínio. A landing page é `index.html`.
3. Envie também `admin.php`, `galeria-api.php`, `galeria-core.php`, `.mto-data/.htaccess` e `uploads/galeria/.htaccess`. Habilite a exibição de arquivos ocultos para conferir os dois `.htaccess`.
4. Selecione PHP 8.1 ou superior e ative HTTPS. Configure `upload_max_filesize` para pelo menos `8M` e `post_max_size` para pelo menos `10M`.
5. As pastas `.mto-data` e `uploads/galeria` precisam permitir escrita pelo PHP. Use as permissões padrão da conta da hospedagem; não use `777`.
6. Acesse `https://SEU-DOMINIO/admin.php`. No primeiro acesso, use a chave entregue separadamente e crie uma senha de pelo menos 12 caracteres. A chave de configuração não está neste repositório e deixa de funcionar após a criação da senha.
7. Envie uma foto de teste pelo painel e confira a galeria na página pública.

Documentação da hospedagem: [Gerenciador de Arquivos](https://www.hostinger.com/support/4548688-basic-actions-in-the-file-manager-in-hostinger/) e [opções PHP](https://www.hostinger.com/support/4667515-how-to-manage-php-extensions-and-options-in-hostinger/).

## Uso pelo proprietário

- Entre pelo link **Área do proprietário**, no rodapé, ou por `/admin.php`.
- Escolha uma foto, descreva o serviço e pressione **Publicar foto**.
- A foto será salva no servidor e aparecerá para todos os visitantes ao carregar a página. As fotos enviadas aparecem antes das seis imagens iniciais.
- O botão **Excluir foto** remove uma foto enviada pelo painel. As imagens iniciais fazem parte do HTML e não aparecem no painel.
- Formatos: JPG, PNG ou WebP. Até 8 MB, 24 megapixels e 60 fotos publicadas.
- O painel não funciona abrindo `admin.php` como um arquivo local; é necessário um servidor PHP.

## Arquivos e persistência

- `index.html`: página pública; fontes e imagens originais incorporadas, mapa estático e galeria com navegação por teclado e toque.
- `admin.php`: interface do proprietário.
- `galeria-api.php` / `galeria-core.php`: autenticação, sessão, validação e persistência.
- `.mto-data/state.php`: criado após configurar o acesso; contém hash da senha e lista de fotos. Protegido por PHP e `.htaccess`, nunca versionado.
- `uploads/galeria/`: imagens do proprietário, nunca versionadas.

**Atualizações não devem apagar `.mto-data` nem `uploads/galeria`.** Preserve essas pastas no deploy e no backup. Não envie arquivos `.work`, chaves de acesso ou documentos privados ao servidor público.

Instagram e Google continuam sem destinos oficiais configurados. Os links podem ser preenchidos em `MTO_CONFIG`, no início de `index.html`. A seção de avaliações foi removida a pedido do cliente.

## Validação

O projeto inclui proteção por senha com hash, CSRF, sessões com cookie HttpOnly e SameSite, limite de tentativas de acesso, arquivos de foto com nomes aleatórios e gravação de estado com bloqueio. As fotos são validadas no servidor por formato, tamanho e dimensões.
