<?php
require_once __DIR__ . '/config.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

function respond(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['status' => 'error', 'message' => 'Metode HTTP tidak diizinkan.']);
}

$keywords = trim((string) ($_POST['keywords'] ?? ''));

if ($keywords === '') {
    respond(400, ['status' => 'error', 'message' => 'Kata kunci kegiatan tidak boleh kosong.']);
}

$apiKey = trim((string) GEMINI_API_KEY);
if ($apiKey === '') {
    respond(500, [
        'status' => 'error',
        'message' => 'Gemini API belum dikonfigurasi. Atur environment variable GEMINI_API_KEY pada server.'
    ]);
}

$systemPrompt = 'Ubah kata kunci kegiatan menjadi 1 sampai 2 kalimat ringkas, formal, dan fokus pada tindakan teknis atau operasional utama untuk laporan harian kedinasan. Langsung hasilkan teks uraian kegiatan tanpa kalimat pembuka atau penutup.';

$payload = [
    'contents' => [[
        'parts' => [[
            'text' => $systemPrompt . "\n\nKata Kunci Kegiatan: " . $keywords
        ]]
    ]]
];

$model = 'gemini-3-flash-preview';
$endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent';

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

if ($response === false) {
    error_log('Gemini cURL error: ' . $curlError);
    respond(502, [
        'status' => 'error',
        'message' => 'Tidak dapat terhubung ke Gemini API. Periksa koneksi server dan sertifikat TLS.'
    ]);
}

$responseData = json_decode($response, true);
if (!is_array($responseData)) {
    error_log('Gemini returned a non-JSON response. HTTP status: ' . $httpCode);
    respond(502, ['status' => 'error', 'message' => 'Respons Gemini API tidak dapat dibaca.']);
}

if ($httpCode >= 200 && $httpCode < 300) {
    $generatedText = trim((string) ($responseData['candidates'][0]['content']['parts'][0]['text'] ?? ''));
    if ($generatedText !== '') {
        respond(200, ['status' => 'success', 'text' => $generatedText]);
    }
    respond(502, ['status' => 'error', 'message' => 'Gemini API tidak mengembalikan teks uraian.']);
}

$errorMessage = (string) ($responseData['error']['message'] ?? 'Permintaan ke Gemini API gagal.');
error_log('Gemini API HTTP ' . $httpCode . ': ' . $errorMessage);

if ($httpCode === 400 || $httpCode === 401 || $httpCode === 403) {
    respond($httpCode, [
        'status' => 'error',
        'message' => 'Autentikasi atau konfigurasi Gemini API ditolak. Periksa key dan izin API pada Google AI Studio.'
    ]);
}
if ($httpCode === 404) {
    respond(502, [
        'status' => 'error',
        'message' => 'Model Gemini yang dikonfigurasi tidak tersedia untuk endpoint atau project ini. Periksa model yang didukung.'
    ]);
}
respond(502, ['status' => 'error', 'message' => 'Gemini API sedang gagal memproses permintaan (HTTP ' . $httpCode . ').']);
