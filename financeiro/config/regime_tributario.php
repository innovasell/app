<?php
// Configurações e cálculos base para o Módulo Tributário/Financeiro NFe

/**
 * Retorna as alíquotas de PIS e COFINS aplicáveis baseadas no regime (Lucro Presumido ou Lucro Real).
 * Assume regime cumulativo para Presumido e não-cumulativo para o Real.
 *
 * @param float $baseCalculo A base de cálculo (tipicamente Valor Produto - Desconto)
 * @param string $regime Pode ser 'presumido' ou 'real'
 * @return array [Valor PIS, % PIS, Valor COFINS, % COFINS]
 */
function calcularPisCofins(float $baseCalculo, string $regime): array {
    if ($regime === 'real') {
        // Lucro Real — Regime Não-Cumulativo (Lei 10.637/02 e 10.833/03)
        $pPIS    = 1.65;
        $pCOFINS = 7.60;
    } else {
        // Lucro Presumido — Regime Cumulativo (Lei 9.718/98)
        $pPIS    = 0.65;
        $pCOFINS = 3.00;
    }

    $vPIS    = round($baseCalculo * ($pPIS    / 100), 2);
    $vCOFINS = round($baseCalculo * ($pCOFINS / 100), 2);
    
    return [$vPIS, $pPIS, $vCOFINS, $pCOFINS];
}
