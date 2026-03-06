<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Configs de limite de execução para arquivos grandes
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../../sistema-cotacoes/conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método inválido.");
    }

    if (!isset($_FILES['arquivo_movimentacoes']) || $_FILES['arquivo_movimentacoes']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Arquivo de movimentações inválido ou não enviado.");
    }

    if (!isset($_FILES['arquivo_pedidos']) || $_FILES['arquivo_pedidos']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Arquivo de pedidos inválido ou não enviado.");
    }

    $fileMov = $_FILES['arquivo_movimentacoes']['tmp_name'];
    $filePed = $_FILES['arquivo_pedidos']['tmp_name'];

    // --- HELPER: PTAX API ---
    function fetchPtaxRange($start, $end) {
        $startFmt = DateTime::createFromFormat('Y-m-d', $start)->format('m-d-Y');
        $endFmt = DateTime::createFromFormat('Y-m-d', $end)->format('m-d-Y');
        $url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@dataInicial='{$startFmt}'&@dataFinalCotacao='{$endFmt}'&\$top=100&\$format=json";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        $rates = [];
        if (isset($data['value']) && is_array($data['value'])) {
            foreach ($data['value'] as $item) {
                $datePart = substr($item['dataHoraCotacao'], 0, 10);
                $rates[$datePart] = [
                    'compra' => $item['cotacaoCompra'],
                    'venda' => $item['cotacaoVenda']
                ];
            }
        }
        return $rates;
    }

    $pdo->beginTransaction();

    // 1. Cria Lote
    $stmtBatch = $pdo->prepare("INSERT INTO com_commission_batches (periodo) VALUES (:periodo)");
    $stmtBatch->execute([':periodo' => date('Y-m')]);
    $batchId = $pdo->lastInsertId();

    // Helper: Normalize Headers
    function normalizeHeader($h) {
        return mb_strtoupper(trim(str_replace(['"', "'"], "", $h)), 'UTF-8');
    }

    // Helper: Find Column Index by exact match preferred
    function findColumnIndex($headers, $search) {
        $search = normalizeHeader($search);
        // Primeiro: tentativa de match exato
        foreach ($headers as $index => $header) {
            if (normalizeHeader($header) === $search) {
                return $index;
            }
        }
        // Segundo: fallback parcial
        foreach ($headers as $index => $header) {
            if (strpos(normalizeHeader($header), $search) !== false) {
                return $index;
            }
        }
        return -1;
    }

    // Helper: Parse Number
    function parseNumber($val) {
        if ($val === null || trim($val) === '') return 0;
        $val = str_replace(["R$", " "], "", $val);
        $val = str_replace(".", "", $val); // milhar
        $val = str_replace(",", ".", $val); // decimal
        return is_numeric($val) ? (float)$val : 0;
    }

    // Helper: CSV Reader (detects delimiter)
    function readCsvRows($filepath) {
        $handle = fopen($filepath, "r");
        if (!$handle) throw new Exception("Não foi possível ler o arquivo: $filepath");

        $firstLine = fgets($handle);
        rewind($handle);
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $delimiter = (substr_count($firstLine, ';') >= substr_count($firstLine, ',')) ? ';' : ',';

        $rows = [];
        $header = fgetcsv($handle, 0, $delimiter);
        $rows['header'] = $header;
        $rows['data'] = [];

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows['data'][] = $data;
        }
        fclose($handle);
        return $rows;
    }

    // --- LEITURA DO PEDIDOS (Gera Mapa NF -> Regras) ---
    $pedidosCsv = readCsvRows($filePed);
    $pedHeaders = $pedidosCsv['header'];

    $idxPedNf        = findColumnIndex($pedHeaders, 'NOTA FISCAL'); // ou NF-e
    $idxPedData      = findColumnIndex($pedHeaders, 'DATA');
    $idxPedRep       = findColumnIndex($pedHeaders, 'REPRESENTANTE');
    $idxPedCliente   = findColumnIndex($pedHeaders, 'CLIENTE');
    $idxPedVctos     = findColumnIndex($pedHeaders, 'VENCIMENTO'); // Vencimento(s)
    $idxPedPm        = findColumnIndex($pedHeaders, 'PM (DIAS)');

    if ($idxPedNf === -1) throw new Exception("Coluna 'Nota Fiscal' não encontrada no arquivo de pedidos.");

    $mapPedidos = [];
    foreach ($pedidosCsv['data'] as $row) {
        if (!isset($row[$idxPedNf]) || trim($row[$idxPedNf]) === '') continue;
        
        $nfRaw = trim($row[$idxPedNf]);
        // Se a NF do pedido vem simples, transformamos em pattern (ex 000023309)
        $nfKey = ltrim($nfRaw, '0'); // tira zeros à esquerda para map

        $data_raw = isset($row[$idxPedData]) ? trim($row[$idxPedData]) : '';
        $data_nf = null;
        if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{2,4})/', $data_raw, $m)) {
            $year = strlen($m[3]) == 2 ? "20{$m[3]}" : $m[3];
            $data_nf = "{$year}-{$m[2]}-{$m[1]}";
        } elseif (preg_match('/(\d{4})[\/\-](\d{2})[\/\-](\d{2})/', $data_raw, $m)) {
            $data_nf = "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        // Calcula PM
        $pmDias = 0;
        if ($idxPedPm !== -1 && isset($row[$idxPedPm]) && trim($row[$idxPedPm]) !== '') {
            $pmDias = parseNumber($row[$idxPedPm]);
        } elseif ($idxPedVctos !== -1 && isset($row[$idxPedVctos]) && trim($row[$idxPedVctos]) !== '' && $data_nf) {
            // Processa Vencimento(s) "27/02/2026 | 06/03/2026 | 13/03/2026"
            $vctos = explode('|', $row[$idxPedVctos]);
            $somaDias = 0;
            $qtdVctos = 0;
            $dataBase = new DateTime($data_nf);
            foreach ($vctos as $vctoTxt) {
                $vctoTxt = trim($vctoTxt);
                if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/', $vctoTxt, $m)) {
                    $dtV = new DateTime("{$m[3]}-{$m[2]}-{$m[1]}");
                    $diff = $dataBase->diff($dtV)->days;
                    if ($dtV < $dataBase) $diff = -$diff;
                    $somaDias += $diff;
                    $qtdVctos++;
                }
            }
            if ($qtdVctos > 0) {
                $pmDias = $somaDias / $qtdVctos;
            }
        }

        $mapPedidos[$nfKey] = [
            'data_nf'       => $data_nf,
            'representante' => isset($row[$idxPedRep]) ? trim($row[$idxPedRep]) : '',
            'cliente'       => isset($row[$idxPedCliente]) ? trim($row[$idxPedCliente]) : '',
            'pm_dias'       => $pmDias
        ];
    }

    // --- LEITURA DAS MOVIMENTAÇÕES ---
    $movCsv = readCsvRows($fileMov);
    $movHeaders = $movCsv['header'];

    $idxMovTipo      = findColumnIndex($movHeaders, 'TIPO');
    $idxMovCfop      = findColumnIndex($movHeaders, 'CFOP');
    $idxMovNfe       = findColumnIndex($movHeaders, 'NF-E');
    $idxMovCodigo    = findColumnIndex($movHeaders, 'CÓDIGO');
    $idxMovDesc      = findColumnIndex($movHeaders, 'DESCRIÇÃO');
    $idxMovFabr      = findColumnIndex($movHeaders, 'FABRICANTE');
    $idxMovQtde      = findColumnIndex($movHeaders, 'QTDE');
    $idxMovValor     = findColumnIndex($movHeaders, 'VALOR'); // bruts
    
    // Adicionais Movimentações 
    $idxMovData      = findColumnIndex($movHeaders, ['DATA', 'DATA NF', 'DATA EMISS', 'EMISSAO']);
    $idxMovRep       = findColumnIndex($movHeaders, 'REPRESENTANTE');
    $idxMovCliente   = findColumnIndex($movHeaders, 'CLIENTE/REMETENTE');
    if ($idxMovCliente === -1) $idxMovCliente = findColumnIndex($movHeaders, 'CLIENTE');

    // Impostos
    $idxMovIcms      = findColumnIndex($movHeaders, 'VALOR ICMS');
    $idxMovPis       = findColumnIndex($movHeaders, 'VALOR PIS');
    $idxMovCofins    = findColumnIndex($movHeaders, 'VALOR COFINS');
    
    if ($idxMovNfe === -1 || $idxMovTipo === -1 || $idxMovDesc === -1) {
        throw new Exception("Colunas essenciais (Tipo, NF-e, Descrição) não encontradas nas Movimentações.");
    }

    $stmtInsert = $pdo->prepare("INSERT INTO com_commission_items 
        (batch_id, nfe, data_nf, cfop, codigo, descricao, embalagem, fabricante, representante, cliente, 
         qtde, valor_bruto, icms, pis, cofins, venda_net, preco_net_un, preco_lista_brl, desconto_brl, 
         desconto_pct, comissao_base_pct, pm_dias, pm_semanas, ajuste_prazo_pct, comissao_final_pct, 
         valor_comissao, flag_aprovacao, flag_teto, lista_nao_encontrada)
        VALUES 
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmtPrice = $pdo->prepare("SELECT price_brl FROM cot_price_list WHERE codigo_produto = ? AND embalagem = ? ORDER BY id DESC LIMIT 1");

    $itemsAdicionados = 0;
    $itemsIgnorados = 0;
    $motivosIgnorados = ['cfop' => 0, 'valor_zero' => 0, 'sem_nfe' => 0];

    foreach ($movCsv['data'] as $row) {
        $tipo = isset($row[$idxMovTipo]) ? mb_strtolower(trim($row[$idxMovTipo]), 'UTF-8') : '';
        $cfop = isset($row[$idxMovCfop]) ? trim($row[$idxMovCfop]) : '';

        // Filtra apenas saídas tributáveis exatas definidas pelo usuário
        $cfopsValidos = ['5102', '5123', '6102', '6123', '6106', '6110', '5106', '5119'];
        if (!in_array($cfop, $cfopsValidos)) {
            $itemsIgnorados++;
            $motivosIgnorados['cfop']++;
            continue;
        }

        $nfe        = isset($row[$idxMovNfe]) ? trim($row[$idxMovNfe]) : '';
        $codigo     = isset($row[$idxMovCodigo]) ? trim($row[$idxMovCodigo]) : '';
        $descRaw    = isset($row[$idxMovDesc]) ? trim($row[$idxMovDesc]) : '';
        $fabricante = isset($row[$idxMovFabr]) ? trim($row[$idxMovFabr]) : '';
        $qtde       = isset($row[$idxMovQtde]) ? parseNumber($row[$idxMovQtde]) : 0;
        $valorBruto = isset($row[$idxMovValor]) ? parseNumber($row[$idxMovValor]) : 0;
        $icms       = $idxMovIcms !== -1 && isset($row[$idxMovIcms]) ? parseNumber($row[$idxMovIcms]) : 0;
        $pis        = $idxMovPis !== -1 && isset($row[$idxMovPis]) ? parseNumber($row[$idxMovPis]) : 0;
        $cofins     = $idxMovCofins !== -1 && isset($row[$idxMovCofins]) ? parseNumber($row[$idxMovCofins]) : 0;

        if ($valorBruto <= 0) {
            $itemsIgnorados++;
            $motivosIgnorados['valor_zero']++;
            continue;
        }
        
        if (empty($nfe)) {
            $itemsIgnorados++;
            $motivosIgnorados['sem_nfe']++;
            continue;
        }

        // Extrai embalagem (ultimo par de parenteses)
        $embalagem = '';
        $descricaoLimpa = $descRaw;
        $embalagemLimpaPriceList = '';

        if (preg_match('/\(([^)]+)\)[^(]*$/', $descRaw, $matches)) {
            $embalagem = trim($matches[1]);
            // Remove o par de parênteses da descrição limpa
            $descricaoLimpa = trim(str_replace('(' . $matches[1] . ')', '', $descRaw));
            // A embalagem limpa p/ buscar na cot_price_list: Ex: 1 KG, 22,680 KG
            $embalagemLimpaPriceList = $embalagem;
            // Adicional: adiciona parênteses se precisar no DB, a documentação falava em (1 KG)
            $embalagem = "($embalagem)"; 
        }

        // Calcula net
        $venda_net = $valorBruto - $icms - $pis - $cofins;
        $preco_net_un = $qtde > 0 ? $venda_net / $qtde : 0;

        // Limpa código (ex: 063004003) -> manter apenas os 9 digitos principais para a busca
        $codigo9 = substr($codigo, 0, 9);

        // Extrai dados contextuais da Movimentação
        $data_mov_raw = ($idxMovData !== -1 && isset($row[$idxMovData])) ? trim($row[$idxMovData]) : '';
        $data_nf_mov = null;
        if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{2,4})/', $data_mov_raw, $m)) {
            $year = strlen($m[3]) == 2 ? "20{$m[3]}" : $m[3];
            $data_nf_mov = "{$year}-{$m[2]}-{$m[1]}";
        } elseif (preg_match('/(\d{4})[\/\-](\d{2})[\/\-](\d{2})/', $data_mov_raw, $m)) {
            $data_nf_mov = "{$m[1]}-{$m[2]}-{$m[3]}";
        }
        $rep_mov = ($idxMovRep !== -1 && isset($row[$idxMovRep])) ? trim($row[$idxMovRep]) : '';
        $cliente_mov = ($idxMovCliente !== -1 && isset($row[$idxMovCliente])) ? trim($row[$idxMovCliente]) : '';

        // Encontra no mapPedidos para cruzar informações ou pegar PM (Nota Fiscal chave)
        // O $nfe costuma vir como "001/000023309"
        $partesNf = explode('/', $nfe);
        $nfBusca = end($partesNf);
        $nfBusca = ltrim($nfBusca, '0'); // tira zero para parear ex 23309

        $pedData = isset($mapPedidos[$nfBusca]) ? $mapPedidos[$nfBusca] : [];
        
        $representante = !empty($rep_mov) ? $rep_mov : ($pedData['representante'] ?? '');
        $cliente = !empty($cliente_mov) ? $cliente_mov : ($pedData['cliente'] ?? '');
        $data_nf = !empty($data_nf_mov) ? $data_nf_mov : ($pedData['data_nf'] ?? null);
        $pm_dias = $pedData['pm_dias'] ?? 0;

        // Tenta buscar o preço de lista
        // (A cot_price_list costuma ter o codigo LIKE :cod e a embalagem EXATA)
        $preco_lista_usd = 0;
        $preco_lista_brl = 0;
        $ptax_usado = 0;
        $desconto_brl = 0;
        $desconto_pct = 0;
        $comissao_base_pct = 0;
        $lista_nao_encontrada = 1;
        
        $codigo9 = substr(trim($codigo), 0, 9);
        
        // Tenta buscar o preço pela Embalagem limpa (sem parênteses) ou pela versão inteira
        $stmtPrice = $pdo->prepare("SELECT preco_net_usd FROM cot_price_list WHERE codigo LIKE ? AND (embalagem = ? OR embalagem = ?) ORDER BY id DESC LIMIT 1");
        $stmtPrice->execute(["{$codigo9}%", $embalagemLimpaPriceList, $embalagem]);
        $priceRow = $stmtPrice->fetch(PDO::FETCH_ASSOC);

        if ($priceRow && $priceRow['preco_net_usd'] > 0) {
            $preco_lista_usd = (float)$priceRow['preco_net_usd'];
            $lista_nao_encontrada = 0;
            
            // Gerencia PTAX
            if ($data_nf) {
                // Tenta cache do BD primeiro
                $stmtPtax = $pdo->prepare("SELECT cotacao_venda FROM fin_ptax_rates WHERE data_cotacao = ?");
                $stmtPtax->execute([$data_nf]);
                $ptaxRow = $stmtPtax->fetch(PDO::FETCH_ASSOC);
                
                if ($ptaxRow && $ptaxRow['cotacao_venda'] > 0) {
                    $ptax_usado = (float)$ptaxRow['cotacao_venda'];
                } else {
                    // Busca na API Olinda
                    $apiRates = fetchPtaxRange($data_nf, $data_nf);
                    if (!empty($apiRates)) {
                        $ptax_usado = (float)($apiRates[$data_nf]['venda'] ?? 0);
                        if ($ptax_usado > 0) {
                            $stmtUpsert = $pdo->prepare("INSERT INTO fin_ptax_rates (data_cotacao, cotacao_compra, cotacao_venda) 
                                VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE cotacao_compra=VALUES(cotacao_compra), cotacao_venda=VALUES(cotacao_venda)");
                            $stmtUpsert->execute([$data_nf, $apiRates[$data_nf]['compra'], $apiRates[$data_nf]['venda']]);
                        }
                    }
                }
            }
            
            // Fallback PTAX (se não achou no dia) - pega a mais recente antes do dia
            if ($ptax_usado == 0 && $data_nf) {
                $stmtPtaxAlt = $pdo->prepare("SELECT cotacao_venda FROM fin_ptax_rates WHERE data_cotacao <= ? ORDER BY data_cotacao DESC LIMIT 1");
                $stmtPtaxAlt->execute([$data_nf]);
                $ptaxRowAlt = $stmtPtaxAlt->fetch(PDO::FETCH_ASSOC);
                if ($ptaxRowAlt) $ptax_usado = (float)$ptaxRowAlt['cotacao_venda'];
            }
            
            if ($ptax_usado > 0) {
                $preco_lista_brl = $preco_lista_usd * $ptax_usado;
            }

            // Desconto = PrecoNET_UN - PrecoLista
            if ($preco_lista_brl > 0) {
                $desconto_brl = $preco_lista_brl - $preco_net_un; // positivo=teve desconto
                $desconto_pct = $desconto_brl / $preco_lista_brl;
                if ($desconto_pct < 0) $desconto_pct = 0;
            }

            // Matriz %
            if ($desconto_pct <= 0) {
                $comissao_base_pct = 0.0100; // 1%
            } elseif ($desconto_pct <= 0.05) {
                $comissao_base_pct = 0.0090; // 0.9%
            } elseif ($desconto_pct <= 0.10) {
                $comissao_base_pct = 0.0070; // 0.7%
            } elseif ($desconto_pct <= 0.15) {
                $comissao_base_pct = 0.0050; // 0.5%
            } elseif ($desconto_pct <= 0.20) {
                $comissao_base_pct = 0.0040; // 0.4%
            } else {
                $comissao_base_pct = 0.0025; // 0.25% (acima 20%)
            }
        }

        // Calcula Ajuste do PM (Baseline 28d)
        $pm_semanas = $pm_dias / 7;
        
        $semanasAjuste = 0;
        // Diferenca em semanas exatas (arredondado pra baixo ou float? Assumindo cálculo float/fracional ou semanas exatas, vamos pelo float)
        $diffSemanas = ($pm_dias - 28) / 7;
        $ajuste_prazo_pct = - ($diffSemanas * 0.0005); // -0.05% por semana a mais, +0.05% por semana a menos

        $comissao_final_pct = $comissao_base_pct + $ajuste_prazo_pct;
        if ($comissao_final_pct < 0.0005) {
            $comissao_final_pct = 0.0005; // piso mínimo 0.05%
        }

        // Se a lista não foi encontrada, a comissão final ainda não pode ser calculada com segurança (fica 0 ou calculamos só o ajuste)
        if ($lista_nao_encontrada) {
            $comissao_base_pct = 0;
            $comissao_final_pct = 0;
        }

        $valor_comissao = $venda_net * $comissao_final_pct;

        // Aprovações Exceção
        $flag_aprovacao = ($desconto_pct > 0.20 || $pm_dias > 42) ? 1 : 0;
        $flag_teto = ($valor_comissao > 25000) ? 1 : 0;

        if ($flag_teto) {
            $excedente = $valor_comissao - 25000;
            $premio = $excedente * 0.10;
            $valor_comissao = 25000 + $premio; // Combina comissão teto + prêmio no valor final para exibição
        }

        $stmtInsert->execute([
            $batchId, $nfe, $data_nf, $cfop, $codigo9, $descricaoLimpa, $embalagem, $fabricante, $representante, $cliente,
            $qtde, $valorBruto, $icms, $pis, $cofins, $venda_net, cloneDecimal($preco_net_un), cloneDecimal($preco_lista_brl), cloneDecimal($desconto_brl),
            cloneDecimal($desconto_pct), cloneDecimal($comissao_base_pct), cloneDecimal($pm_dias), cloneDecimal($pm_semanas), 
            cloneDecimal($ajuste_prazo_pct), cloneDecimal($comissao_final_pct), cloneDecimal($valor_comissao), 
            $flag_aprovacao, $flag_teto, $lista_nao_encontrada
        ]);

        $itemsAdicionados++;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'batch_id' => $batchId, 
        'items_processed' => $itemsAdicionados,
        'items_ignored' => $itemsIgnorados,
        'ignore_reasons' => $motivosIgnorados
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// helper to fix format
function cloneDecimal($val) {
    return is_numeric($val) ? number_format((float)$val, 4, '.', '') : 0;
}
