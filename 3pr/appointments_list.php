<?php
session_start();
require_once 'config.php';
require_once 'src/Database.php';
require_once 'src/Exception/RepositoryException.php';
require_once 'src/Repository/AbstractRepository.php';
require_once 'src/Repository/AppointmentRepository.php';
require_once 'src/Repository/MechanicRepository.php';

use BikeRepair\Database;
use BikeRepair\Repository\AppointmentRepository;
use BikeRepair\Repository\MechanicRepository;

$db = Database::getInstance()->getConnection();
$appointmentRepo = new AppointmentRepository($db);
$mechanicRepo = new MechanicRepository($db);

// Фильтры
$filters = [
    'date_from' => $_GET['date_from'] ?? null,
    'date_to' => $_GET['date_to'] ?? null,
    'status' => $_GET['status'] ?? null,
    'mechanic_id' => $_GET['mechanic_id'] ?? null
];

// Пагинация
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$allAppointments = $appointmentRepo->getAppointmentsWithFilters($filters);
$total = count($allAppointments);
$lastPage = ceil($total / $perPage);
$page = max(1, min($page, $lastPage));
$offset = ($page - 1) * $perPage;
$appointments = array_slice($allAppointments, $offset, $perPage);

$mechanics = $mechanicRepo->findAll();

$statusColors = [
    'запланировано' => '#ffc107', // жёлтый
    'подтверждено' => '#28a745',  // зелёный
    'выполняется' => '#17a2b8',   // голубой
    'завершено' => '#6c757d',     // серый
    'отменено' => '#dc3545'       // красный
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление записями</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: auto; background: white; padding: 20px; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; vertical-align: middle; }
        th { background: #007bff; color: white; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; color: white; font-size: 12px; }
        .filters { display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 20px; }
        .filters input, .filters select, .filters button { padding: 8px; }
        .btn { padding: 6px 12px; border-radius: 4px; text-decoration: none; display: inline-block; margin: 2px; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .btn-primary { background: #007bff; color: white; border: none; }
        .btn-danger { background: #dc3545; color: white; border: none; }
        .btn-warning { background: #ffc107; color: #333; border: none; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { margin: 0 3px; padding: 5px 10px; background: #e9ecef; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📋 Управление записями</h1>
    
    <form method="GET" class="filters">
        <input type="date" name="date_from" placeholder="Дата от" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
        <input type="date" name="date_to" placeholder="Дата до" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
        <select name="status">
            <option value="">Все статусы</option>
            <option value="запланировано" <?= $filters['status'] == 'запланировано' ? 'selected' : '' ?>>Запланировано</option>
            <option value="подтверждено" <?= $filters['status'] == 'подтверждено' ? 'selected' : '' ?>>Подтверждено</option>
            <option value="выполняется" <?= $filters['status'] == 'выполняется' ? 'selected' : '' ?>>Выполняется</option>
            <option value="завершено" <?= $filters['status'] == 'завершено' ? 'selected' : '' ?>>Завершено</option>
            <option value="отменено" <?= $filters['status'] == 'отменено' ? 'selected' : '' ?>>Отменено</option>
        </select>
        <select name="mechanic_id">
            <option value="">Все мастера</option>
            <?php foreach ($mechanics as $m): ?>
                <option value="<?= $m['mechanic_id'] ?>" <?= $filters['mechanic_id'] == $m['mechanic_id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Применить</button>
        <a href="appointments_list.php" class="btn">Сбросить</a>
    </form>
    
    <table>
        <thead>
            <tr><th>Дата и время</th><th>Клиент</th><th>Телефон</th><th>Услуга</th><th>Мастер</th><th>Статус</th><th>Действия</th></tr>
        </thead>
        <tbody>
            <?php foreach ($appointments as $a): ?>
            <tr>
                <td><?= date('d.m.Y H:i', strtotime($a['app_datetime'])) ?></td>
                <td><?= htmlspecialchars($a['client_name']) ?></td>
                <td><?= htmlspecialchars($a['client_phone'] ?? '-') ?></td>
                <td><?= htmlspecialchars($a['service_name']) ?> (<strong><?= number_format($a['base_price'], 2) ?> ₽</strong>)</td>
                <td><?= htmlspecialchars($a['mechanic_name']) ?></td>
                <td><span class="status-badge" style="background: <?= $statusColors[$a['status']] ?? '#6c757d' ?>"><?= $a['status'] ?></span></td>
                <td>
                    <a href="appointment_view.php?id=<?= $a['app_id'] ?>" class="btn btn-sm btn-primary">Просмотр</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="pagination">
        <?php for ($i = 1; $i <= $lastPage; $i++): ?>
            <a href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    
    <p><a href="booking.php" class="btn btn-primary">+ Новая запись</a></p>
</div>
</body>
</html>