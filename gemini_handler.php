<?php
require_once __DIR__ . '/config.php';

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

$systemPrompt = "Antum adalah asisten penulisan laporan harian kedinasan Tenaga Layanan Umum Dinas Pendidikan Kota Palangka Raya. Tugas antum: Ubah kata kunci kegiatan menjadi 1 sampai 2 kalimat ringkas, formal, dan fokus pada tindakan teknis atau operasional utama. Dilarang menambah kalimat templat seperti pelaporan ke atasan, pertanggungjawaban tugas, kata pengantar, atau penutup. Langsung hasilkan teks uraian kegiatan.";

$payload = [
    'contents' => [
        [
            'parts' => [
                ['text' => $systemPrompt . "\n\nKata Kunci Kegiatan: " . $keywords]
            ]
        ]
    ]
];

$cleanApiKey = trim(GEMINI_API_KEY);
$endpoint    = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash-lite:generateContent?key=' . $cleanApiKey;

$ch = curl_init($endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $responseData = json_decode($response, true);

    $generatedText = '';
    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $generatedText = $responseData['candidates'][0]['content']['parts'][0]['text'];
    } elseif (isset($responseData['output_text'])) {
        $generatedText = $responseData['output_text'];
    } elseif (isset($responseData['text'])) {
        $generatedText = $responseData['text'];
    }

    $generatedText = trim($generatedText);

    if (!empty($generatedText)) {
        echo json_encode([
            'status' => 'success',
            'text'   => $generatedText
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Respon API kosong atau format JSON tidak terurai.',
            'raw'     => $response
        ]);
    }
} else {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal memproses ke Gemini API (HTTP ' . $httpCode . '): ' . ($curlError ?: $response)
    ]);
}
