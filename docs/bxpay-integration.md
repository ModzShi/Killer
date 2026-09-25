# Integração BX Pay

## Configuração

Abra **Painel administrativo → Gateway BX Pay**, preencha Client ID e Client Secret, salve e teste a conexão. Marque **Usar BX Pay nos depósitos PIX** e salve para selecionar a gateway. A chave secreta não é devolvida ao navegador. Configuração protegida por sessão administrativa e CSRF.

As tabelas bxpay_config, bxpay_settings e bxpay_deposits são aditivas e já foram preparadas no banco local. Em outra instalação, a tela administrativa prepara essas tabelas no primeiro acesso e precisa de permissão CREATE TABLE. As credenciais KnucklesPay existentes ficam separadas.

## Depósitos

O formulário existente utiliza BX Pay quando selecionada. O mínimo é o maior entre R$ 5 e o mínimo do site. A referência aleatória e o depósito são persistidos antes da chamada; há bloqueio de geração simultânea por conta. Falhas de rede não causam repetição automática de cobrança. O cliente recebe PIX copia e cola armazenado no servidor e vinculado à sua sessão.

A ação **Já paguei** consulta o extrato autenticado da BX Pay. O webhook em webhook/bxpay.php é apenas um aviso para executar a mesma consulta, nunca uma fonte confiável do status ou valor. O código só credita quando o extrato contém referência/identificador correspondente, tipo DEPOSIT ou RECEIVEPIX, status PAID e valor bruto exato. Status, saldo, valor depositado e comissões são atualizados em uma transação, com bloqueio de linhas e prevenção de crédito duplicado.

As consultas respeitam intervalo mínimo de 20 segundos por depósito e buscam no máximo as 500 transações mais recentes. Cobranças fora dessa janela precisam de conciliação assistida; ampliar a busca depende do endpoint de consulta individual ou filtro oficial do provedor.

## Compatibilidade a validar com a conta real

A documentação fornecida não exemplifica o envelope do extrato nem garante que external_id enviado seja devolvido. O cliente aceita qrcode na resposta JSON, em data.qrcode ou como texto copia e cola. A conciliação aceita listas em transactions, data.transactions ou data, sempre verificando os campos de cada transação. Formatos desconhecidos, ausência de referência e valores divergentes falham sem creditar saldo. Nenhum teste com credenciais ou dinheiro reais foi realizado.

Para receber notificações automáticas, a URL pública HTTPS deve ser cadastrada na credencial da BX Pay, caso esse recurso seja disponibilizado pelo provedor. localhost não recebe callbacks externos. O botão Já paguei funciona sem webhook. Não foi inventada uma assinatura HMAC para a BX Pay.

Saques mantêm a aprovação manual existente. O cliente de API possui operação de saque, mas não faz transferências automáticas pelo painel.

Os endpoints antigos deposito/aprovar_teste.php e deposito/processarsaldo.php foram desativados porque permitiam crédito sem confirmação. O webhook KnucklesPay não pode confirmar referências BX Pay.

## Testes

O modo offline foi retirado. Antes de abrir depósitos ao público, valide uma transação controlada com as credenciais reais da BX Pay em HTTPS. Veja [hospedagem.md](hospedagem.md).

- C:/xampp/php/php.exe tests/bxpay_test.php
- C:/xampp/php/php.exe tests/bxpay_reconciliation_test.php

O segundo teste utiliza tabelas temporárias na conexão MySQL; não altera os saldos ou registros reais. Verifica comparação de referência/valor/tipo/status, crédito único, CPA único e rollback.
