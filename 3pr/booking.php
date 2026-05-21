<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once 'config.php';
require_once 'src/Database.php';
require_once 'src/Exception/RepositoryException.php';
require_once 'src/Repository/AbstractRepository.php';
require_once 'src/Repository/ClientRepository.php';
require_once 'src/Repository/BikeRepository.php';
require_once 'src/Repository/ServiceRepository.php';
require_once 'src/Repository/MechanicRepository.php';
require_once 'src/Repository/AppointmentRepository.php';

use BikeRepair\Database;
use BikeRepair\Repository\ClientRepository;
use BikeRepair\Repository\BikeRepository;
use BikeRepair\Repository\ServiceRepository;
use BikeRepair\Repository\MechanicRepository;
use BikeRepair\Repository\AppointmentRepository;

$db = Database::getInstance()->getConnection();
$serviceRepo = new ServiceRepository($db);
$mechanicRepo = new MechanicRepository($db);
$clientRepo = new ClientRepository($db);
$bikeRepo = new BikeRepository($db);
$appointmentRepo = new AppointmentRepository($db);

$step = $_GET['step'] ?? 1;
$errors = [];
$success = false;
$bookingCode = null;

// Создание клиента и записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] == 4) {
    // Проверка CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
    
    $clientName = trim($_POST['client_name'] ?? '');
    $clientPhone = trim($_POST['client_phone'] ?? '');
    $clientEmail = trim($_POST['client_email'] ?? '');
    $bikeFrame = $_POST['bike_frame'] ?? '';
    $bikeWheel = (float)($_POST['bike_wheel'] ?? 0);
    $bikeBrake = $_POST['bike_brake'] ?? '';
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $mechanicId = (int)($_POST['mechanic_id'] ?? 0);
    $appointmentDate = $_POST['appointment_date'] ?? '';
    $appointmentTime = $_POST['appointment_time'] ?? '';
    
    // Валидация
    if (empty($clientName)) $errors[] = 'Введите ФИО';
    if (empty($clientPhone)) $errors[] = 'Введите телефон';
    if (empty($bikeFrame)) $errors[] = 'Выберите тип рамы';
    if ($bikeWheel <= 0) $errors[] = 'Укажите размер колёс';
    if (empty($bikeBrake)) $errors[] = 'Выберите тип тормозов';
    if ($serviceId <= 0) $errors[] = 'Выберите услугу';
    if ($mechanicId <= 0) $errors[] = 'Выберите мастера';
    if (empty($appointmentDate)) $errors[] = 'Выберите дату';
    if (empty($appointmentTime)) $errors[] = 'Выберите время';
    
    if (empty($errors)) {
        try {
    $db->beginTransaction();
    
    // Создаём клиента
    $clientId = $clientRepo->create($clientName, $clientPhone, $clientEmail ?: null);
    
    // Создаём велосипед
    $bikeId = $bikeRepo->create($clientId, $bikeFrame, $bikeWheel, $bikeBrake);
    
    // Создаём запись
    $datetime = $appointmentDate . ' ' . $appointmentTime;
    $appointmentId = $appointmentRepo->createAppointmentSafe([
        'client_id' => $clientId,
        'bike_id' => $bikeId,
        'service_id' => $serviceId,
        'mechanic_id' => $mechanicId,
        'app_datetime' => $datetime,
        'status' => 'запланировано'
    ]);
    
    $db->commit();
    
    $bookingCode = 'BK-' . strtoupper(substr(md5($appointmentId), 0, 8));
    $success = true;
    
} catch (Exception $e) {
    $db->rollBack();   // ОБЯЗАТЕЛЬНО: откат при ошибке
    $errors[] = $e->getMessage();
}
    }
}

// AJAX: получение мастеров по услуге
if (isset($_GET['ajax']) && $_GET['ajax'] == 'mechanics' && isset($_GET['service_id'])) {
    $serviceId = (int)$_GET['service_id'];
    $mechanics = $mechanicRepo->findAll([], [], 'full_name ASC');
    
    // Фильтрация по сложности (если нужно)
    $service = $serviceRepo->findById($serviceId);
    $result = [];
    foreach ($mechanics as $m) {
        if ($service && $service['type'] == 'complex' && $m['experience_years'] <= 2) {
            continue;
        }
        $result[] = ['id' => $m['mechanic_id'], 'name' => $m['full_name']];
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// AJAX: получение слотов
if (isset($_GET['ajax']) && $_GET['ajax'] == 'slots' && isset($_GET['mechanic_id']) && isset($_GET['date']) && isset($_GET['service_id'])) {
    $mechanicId = (int)$_GET['mechanic_id'];
    $date = $_GET['date'];
    $serviceId = (int)$_GET['service_id'];
    
    $service = $serviceRepo->findById($serviceId);
    $duration = ($service['type'] == 'simple') ? 30 : 90; // простая 30 мин, сложная 90 мин
    
    $slots = $appointmentRepo->getAvailableSlots($mechanicId, $date, $duration);
    header('Content-Type: application/json');
    echo json_encode($slots);
    exit;
}

// Получение списков для формы
$services = $serviceRepo->findAll();
$mechanics = $mechanicRepo->findAll();

// CSRF токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Запись на ремонт велосипеда</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 700px; margin: auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, select, button { padding: 10px; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }
        button { background: #007bff; color: white; cursor: pointer; }
        button:disabled { background: #ccc; }
        .slots { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .slot-btn { background: #28a745; color: white; border: none; padding: 10px 15px; cursor: pointer; width: auto; }
        .slot-btn.selected { background: #007bff; }
        .error { color: red; margin-top: 10px; }
        .success { color: green; margin-top: 10px; }
        .loader { display: none; margin: 10px 0; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <h1>🚲 Запись на ремонт велосипеда</h1>
    
    <?php if ($success): ?>
        <div class="success">
            <h3>✅ Запись успешно создана!</h3>
            <p><strong>Код бронирования:</strong> <?= htmlspecialchars($bookingCode) ?></p>
            <p>Мы свяжемся с вами для подтверждения.</p>
            <a href="index.php?entity=appointment&action=list">Перейти к списку записей</a>
        </div>
    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $e): ?>
                    <p>❌ <?= htmlspecialchars($e) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="bookingForm">
            <input type="hidden" name="step" value="4">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <!-- Данные клиента -->
            <h3>Ваши данные</h3>
            <div class="form-group">
                <label>ФИО *</label>
                <input type="text" name="client_name" required>
            </div>
            <div class="form-group">
                <label>Телефон * (+7XXXXXXXXXX)</label>
                <input type="tel" name="client_phone" pattern="\+7[0-9]{10}" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="client_email">
            </div>
            
            <!-- Данные велосипеда -->
            <h3>Велосипед</h3>
            <div class="form-group">
                <label>Тип рамы *</label>
                <select name="bike_frame" required>
                    <option value="">-- Выберите --</option>
                    <option value="горная">Горная</option>
                    <option value="шоссейная">Шоссейная</option>
                    <option value="городская">Городская</option>
                </select>
            </div>
            <div class="form-group">
                <label>Размер колёс (дюймы) *</label>
                <input type="number" step="0.5" name="bike_wheel" required>
            </div>
            <div class="form-group">
                <label>Тип тормозов *</label>
                <select name="bike_brake" required>
                    <option value="">-- Выберите --</option>
                    <option value="дисковые">Дисковые</option>
                    <option value="ободные">Ободные</option>
                    <option value="барабанные">Барабанные</option>
                </select>
            </div>
            
            <!-- Услуга и специалист -->
            <h3>Услуга и специалист</h3>
            <div class="form-group">
                <label>Услуга *</label>
                <select name="service_id" id="serviceSelect" required>
                    <option value="">-- Выберите --</option>
                    <?php foreach ($services as $s): ?>
                        <option value="<?= $s['service_id'] ?>"><?= htmlspecialchars($s['service_name']) ?> (<?= $s['type'] == 'simple' ? 'Простая' : 'Сложная' ?>) - <?= number_format($s['base_price'], 2) ?> ₽</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Мастер *</label>
                <select name="mechanic_id" id="mechanicSelect" required>
                    <option value="">-- Сначала выберите услугу --</option>
                </select>
            </div>
            
            <!-- Дата и время -->
            <h3>Дата и время</h3>
            <div class="form-group">
                <label>Дата *</label>
                <input type="date" name="appointment_date" id="dateInput" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
            </div>
            <div class="form-group">
                <label>Время *</label>
                <div id="slotsContainer" class="slots">— Сначала выберите дату и мастера —</div>
                <input type="hidden" name="appointment_time" id="selectedTime" required>
            </div>
            <div id="loader" class="loader">Загрузка свободных слотов...</div>
            
            <button type="submit" id="submitBtn">Записаться</button>
        </form>
    <?php endif; ?>
</div>

<script>
    const serviceSelect = document.getElementById('serviceSelect');
    const mechanicSelect = document.getElementById('mechanicSelect');
    const dateInput = document.getElementById('dateInput');
    const slotsContainer = document.getElementById('slotsContainer');
    const loader = document.getElementById('loader');
    const selectedTimeInput = document.getElementById('selectedTime');
    const submitBtn = document.getElementById('submitBtn');
    
    let currentMechanicId = null;
    let currentDate = null;
    let currentServiceId = null;
    
    // Загрузка мастеров при выборе услуги
    serviceSelect.addEventListener('change', function() {
        const serviceId = this.value;
        if (!serviceId) {
            mechanicSelect.innerHTML = '<option value="">-- Сначала выберите услугу --</option>';
            return;
        }
        
        fetch(`booking.php?ajax=mechanics&service_id=${serviceId}`)
            .then(res => res.json())
            .then(data => {
                mechanicSelect.innerHTML = '<option value="">-- Выберите мастера --</option>';
                data.forEach(m => {
                    mechanicSelect.innerHTML += `<option value="${m.id}">${m.name}</option>`;
                });
            });
    });
    
    // Загрузка слотов при выборе мастера и даты
    function loadSlots() {
        const mechanicId = mechanicSelect.value;
        const date = dateInput.value;
        const serviceId = serviceSelect.value;
        
        if (!mechanicId || !date || !serviceId) {
            slotsContainer.innerHTML = '— Выберите услугу, мастера и дату —';
            return;
        }
        
        loader.style.display = 'block';
        slotsContainer.innerHTML = '';
        
        fetch(`booking.php?ajax=slots&mechanic_id=${mechanicId}&date=${date}&service_id=${serviceId}`)
            .then(res => res.json())
            .then(slots => {
                loader.style.display = 'none';
                if (slots.length === 0) {
                    slotsContainer.innerHTML = '❌ Нет свободных слотов на выбранную дату';
                } else {
                    slots.forEach(slot => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'slot-btn';
                        btn.textContent = slot.time_display;
                        btn.dataset.time = slot.time;
                        btn.onclick = () => {
                            document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
                            btn.classList.add('selected');
                            selectedTimeInput.value = slot.time;
                        };
                        slotsContainer.appendChild(btn);
                    });
                }
            })
            .catch(() => {
                loader.style.display = 'none';
                slotsContainer.innerHTML = 'Ошибка загрузки слотов';
            });
    }
    
    mechanicSelect.addEventListener('change', loadSlots);
    dateInput.addEventListener('change', loadSlots);
</script>
</body>
</html>