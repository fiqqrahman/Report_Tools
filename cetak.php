<?php
require_once __DIR__ . '/db.php';

$driveBaseFolder = 'G:/My Drive/PJLP/2026/DOKUMENTASI';
if (!is_dir($driveBaseFolder)) {
    $driveBaseFolder = __DIR__ . '/drive_photos';
}

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

$startMonthInput = (int)($_GET['start_month'] ?? 8);
$endMonthInput   = (int)($_GET['end_month'] ?? 9);
$filterYear      = (int)($_GET['year'] ?? date('Y'));

$startDateInput  = $_GET['start_date'] ?? '';
$endDateInput    = $_GET['end_date'] ?? '';

if (!empty($startDateInput) && !empty($endDateInput)) {
    $startDate = $startDateInput;
    $endDate   = $endDateInput;
} else {
    $startDate = sprintf('%04d-%02d-20', $filterYear, $startMonthInput);
    $endDate   = sprintf('%04d-%02d-20', $filterYear, $endMonthInput);
}

$startTs = strtotime($startDate);
$endTs   = strtotime($endDate);

$startMonthName = $months[(int)date('n', $startTs)];
$endMonthName   = $months[(int)date('n', $endTs)];

if ($startMonthName === $endMonthName) {
    $periodeLabel = date('j', $startTs) . ' s.d. ' . date('j', $endTs) . ' ' . $endMonthName . ' ' . date('Y', $endTs);
} else {
    $periodeLabel = date('j', $startTs) . ' ' . $startMonthName . ' s.d. ' . date('j', $endTs) . ' ' . $endMonthName . ' ' . date('Y', $endTs);
}

$stmtLog = $pdo->prepare("SELECT * FROM tb_kegiatan WHERE tanggal_real BETWEEN :start AND :end ORDER BY tanggal_real ASC");
$stmtLog->execute([':start' => $startDate, ':end' => $endDate]);
$logsList = $stmtLog->fetchAll();

function getPhysicalPath(string $relPath, string $driveBaseFolder): string
{
    if (str_starts_with($relPath, 'uploads/')) {
        $fullPath = realpath(__DIR__ . '/' . $relPath);
        $baseReal = realpath(__DIR__ . '/uploads');
    } else {
        $baseReal = realpath($driveBaseFolder);
        $fullPath = realpath($driveBaseFolder . '/' . $relPath);
    }

    if ($fullPath && $baseReal && str_starts_with($fullPath, $baseReal) && file_exists($fullPath)) {
        return $fullPath;
    }
    return '';
}

$logoPath = __DIR__ . '/logo.png';
$logoBase64 = '';
if (file_exists($logoPath)) {
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/png;base64,' . base64_encode($logoData);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title></title>
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11pt;
            color: #000;
            background-color: #fff;
            padding: 0.3cm 1.5cm 1.5cm 1.5cm;
            box-sizing: border-box;
        }

        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }

        .kop-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .line-double {
            border-bottom: 4px solid #000;
            margin-bottom: 12px;
            margin-top: 4px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .biodata-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .biodata-table td {
            border: none;
            padding: 2px 0;
            vertical-align: top;
            font-size: 11pt;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
        }

        .main-table th,
        .main-table td {
            border: 1px solid #000;
            padding: 6px;
            vertical-align: middle;
        }

        .main-table th {
            background-color: #ffffff;
            text-align: center;
            font-weight: bold;
        }

        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        .ttd-table td {
            border: none;
            text-align: center;
            vertical-align: top;
        }

        .img-cell {
            text-align: center;
            vertical-align: middle;
            padding: 6px !important;
        }

        .img-kegiatan {
            height: 3.7cm !important;
            width: auto !important;
            max-width: 100%;
            display: block;
            margin: 4px auto;
        }

        @media print {
            body {
                background: #fff;
            }
        }
    </style>
</head>

<body>
    <table class="kop-table">
        <tr>
            <td style="width: 18%; text-align: center;">
                <?php if (!empty($logoBase64)): ?>
                    <img src="<?= $logoBase64 ?>" style="width: 85px; height: auto;">
                <?php endif; ?>
            </td>
            <td style="width: 82%; text-align: center;">
                <span style="font-size: 13pt; font-weight: bold; letter-spacing: 0.5px;">PEMERINTAH KOTA PALANGKA RAYA</span><br>
                <span style="font-size: 15pt; font-weight: bold; letter-spacing: 0.5px;">DINAS PENDIDIKAN</span><br>
                <span style="font-size: 8pt; line-height: 1.2; display: block; margin-top: 2px;">Jalan .G.Obos XI Komplek Perkantoran Lingkar Dalam, Jekan Raya Palangka Raya, Kalimantan Tengah 73112<br>
                    Telepon 0536 – 3222372, Faksimile (0536) 3221654<br>
                    Laman <a href="http://disdik.palangkaraya.go.id" style="color:#000; text-decoration: underline;">disdik.palangkaraya.go.id</a>, Pos-el <a href="mailto:disdik@palangkaraya.go.id" style="color:#000; text-decoration: underline;">disdik@palangkaraya.go.id</a></span>
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
            <td style="width: 18%;">Nama</td>
            <td style="width: 2%;">:</td>
            <td style="width: 80%;">Taufiqurahman</td>
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
            <td><?= htmlspecialchars($periodeLabel) ?></td>
        </tr>
    </table>

    <table class="main-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 25%;">Hari / Tanggal</th>
                <th style="width: 45%;">Uraian Kegiatan</th>
                <th style="width: 25%;">Foto Kegiatan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logsList)): ?>
                <tr>
                    <td colspan="4" style="text-align:center;">Belum ada data kegiatan pada periode ini.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logsList as $i => $log): ?>
                    <?php $fotos = json_decode($log['fotos'], true) ?: []; ?>
                    <tr>
                        <td style="text-align: center;"><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($log['tanggal_formatted']) ?></td>
                        <td><?= nl2br(htmlspecialchars($log['uraian'])) ?></td>
                        <td class="img-cell">
                            <?php foreach ($fotos as $relPath): ?>
                                <?php
                                $physPath = getPhysicalPath($relPath, $driveBaseFolder);
                                if (!empty($physPath)):
                                    $mimeType = mime_content_type($physPath);
                                    $imgBase64 = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($physPath));
                                ?>
                                    <img src="<?= $imgBase64 ?>" class="img-kegiatan">
                                <?php endif; ?>
                            <?php endforeach; ?>
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

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>

</html>