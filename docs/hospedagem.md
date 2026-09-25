# Preparação para hospedagem

O site usa PHP 8.1 ou superior, MySQL/MariaDB, `mysqli` com `mysqlnd`, `curl`, HTTPS e Apache/LiteSpeed com `.htaccess` e `mod_rewrite`. O modo offline foi desativado. Os pagamentos PIX usam exclusivamente a BX Pay; sem credenciais válidas e gateway ativada, a tela de depósito informa que o serviço está indisponível.

## Antes de publicar

1. Crie um banco MySQL e configure `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` e `APP_ENV=production` no ambiente da hospedagem. Se o painel não permitir variáveis de ambiente, edite `conectarbanco.php` no servidor com os dados do banco e desative a exibição de erros na configuração PHP. Não exponha esse arquivo nem os dumps SQL. Confirme que o `.htaccess` está ativo.
2. Importe o banco pelo phpMyAdmin fora de `public_html`. Se usar os dumps existentes, revise e remova contas, dados e credenciais de teste; altere imediatamente as senhas administrativas e das gateways importadas. Não publique os arquivos `.sql` na pasta acessível pela web.
3. Abra **ADM → Gateway BX Pay**, salve Client ID e Client Secret de produção, teste a conexão e ative a BX Pay. A página de depósito não volta à gateway antiga.
4. Configure no provedor, se disponível, o webhook HTTPS `https://SEU-DOMINIO/webhook/bxpay.php`. O webhook apenas inicia a conciliação; o crédito depende de consulta autenticada à API. O botão **Já paguei** também permite a consulta pelo cliente.
5. Verifique o certificado HTTPS, a extensão cURL, os e-mails de login e as permissões de escrita do PHP no banco. A aplicação cria tabelas adicionais de autenticação, gerente, jogo e BX Pay no primeiro acesso; o usuário do banco precisa de `CREATE TABLE` e `ALTER TABLE` quando houver migrações.
6. Confirme que `/config/`, `/app/`, `/payments/`, `/tests/`, `/tools/`, arquivos `.sql` e backups respondem com 403. Se a hospedagem não aplicar `.htaccess`, reproduza as regras no servidor antes de abrir o site.

## Gerentes

O administrador cria gerentes em **ADM → Gerentes**. Cada gerente cria um link exclusivo de influenciador e divide exatamente 70% do depósito confirmado entre sua parte e a do influenciador. Os 30% restantes ficam com a plataforma. O histórico guarda os percentuais usados em cada depósito, mesmo após alteração do link.

O gerente também cria contas demo. Elas usam saldo fictício, dificuldade fácil e não podem depositar nem sacar. O gerente edita somente o saldo das próprias contas demo; as alterações são auditadas. Ele pode solicitar saque manual da sua comissão disponível. O administrador confere a transferência fora do site e, em **ADM → Saques de gerentes**, marca a solicitação como paga ou rejeitada. Marcar como paga não envia PIX automaticamente.

## Limites que exigem validação antes de receber dinheiro real

A documentação da BX Pay disponível no projeto não garante o formato real do extrato nem o retorno do `external_id`. Uma conta de produção e uma transação controlada são necessárias para validar a conciliação de ponta a ponta. Sem essa verificação, pagamentos podem ficar pendentes para revisão, embora o código evite creditar respostas não confirmadas.

O resultado da corrida ainda é gerado pelo jogo no navegador. A proteção por rodada e limite de prêmio reduz duplicações e excessos, mas um cliente modificado pode simular progresso. Para operar apostas e prêmios reais com resistência a fraude, a validação da corrida precisa ser autoritativa no servidor ou os prêmios precisam de revisão manual. Nenhum site pode ser declarado imune a invasões ou livre de falhas sem auditoria e validação no ambiente final.
