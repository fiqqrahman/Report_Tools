<?php

$key = getenv('GEMINI_API_KEY');

header('Content-Type: text/plain; charset=utf-8');

echo ($key !== false && trim($key) !== '')
    ? 'GEMINI_API_KEY terdeteksi'
    : 'GEMINI_API_KEY belum terbaca';
