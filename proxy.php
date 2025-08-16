<?php
// proxy.php - Versão Robusta para contornar bloqueios de IPTV

// Pega a URL do stream da query string
$stream_url = $_GET['url'] ?? null;

// Validação da URL
if (!$stream_url || !filter_var($stream_url, FILTER_VALIDATE_URL)) {
    http_response_code(400 );
    die('URL de stream inválida ou não fornecida.');
}

// ==================================================================
// SIMULAÇÃO DE UM PLAYER LEGÍTIMO
// ==================================================================
// Cabeçalhos que um player de IPTV comum (como o VLC ou Tivimate) poderia enviar.
// Isso ajuda a "enganar" o servidor que verifica esses valores.
$headers = [
    'Accept: */*',
    'Connection: keep-alive',
    'Accept-Encoding: gzip, deflate',
    'Accept-Language: en-US,en;q=0.9'
];

// User-Agent de um player conhecido. Esta é a parte mais importante.
// Estamos nos disfarçando de "VLC media player".
$user_agent = 'VLC/3.0.20 (Windows; x86_64)';
// ==================================================================

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $stream_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false); // Não queremos os cabeçalhos da resposta na saída
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Segue redirecionamentos
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos

// APLICA A SIMULAÇÃO
curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
// Habilita o cURL para decodificar respostas comprimidas (gzip)
curl_setopt($ch, CURLOPT_ENCODING, '');

$data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE );
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$error = curl_error($ch);

curl_close($ch);

// Se a requisição falhou ou o servidor respondeu com erro
if ($data === false || $http_code >= 400 ) {
    // Retorna um erro claro para o navegador, incluindo a mensagem de erro do cURL
    http_response_code($http_code > 0 ? $http_code : 502 ); // 502 Bad Gateway é apropriado para erros de proxy
    die("Proxy falhou ao buscar o stream. Servidor de origem respondeu com o código: {$http_code}. Erro cURL: {$error}" );
}

// Retransmite o conteúdo com os cabeçalhos corretos para o Shaka Player
header('Content-Type: ' . ($content_type ?: 'application/vnd.apple.mpegurl'));
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');

echo $data;
