<?php
session_start();

$driveBaseFolder = 'G:/My Drive/PJLP/2026/DOKUMENTASI';

if (!is_dir($driveBaseFolder)) {
    $driveBaseFolder = __DIR__ . '/drive_photos';
    if (!is_dir($driveBaseFolder)) {
        mkdir($driveBaseFolder, 0777, true);
    }
}

if (!isset($_SESSION['logs'])) {
    $_SESSION['logs'] = [];
}

function getPhotoDate(string $filePath): string
{
    $date = '';
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($filePath);
        if (isset($exif['DateTimeOriginal'])) {
            $date = $exif['DateTimeOriginal'];
        }
    }
    if (empty($date)) {
        $date = date('Y-m-d', filemtime($filePath));
    } else {
        $date = date('Y-m-d', strtotime($date));
    }
    return $date;
}

$subfolderDirs = glob($driveBaseFolder . '/*', GLOB_ONLYDIR);
$availableSubfolders = [];
if ($subfolderDirs !== false) {
    foreach ($subfolderDirs as $dir) {
        $availableSubfolders[] = basename($dir);
    }
}

$selectedSubfolder = $_GET['subfolder'] ?? ($_SESSION['last_subfolder'] ?? '');

if (!empty($selectedSubfolder)) {
    $targetDir = realpath($driveBaseFolder . '/' . basename($selectedSubfolder));
    $realBase = realpath($driveBaseFolder);
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
                'name' => $filename,
                'path' => $file,
                'date' => getPhotoDate($file)
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $tanggal = $_POST['tanggal'] ?? '';
        $uraian = $_POST['uraian'] ?? '';
        $selectedPhotos = $_POST['photos'] ?? [];

        $photoDataArray = [];
        $count = 0;

        if (!empty($selectedSubfolder)) {
            $activeFolderPath = realpath($driveBaseFolder . '/' . $selectedSubfolder);
            foreach ($selectedPhotos as $photoName) {
                if ($count >= 2) break;
                $fullPath = realpath($activeFolderPath . '/' . basename($photoName));
                if ($fullPath && str_starts_with($fullPath, $activeFolderPath) && file_exists($fullPath)) {
                    $fileData = file_get_contents($fullPath);
                    $mimeType = mime_content_type($fullPath);
                    $photoDataArray[] = 'data:' . $mimeType . ';base64,' . base64_encode($fileData);
                    $count++;
                }
            }
        }

        if (isset($_FILES['uploaded_photos']) && count($_FILES['uploaded_photos']['name']) > 0) {
            for ($i = 0; $i < count($_FILES['uploaded_photos']['name']); $i++) {
                if ($count >= 2) break;
                if ($_FILES['uploaded_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['uploaded_photos']['tmp_name'][$i];
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

        header("Location: index.php?subfolder=" . urlencode($selectedSubfolder));
        exit;
    }

    if ($_POST['action'] === 'reset') {
        $_SESSION['logs'] = [];
        header("Location: index.php?subfolder=" . urlencode($selectedSubfolder));
        exit;
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
                <td style="width: 83%;">Taufiqurahman</td>
            </tr>
            <tr>
                <td>Jenis Jasa</td>
                <td>:</td>
                <td>Tenaga Layanan Umum</td>
            </tr>
            <tr>
                <td>Unit Kerja</td>
                <td>:</td>
                <td>Dinas Pendidikan Kota Palangka Raya</td>
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
                    Kepala Sub Bagian Umum dan Kepegawaian<br>Dinas Pendidikan Kota Palangka Raya<br><br><br><br><br>
                    <b><u>Lenataurisia P., S.T., M.A.P</u></b><br>
                    NIP 198204282010012013
                </td>
                <td style="width: 50%;">
                    <br>
                    Yang Melapor,<br><br><br><br><br><br>
                    <b><u>Taufiqurahman, S.Kom</u></b>
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
                <span class="badge bg-light text-primary">Google Drive Sync Enabled</span>
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

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label fw-bold">2. Pilih Foto dari Folder "<?= htmlspecialchars($selectedSubfolder ?: 'Belum Dipilih') ?>" (Maks. 2 Foto):</label>
                        <?php if (empty($selectedSubfolder)): ?>
                            <div class="alert alert-warning py-2">Silakan pilih folder di atas terlebih dahulu untuk memuat daftar foto.</div>
                        <?php elseif (empty($scannedFiles)): ?>
                            <div class="alert alert-info py-2">Tidak ada berkas gambar (.jpg, .png) di dalam folder ini.</div>
                        <?php else: ?>
                            <select name="photos[]" id="drivePhotoSelect" class="form-select" multiple size="4">
                                <?php foreach ($scannedFiles as $sf): ?>
                                    <option value="<?= htmlspecialchars($sf['name']) ?>" data-date="<?= htmlspecialchars($sf['date']) ?>">
                                        📷 <?= htmlspecialchars($sf['name']) ?> (Tgl EXIF: <?= htmlspecialchars($sf['date']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Tahan Ctrl / Cmd untuk memilih hingga 2 berkas foto.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Atau Unggah Manual (Maks. 2 Foto):</label>
                        <input type="file" name="uploaded_photos[]" class="form-control" accept="image/*" multiple>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">3. Hari / Tanggal:</label>
                        <input type="text" name="tanggal" id="tanggalInput" class="form-control" placeholder="Contoh: Senin, 17 Agustus 2026" required>
                        <div class="form-text">Otomatis terisi dari EXIF foto yang dipilih, namun dapat diedit secara bebas.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">4. Uraian Kegiatan:</label>
                        <textarea name="uraian" class="form-control" rows="3" placeholder="Tulis rincian kegiatan..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-success fw-bold">
                        Tambah Kegiatan
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Preview & Export</h5>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="reset">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus semua data dalam sesi ini?')">Reset Data</button>
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
                                                    <img src="<?= $imgData ?>" class="img-thumbnail me-1 mb-1" style="max-width: 70px; max-height: 70px;">
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

    <script>
        const selectElem = document.getElementById('drivePhotoSelect');
        if (selectElem) {
            selectElem.addEventListener('change', function() {
                let selectedOptions = Array.from(this.selectedOptions);
                if (selectedOptions.length > 2) {
                    alert('Maksimal memilih 2 foto!');
                    selectedOptions[selectedOptions.length - 1].selected = false;
                    return;
                }
                if (selectedOptions.length > 0) {
                    let firstDate = selectedOptions[0].getAttribute('data-date');
                    if (firstDate) {
                        document.getElementById('tanggalInput').value = firstDate;
                    }
                }
            });
        }
    </script>
</body>

</html>