# Konfigurasi Gemini API

Fitur AI membaca API key dari environment variable `GEMINI_API_KEY`. Key tidak boleh disimpan di source code atau dikomit ke GitHub.

## Windows / XAMPP
Atur environment variable `GEMINI_API_KEY` untuk proses yang menjalankan Apache, kemudian restart Apache. Cara yang sesuai bergantung pada cara XAMPP/Apache dijalankan. Pastikan PHP bisa membacanya melalui `getenv('GEMINI_API_KEY')`.

## Linux
Atur `GEMINI_API_KEY` di environment service PHP/Apache atau konfigurasi deployment yang terlindungi, lalu restart service terkait.

Gunakan API key baru yang valid dari Google AI Studio dan cabut key yang pernah terekspos. Jangan menaruh key di file README, source code, URL, issue, atau commit.

Handler memakai header `x-goog-api-key` dan mengaktifkan verifikasi TLS. Model yang dikonfigurasi adalah `gemini-3.8-flash`; pastikan nama model tersebut tersedia untuk project/API key yang dipakai. Jika tidak tersedia, pilih model Gemini 3 yang terdaftar pada dokumentasi resmi untuk project tersebut.
