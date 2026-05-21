<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once 'config.php';
require_once 'src/Database.php';
require_once 'src/Exception/RepositoryException.php';
require_once 'src/Repository/AbstractRepository.php';
require_once 'src/Repository/AppointmentRepository.php';
require_once 'src/Repository/ClientRepository.php';
require_once 'src/Repository/BikeRepository.php';
require_once 'src/Repository/ServiceRepository.php';
require_once 'src/Repository/MechanicRepository.php';

use BikeRepair\Database;
use BikeRepair\Repository\AppointmentRepository;
use BikeRepair\Repository\ClientRepository;
use BikeRepair\Repository\BikeRepository;
use BikeRepair\Repository\ServiceRepository;
use BikeRepair\Repository\MechanicRepository;
use BikeRepair\Exception\RepositoryException;

$db = Database::getInstance()->getConnection();
$appointmentRepo = new AppointmentRepository($db);
$clientRepo = new ClientRepository($db);
$bikeRepo = new BikeRepository($db);
$serviceRepo = new ServiceRepository($db);
$mechanicRepo = new MechanicRepository($db);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$appointment = $appointmentRepo->findById($id);

if (!$appointment) {
    $_SESSION['flash'] = ['type' => 'danger', 'message' => 'Запись не найдена'];
    header('Location: appointments_list.php');
    exit;
}

// Получаем связанные данные
$client = $clientRepo->findById($appointment['client_id']);
$bike = $bikeRepo->findById($appointment['bike_id']);
$service = $serviceRepo->findById($appointment['service_id']);
$mechanic = $mechanicRepo->findById($appointment['mechanic_id']);

// Получаем историю изменений
$logSql = "SELECT * FROM AppointmentLog WHERE appointment_id = :id ORDER BY changed_at DESC";
$logStmt = $db->prepare($logSql);
$logStmt->execute([':id' => $id]);
$logs = $logStmt->fetchAll();

// CSRF токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$message = '';
$error = '';

// Обработка отмены записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        $error = 'Ошибка безопасности. Попробуйте снова.';
    } elseif ($_POST['action'] === 'cancel') {
        $reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : 'Не указана';
        try {
            $appointmentRepo->changeStatus($id, 'отменено', $reason);
            $message = 'Запись успешно отменена';
            // Обновляем данные
            $appointment = $appointmentRepo->findById($id);
        } catch (RepositoryException $e) {
            $error = $e->getMessage();
        }
    }
}

$statusColors = [
    'запланировано' => '#ffc107',
    'подтверждено' => '#28a745',
    'выполняется' => '#17a2b8',
    'завершено' => '#6c757d',
    'отменено' => '#dc3545'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Просмотр записи №<?= $id ?></title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .card-header {
            background: #007bff;
            color: white;
            padding: 15px 20px;
            font-size: 18px;
            font-weight: bold;
        }
        .card-body {
            padding: 20px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .info-label {
            font-weight: 600;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 16px;
            color: #333;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            color: white;
            font-size: 13px;
            font-weight: 500;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            border: none;
            margin-right: 10px;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .logs-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .logs-table th, .logs-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .logs-table th {
            background: #f8f9fa;
        }
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }
        hr {
            margin: 20px 0;
        }
        @media (max-width: 600px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            🔧 Запись на ремонт №<?= htmlspecialchars($appointment['app_id']) ?>
        </div>
        <div class="card-body">
            
            <?php if ($message): ?>
                <div class="alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">📅 Дата и время</div>
                    <div class="info-value"><?= date('d.m.Y H:i', strtotime($appointment['app_datetime'])) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">📊 Статус</div>
                    <div class="info-value">
                        <span class="status-badge" style="background: <?= $statusColors[$appointment['status']] ?? '#6c757d' ?>">
                            <?= htmlspecialchars($appointment['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">👤 Клиент</div>
                    <div class="info-value"><?= htmlspecialchars($client['full_name'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">📞 Телефон</div>
                    <div class="info-value"><?= htmlspecialchars($client['phone'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">📧 Email</div>
                    <div class="info-value"><?= htmlspecialchars($client['email'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">🚲 Велосипед</div>
                    <div class="info-value">
                        <?= htmlspecialchars($bike['frame_type'] ?? '-') ?>, 
                        <?= htmlspecialchars($bike['wheel_size'] ?? '-') ?>" колёса, 
                        тормоза <?= htmlspecialchars($bike['brake_type'] ?? '-') ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">🔧 Услуга</div>
                    <div class="info-value">
                        <?= htmlspecialchars($service['service_name'] ?? '-') ?>
                        (<?= $service['type'] == 'simple' ? 'Простая' : 'Сложная' ?>)
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">💰 Стоимость</div>
                    <div class="info-value"><?= number_format($service['base_price'] ?? 0, 2) ?> ₽</div>
                </div>
                <div class="info-item">
                    <div class="info-label">👨‍🔧 Мастер</div>
                    <div class="info-value"><?= htmlspecialchars($mechanic['full_name'] ?? '-') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">⭐ Опыт мастера</div>
                    <div class="info-value"><?= htmlspecialchars($mechanic['experience_years'] ?? '-') ?> лет</div>
                </div>
                <?php if ($appointment['actual_minutes']): ?>
                <div class="info-item">
                    <div class="info-label">⏱️ Время выполнения</div>
                    <div class="info-value"><?= $appointment['actual_minutes'] ?> минут</div>
                </div>
                <?php endif; ?>
                <?php if ($appointment['cancel_reason']): ?>
                <div class="info-item">
                    <div class="info-label">❓ Причина отмены</div>
                    <div class="info-value"><?= htmlspecialchars($appointment['cancel_reason']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="action-buttons">
                <a href="appointments_list.php" class="btn btn-secondary">← Назад к списку</a>
                <a href="booking.php?reschedule=<?= $id ?>" class="btn btn-warning">🔄 Перенести запись</a>
                
                <?php if ($appointment['status'] !== 'отменено' && $appointment['status'] !== 'завершено'): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Вы действительно хотите отменить эту запись?')">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="cancel">
                    <input type="text" name="cancel_reason" placeholder="Причина отмены" style="padding: 8px; width: 200px;">
                    <button type="submit" class="btn btn-danger">❌ Отменить запись</button>
                </form>
                <?php endif; ?>
            </div>
            
            <hr>
            
            <h3>📜 История изменений</h3>
            <?php if (empty($logs)): ?>
                <p>Нет записей об изменениях</p>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr><th>Дата</th><th>Было</th><th>Стало</th><th>Кто изменил</th><th>Комментарий</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?= date('d.m.Y H:i:s', strtotime($log['changed_at'])) ?></td>
                            <td><?= htmlspecialchars($log['old_status'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($log['new_status']) ?></td>
                            <td><?= htmlspecialchars($log['changed_by'] ?? 'system') ?></td>
                            <td><?= htmlspecialchars($log['change_comment'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>