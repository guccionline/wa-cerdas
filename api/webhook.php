<?php
// 1. Konfigurasi - Ambil dari Environment Variables
$apiKey      = getenv('DEEPSEEK_API_KEY') ?: '';
$secretToken = getenv('WEBHOOK_SECRET')   ?: '';

// 2. Header Response
header("Content-Type: application/json");

// 3. Verifikasi keamanan - cek secret token dari header
//    Hanya wajib jika WEBHOOK_SECRET sudah dikonfigurasi
$headers       = getallheaders();
$receivedToken = isset($headers['X-Webhook-Token']) ? $headers['X-Webhook-Token'] : '';
if ($secretToken !== '' && $receivedToken !== $secretToken) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// 4. Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

// 5. Baca request dari platform chat
$input_raw = file_get_contents('php://input');
$data      = json_decode($input_raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
}

// 6. Ambil data pesan
$app_name     = $data['app']     ?? 'Tidak diketahui';
$sender       = $data['sender']  ?? 'Tidak diketahui';
$user_message = $data['message'] ?? '';

// 7. Fungsi untuk memanggil DeepSeek AI via OpenAI-compatible endpoint
function getDeepSeekResponse(string $message): string {
    global $apiKey;

    if ($apiKey === '') {
        return "Maaf, API Key DeepSeek belum dikonfigurasi.";
    }

    $payload = json_encode([
        "model"    => "deepseek-chat",
        "messages" => [
            [
                "role"    => "system",
                "content" => "Kamu adalah asisten WhatsApp yang membantu dan ramah. Jawab dengan singkat dan jelas."
            ],
            [
                "role"    => "user",
                "content" => $message
            ]
        ],
        "temperature" => 0.7,
        "max_tokens"  => 1024
    ]);

    $ch = curl_init("https://api.deepseek.com/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            "Content-Type: application/json",
            "Authorization: Bearer {$apiKey}"
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("DeepSeek cURL Error: {$curlErr}");
        return "Maaf, terjadi kesalahan koneksi ke DeepSeek.";
    }

    $decoded = json_decode($result, true);

    if ($httpCode !== 200 || !isset($decoded['choices'][0]['message']['content'])) {
        $errMsg = $decoded['error']['message'] ?? $result;
        error_log("DeepSeek API Error (HTTP {$httpCode}): {$errMsg}");
        return "Maaf, terjadi kesalahan dari layanan AI.";
    }

    return trim($decoded['choices'][0]['message']['content']);
}

// 8. Proses pesan dengan DeepSeek
$bot_reply = getDeepSeekResponse($user_message);

// 9. Log ke stderr (tidak menulis file di direktori web)
error_log(date('Y-m-d H:i:s') . " | App: {$app_name} | Sender: {$sender} | User: {$user_message} | Bot: {$bot_reply}");

// 10. Kirim response balik ke platform chat
http_response_code(200);
echo json_encode([
    "status"    => "success",
    "reply"     => $bot_reply,
    "timestamp" => time()
]);
