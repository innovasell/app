<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Copy logic from ptax.php
$dataHoje = date('m-d-Y');
$dataInicio = date('m-d-Y', strtotime('-5 days'));

echo "Date Range: $dataInicio to $dataHoje\n";

$url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@dataInicial='$dataInicio'&@dataFinalCotacao='$dataHoje'&%24orderby=dataHoraCotacao%20desc&%24top=1&%24format=json";

echo "URL: $url\n\n";

$response = file_get_contents($url);

if ($response === false) {
    echo "Error: file_get_contents failed.\n";
    print_r(error_get_last());
} else {
    echo "Response:\n$response\n";
}
?>