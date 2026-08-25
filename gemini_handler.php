<?php
require_once __DIR__ . '/config.php';

// PERBAIKAN 1: Matikan display_errors agar warning/deprecation PHP tidak merusak JSON frontend
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Metode HTTP tidak diizinkan.']);
    exit;
}

$keywords = trim($_POST['keywords'] ?? '');

if (empty($keywords)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Kata kunci kegiatan tidak boleh kosong.']);
    exit;
}

$systemPrompt = "Ubah kata kunci kegiatan menjadi 1 sampai 2 kalimat ringkas, formal, dan fokus pada tindakan teknis atau operasional utama untuk laporan harian kedinasan. Langsung hasilkan teks uraian kegiatan tanpa kalimat pembuka atau penutup.";

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $systemPrompt . "\n\nKata Kunci Kegiatan: " . $keywords]
            ]
        ]
    ]
];

$apiKey = trim(GEMINI_API_KEY);
// Endpoint yang terbukti berhasil di environment antum
$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=' . $apiKey;

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// PERBAIKAN 2: curl_close($ch) DIHAPUS karena deprecated di PHP 8.5+

if ($curlError) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'cURL Error: ' . $curlError
    ]);
    exit;
}

$responseData = json_decode($response, true);

if ($httpCode === 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $generatedText = trim($responseData['candidates'][0]['content']['parts'][0]['text']);

    // Output Murni JSON tanpa gangguan HTML warning
    echo json_encode([
        'status' => 'success',
        'text'   => $generatedText
    ]);
} else {
    http_response_code($httpCode ?: 500);
    $errorMessage = $responseData['error']['message'] ?? 'Gagal memproses respon dari Gemini API.';
    echo json_encode([
        'status'  => 'error',
        'message' => 'API Error (HTTP ' . $httpCode . '): ' . $errorMessage
    ]);
}
