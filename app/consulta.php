<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/**
 * Only documented routes and parameters can be sent to the provider.
 * Keep this list on the server; never accept an arbitrary URL from a browser.
 */
function consulta_catalog(): array {
    $items = [];
    $add = static function(string $module, string $file, string $param, string $label, array $fixed = []) use (&$items): void {
        $id = preg_replace('/[^a-z0-9]+/', '_', strtolower($file . '_' . $param . '_' . implode('_', $fixed))) ?? '';
        $items[$id] = ['module'=>$module,'file'=>$file,'param'=>$param,'label'=>$label,'fixed'=>$fixed];
    };
    foreach (['cpf'=>'CPF','telefone'=>'Telefone','email'=>'E-mail','rg'=>'RG','cpf_parente'=>'CPF de parente','nome'=>'Nome'] as $param=>$label) $add('pessoas','consulta_serasa.php',$param,'Consulta completa · '.$label);
    foreach (['cpf'=>'CPF','nome'=>'Nome','mae'=>'Nome da mãe','pai'=>'Nome do pai','rg'=>'RG'] as $param=>$label) $add('pessoas','dados01.php',$param,'Dados 01 · '.$label,['action'=>['cpf'=>'consultar_cpf','nome'=>'buscar_nome','mae'=>'buscar_mae','pai'=>'buscar_pai','rg'=>'buscar_rg'][$param]]);
    foreach (['cpf'=>'CPF','telefone'=>'Telefone','placa'=>'Placa','email'=>'E-mail','cep'=>'CEP'] as $param=>$label) $add(in_array($param,['placa'],true)?'veiculos':($param==='cep'?'enderecos':'pessoas'),'api_full.php',$param,'API completa · '.$label);
    $add('pessoas','basic220m.php','cpf','Dados básicos · CPF');
    $add('pessoas','brazilianpeople.php','cpf','Dados populacionais · CPF');
    foreach (['doc'=>'Documento','nome'=>'Nome','telefone'=>'Telefone'] as $param=>$label) $add('restricoes','spc1.php',$param,'SPC 1 · '.$label);
    foreach (['cpf'=>'CPF','nome'=>'Nome','email'=>'E-mail','cep'=>'CEP','placa'=>'Placa','cnpj'=>'CNPJ','nome_mae'=>'Nome da mãe','renavan'=>'Renavam'] as $param=>$label) {
        $module = $param==='placa'||$param==='renavan'?'veiculos':($param==='cnpj'?'empresas':($param==='cep'?'enderecos':'restricoes'));
        $add($module,'spc2.php',$param,'SPC 2 · '.$label);
    }
    $add('restricoes','situacao.php','cpf','Situação cadastral · CPF');
    foreach (['cpf'=>'CPF','cep'=>'CEP','telefone'=>'Telefone'] as $param=>$label) $add($param==='cep'?'enderecos':'contatos','telefone0.php',$param,'Telefone 0 · '.$label);
    foreach (['telefone'=>'Telefone','cpf'=>'CPF','cep'=>'CEP','nome'=>'Nome'] as $param=>$label) $add($param==='cep'?'enderecos':'contatos','telefone1.php',$param,'Telefone 1 · '.$label);
    foreach (['doc'=>'Documento','telefone'=>'Telefone'] as $param=>$label) $add('contatos','br21m.php',$param,'BR 21M · '.$label);
    foreach (['cpf'=>'CPF','nome'=>'Nome','email'=>'E-mail','nome_mae'=>'Nome da mãe','cep'=>'CEP'] as $param=>$label) $add($param==='cep'?'enderecos':'empresas','credilink.php',$param,'Credi Link · '.$label);
    foreach (['cpf'=>'CPF','nome'=>'Nome'] as $param=>$label) {
        $add('fotos','fotoma.php',$param,'Foto MA · '.$label);
        $add('fotos','fotoro.php',$param,'Foto RO · '.$label);
    }
    foreach (['foto03.php','foto04.php','foto06.php','foto07.php','foto08.php'] as $file) $add('fotos',$file,'id',strtoupper(substr($file,0,-4)).' · ID');
    foreach (['PLACA'=>'Placa','CHASSI'=>'Chassi','RENAVAM'=>'Renavam','NUM_MOTOR'=>'Número do motor','NUM_CAIXA_CAMBIO'=>'Número do câmbio','NUM_EIXO_TRAS'=>'Eixo traseiro','NUM_TERC_EIXO'=>'Terceiro eixo','NUM_IDENT_IMP'=>'Identificação'] as $campo=>$label) $add('veiculos','credauto_bin.php','valor','Credauto BIN · '.$label,['campo'=>$campo]);
    foreach (['CHASSI'=>'Chassi','PLACA'=>'Placa'] as $campo=>$label) $add('veiculos','credauto_emplacamento.php','valor','Emplacamento · '.$label,['campo'=>$campo]);
    $add('veiculos','consulta_bv_detran.php','placa','Detran · Placa');
    foreach (['CLARO_CPF','cadsus','nextel'] as $tabela) foreach (['cpf'=>'CPF','nome'=>'Nome','telefone'=>'Telefone'] as $param=>$label) $add('cadastros','api_cad_claro_nex.php',$param,$tabela.' · '.$label,['tabela'=>$tabela]);
    foreach (['celular'=>'Celular','email'=>'E-mail'] as $param=>$label) $add('cadastros','api_cad_claro_nex.php',$param,'cadsus · '.$label,['tabela'=>'cadsus']);
    foreach (['identity'=>'Documento','telephone'=>'Telefone'] as $param=>$label) $add('compras','compras_paycom.php',$param,'Paycom 1 · '.$label);
    $add('compras','compras_paycom2.php','telefone','Paycom 2 · Telefone');
    $add('trabalho','rais2019.php','cpf','RAIS 2019 · CPF');
    return $items;
}

function consulta_normalize_input(string $parameter, string $value, array $fixed): string {
    $kind = $parameter === 'valor' ? strtolower((string)($fixed['campo'] ?? '')) : $parameter;
    if (in_array($kind, ['cpf','cpf_parente','cnpj','telefone','telephone','celular','cep','renavam'], true)) {
        if (!preg_match('/^[0-9 .()\/+-]{1,120}$/D', $value)) return '';
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }
    return trim($value);
}

function consulta_validate_input(string $parameter, string $value, array $fixed): bool {
    if ($value === '' || strlen($value) > 120 || preg_match('/[\x00-\x1F\x7F]/', $value)) return false;
    $kind = $parameter === 'valor' ? strtolower((string)($fixed['campo'] ?? '')) : $parameter;
    return match($kind) {
        'cpf','cpf_parente' => (bool)preg_match('/^\d{11}$/D',$value),
        'cnpj' => (bool)preg_match('/^\d{14}$/D',$value),
        'telefone','telephone','celular' => (bool)preg_match('/^\d{10,13}$/D',$value),
        'cep' => (bool)preg_match('/^\d{8}$/D',$value),
        'email' => filter_var($value,FILTER_VALIDATE_EMAIL)!==false,
        'placa' => (bool)preg_match('/^[A-Za-z0-9-]{7,8}$/D',$value),
        'chassi' => (bool)preg_match('/^[A-Za-z0-9]{11,17}$/D',$value),
        'renavam' => (bool)preg_match('/^\d{9,12}$/D',$value),
        'nome','nome_mae','mae','pai' => (bool)preg_match('/^[\p{L}\p{M} .\x27-]{3,120}$/uD',$value),
        'doc','identity','rg','id' => (bool)preg_match('/^[A-Za-z0-9]{5,20}$/D',$value),
        default => (bool)preg_match('/^[A-Za-z0-9-]{4,40}$/D',$value),
    };
}

function consulta_provider_error(int $status, string $file, string $body): string {
    $message = '';
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        foreach (['message', 'mensagem', 'error', 'erro', 'detail', 'detalhe'] as $key) {
            $candidate = $decoded[$key] ?? null;
            if (is_array($candidate)) $candidate = $candidate['message'] ?? $candidate['mensagem'] ?? null;
            if (!is_string($candidate)) continue;
            $candidate = trim(preg_replace('/[\x00-\x1F\x7F]/', ' ', $candidate) ?? '');
            if ($candidate !== '') { $message = function_exists('mb_substr') ? mb_substr($candidate, 0, 200, 'UTF-8') : substr($candidate, 0, 200); break; }
        }
    }
    $prefix = 'A API retornou HTTP ' . $status . ' em ' . $file . '.';
    if ($message !== '') return $prefix . ' Resposta: ' . $message;
    if ($status === 404) return $prefix . ' A API não informou se a rota ou o registro está ausente.';
    return $prefix . ' A API não informou o motivo.';
}
