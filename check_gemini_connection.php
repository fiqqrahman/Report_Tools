
<?php
header('Content-Type: text/plain; charset=utf-8');

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent';

$payload = [
    'contents' => [[
        'parts' => [['text' => 'Tes koneksi']]
    ]]
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-goog-api-key: ' . trim((string) getenv('GEMINI_API_KEY')),
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "PHP: " . PHP_VERSION . "\n";
echo "HTTP Status: " . $status . "\n";
echo "cURL Error Number: " . $errno . "\n";
echo "cURL Error: " . ($error ?: 'Tidak ada') . "\n";

if ($response !== false) {
    $data = json_decode($response, true);
    echo "API Message: "
        . ($data['error']['message'] ?? 'Tidak ada pesan error') . "\n";
}
