# MK no site Subway

Endereco: `https://slategray-curlew-118286.hostingersite.com/`

## Arquivos

Copie `mk/lib.php`, `mk/api.php`, `adm/mk/index.php` e a entrada `MK Keys` de `adm/components/panel-nav.php` para a instalacao do Subway. O painel usa a sessao de administrador e o mesmo banco de dados MySQL do site. Guarde o segredo e as DLLs em `mk-private/` no diretorio acima da raiz publica do site. O codigo aceita `config/mk-secret.bin` e `config/mk-releases/` como alternativa local, protegida pelo `.htaccess` do Subway e fora do Git.

## Migracao

1. Faça backup do banco remoto. Abra `/adm/mk/` com a conta de administrador para criar as tabelas.
2. Importe `mk-data-import.sql` pelo phpMyAdmin no banco do Subway. Esse arquivo contem hashes de senha, vinculos de dispositivo e dados de licenca; nao o coloque em `public_html`.
3. Copie o arquivo original `server-secret.bin` para `mk-private/server-secret.bin` acima da raiz publica no servidor. Sem o mesmo segredo, as keys antigas nao serao aceitas. Nao publique esse arquivo no Git.
4. Copie a DLL de `data/releases/<SHA256>.dll` para `mk-private/releases/<SHA256>.dll` no servidor. O hash da DLL deve corresponder ao registrado em `mk_releases`.
5. Verifique `GET /mk/api.php?route=releases/latest`; confira a versao e o SHA-256. Teste cadastro e login com uma key de teste criada no painel.

## Cliente

O cliente em `online-mk/hostinger-client` aponta para o site Hostinger. A pasta inteira do build atual e necessaria e requer .NET 8 no PC. A versao antiga que aponta para Tailscale continua separada. Nao entregue o cliente hospedado enquanto o endpoint de releases e a migracao remota nao estiverem ativos.

## Atualizacoes

Em `/adm/mk/`, publique uma DLL x64 de ate 25 MB com uma versao nova. O cliente compara o SHA-256 e baixa a DLL ao iniciar. Cada key nova comeca a contar dias quando o usuario conclui o cadastro em Registrar. O painel mostra vendedor, usuario, ativacao, expiracao e contagem em tempo real.
