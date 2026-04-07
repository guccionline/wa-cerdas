<?php
// 1. Sertakan library DeepSeek
require_once __DIR__ . '/../vendor/autoload.php';

use DeepSeek\DeepSeekClient;

// 2. Konfigurasi - Ambil dari Environment Variables
$apiKey = getenv('DEEPSEEK_API_KEY') ?: 'YOUR_API_KEY_HERE';
$secretToken = getenv('WEBHOOK_SECRET') ?: 'your-secret-token-here';

// 3. Header Response
header("Content-Type: application/json");

// 4. [OPSIONAL] Verifikasi keamanan - cek secret token dari header
$headers = getallheaders();
$receivedToken = isset($headers['X-Webhook-Token']) ? $headers['X-Webhook-Token'] : '';
if ($secretToken !== 'your-secret-token-here' && $receivedToken !== $secretToken) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// 5. Baca request dari platform chat
$input_raw = file_get_contents('php://input');
$data = json_decode($input_raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
}

// 6. Ambil data pesan
$app_name   = $data['app'] ?? 'Tidak diketahui';
$sender     = $data['sender'] ?? 'Tidak diketahui';
$user_message = $data['message'] ?? '';

// 7. Fungsi untuk memanggil DeepSeek AI
function getDeepSeekResponse($message) {
    global $apiKey;
    
    if (!$apiKey || $apiKey === 'YOUR_API_KEY_HERE') {
        return "Maaf, API Key DeepSeek belum dikonfigurasi.";
    }
    
    try {
        $response = DeepSeekClient::build($apiKey)
            ->setTemperature(0.7)
            ->query($message)
            ->run();
        return $response;
    } catch (Exception $e) {
        error_log("DeepSeek Error: " . $e->getMessage());
        return "Maaf, terjadi kesalahan teknis.";
    }
}

// 8. Proses pesan dengan DeepSeek
$bot_reply = getDeepSeekResponse($user_message);

// 9. Log ke file (opsional, untuk debugging)
$log_entry = date('Y-m-d H:i:s') . " | App: {$app_name} | Sender: {$sender} | User: {$user_message} | Bot: {$bot_reply}\n";
file_put_contents('webhook.log', $log_entry, FILE_APPEND);

// 10. Kirim response balik ke platform chat
$response = [
    "status" => "success",
    "reply" => $bot_reply,
    "timestamp" => time()
];

http_response_code(200);
echo json_encode($response);
?>