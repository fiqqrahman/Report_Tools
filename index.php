<?php
session_start();

$driveBaseFolder = 'G:/My Drive/PJLP/2026/DOKUMENTASI';

if (!is_dir($driveBaseFolder)) {
    $driveBaseFolder = __DIR__ . '/drive_photos';
    if (!is_dir($driveBaseFolder)) {
        mkdir($driveBaseFolder, 0777, true);
    }
}

if (!isset($_SESSION['staged_photos'])) {
    $_SESSION['staged_photos'] = [];
}

if (!isset($_SESSION['logs'])) {
    $_SESSION['logs'] = [];
}

$selectedSubfolder = $_GET['subfolder'] ?? ($_SESSION['last_subfolder'] ?? '');

if (isset($_GET['get_image'])) {
    $relPath  = $_GET['get_image'];
    $baseReal = realpath($driveBaseFolder);
    $filePath = realpath($driveBaseFolder . '/' . $relPath);

    if ($filePath && $baseReal && str_starts_with($filePath, $baseReal) && file_exists($filePath)) {
        $mimeType = mime_content_type($filePath);
        header("Content-Type: " . $mimeType);
        header("Content-Length: " . filesize($filePath));
        readfile($filePath);
        exit;
    }
    http_response_code(404);
    exit;
}

function getPhotoDate(string $filePath): string
{
    $rawDate = '';
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($filePath);
        if (isset($exif['DateTimeOriginal'])) {
            $rawDate = $exif['DateTimeOriginal'];
        }
    }

    $timestamp = empty($rawDate) ? filemtime($filePath) : strtotime($rawDate);
    if ($timestamp === false) {
        $timestamp = time();
    }

    $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $months = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $dayName   = $days[(int)date('w', $timestamp)];
    $dayNum    = date('j', $timestamp);
    $monthName = $months[(int)date('n', $timestamp)];
    $year      = date('Y', $timestamp);

    return "{$dayName}, {$dayNum} {$monthName} {$year}";
}

$subfolderDirs = glob($driveBaseFolder . '/*', GLOB_ONLYDIR);
$availableSubfolders = [];
if ($subfolderDirs !== false) {
    foreach ($subfolderDirs as $dir) {
        $availableSubfolders[] = basename($dir);
    }
}

if (!empty($selectedSubfolder)) {
    $targetDir = realpath($driveBaseFolder . '/' . basename($selectedSubfolder));
    $realBase  = realpath($driveBaseFolder);
    if ($targetDir && $realBase && str_starts_with($targetDir, $realBase) && is_dir($targetDir)) {
        $_SESSION['last_subfolder'] = $selectedSubfolder;
    } else {
        $selectedSubfolder = '';
        unset($_SESSION['last_subfolder']);
    }
}

$scannedFiles = [];
if (!empty($selectedSubfolder)) {
    $activeFolderPath = $driveBaseFolder . '/' . $selectedSubfolder;
    $files = glob($activeFolderPath . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
    if ($files !== false) {
        foreach ($files as $file) {
            $filename = basename($file);
            $scannedFiles[] = [
                'name'     => $filename,
                'rel_path' => $selectedSubfolder . '/' . $filename,
                'path'     => $file,
                'date'     => getPhotoDate($file)
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'stage_photo') {
        $photoRelPath = $_POST['photo_rel_path'] ?? '';
        if (!empty($photoRelPath)) {
            $baseReal = realpath($driveBaseFolder);
            $fullPath = realpath($driveBaseFolder . '/' . $photoRelPath);
            if ($fullPath && $baseReal && str_starts_with($fullPath, $baseReal) && file_exists($fullPath)) {
                if (count($_SESSION['staged_photos']) < 2 && !in_array($photoRelPath, $_SESSION['staged_photos'])) {
                    $_SESSION['staged_photos'][] = $photoRelPath;
                }
            }
        }
        header("Location: index.php?subfolder=" . urlencode($selectedSubfolder));
        exit;
    }

    if ($_POST['action'] === 'unstage_photo') {
        $index = (int)($_POST['photo_index'] ?? -1);
        if (isset($_SESSION['staged_photos'][$index])) {
            array_splice($_SESSION['staged_photos'], $index, 1);
        }
        header("Location: index.php?subfolder=" . urlencode($selectedSubfolder));
        exit;
    }

    if ($_POST['action'] === 'clear_staged') {
        $_SESSION['staged_photos'] = [];
        header("Location: index.php?subfolder=" . urlencode($selectedSubfolder));
        exit;
    }

    if ($_POST['action'] === 'add') {
        $tanggal = $_POST['tanggal'] ?? '';
        $uraian  = $_POST['uraian'] ?? '';

        $photoDataArray = [];
        $count = 0;
        $baseReal = realpath($driveBaseFolder);

        foreach ($_SESSION['staged_photos'] as $relPath) {
            if ($count >= 2) break;
            $fullPath = realpath($driveBaseFolder . '/' . $relPath);
            if ($fullPath && $baseReal && str_starts_with($fullPath, $baseReal) && file_exists($fullPath)) {
                $fileData = file_get_contents($fullPath);
                $mimeType = mime_content_type($fullPath);
                $photoDataArray[] = 'data:' . $mimeType . ';base64,' . base64_encode($fileData);
                $count++;
            }
        }

        if (isset($_FILES['uploaded_photos']) && count($_FILES['uploaded_photos']['name']) > 0) {
            for ($i = 0; $i < count($_FILES['uploaded_photos']['name']); $i++) {
                if ($count >= 2) break;
                if ($_FILES['uploaded_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName  = $_FILES['uploaded_photos']['tmp_name'][$i];
                    $mimeType = $_FILES['uploaded_photos']['type'][$i];
                    $fileData = file_get_contents($tmpName);
                    $photoDataArray[] = 'data:' . $mimeType . ';base64,' . base64_encode($fileData);
                    $count++;
                }
            }
        }

        $_SESSION['logs'][] = [
            'tanggal' => $tanggal,
            'uraian'  => $uraian,
            'fotos'   => $photoDataArray
        ];

        $_SESSION['staged_photos'] = [];

        header("Location: index.php?subfolder=" . urlencode($selectedSubfolder));
        exit;
    }

    if ($_POST['action'] === 'reset') {
        $_SESSION['logs'] = [];
        $_SESSION['staged_photos'] = [];
        header("Location: index.php?subfolder=" . urlencode($selectedSubfolder));
        exit;
    }
}

$latestEXIFDate = '';
if (!empty($_SESSION['staged_photos'])) {
    $lastStagedRel = end($_SESSION['staged_photos']);
    $fullPath = realpath($driveBaseFolder . '/' . $lastStagedRel);
    if ($fullPath && file_exists($fullPath)) {
        $latestEXIFDate = getPhotoDate($fullPath);
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'word') {
    $bulan = $_GET['bulan'] ?? date('F Y');

    header("Content-Type: application/vnd.ms-word; charset=utf-8");
    header("Content-Disposition: attachment; filename=Laporan_Harian_PJLP_" . date('Ymd') . ".doc");
    header("Pragma: no-cache");
    header("Expires: 0");

    $logoPath = __DIR__ . '/logo.png';
    $logoBase64 = '';
    if (file_exists($logoPath)) {
        $logoData = file_get_contents($logoPath);
        $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
    }
?>
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>

    <head>
        <meta charset="utf-8">
        <style>
            body {
                font-family: 'Arial', sans-serif;
                font-size: 11pt;
            }

            .kop-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 5px;
            }

            .kop-table td {
                border: none;
                padding: 2px;
            }

            .line-double {
                border-bottom: 3px double #000;
                margin-bottom: 15px;
            }

            .title {
                text-align: center;
                font-weight: bold;
                font-size: 12pt;
                margin-bottom: 15px;
            }

            .biodata-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 15px;
            }

            .biodata-table td {
                border: none;
                padding: 3px;
                vertical-align: top;
            }

            .main-table {
                width: 100%;
                border-collapse: collapse;
            }

            .main-table th,
            .main-table td {
                border: 1px solid #000;
                padding: 6px;
                text-align: left;
                vertical-align: top;
            }

            .main-table th {
                background-color: #f2f2f2;
                text-align: center;
            }

            .ttd-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 30px;
            }

            .ttd-table td {
                border: none;
                text-align: center;
                vertical-align: top;
            }

            .img-container {
                text-align: center;
            }

            .img-kegiatan {
                max-width: 140px;
                max-height: 100px;
                margin: 2px;
                display: inline-block;
            }
        </style>
    </head>

    <body>
        <table class="kop-table">
            <tr>
                <td style="width: 15%; text-align: center;">
                    <?php if (!empty($logoBase64)): ?>
                        <img src="<?= $logoBase64 ?>" style="width: 80px; height: auto;">
                    <?php endif; ?>
                </td>
                <td style="width: 85%; text-align: center;">
                    <span style="font-size: 12pt; font-weight: bold;">PEMERINTAH KOTA PALANGKA RAYA</span><br>
                    <span style="font-size: 14pt; font-weight: bold;">DINAS PENDIDIKAN</span><br>
                    <span style="font-size: 8pt;">Jalan G.Obos XI Komplek Perkantoran Lingkar Dalam, Jekan Raya Palangka Raya, Kalimantan Tengah 73112<br>
                        Telepon 0536 – 3222372, Faksimile (0536) 3221654<br>
                        Laman disdik.palangkaraya.go.id, Pos-el disdik@palangkaraya.go.id</span>
                </td>
            </tr>
        </table>
        <div class="line-double"></div>

        <div class="title">
            LAPORAN HARIAN<br>
            TENAGA PENYEDIA JASA LAINNYA PERORANGAN
        </div>

        <table class="biodata-table">
            <tr>
                <td style="width: 15%;">Nama</td>
                <td style="width: 2%;">:</td>
                <td style="width: 83%;">Taufiqurahman[cite: 5]</td>
            </tr>
            <tr>
                <td>Jenis Jasa</td>
                <td>:</td>
                <td>Tenaga Layanan Umum[cite: 5]</td>
            </tr>
            <tr>
                <td>Unit Kerja</td>
                <td>:</td>
                <td>Dinas Pendidikan Kota Palangka Raya[cite: 5]</td>
            </tr>
            <tr>
                <td>Bulan</td>
                <td>:</td>
                <td><?= htmlspecialchars($bulan) ?></td>
            </tr>
        </table>

        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 20%;">Hari / Tanggal</th>
                    <th style="width: 45%;">Uraian Kegiatan</th>
                    <th style="width: 30%;">Foto Kegiatan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($_SESSION['logs'])): ?>
                    <tr>
                        <td colspan="4" style="text-align:center;">Belum ada data kegiatan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($_SESSION['logs'] as $i => $log): ?>
                        <tr>
                            <td style="text-align: center;"><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($log['tanggal']) ?></td>
                            <td><?= nl2br(htmlspecialchars($log['uraian'])) ?></td>
                            <td class="img-container">
                                <?php if (!empty($log['fotos'])): ?>
                                    <?php foreach ($log['fotos'] as $imgData): ?>
                                        <img src="<?= $imgData ?>" class="img-kegiatan">
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="ttd-table">
            <tr>
                <td style="width: 50%;">
                    Mengetahui,<br>
                    Kepala Sub Bagian Umum dan Kepegawaian<br>Dinas Pendidikan Kota Palangka Raya[cite: 5]<br><br><br><br><br>
                    <b><u>Lenataurisia P., S.T., M.A.P</u></b>[cite: 5]<br>
                    NIP 198204282010012013[cite: 5]
                </td>
                <td style="width: 50%;">
                    <br>
                    Yang Melapor,<br><br><br><br><br><br>
                    <b><u>Taufiqurahman, S.Kom</u></b>[cite: 5]
                </td>
            </tr>
        </table>
    </body>

    </html>
<?php
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Laporan Harian PJLP - Dinas Pendidikan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-4" style="max-width: 960px;">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Form Log Kegiatan Harian PJLP</h5>
                <span class="badge bg-light text-primary">Cross-Folder Staging Active</span>
            </div>
            <div class="card-body">

                <div class="mb-4 p-3 bg-white border rounded">
                    <label class="form-label fw-bold text-dark">1. Pilih Folder Bulan/Tahun (Google Drive):</label>
                    <form method="GET" id="subfolderForm">
                        <select name="subfolder" class="form-select border-primary" onchange="document.getElementById('subfolderForm').submit()">
                            <option value="">-- Pilih Folder Laporan --</option>
                            <?php foreach ($availableSubfolders as $folderName): ?>
                                <option value="<?= htmlspecialchars($folderName) ?>" <?= $selectedSubfolder === $folderName ? 'selected' : '' ?>>
                                    📁 <?= htmlspecialchars($folderName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <div class="mb-4 p-3 border rounded bg-white">
                    <label class="form-label fw-bold text-dark">2. Pilih Foto dari Folder "<?= htmlspecialchars($selectedSubfolder ?: 'Belum Dipilih') ?>":</label>
                    <?php if (empty($selectedSubfolder)): ?>
                        <div class="alert alert-warning py-2 mb-0">Silakan pilih folder di atas terlebih dahulu untuk memuat daftar foto.</div>
                    <?php elseif (empty($scannedFiles)): ?>
                        <div class="alert alert-info py-2 mb-0">Tidak ada berkas gambar (.jpg, .png) di dalam folder ini.</div>
                    <?php else: ?>
                        <div class="input-group">
                            <select id="singlePhotoSelect" class="form-select border-secondary">
                                <option value="">-- Pilih Foto yang Ingin Ditambahkan --</option>
                                <?php foreach ($scannedFiles as $sf): ?>
                                    <option value="<?= htmlspecialchars($sf['rel_path']) ?>" data-date="<?= htmlspecialchars($sf['date']) ?>">
                                        📷 <?= htmlspecialchars($sf['name']) ?> (EXIF: <?= htmlspecialchars($sf['date']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="btnAddStage" class="btn btn-outline-success fw-bold">
                                ➕ Pilih Foto Ini
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card bg-white p-3 border mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold text-primary mb-0">
                            🖼️ Daftar Foto Terpilih (Maks. 2 Foto - Bebas Lintas Folder):
                        </label>
                        <?php if (!empty($_SESSION['staged_photos'])): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="clear_staged">
                                <button type="submit" class="btn btn-sm btn-link text-danger text-decoration-none p-0">Kosongkan Foto</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div id="stagedContainer" class="d-flex flex-wrap gap-3 align-items-center min-vh-10 border border-dashed rounded p-3 bg-light">
                        <?php if (empty($_SESSION['staged_photos'])): ?>
                            <span class="text-muted small italic">Belum ada foto yang dimasukkan ke daftar terpilih.</span>
                        <?php else: ?>
                            <?php foreach ($_SESSION['staged_photos'] as $idx => $relPath): ?>
                                <?php
                                $parts = explode('/', $relPath);
                                $folderLabel = $parts[0] ?? '';
                                $fileLabel = $parts[1] ?? $relPath;
                                ?>
                                <div class="card shadow-sm border text-center p-2 bg-white" style="width: 200px;">
                                    <img src="index.php?get_image=<?= urlencode($relPath) ?>"
                                        class="card-img-top rounded zoomable-img"
                                        style="height: 130px; object-fit: cover; cursor: pointer;">
                                    <div class="card-body p-2 text-truncate">
                                        <span class="badge bg-secondary mb-1"><?= htmlspecialchars($folderLabel) ?></span>
                                        <div class="small fw-bold text-dark text-truncate" title="<?= htmlspecialchars($fileLabel) ?>">
                                            <?= htmlspecialchars($fileLabel) ?>
                                        </div>
                                    </div>
                                    <form method="POST" class="mt-1">
                                        <input type="hidden" name="action" value="unstage_photo">
                                        <input type="hidden" name="photo_index" value="<?= $idx ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger w-100 py-0" style="font-size: 11px;">
                                            ❌ Hapus
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Atau Unggah Manual Tambahan (Jika Perlu):</label>
                        <input type="file" name="uploaded_photos[]" class="form-control" accept="image/*" multiple>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">3. Hari / Tanggal:</label>
                        <input type="text" name="tanggal" id="tanggalInput" class="form-control"
                            value="<?= htmlspecialchars($latestEXIFDate) ?>"
                            placeholder="Contoh: Selasa, 2 Agustus 2026" required>
                        <div class="form-text">Terisi otomatis dari foto terakhir di daftar terpilih, dapat diedit bebas.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">4. Uraian Kegiatan:</label>
                        <div class="input-group mb-2">
                            <input type="text" id="aiKeywordInput" class="form-control border-purple" placeholder="Ketik kata kunci cepat... (contoh: perbaiki jaringan server & ganti IP static)">
                            <button type="button" id="btnGenerateAI" class="btn text-white fw-bold" style="background-color: #6f42c1;">
                                ✨ Generate via AI
                            </button>
                        </div>
                        <textarea name="uraian" id="uraianInput" class="form-control" rows="3" placeholder="Uraian formal akan muncul di sini..." required></textarea>
                        <div class="form-text">Ketik kata kunci di atas lalu klik Generate via AI, atau ketik manual langsung di kotak teks.</div>
                    </div>

                    <button type="submit" class="btn btn-success fw-bold w-100 py-2">
                        ➕ Simpan Kegiatan Ke Laporan
                    </button>
                </form>

            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Preview & Export Laporan</h5>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus semua data dalam sesi ini?')">Reset Data Laporan</button>
                </form>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center mb-4">
                    <input type="hidden" name="export" value="word">
                    <div class="col-auto">
                        <label class="col-form-label fw-bold">Bulan Laporan:</label>
                    </div>
                    <div class="col-auto">
                        <input type="text" name="bulan" class="form-control" value="<?= htmlspecialchars($selectedSubfolder ?: date('F Y')) ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary fw-bold">Export ke Word (.doc)</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-secondary">
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 25%;">Hari / Tanggal</th>
                                <th style="width: 45%;">Uraian Kegiatan</th>
                                <th style="width: 25%;">Foto Kegiatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($_SESSION['logs'])): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Belum ada data kegiatan yang dimasukkan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($_SESSION['logs'] as $i => $log): ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($log['tanggal']) ?></td>
                                        <td><?= nl2br(htmlspecialchars($log['uraian'])) ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($log['fotos'])): ?>
                                                <?php foreach ($log['fotos'] as $imgData): ?>
                                                    <img src="<?= $imgData ?>" class="img-thumbnail me-1 mb-1 zoomable-img" style="max-width: 80px; max-height: 80px; cursor: pointer;">
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <form id="stagePhotoForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="stage_photo">
        <input type="hidden" name="photo_rel_path" id="stagePhotoRelPath">
    </form>

    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">Inspeksi Foto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center bg-dark">
                    <img id="modalImageTarget" src="" class="img-fluid rounded" style="max-height: 75vh;">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const singlePhotoSelect = document.getElementById('singlePhotoSelect');
        const btnAddStage = document.getElementById('btnAddStage');
        const stagePhotoForm = document.getElementById('stagePhotoForm');
        const stagePhotoRelPath = document.getElementById('stagePhotoRelPath');
        const currentStagedCount = <?= count($_SESSION['staged_photos']) ?>;

        const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        const modalImageTarget = document.getElementById('modalImageTarget');

        if (btnAddStage) {
            btnAddStage.addEventListener('click', function() {
                if (currentStagedCount >= 2) {
                    alert('Maksimal 2 foto dalam daftar terpilih!');
                    return;
                }
                let val = singlePhotoSelect.value;
                if (!val) {
                    alert('Silakan pilih foto terlebih dahulu.');
                    return;
                }
                stagePhotoRelPath.value = val;
                stagePhotoForm.submit();
            });
        }

        document.querySelectorAll('.zoomable-img').forEach(img => {
            img.addEventListener('click', function() {
                modalImageTarget.src = this.src;
                imageModal.show();
            });
        });

        document.getElementById('btnGenerateAI')?.addEventListener('click', function() {
            let keywordsInput = document.getElementById('aiKeywordInput');
            let uraianArea = document.getElementById('uraianInput');
            let btn = this;

            if (!keywordsInput.value.trim()) {
                alert('Ketik kata kunci kegiatannya terlebih dahulu, Captain!');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '⏳ Menyusun Uraian...';

            let formData = new FormData();
            formData.append('keywords', keywordsInput.value);

            fetch('gemini_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        uraianArea.value = data.text;
                    } else {
                        alert('Kesalahan: ' + data.message);
                    }
                })
                .catch(() => {
                    alert('Terjadi kendala koneksi pada server lokal.');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '✨ Generate via AI';
                });
        });
    </script>
</body>

</html>