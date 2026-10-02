# Segurança antes da publicação

Este projeto depende de PHP e das regras de `.htaccess` do Apache/LiteSpeed. A proteção do código não substitui a configuração da hospedagem.

1. Coloque os arquivos do site no diretório público correto e mantenha exportações de banco, backups, logs e segredos **fora de `public_html`**. Não envie `conectarbanco.php`, `.env`, chaves ou backups ao GitHub. Use `conectarbanco.example.php` apenas como modelo, sem valores reais.
2. Configure o banco com um usuário exclusivo para o site, somente com as permissões necessárias. Desative acesso remoto ao MySQL quando não for necessário. Guarde uma cópia de segurança em local privado e verifique a restauração.
3. No domínio publicado, confirme que `/.git/config`, `/.env`, `/conectarbanco.php`, `/conectarbanco.example.php`, `/app/auth.php` e qualquer arquivo `.sql` ou `.backup.php` retornam **403 ou 404**. Se algum deles abrir, suspenda a publicação e corrija a configuração do servidor. Uma configuração que entregue o código PHP como texto expõe segredos mesmo com controles no aplicativo.
4. Use HTTPS, mantenha PHP e extensões atualizados, restrinja o painel da Hostinger com autenticação em duas etapas e limite quem pode editar arquivos e banco.
5. A chave `APP_KEY` protege senhas demo recuperáveis e URLs privadas do Pushcut. Ela precisa ser longa, aleatória e estável. Instalar ou trocar essa chave em um site que já tenha dados cifrados exige migração: o código anterior usava a senha do banco como chave alternativa. Não altere a senha do banco sem planejar a migração desses dados.
6. Troque qualquer senha ou token que já tenha aparecido em captura de tela, conversa, backup compartilhado ou repositório. Apagar o arquivo do Git não invalida uma credencial exposta.
7. Faça um teste com contas de jogador, gerente e administrador depois do deploy. A alteração de senha ou bloqueio de uma conta encerra as sessões antigas, então esses usuários precisarão entrar novamente.

## Limite importante do jogo pago

O resultado da corrida paga ainda é informado pelo navegador. O servidor limita valor, dono da rodada e crédito duplicado, mas não consegue comprovar sozinho que a corrida foi vencida. Antes de operar com dinheiro real, a vitória precisa ser validada por uma lógica de jogo controlada pelo servidor (ou por um replay verificável). Esconder JavaScript, bloquear o botão direito ou acrescentar um token ao navegador não resolve essa condição.
