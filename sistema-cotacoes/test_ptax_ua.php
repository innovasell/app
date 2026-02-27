<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$dataHoje = date('m-d-Y');
$dataInicio = date('m-d-Y', strtotime('-5 days'));

$url = "https://olinda.bcb.gov.br/olinda/servico/PTAX/versao/v1/odata/CotacaoDolarPeriodo(dataInicial=@dataInicial,dataFinalCotacao=@dataFinalCotacao)?@dataInicial='$dataInicio'&@dataFinalCotacao='$dataHoje'&%24orderby=dataHoraCotacao%20desc&%24top=1&%24format=json";

$options = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3\r\n"
    ]
];
$context = stream_context_create($options);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo "Error with User-Agent.\n";
    print_r(error_get_last());
} else {
    echo "Response with User-Agent:\n$response\n";
}
?>