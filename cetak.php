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
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        @page {
            size: A4 portrait;
            margin: 1.0cm 1.2cm 1.2cm 1.2cm;
        }

        @page :first {
            margin-top: 0.4cm;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 10pt;
            color: #000;
            background-color: #fff;
            padding: 0;
            margin: 0;
        }

        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0px;
        }

        .kop-table td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        .line-double {
            border-bottom: 3px solid #000;
            margin-bottom: 8px;
            margin-top: 2px;
        }

        .title {
            text-align: center;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 8px;
            line-height: 1.2;
        }

        .biodata-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        .biodata-table td {
            border: none;
            padding: 1px 0;
            vertical-align: top;
            font-size: 10pt;
        }

        .table-wrapper {
            width: 100%;
            padding-right: 5px;
            padding-left: 2px;
        }

        .main-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .main-table thead {
            display: table-row-group;
        }

        .main-table th,
        .main-table td {
            border: 1px solid #000;
            padding: 4px 5px;
            vertical-align: middle;
            font-size: 9.5pt;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .main-table th:last-child,
        .main-table td:last-child {
            border-right: 1.5px solid #000 !important;
        }

        .main-table th {
            background-color: #ffffff;
            text-align: center;
            font-weight: bold;
        }

        .main-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .ttd-table td {
            border: none;
            text-align: center;
            vertical-align: top;
            font-size: 10pt;
        }

        .img-cell {
            text-align: center;
            vertical-align: middle;
            padding: 4px !important;
        }

        .img-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            width: 100%;
        }

        .img-kegiatan {
            width: calc(50% - 3px) !important;
            max-height: 5.8cm !important;
            height: auto !important;
            object-fit: contain;
            display: block;
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

    <div class="table-wrapper">
        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 5%;">No</th>
                    <th style="width: 22%;">Hari / Tanggal</th>
                    <th style="width: 23%;">Uraian Kegiatan</th>
                    <th style="width: 50%;">Foto Kegiatan</th>
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
                                <div class="img-container">
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
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <table class="ttd-table">
        <tr>
            <td style="width: 50%;">
                Mengetahui,<br>
                Kepala Sub Bagian Umum dan Kepegawaian<br>Dinas Pendidikan Kota Palangka Raya<br><br><br><br><br>
                <b>Lenataurisia P., S.T., M.A.P</b><br>
                NIP 198204282010012013
            </td>
            <td style="width: 50%;">
                <br>
                Yang Melapor,<br><br><br><br><br><br>
                <b>Taufiqurahman, S.Kom</b>
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