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

// 1. TANGKAP PARAMETER FILTER & STATE URL
$selectedSubfolder = $_GET['subfolder'] ?? ($_SESSION['last_subfolder'] ?? '');
$startMonthInput   = (int)($_GET['start_month'] ?? 8);
$endMonthInput     = (int)($_GET['end_month'] ?? 9);
$filterYear        = (int)($_GET['filter_year'] ?? date('Y'));
$filterMode        = ($_GET['filter_mode'] ?? 'month') === 'custom' ? 'custom' : 'month';
$startDateInput    = $_GET['start_date'] ?? '';
$endDateInput      = $_GET['end_date'] ?? '';

$startMonthInput = max(1, min(12, $startMonthInput));
$endMonthInput = max(1, min(12, $endMonthInput));
$filterYear = max(2000, min(2100, $filterYear));
$validStartDate = is_string($startDateInput) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $startDateInput) && strtotime($startDateInput) !== false && date('Y-m-d', strtotime($startDateInput)) === $startDateInput;
$validEndDate = is_string($endDateInput) && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $endDateInput) && strtotime($endDateInput) !== false && date('Y-m-d', strtotime($endDateInput)) === $endDateInput;
if ($filterMode !== 'custom' || !$validStartDate || !$validEndDate || $startDateInput > $endDateInput) {
    $filterMode = 'month';
    $startDateInput = '';
    $endDateInput = '';
}

// Helper untuk membangun URL Redirect dinamis agar filter tidak ter-reset
function buildRedirectUrl(array $extraParams = []): string
{
    $params = [];
    if (isset($_GET['subfolder'])) $params['subfolder'] = $_GET['subfolder'];
    if (isset($_GET['start_month'])) $params['start_month'] = $_GET['start_month'];
    if (isset($_GET['end_month'])) $params['end_month'] = $_GET['end_month'];
    if (isset($_GET['filter_year'])) $params['filter_year'] = $_GET['filter_year'];
    if (isset($_GET['start_date'])) $params['start_date'] = $_GET['start_date'];
    if (isset($_GET['end_date'])) $params['end_date'] = $_GET['end_date'];
    if (isset($_GET['filter_mode'])) $params['filter_mode'] = $_GET['filter_mode'];

    $merged = array_merge($params, $extraParams);
    return 'index.php?' . http_build_query($merged);
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

// Endpoint AJAX Fetch Gambar Subfolder
if (isset($_GET['api_action']) && $_GET['api_action'] === 'get_subfolder_photos') {
    header('Content-Type: application/json');
    $requestedSub = $_GET['subfolder'] ?? '';
    $photos = [];
    if (!empty($requestedSub)) {
        $targetDir = realpath($driveBaseFolder . '/' . basename($requestedSub));
        $realBase  = realpath($driveBaseFolder);
        if ($targetDir && $realBase && str_starts_with($targetDir, $realBase) && is_dir($targetDir)) {
            $files = glob($targetDir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
            if ($files !== false) {
                foreach ($files as $file) {
                    $filename = basename($file);
                    $dateInfo = getPhotoDateDetails($file);
                    $photos[] = [
                        'name'      => $filename,
                        'rel_path'  => $requestedSub . '/' . $filename,
                        'date'      => $dateInfo['formatted'],
                        'real_date' => $dateInfo['real_date']
                    ];
                }
            }
        }
    }
    echo json_encode(['status' => 'success', 'files' => $photos]);
    exit;
}

// Endpoint Serve Gambar
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

// Load Subfolder List
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

// 2. FORM ACTION POST HANDLER (PRESERVING FILTER)
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
        header("Location: " . buildRedirectUrl());
        exit;
    }

    if ($_POST['action'] === 'unstage_photo') {
        $index = (int)($_POST['photo_index'] ?? -1);
        if (isset($_SESSION['staged_photos'][$index])) {
            array_splice($_SESSION['staged_photos'], $index, 1);
        }
        header("Location: " . buildRedirectUrl());
        exit;
    }

    if ($_POST['action'] === 'clear_staged') {
        $_SESSION['staged_photos'] = [];
        header("Location: " . buildRedirectUrl());
        exit;
    }

    if ($_POST['action'] === 'add') {
        $tanggalFormatted = $_POST['tanggal'] ?? '';
        $tanggalReal      = $_POST['tanggal_real'] ?? date('Y-m-d');
        $uraian           = $_POST['uraian'] ?? '';
        $photoPaths       = [];

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
        header("Location: " . buildRedirectUrl());
        exit;
    }

    if ($_POST['action'] === 'edit_item') {
        $editId           = (int)($_POST['edit_id'] ?? 0);
        $tanggalFormatted = $_POST['tanggal'] ?? '';
        $tanggalReal      = $_POST['tanggal_real'] ?? date('Y-m-d');
        $uraian           = $_POST['uraian'] ?? '';

        if ($editId > 0) {
            $photoPaths = [];
            $stagedDriveJson = $_POST['edit_drive_photos'] ?? '[]';
            $stagedDrive = json_decode($stagedDriveJson, true) ?: [];

            foreach ($stagedDrive as $relPath) {
                if (count($photoPaths) >= 2) break;
                if (str_starts_with($relPath, 'uploads/')) {
                    $fullPath = realpath(__DIR__ . '/' . $relPath);
                    $baseReal = realpath(__DIR__ . '/uploads');
                } else {
                    $baseReal = realpath($driveBaseFolder);
                    $fullPath = realpath($driveBaseFolder . '/' . $relPath);
                }
                if ($fullPath && $baseReal && str_starts_with($fullPath, $baseReal) && file_exists($fullPath)) {
                    $photoPaths[] = $relPath;
                }
            }

            if (isset($_FILES['edit_uploaded_photos']) && count($_FILES['edit_uploaded_photos']['name']) > 0) {
                $uploadDir = __DIR__ . '/uploads';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                for ($i = 0; $i < count($_FILES['edit_uploaded_photos']['name']); $i++) {
                    if (count($photoPaths) >= 2) break;
                    if ($_FILES['edit_uploaded_photos']['error'][$i] === UPLOAD_ERR_OK) {
                        $tmpName  = $_FILES['edit_uploaded_photos']['tmp_name'][$i];
                        $origName = basename($_FILES['edit_uploaded_photos']['name'][$i]);
                        $newName  = time() . '_edit_' . $i . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $origName);
                        $targetPath = $uploadDir . '/' . $newName;
                        if (move_uploaded_file($tmpName, $targetPath)) {
                            $photoPaths[] = 'uploads/' . $newName;
                        }
                    }
                }
            }

            $stmt = $pdo->prepare("UPDATE tb_kegiatan SET tanggal_real = :tgl_real, tanggal_formatted = :tgl_fmt, uraian = :uraian, fotos = :fotos WHERE id = :id");
            $stmt->execute([
                ':tgl_real' => $tanggalReal,
                ':tgl_fmt'  => $tanggalFormatted,
                ':uraian'   => $uraian,
                ':fotos'    => json_encode($photoPaths),
                ':id'       => $editId
            ]);
        }
        header("Location: " . buildRedirectUrl());
        exit;
    }

    if ($_POST['action'] === 'delete_item') {
        $id = (int)($_POST['item_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM tb_kegiatan WHERE id = :id");
            $stmt->execute([':id' => $id]);
        }
        header("Location: " . buildRedirectUrl());
        exit;
    }
}

// 3. HITUNG DATE RANGE FILTER
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

if ($filterMode === 'custom' && $validStartDate && $validEndDate && $startDateInput <= $endDateInput) {
    $startDate = $startDateInput;
    $endDate   = $endDateInput;
} else {
    $startDate = sprintf('%04d-%02d-20', $filterYear, $startMonthInput);
    $endDate   = sprintf('%04d-%02d-20', $filterYear, $endMonthInput);
}

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
    <title>Sistem Laporan Harian PJLP - Dinas Pendidikan</title>
    <link rel="icon" type="image/png" href="logo.png?v=<?= filemtime(__DIR__ . '/logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .collapse:not(.show) {
            display: none;
        }

        .modal {
            display: none;
            background-color: rgba(15, 23, 42, 0.75);
        }

        .modal.show {
            display: flex !important;
        }
    </style>
</head>

<body class="bg-slate-100/70 text-slate-800 antialiased min-h-screen">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
        <header class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 flex items-center justify-center shrink-0">
                    <img src="logo.png" alt="Logo Dinas Pendidikan" class="max-h-full max-w-full object-contain">
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">PJLP DAILY REPORT TOOLS</h1>
                    <p class="text-xs font-medium text-slate-500">Dinas Pendidikan Kota Palangka Raya</p>
                </div>
            </div>
            <div class="flex items-center gap-3 border-t md:border-t-0 pt-3 md:pt-0 border-slate-100">
                <div class="text-right hidden sm:block">
                    <div class="text-xs font-semibold text-slate-700">Taufiqurahman, S.Kom</div>
                    <div class="text-[11px] text-slate-400">Tenaga Layanan Umum</div>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">
            <div class="lg:col-span-5 flex flex-col gap-5">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 flex flex-col justify-between h-full">
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                </svg>
                                1. Sumber Media/lampiran
                            </h2>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-slate-700 mb-1.5">Pilih Folder Laporan Bulan/Tahun:</label>
                            <form method="GET" id="subfolderForm">
                                <!-- Pass-through parameter filter agar tidak hilang saat berpindah folder -->
                                <?php if (!empty($startDateInput)): ?>
                                    <input type="hidden" name="start_date" value="<?= htmlspecialchars($startDateInput) ?>">
                                <?php endif; ?>
                                <?php if (!empty($endDateInput)): ?>
                                    <input type="hidden" name="end_date" value="<?= htmlspecialchars($endDateInput) ?>">
                                <?php endif; ?>
                                <input type="hidden" name="start_month" value="<?= $startMonthInput ?>">
                                <input type="hidden" name="end_month" value="<?= $endMonthInput ?>">
                                <input type="hidden" name="filter_year" value="<?= $filterYear ?>">

                                <select name="subfolder" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-800 focus:outline-none focus:ring-1 focus:ring-slate-800" onchange="document.getElementById('subfolderForm').submit()">
                                    <option value="">-- Pilih Folder Dokumentasi --</option>
                                    <?php foreach ($availableSubfolders as $folderName): ?>
                                        <option value="<?= htmlspecialchars($folderName) ?>" <?= $selectedSubfolder === $folderName ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($folderName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-medium text-slate-700 mb-1.5">Pilih Berkas Foto Dari Folder Active:</label>
                            <?php if (empty($selectedSubfolder)): ?>
                                <div class="p-3 bg-slate-50 border border-slate-200 text-slate-500 text-xs rounded-lg text-center">
                                    Silakan tentukan folder di atas terlebih dahulu.
                                </div>
                            <?php elseif (empty($scannedFiles)): ?>
                                <div class="p-3 bg-slate-50 border border-slate-200 text-slate-500 text-xs rounded-lg text-center">
                                    Tidak ada file foto terdeteksi dalam folder ini.
                                </div>
                            <?php else: ?>
                                <div class="space-y-2">
                                    <select id="singlePhotoSelect" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-800 focus:outline-none focus:ring-1 focus:ring-slate-800">
                                        <option value="">-- Pilih Berkas Gambar --</option>
                                        <?php foreach ($scannedFiles as $sf): ?>
                                            <option value="<?= htmlspecialchars($sf['rel_path']) ?>" data-date="<?= htmlspecialchars($sf['date']) ?>" data-real="<?= htmlspecialchars($sf['real_date']) ?>">
                                                <?= htmlspecialchars($sf['name']) ?> (EXIF: <?= htmlspecialchars($sf['date']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" id="btnAddStage" class="w-full py-2 bg-slate-800 hover:bg-slate-900 text-white font-medium text-xs rounded-lg shadow-sm transition">
                                        + Masukkan Foto Ke Daftar Terpilih
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="border border-slate-200 rounded-lg p-3 bg-slate-50/50 mt-2">
                        <div class="flex justify-between items-center mb-2.5">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Daftar Foto Terpilih (Maks. 2)</span>
                            <?php if (!empty($_SESSION['staged_photos'])): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="clear_staged">
                                    <button type="submit" class="text-[11px] text-rose-600 hover:text-rose-800 font-medium">Reset Pilih</button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div id="stagedContainer" class="grid grid-cols-2 gap-2 min-h-[90px] items-center">
                            <?php if (empty($_SESSION['staged_photos'])): ?>
                                <span class="col-span-2 text-slate-400 text-xs text-center py-4 italic">Belum ada foto terpilih.</span>
                            <?php else: ?>
                                <?php foreach ($_SESSION['staged_photos'] as $idx => $relPath): ?>
                                    <?php
                                    $parts = explode('/', $relPath);
                                    $fileLabel = $parts[1] ?? $relPath;
                                    ?>
                                    <div class="bg-white border border-slate-200 rounded-lg p-2 shadow-sm text-center">
                                        <img src="index.php?get_image=<?= urlencode($relPath) ?>" class="w-full h-20 object-cover rounded mb-1.5 border border-slate-100 zoomable-img cursor-pointer">
                                        <div class="text-[10px] font-medium text-slate-700 truncate mb-1" title="<?= htmlspecialchars($fileLabel) ?>"><?= htmlspecialchars($fileLabel) ?></div>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="unstage_photo">
                                            <input type="hidden" name="photo_index" value="<?= $idx ?>">
                                            <button type="submit" class="w-full py-0.5 text-[10px] font-medium text-rose-600 hover:bg-rose-50 rounded border border-rose-200 transition">
                                                Batal Pilih
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 h-full flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-2">
                                <svg class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                2. Detail & Uraian Kegiatan Laporan
                            </h2>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="action" value="add">
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Opsional: Unggah Foto Manual</label>
                                <input type="file" name="uploaded_photos[]" class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 border border-slate-200 rounded-lg cursor-pointer bg-white" accept="image/*" multiple>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 mb-1">Tanggal System/Database (Real):</label>
                                    <input type="date" name="tanggal_real" id="tanggalRealInput" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-800 focus:outline-none focus:ring-1 focus:ring-slate-800" value="<?= htmlspecialchars($latestEXIFReal) ?>" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-700 mb-1">Hari & Tanggal Laporan (Teks):</label>
                                    <input type="text" name="tanggal" id="tanggalInput" class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-sm focus:border-slate-800 focus:outline-none focus:ring-1 focus:ring-slate-800" value="<?= htmlspecialchars($latestEXIFDate) ?>" placeholder="Contoh: Selasa, 28 Juli 2026" required>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400 -mt-2">*Pastikan Tanggal System (Real) terisi sesuai tanggal target agar lolos filter query SQL.</p>
                            <div>
                                <label class="block text-xs font-medium text-slate-700 mb-1">Uraian Pekerjaan / Kegiatan Harian:</label>
                                <div class="flex gap-2 mb-2">
                                    <input type="text" id="aiKeywordInput" class="flex-1 rounded-lg border border-indigo-200 bg-indigo-50/30 px-3 py-1.5 text-xs text-slate-800 focus:border-indigo-600 focus:bg-white focus:outline-none" placeholder="Ketik rincian singkat... (contoh: Pembuatan Surat, Pendataan, Rapat Koordinasi, dll.)">
                                    <button type="button" id="btnGenerateAI" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-lg shadow-sm transition flex items-center gap-1.5 whitespace-nowrap">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                        AI Generate
                                    </button>
                                </div>
                                <textarea name="uraian" id="uraianInput" class="w-full rounded-lg border border-slate-300 bg-white p-3 text-xs text-slate-800 leading-relaxed shadow-sm focus:border-slate-800 focus:outline-none focus:ring-1 focus:ring-slate-800" rows="4" placeholder="Uraian formal pekerjaan akan tampil di sini..." required></textarea>
                            </div>
                            <button type="submit" class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs rounded-lg shadow-sm transition duration-150 tracking-wide uppercase">
                                Submit
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 bg-slate-900 text-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h2 class="font-bold text-sm uppercase tracking-wider">Rekapitulasi Laporan</h2>
                </div>
                <div class="text-xs text-slate-400">
                    Periode: <span class="text-white font-medium"><?= date('d/m/Y', strtotime($startDate)) ?></span> s.d. <span class="text-white font-medium"><?= date('d/m/Y', strtotime($endDate)) ?></span>
                </div>
            </div>
            <div class="p-6">
                <form method="GET" id="periodFilterForm" class="mb-6 space-y-3">
                    <input type="hidden" name="subfolder" value="<?= htmlspecialchars($selectedSubfolder) ?>">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2.5 items-end">
                        <div class="lg:col-span-3">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Bulan Awal Cut-Off:</label>
                            <select name="start_month" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 shadow-sm focus:border-slate-800 focus:outline-none">
                                <?php foreach ($months as $mNum => $mName): ?>
                                    <option value="<?= $mNum ?>" <?= $startMonthInput === $mNum ? 'selected' : '' ?>><?= $mName ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lg:col-span-3">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Bulan Akhir Cut-Off:</label>
                            <select name="end_month" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 shadow-sm focus:border-slate-800 focus:outline-none">
                                <?php foreach ($months as $mNum => $mName): ?>
                                    <option value="<?= $mNum ?>" <?= $endMonthInput === $mNum ? 'selected' : '' ?>><?= $mName ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lg:col-span-2">
                            <label class="block text-[11px] font-semibold text-slate-600 mb-1">Tahun:</label>
                            <input type="number" name="filter_year" class="w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 shadow-sm focus:border-slate-800 focus:outline-none" value="<?= $filterYear ?>">
                        </div>
                        <div class="lg:col-span-2">
                            <button type="submit" data-period-mode="month" class="w-full py-1.5 bg-slate-800 hover:bg-slate-900 text-white font-medium text-xs rounded-lg shadow-sm transition">
                                Filter Periode
                            </button>
                        </div>
                        <div class="lg:col-span-2">
                            <button type="button" id="btnToggleCustomDate" class="w-full py-1.5 border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium text-xs rounded-lg transition">
                                Custom Tanggal
                            </button>
                        </div>
                    </div>
                    <input type="hidden" name="filter_mode" id="filterModeInput" value="<?= htmlspecialchars($filterMode) ?>">
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                        <span id="periodLockStatus" class="text-xs text-slate-600">Periode filter tidak dikunci</span>
                        <button type="button" id="btnTogglePeriodLock" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Kunci Periode</button>
                    </div>
                    <div id="customDateCollapse" class="<?= $filterMode === 'custom' ? '' : 'hidden' ?>">
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg grid grid-cols-1 sm:grid-cols-5 gap-3 items-end mt-3">
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Dari Tanggal Presisi:</label>
                                <input type="date" name="start_date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs text-slate-800 focus:border-slate-800 focus:outline-none" value="<?= htmlspecialchars($startDate) ?>">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-slate-600 mb-1">Sampai Tanggal Presisi:</label>
                                <input type="date" name="end_date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1 text-xs text-slate-800 focus:border-slate-800 focus:outline-none" value="<?= htmlspecialchars($endDate) ?>">
                            </div>
                            <div>
                                <button type="submit" data-period-mode="custom" class="w-full py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-lg transition">
                                    Terapkan
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="flex justify-between items-center pb-3 border-b border-slate-100 mb-4">
                    <span class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Pratinjau Cetak Laporan Fisik</span>
                    <a href="cetak.php?filter_mode=<?= urlencode($filterMode) ?><?= $filterMode === 'custom' ? '&start_date=' . urlencode($startDate) . '&end_date=' . urlencode($endDate) : '' ?>&start_month=<?= $startMonthInput ?>&end_month=<?= $endMonthInput ?>&year=<?= $filterYear ?>&target=pdf" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs rounded-lg shadow-sm transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                        </svg>
                        Cetak Laporan PDF
                    </a>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-lg">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50 text-slate-700 uppercase font-bold border-b border-slate-200">
                                <th class="p-3 text-center w-12 border-r border-slate-200">No</th>
                                <th class="p-3 w-44 border-r border-slate-200">Hari / Tanggal</th>
                                <th class="p-3 border-r border-slate-200">Uraian Pekerjaan / Kegiatan</th>
                                <th class="p-3 text-center w-36 border-r border-slate-200">Dokumentasi Foto</th>
                                <th class="p-3 text-center w-28">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (empty($logsList)): ?>
                                <tr>
                                    <td colspan="5" class="p-5 text-center text-slate-400 italic">Belum ada entri data kegiatan pada periode rentang tanggal ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logsList as $i => $log): ?>
                                    <?php $fotos = json_decode($log['fotos'], true) ?: []; ?>
                                    <tr class="hover:bg-slate-50/80 transition">
                                        <td class="p-3 text-center font-mono text-slate-500 border-r border-slate-200"><?= $i + 1 ?></td>
                                        <td class="p-3 font-semibold text-slate-800 border-r border-slate-200"><?= htmlspecialchars($log['tanggal_formatted']) ?></td>
                                        <td class="p-3 leading-relaxed text-slate-700 border-r border-slate-200"><?= nl2br(htmlspecialchars($log['uraian'])) ?></td>
                                        <td class="p-3 text-center border-r border-slate-200">
                                            <?php
                                            $jumlahFoto = count($fotos);
                                            if ($jumlahFoto === 1): ?>
                                                <span class="inline-flex items-center justify-center text-amber-500 bg-amber-50 border border-amber-200 px-2.5 py-1 rounded-full font-bold text-xs gap-1" title="1 Foto Terlampir">
                                                    <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </span>
                                            <?php elseif ($jumlahFoto >= 2): ?>
                                                <span class="inline-flex items-center justify-center text-emerald-600 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-full font-bold text-xs gap-0.5" title="2 Foto Terlampir">
                                                    <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    <svg class="w-4 h-4 stroke-[3] -ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-slate-300 font-medium text-xs">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-3 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button type="button" class="btn-open-edit px-2 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 font-medium rounded text-[11px] border border-amber-200 transition"
                                                    data-id="<?= $log['id'] ?>"
                                                    data-tgl-fmt="<?= htmlspecialchars($log['tanggal_formatted']) ?>"
                                                    data-tgl-real="<?= htmlspecialchars($log['tanggal_real']) ?>"
                                                    data-uraian="<?= htmlspecialchars($log['uraian']) ?>"
                                                    data-fotos='<?= htmlspecialchars(json_encode($fotos), ENT_QUOTES) ?>'>
                                                    Edit
                                                </button>
                                                <form method="POST" onsubmit="return confirm('Hapus entri kegiatan ini?')">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="item_id" value="<?= $log['id'] ?>">
                                                    <button type="submit" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 font-medium rounded text-[11px] border border-rose-200 transition">
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
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

    <div class="modal fade fixed inset-0 z-50 justify-center items-center" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog max-w-3xl w-full mx-4">
            <div class="bg-slate-900 rounded-xl overflow-hidden shadow-2xl border border-slate-700">
                <div class="px-4 py-3 bg-slate-800 text-white flex justify-between items-center border-b border-slate-700">
                    <h5 class="text-xs font-semibold uppercase tracking-wider text-slate-300">Inspeksi Pratinjau Foto Dokumentasi</h5>
                    <button type="button" class="text-slate-400 hover:text-white text-lg font-bold" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <div class="p-4 text-center bg-slate-950">
                    <img id="modalImageTarget" src="" class="max-h-[75vh] mx-auto rounded shadow-lg object-contain">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade fixed inset-0 z-50 justify-center items-center" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog max-w-2xl w-full mx-4">
            <div class="bg-white rounded-xl overflow-hidden shadow-2xl border border-slate-200">
                <div class="px-5 py-4 bg-slate-900 text-white flex justify-between items-center">
                    <h5 class="text-xs font-bold uppercase tracking-wider text-slate-200 flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                        Edit Entri Kegiatan Laporan
                    </h5>
                    <button type="button" class="text-slate-400 hover:text-white text-lg font-bold" data-bs-dismiss="modal" aria-label="Close">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" class="p-5 space-y-4">
                    <input type="hidden" name="action" value="edit_item">
                    <input type="hidden" name="edit_id" id="editModalId">
                    <input type="hidden" name="edit_drive_photos" id="editDrivePhotosInput" value="[]">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Tanggal System (Real):</label>
                            <input type="date" name="tanggal_real" id="editModalTanggalReal" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-slate-800 focus:outline-none" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Hari & Tanggal Laporan:</label>
                            <input type="text" name="tanggal" id="editModalTanggalFmt" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-slate-800 focus:outline-none" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Uraian Kegiatan Pekerjaan:</label>
                        <textarea name="uraian" id="editModalUraian" class="w-full rounded-lg border border-slate-300 bg-white p-3 text-xs text-slate-800 leading-relaxed focus:border-slate-800 focus:outline-none" rows="4" required></textarea>
                    </div>
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">Pilih Folder Laporan Bulan/Tahun (Drive):</label>
                            <select id="editSubfolderSelect" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-slate-800 focus:outline-none">
                                <option value="">-- Pilih Folder Dokumentasi --</option>
                                <?php foreach ($availableSubfolders as $folderName): ?>
                                    <option value="<?= htmlspecialchars($folderName) ?>" <?= $selectedSubfolder === $folderName ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($folderName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1.5">Pilih Berkas Foto Dari Folder Active:</label>
                            <div class="space-y-2">
                                <select id="editSinglePhotoSelect" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 focus:border-slate-800 focus:outline-none">
                                    <option value="">-- Pilih Berkas Gambar --</option>
                                    <?php foreach ($scannedFiles as $sf): ?>
                                        <option value="<?= htmlspecialchars($sf['rel_path']) ?>" data-date="<?= htmlspecialchars($sf['date']) ?>" data-real="<?= htmlspecialchars($sf['real_date']) ?>">
                                            <?= htmlspecialchars($sf['name']) ?> (EXIF: <?= htmlspecialchars($sf['date']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" id="btnEditAddStage" class="w-full py-1.5 bg-slate-800 hover:bg-slate-900 text-white font-medium text-xs rounded-lg shadow-sm transition">
                                    + Masukkan Foto Ke Daftar Edit
                                </button>
                            </div>
                        </div>
                        <div class="border-t border-slate-200 pt-3">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Daftar Foto Terpilih (Maks. 2)</span>
                                <button type="button" id="btnEditResetStaged" class="text-[11px] text-rose-600 hover:text-rose-800 font-medium">Reset Pilih</button>
                            </div>
                            <div id="editStagedContainer" class="grid grid-cols-2 gap-2 min-h-[90px] items-center">
                                <span class="col-span-2 text-slate-400 text-xs text-center py-4 italic">Belum ada foto terpilih.</span>
                            </div>
                        </div>
                        <div class="border-t border-slate-200 pt-3">
                            <label class="block text-xs font-medium text-slate-700 mb-1">Atau Unggah Manual Dari Perangkat (Maks. 2):</label>
                            <input type="file" name="edit_uploaded_photos[]" class="w-full text-xs text-slate-500 file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-200 file:text-slate-700 hover:file:bg-slate-300 border border-slate-200 rounded-lg cursor-pointer bg-white" accept="image/*" multiple>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                        <button type="button" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold text-xs rounded-lg transition" data-bs-dismiss="modal">
                            Batal
                        </button>
                        <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs rounded-lg transition uppercase">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const singlePhotoSelect = document.getElementById('singlePhotoSelect');
        const btnAddStage = document.getElementById('btnAddStage');
        const stagePhotoForm = document.getElementById('stagePhotoForm');
        const stagePhotoRelPath = document.getElementById('stagePhotoRelPath');
        const currentStagedCount = <?= count($_SESSION['staged_photos']) ?>;
        const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
        const modalImageTarget = document.getElementById('modalImageTarget');
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        let editStagedPhotos = [];

        const periodForm = document.querySelector('form#periodFilterForm');
        const filterModeInput = document.getElementById('filterModeInput');
        const lockButton = document.getElementById('btnTogglePeriodLock');
        const lockStatus = document.getElementById('periodLockStatus');
        const periodStorageKey = 'pjlpReportPeriodLock.v1';
        const periodFormElement = periodForm;
        let periodLocked = false;

        if (periodFormElement) {
            periodFormElement.id = 'periodFilterForm';
            const storedPeriod = (() => {
                try { return JSON.parse(localStorage.getItem(periodStorageKey) || 'null'); }
                catch (_) { return null; }
            })();

            const setPeriodFieldsDisabled = (disabled) => {
                periodFormElement.querySelectorAll('select[name="start_month"], select[name="end_month"], input[name="filter_year"], input[name="start_date"], input[name="end_date"]').forEach(el => {
                    el.disabled = disabled;
                });
            };
            const setLockUi = (locked) => {
                periodLocked = locked;
                setPeriodFieldsDisabled(locked);
                lockStatus.textContent = locked ? 'Periode laporan terkunci' : 'Periode filter tidak dikunci';
                lockButton.textContent = locked ? 'Buka Kunci Periode' : 'Kunci Periode';
                lockButton.classList.toggle('bg-slate-800', locked);
                lockButton.classList.toggle('text-white', locked);
            };
            const getPeriod = () => ({
                filter_mode: filterModeInput.value === 'custom' ? 'custom' : 'month',
                start_month: periodFormElement.elements.start_month.value,
                end_month: periodFormElement.elements.end_month.value,
                filter_year: periodFormElement.elements.filter_year.value,
                start_date: periodFormElement.elements.start_date?.value || '',
                end_date: periodFormElement.elements.end_date?.value || ''
            });
            const applyPeriod = (p) => {
                if (!p) return;
                ['start_month', 'end_month', 'filter_year', 'start_date', 'end_date'].forEach(name => {
                    if (periodFormElement.elements[name] && p[name] !== undefined) periodFormElement.elements[name].value = p[name];
                });
                filterModeInput.value = p.filter_mode === 'custom' ? 'custom' : 'month';
                document.getElementById('customDateCollapse')?.classList.toggle('hidden', filterModeInput.value !== 'custom');
            };

            if (storedPeriod?.locked && storedPeriod.period) {
                applyPeriod(storedPeriod.period);
                const url = new URL(window.location.href);
                Object.entries(storedPeriod.period).forEach(([key, value]) => {
                    if (value !== '') url.searchParams.set(key, value);
                    else url.searchParams.delete(key);
                });
                if (url.href !== window.location.href) window.location.replace(url.href);
                setLockUi(true);
            }
            lockButton?.addEventListener('click', () => {
                if (periodLocked) {
                    localStorage.setItem(periodStorageKey, JSON.stringify({locked:false, period:getPeriod()}));
                    setLockUi(false);
                } else {
                    localStorage.setItem(periodStorageKey, JSON.stringify({locked:true, period:getPeriod()}));
                    setLockUi(true);
                }
            });
            periodFormElement.querySelectorAll('button[data-period-mode]').forEach(button => {
                button.addEventListener('click', () => {
                    filterModeInput.value = button.dataset.periodMode;
                    localStorage.setItem(periodStorageKey, JSON.stringify({locked:periodLocked, period:getPeriod()}));
                });
            });
            periodFormElement.addEventListener('submit', (event) => {
                if (periodLocked) {
                    event.preventDefault();
                    return;
                }
                localStorage.setItem(periodStorageKey, JSON.stringify({locked:false, period:getPeriod()}));
            });
        }

        document.getElementById('btnToggleCustomDate')?.addEventListener('click', function() {
            const customDateDiv = document.getElementById('customDateCollapse');
            if (customDateDiv) {
                customDateDiv.classList.toggle('hidden');
            }
        });

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

        function renderEditStagedPhotos() {
            const container = document.getElementById('editStagedContainer');
            const hiddenInput = document.getElementById('editDrivePhotosInput');
            container.innerHTML = '';
            hiddenInput.value = JSON.stringify(editStagedPhotos);
            if (editStagedPhotos.length === 0) {
                container.innerHTML = '<span class="col-span-2 text-slate-400 text-xs text-center py-4 italic">Belum ada foto terpilih.</span>';
                return;
            }
            editStagedPhotos.forEach((path, idx) => {
                const parts = path.split('/');
                const fileLabel = parts[1] || path;
                const wrapper = document.createElement('div');
                wrapper.className = 'bg-white border border-slate-200 rounded-lg p-2 shadow-sm text-center';
                const img = document.createElement('img');
                img.src = 'index.php?get_image=' + encodeURIComponent(path);
                img.className = 'w-full h-20 object-cover rounded mb-1.5 border border-slate-100 cursor-pointer';
                img.addEventListener('click', function() {
                    modalImageTarget.src = this.src;
                    imageModal.show();
                });
                const label = document.createElement('div');
                label.className = 'text-[10px] font-medium text-slate-700 truncate mb-1';
                label.title = fileLabel;
                label.textContent = fileLabel;
                const btnRemove = document.createElement('button');
                btnRemove.type = 'button';
                btnRemove.className = 'w-full py-0.5 text-[10px] font-medium text-rose-600 hover:bg-rose-50 rounded border border-rose-200 transition';
                btnRemove.textContent = 'Batal Pilih';
                btnRemove.addEventListener('click', function() {
                    editStagedPhotos.splice(idx, 1);
                    renderEditStagedPhotos();
                });
                wrapper.appendChild(img);
                wrapper.appendChild(label);
                wrapper.appendChild(btnRemove);
                container.appendChild(wrapper);
            });
        }

        document.querySelectorAll('.btn-open-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const tglFmt = this.getAttribute('data-tgl-fmt');
                const tglReal = this.getAttribute('data-tgl-real');
                const uraian = this.getAttribute('data-uraian');
                const fotos = JSON.parse(this.getAttribute('data-fotos') || '[]');
                document.getElementById('editModalId').value = id;
                document.getElementById('editModalTanggalFmt').value = tglFmt;
                document.getElementById('editModalTanggalReal').value = tglReal;
                document.getElementById('editModalUraian').value = uraian;
                editStagedPhotos = [...fotos];
                renderEditStagedPhotos();
                editModal.show();
            });
        });

        document.getElementById('editSubfolderSelect')?.addEventListener('change', function() {
            const selectedSub = this.value;
            const photoSelect = document.getElementById('editSinglePhotoSelect');
            photoSelect.innerHTML = '<option value="">-- Loading Berkas Gambar... --</option>';
            if (!selectedSub) {
                photoSelect.innerHTML = '<option value="">-- Pilih Folder Terlebih Dahulu --</option>';
                return;
            }
            fetch('index.php?api_action=get_subfolder_photos&subfolder=' + encodeURIComponent(selectedSub))
                .then(response => response.json())
                .then(data => {
                    photoSelect.innerHTML = '<option value="">-- Pilih Berkas Gambar --</option>';
                    if (data.status === 'success' && data.files.length > 0) {
                        data.files.forEach(sf => {
                            const opt = document.createElement('option');
                            opt.value = sf.rel_path;
                            opt.textContent = `${sf.name} (EXIF: ${sf.date})`;
                            opt.setAttribute('data-real', sf.real_date);
                            opt.setAttribute('data-date', sf.date);
                            photoSelect.appendChild(opt);
                        });
                    } else {
                        photoSelect.innerHTML = '<option value="">-- Tidak Ada File Foto --</option>';
                    }
                })
                .catch(() => {
                    photoSelect.innerHTML = '<option value="">-- Gagal Memuat Foto --</option>';
                });
        });

        document.getElementById('btnEditAddStage')?.addEventListener('click', function() {
            const select = document.getElementById('editSinglePhotoSelect');
            const val = select.value;
            if (!val) {
                alert('Silakan pilih foto terlebih dahulu.');
                return;
            }
            if (editStagedPhotos.length >= 2) {
                alert('Maksimal 2 foto dalam daftar terpilih!');
                return;
            }
            if (!editStagedPhotos.includes(val)) {
                editStagedPhotos.push(val);
                renderEditStagedPhotos();
            }
        });

        document.getElementById('btnEditResetStaged')?.addEventListener('click', function() {
            editStagedPhotos = [];
            renderEditStagedPhotos();
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
            btn.innerHTML = ' Menyusun Uraian...';
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
                    btn.innerHTML = ' AI Format';
                });
        });
    </script>
</body>

</html>