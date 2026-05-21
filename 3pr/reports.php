<?php
session_start();
require_once 'config.php';
require_once 'src/Database.php';

use BikeRepair\Database;

$db = Database::getInstance()->getConnection();

$month = $_GET['month'] ?? date('Y-m');
$export = $_GET['export'] ?? false;

// Отчёт 1: записи по дням
$sql = "SELECT DATE(app_datetime) AS date, 
               COUNT(*) AS total,
               SUM(s.base_price) AS revenue
        FROM Appointment a
        JOIN Service s ON a.service_id = s.service_id
        WHERE DATE_FORMAT(app_datetime, '%Y-%m') = :month
          AND a.status NOT IN ('отменено')
        GROUP BY DATE(app_datetime)
        ORDER BY date";
$stmt = $db->prepare($sql);
$stmt->execute([':month' => $month]);
$dailyStats = $stmt->fetchAll();

// Отчёт 2: рейтинг мастеров
$sql2 = "SELECT m.full_name, 
                COUNT(a.app_id) AS total_appointments,
                SUM(s.base_price) AS total_revenue
         FROM Mechanic m
         LEFT JOIN Appointment a ON m.mechanic_id = a.mechanic_id AND a.status NOT IN ('отменено')
         LEFT JOIN Service s ON a.service_id = s.service_id
         WHERE DATE_FORMAT(a.app_datetime, '%Y-%m') = :month OR a.app_datetime IS NULL
         GROUP BY m.mechanic_id
         ORDER BY total_revenue DESC";
$stmt2 = $db->prepare($sql2);
$stmt2->execute([':month' => $month]);
$mechanicStats = $stmt2->fetchAll();

// Отчёт 3: отмены
$sql3 = "SELECT DATE(app_datetime) AS date, COUNT(*) AS cancelled
         FROM Appointment
         WHERE status = 'отменено'
           AND DATE_FORMAT(app_datetime, '%Y-%m') = :month
         GROUP BY DATE(app_datetime)";
$stmt3 = $db->prepare($sql3);
$stmt3->execute([':month' => $month]);
$cancelledStats = $stmt3->fetchAll();

// Экспорт в CSV
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report_' . $month . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Дата', 'Кол-во записей', 'Выручка']);
    foreach ($dailyStats as $row) {
        fputcsv($output, [$row['date'], $row['total'], $row['revenue']]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Отчёты</title>
    <style>
        body { font-family: Arial; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #007bff; color: white; }
        h2 { margin-top: 30px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📊 Отчёты</h1>
    
    <form method="GET">
        <label>Месяц: <input type="month" name="month" value="<?= $month ?>"></label>
        <button type="submit">Показать</button>
        <button type="submit" name="export" value="csv">Экспорт в CSV</button>
    </form>
    
    <h2>📅 Записи по дням</h2>
    <table>
        <thead><tr><th>Дата</th><th>Количество</th><th>Выручка (₽)</th></tr></thead>
        <tbody>
            <?php foreach ($dailyStats as $row): ?>
            <tr>
                <td><?= date('d.m.Y', strtotime($row['date'])) ?></td>
                <td><?= $row['total'] ?></td>
                <td><?= number_format($row['revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h2>🏆 Рейтинг мастеров</h2>
    <table>
        <thead><tr><th>Мастер</th><th>Количество записей</th><th>Выручка (₽)</th></tr></thead>
        <tbody>
            <?php foreach ($mechanicStats as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= $row['total_appointments'] ?></td>
                <td><?= number_format($row['total_revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h2>❌ Отменённые записи</h2>
    <table>
        <thead><tr><th>Дата</th><th>Количество отмен</th></tr></thead>
        <tbody>
            <?php foreach ($cancelledStats as $row): ?>
            <tr>
                <td><?= date('d.m.Y', strtotime($row['date'])) ?></td>
                <td><?= $row['cancelled'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>