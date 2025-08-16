<?php
// api.php - ATUALIZADO PARA USAR O PROXY

// Configuração de erros
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

header('Content-Type: application/json');

// Verifica sessão
session_start();
if (!isset($_SESSION['xtream_user'])) {
    http_response_code(401 );
    die(json_encode([
        'status' => 'error',
        'message' => 'Não autenticado',
        'code' => 401
    ]));
}

$user = $_SESSION['xtream_user'];

// Verifica método
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405 );
    die(json_encode([
        'status' => 'error',
        'message' => 'Método não permitido',
        'code' => 405
    ]));
}

/**
 * Função para buscar dados da API Xtream Codes.
 *
 * @param string $server URL base do servidor IPTV.
 * @param string $username Usuário.
 * @param string $password Senha.
 * @param array $params Parâmetros da ação (ex: ['action' => 'get_live_streams']).
 * @return array|null Dados decodificados da API ou null em caso de falha.
 */
function get_xtream_data($server, $username, $password, $params) {
    $url = "{$server}/player_api.php?username={$username}&password={$password}&" . http_build_query($params );

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Aumentado para 15 segundos
    curl_setopt($ch, CURLOPT_USERAGENT, 'Xtream-Player/1.0'); // Simula um user-agent comum
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        // Se houver um erro no cURL, lança uma exceção
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("Erro de comunicação com o servidor IPTV: " . $error_msg, 503); // 503 Service Unavailable
    }

    curl_close($ch);
    return json_decode($response, true);
}

try {
    // Sanitiza e valida parâmetros
    $action = $_GET['action'] ?? '';
    $content_type = $_GET['content_type'] ?? 'live';
    $category_id = $_GET['category_id'] ?? null;
    $stream_id = $_GET['stream_id'] ?? null;
    $series_id = $_GET['series_id'] ?? null;
    $vod_id = $_GET['vod_id'] ?? null;

    // Valida content_type
    $valid_types = ['live', 'movie', 'series'];
    if (!in_array($content_type, $valid_types)) {
        throw new Exception('Tipo de conteúdo inválido', 400);
    }

    $data = null;

    switch ($action) {
        case 'get_categories':
            $action_map = ['live' => 'get_live_categories', 'movie' => 'get_vod_categories', 'series' => 'get_series_categories'];
            $data = get_xtream_data($user['server'], $user['username'], $user['password'], ['action' => $action_map[$content_type]]);
            break;

        case 'get_streams':
            $action_map = ['live' => 'get_live_streams', 'movie' => 'get_vod_streams', 'series' => 'get_series'];
            $params = ['action' => $action_map[$content_type]];
            if ($category_id && $category_id !== 'all') {
                $params['category_id'] = $category_id;
            }
            $data = get_xtream_data($user['server'], $user['username'], $user['password'], $params);
            break;

        // ==================================================================
        // SEÇÃO MODIFICADA PARA USAR O PROXY
        // ==================================================================
        case 'get_stream_url':
            if (empty($stream_id)) {
                throw new Exception('ID do stream é obrigatório', 400);
            }

            $type_map = ['live' => 'live', 'movie' => 'movie', 'series' => 'series'];
            $type_folder = $type_map[$content_type];

            // 1. Monta a URL original e insegura do stream
            $original_stream_url = "{$user['server']}/{$type_folder}/{$user['username']}/{$user['password']}/{$stream_id}.m3u8";

            // 2. Monta a nova URL segura, que passa pelo nosso proxy
            //    A função urlencode() garante que a URL seja passada corretamente como parâmetro.
            $proxy_stream_url = "proxy.php?url=" . urlencode($original_stream_url);

            // 3. Retorna a URL do proxy para o front-end
            $data = ['stream_url' => $proxy_stream_url];
            break;
        // ==================================================================
        // FIM DA SEÇÃO MODIFICADA
        // ==================================================================

        case 'get_series_info':
            if (empty($series_id)) {
                throw new Exception('ID da série é obrigatório', 400);
            }
            $data = get_xtream_data($user['server'], $user['username'], $user['password'], ['action' => 'get_series_info', 'series_id' => $series_id]);
            break;

        case 'get_vod_info':
             if (empty($vod_id)) {
                throw new Exception('ID do VOD é obrigatório', 400);
             }
             $data = get_xtream_data($user['server'], $user['username'], $user['password'], ['action' => 'get_vod_info', 'vod_id' => $vod_id]);
             break;

        default:
            throw new Exception('Ação inválida', 400);
    }

    // Resposta de sucesso padrão
    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (Exception $e) {
    // Resposta de erro padrão
    http_response_code($e->getCode( ) ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode() ?: 500
    ]);
}
