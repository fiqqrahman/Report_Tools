<?php
session_start();
require_once __DIR__ . '/db.php';

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

$selectedSubfolder = $_GET['subfolder'] ?? ($_SESSION['last_subfolder'] ?? '');

if (isset($_GET['get_image'])) {
    $relPath = $_GET['get_image'];
    $filePath = '';
    $baseReal = '';

    if (str_starts_with($relPath, 'uploads/')) {
        $filePath = realpath(__DIR__ . '/' . $relPath);
        $baseReal = realpath(__DIR__ . '/uploads');
    } else {
        $baseReal = realpath($driveBaseFolder);
        $filePath = realpath($driveBaseFolder . '/' . $relPath);
    }

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

function getPhotoDateDetails(string $filePath): array
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

    return [
        'real_date' => date('Y-m-d', $timestamp),
        'formatted' => "{$dayName}, {$dayNum} {$monthName} {$year}"
    ];
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
            $dateInfo = getPhotoDateDetails($file);
            $scannedFiles[] = [
                'name'      => $filename,
                'rel_path'  => $selectedSubfolder . '/' . $filename,
                'path'      => $file,
                'date'      => $dateInfo['formatted'],
                'real_date' => $dateInfo['real_date']
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
        $tanggalFormatted = $_POST['tanggal'] ?? '';
        $tanggalReal      = $_POST['tanggal_real'] ?? date('Y-m-d');
        $uraian           = $_POST['uraian'] ?? '';

        $photoPaths = [];

        foreach ($_SESSION['staged_photos'] as $relPath) {
            if (count($photoPaths) >= 2) break;
            $photoPaths[] = $relPath;
        }

        if (isset($_FILES['uploaded_photos']) && count($_FILES['uploaded_photos']['name']) > 0) {
            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            for ($i = 0; $i < count($_FILES['uploaded_photos']['name']); $i++) {
                if (count($photoPaths) >= 2) break;
                if ($_FILES['uploaded_photos']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName  = $_FILES['uploaded_photos']['tmp_name'][$i];
                    $origName = basename($_FILES['uploaded_photos']['name'][$i]);
                    $newName  = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $origName);
                    $targetPath = $uploadDir . '/' . $newName;

                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $photoPaths[] = 'uploads/' . $newName;
                    }
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO tb_kegiatan (tanggal_real, tanggal_formatted, uraian, fotos) VALUES (:tgl_real, :tgl_fmt, :uraian, :fotos)");
        $stmt->execute([
            ':tgl_real' => $tanggalReal,
            ':tgl_fmt'  => $tanggalFormatted,
            ':uraian'   => $uraian,
            ':fotos'    => json_encode($photoPaths)
        ]);

        $_SESSION['staged_photos'] = [];

        header("Location: index.php?subfolder=" . urlencode($selectedSubfolder));
        exit;
    }

    if ($_POST['action'] === 'delete_item') {
        $id = (int)($_POST['item_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM tb_kegiatan WHERE id = :id");
            $stmt->execute([':id' => $id]);
        }
        header("Location: index.php?subfolder=" . urlencode($selectedSubfolder));
        exit;
    }
}

$filterMonth = (int)($_GET['filter_month'] ?? date('n'));
$filterYear  = (int)($_GET['filter_year'] ?? date('Y'));

$prevMonth = $filterMonth - 1;
$prevYear  = $filterYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$startDate = sprintf('%04d-%02d-21', $prevYear, $prevMonth);
$endDate   = sprintf('%04d-%02d-20', $filterYear, $filterMonth);

$stmtLog = $pdo->prepare("SELECT * FROM tb_kegiatan WHERE tanggal_real BETWEEN :start AND :end ORDER BY tanggal_real ASC");
$stmtLog->execute([':start' => $startDate, ':end' => $endDate]);
$logsList = $stmtLog->fetchAll();

$latestEXIFDate = '';
$latestEXIFReal = date('Y-m-d');
if (!empty($_SESSION['staged_photos'])) {
    $lastStagedRel = end($_SESSION['staged_photos']);
    $fullPath = realpath($driveBaseFolder . '/' . $lastStagedRel);
    if ($fullPath && file_exists($fullPath)) {
        $dateInfo = getPhotoDateDetails($fullPath);
        $latestEXIFDate = $dateInfo['formatted'];
        $latestEXIFReal = $dateInfo['real_date'];
    }
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
                <span class="badge bg-light text-primary">Database & Path Identifier Active</span>
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
                                    <option value="<?= htmlspecialchars($sf['rel_path']) ?>" data-date="<?= htmlspecialchars($sf['date']) ?>" data-real="<?= htmlspecialchars($sf['real_date']) ?>">
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
                    <input type="hidden" name="tanggal_real" id="tanggalRealInput" value="<?= htmlspecialchars($latestEXIFReal) ?>">

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
                            <input type="text" id="aiKeywordInput" class="form-control border-purple" placeholder="Ketik kata kunci cepat... (contoh: perbaikan printer bidang SMP)">
                            <button type="button" id="btnGenerateAI" class="btn text-white fw-bold" style="background-color: #6f42c1;">
                                ✨ Generate via AI
                            </button>
                        </div>
                        <textarea name="uraian" id="uraianInput" class="form-control" rows="3" placeholder="Uraian formal akan muncul di sini..." required></textarea>
                        <div class="form-text">Ketik kata kunci di atas lalu klik Generate via AI, atau ketik manual langsung di kotak teks.</div>
                    </div>

                    <button type="submit" class="btn btn-success fw-bold w-100 py-2">
                        ➕ Simpan Kegiatan Ke Database
                    </button>
                </form>

            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Preview & Filter Laporan (Cut-off 21 - 20)</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-center mb-4">
                    <input type="hidden" name="subfolder" value="<?= htmlspecialchars($selectedSubfolder) ?>">
                    <div class="col-auto">
                        <label class="col-form-label fw-bold">Periode Laporan:</label>
                    </div>
                    <div class="col-auto">
                        <select name="filter_month" class="form-select">
                            <?php
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
                            foreach ($months as $mNum => $mName):
                            ?>
                                <option value="<?= $mNum ?>" <?= $filterMonth === $mNum ? 'selected' : '' ?>><?= $mName ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <input type="number" name="filter_year" class="form-control" value="<?= $filterYear ?>" style="width: 100px;">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-secondary fw-bold">Filter Data</button>
                    </div>
                    <div class="col-auto ms-auto">
                        <a href="cetak.php?month=<?= $filterMonth ?>&year=<?= $filterYear ?>&target=pdf" target="_blank" class="btn btn-danger fw-bold">
                            📄 Cetak PDF
                        </a>
                        <a href="cetak.php?month=<?= $filterMonth ?>&year=<?= $filterYear ?>&target=word" class="btn btn-primary fw-bold">
                            📝 Export Word (.doc)
                        </a>
                    </div>
                </form>

                <div class="alert alert-info py-2 small">
                    Rentang Tanggal Aktif: <b><?= date('d-m-Y', strtotime($startDate)) ?></b> s.d. <b><?= date('d-m-Y', strtotime($endDate)) ?></b>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-secondary text-center">
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 25%;">Hari / Tanggal</th>
                                <th style="width: 45%;">Uraian Kegiatan</th>
                                <th style="width: 15%;">Foto Kegiatan</th>
                                <th style="width: 10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logsList)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Belum ada data kegiatan pada periode ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logsList as $i => $log): ?>
                                    <?php $fotos = json_decode($log['fotos'], true) ?: []; ?>
                                    <tr>
                                        <td class="text-center"><?= $i + 1 ?></td>
                                        <td><?= htmlspecialchars($log['tanggal_formatted']) ?></td>
                                        <td><?= nl2br(htmlspecialchars($log['uraian'])) ?></td>
                                        <td class="text-center">
                                            <?php foreach ($fotos as $relPath): ?>
                                                <img src="index.php?get_image=<?= urlencode($relPath) ?>" class="img-thumbnail me-1 mb-1 zoomable-img" style="max-width: 60px; max-height: 60px; cursor: pointer;">
                                            <?php endforeach; ?>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" onsubmit="return confirm('Hapus entri kegiatan ini?')">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="item_id" value="<?= $log['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger py-0" style="font-size: 11px;">Hapus</button>
                                            </form>
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

        if (singlePhotoSelect) {
            singlePhotoSelect.addEventListener('change', function() {
                let selectedOption = this.options[this.selectedIndex];
                let realDate = selectedOption.getAttribute('data-real');
                if (realDate) {
                    document.getElementById('tanggalRealInput').value = realDate;
                }
            });
        }

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