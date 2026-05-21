-- =====================================================
-- Вариант 23. Система записи в велоремонт
-- Полная схема базы данных (включая логирование и рабочие часы)
-- =====================================================

DROP DATABASE IF EXISTS bike_repair_variant23;
CREATE DATABASE bike_repair_variant23
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE bike_repair_variant23;

-- ==================== ОСНОВНЫЕ ТАБЛИЦЫ ====================

-- 1. Клиенты
CREATE TABLE Client (
    client_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(100) UNIQUE
);

-- 2. Велосипеды
CREATE TABLE Bike (
    bike_id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    frame_type ENUM('горная', 'шоссейная', 'городская') NOT NULL,
    wheel_size DECIMAL(4,1) NOT NULL CHECK (wheel_size BETWEEN 12 AND 29),
    brake_type ENUM('дисковые', 'ободные', 'барабанные') NOT NULL,
    FOREIGN KEY (client_id) REFERENCES Client(client_id)
        ON DELETE CASCADE ON UPDATE CASCADE
);

-- 3. Услуги
CREATE TABLE Service (
    service_id INT PRIMARY KEY AUTO_INCREMENT,
    service_name VARCHAR(100) NOT NULL UNIQUE,
    type ENUM('simple', 'complex') NOT NULL,
    base_price DECIMAL(8,2) NOT NULL CHECK (base_price > 0)
);

-- 4. Мастера
CREATE TABLE Mechanic (
    mechanic_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    experience_years DECIMAL(3,1) NOT NULL CHECK (experience_years >= 0),
    priority INT NOT NULL CHECK (priority BETWEEN 1 AND 10)
);

-- 5. Записи (заказы)
CREATE TABLE Appointment (
    app_id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT NOT NULL,
    bike_id INT NOT NULL,
    service_id INT NOT NULL,
    mechanic_id INT NOT NULL,
    app_datetime DATETIME NOT NULL,
    status ENUM('запланировано', 'подтверждено', 'выполняется', 'завершено', 'отменено') DEFAULT 'запланировано',
    actual_minutes INT CHECK (actual_minutes IS NULL OR actual_minutes > 0),
    cancel_reason VARCHAR(255) NULL,
    FOREIGN KEY (client_id) REFERENCES Client(client_id) ON DELETE RESTRICT,
    FOREIGN KEY (bike_id) REFERENCES Bike(bike_id) ON DELETE RESTRICT,
    FOREIGN KEY (service_id) REFERENCES Service(service_id) ON DELETE RESTRICT,
    FOREIGN KEY (mechanic_id) REFERENCES Mechanic(mechanic_id) ON DELETE RESTRICT,
    UNIQUE KEY unique_mechanic_slot (mechanic_id, app_datetime)
);

-- 6. Запасные части
CREATE TABLE Part (
    part_id INT PRIMARY KEY AUTO_INCREMENT,
    part_name VARCHAR(100) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0 CHECK (stock_quantity >= 0),
    price DECIMAL(8,2) NOT NULL CHECK (price >= 0)
);

-- 7. Аналоги деталей
CREATE TABLE PartAnalog (
    part_id INT NOT NULL,
    analog_part_id INT NOT NULL,
    PRIMARY KEY (part_id, analog_part_id),
    FOREIGN KEY (part_id) REFERENCES Part(part_id) ON DELETE CASCADE,
    FOREIGN KEY (analog_part_id) REFERENCES Part(part_id) ON DELETE CASCADE,
    CHECK (part_id != analog_part_id)
);

-- 8. Использованные детали в заказе
CREATE TABLE OrderPart (
    app_id INT NOT NULL,
    part_id INT NOT NULL,
    quantity_used INT NOT NULL CHECK (quantity_used > 0),
    PRIMARY KEY (app_id, part_id),
    FOREIGN KEY (app_id) REFERENCES Appointment(app_id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES Part(part_id) ON DELETE RESTRICT
);

-- ==================== НОВЫЕ ТАБЛИЦЫ (для бизнес-операций) ====================

-- 9. Лог изменений статуса записи
CREATE TABLE IF NOT EXISTS AppointmentLog (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    old_status VARCHAR(20),
    new_status VARCHAR(20) NOT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    changed_by VARCHAR(100),
    change_comment VARCHAR(255) NULL,
    FOREIGN KEY (appointment_id) REFERENCES Appointment(app_id) ON DELETE CASCADE
);

-- 10. Рабочие часы мастеров (опционально)
CREATE TABLE IF NOT EXISTS WorkingHours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mechanic_id INT NOT NULL,
    day_of_week INT NOT NULL CHECK (day_of_week BETWEEN 1 AND 7), -- 1=понедельник, 7=воскресенье
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (mechanic_id) REFERENCES Mechanic(mechanic_id) ON DELETE CASCADE
);

-- ==================== ИНДЕКСЫ ДЛЯ ПРОИЗВОДИТЕЛЬНОСТИ ====================

CREATE INDEX idx_app_datetime ON Appointment(app_datetime);
CREATE INDEX idx_app_mechanic_status ON Appointment(mechanic_id, status);
CREATE INDEX idx_app_client ON Appointment(client_id);
CREATE INDEX idx_bike_client ON Bike(client_id);

-- ==================== ТЕСТОВЫЕ ДАННЫЕ ====================

INSERT INTO Client (full_name, phone, email) VALUES
('Иванов Пётр Сергеевич', '+79111234567', 'ivanov@mail.ru'),
('Петрова Анна Игоревна', '+79221234567', 'petrova@mail.ru'),
('Сидоров Алексей Владимирович', '+79331234567', 'sidorov@mail.ru'),
('Козлова Елена Дмитриевна', '+79441234567', 'kozlov@mail.ru'),
('Морозов Дмитрий Павлович', '+79551234567', 'morozov@mail.ru');

INSERT INTO Bike (client_id, frame_type, wheel_size, brake_type) VALUES
(1, 'горная', 26.0, 'дисковые'),
(1, 'шоссейная', 28.0, 'ободные'),
(2, 'городская', 24.0, 'барабанные'),
(3, 'горная', 27.5, 'дисковые'),
(4, 'шоссейная', 29.0, 'ободные');

INSERT INTO Service (service_name, type, base_price) VALUES
('Накачка колёс', 'simple', 200.00),
('Замена камеры', 'simple', 500.00),
('Регулировка тормозов', 'simple', 700.00),
('Капитальный ремонт трансмиссии', 'complex', 3500.00),
('Замена вилки', 'complex', 4200.00);

INSERT INTO Mechanic (full_name, experience_years, priority) VALUES
('Кузнецов Олег Иванович', 1.5, 5),
('Смирнова Татьяна Петровна', 3.0, 2),
('Васильев Андрей Сергеевич', 7.2, 1),
('Новикова Ирина Владимировна', 0.8, 8);

INSERT INTO Appointment (client_id, bike_id, service_id, mechanic_id, app_datetime, status, actual_minutes) VALUES
(1, 1, 1, 2, '2026-06-01 10:00:00', 'завершено', 15),
(2, 3, 3, 2, '2026-06-01 11:30:00', 'завершено', 45),
(1, 2, 4, 3, '2026-06-02 09:00:00', 'завершено', 120),
(3, 4, 5, 3, '2026-06-03 14:00:00', 'выполняется', NULL),
(4, 5, 2, 1, '2026-06-04 16:00:00', 'завершено', 30),
(5, 1, 3, 2, '2026-06-05 12:00:00', 'запланировано', NULL);

INSERT INTO Part (part_name, stock_quantity, price) VALUES
('Камера 26"', 15, 350.00),
('Камера 28"', 8, 400.00),
('Тормозная колодка диск', 20, 250.00),
('Цепь 8-скоростная', 5, 900.00),
('Вилка амортизационная', 2, 4500.00);

INSERT INTO PartAnalog (part_id, analog_part_id) VALUES
(1, 2),
(2, 1);

INSERT INTO OrderPart (app_id, part_id, quantity_used) VALUES
(1, 1, 1),
(2, 3, 2),
(3, 4, 1),
(5, 2, 1);

-- Добавляем рабочие часы для мастеров (по умолчанию: 9:00-18:00, перерыв 13:00-14:00)
INSERT INTO WorkingHours (mechanic_id, day_of_week, start_time, end_time) VALUES
(1, 1, '09:00:00', '18:00:00'), (1, 2, '09:00:00', '18:00:00'), (1, 3, '09:00:00', '18:00:00'),
(1, 4, '09:00:00', '18:00:00'), (1, 5, '09:00:00', '18:00:00'),
(2, 1, '09:00:00', '18:00:00'), (2, 2, '09:00:00', '18:00:00'), (2, 3, '09:00:00', '18:00:00'),
(2, 4, '09:00:00', '18:00:00'), (2, 5, '09:00:00', '18:00:00'),
(3, 1, '09:00:00', '18:00:00'), (3, 2, '09:00:00', '18:00:00'), (3, 3, '09:00:00', '18:00:00'),
(3, 4, '09:00:00', '18:00:00'), (3, 5, '09:00:00', '18:00:00'),
(4, 1, '09:00:00', '18:00:00'), (4, 2, '09:00:00', '18:00:00'), (4, 3, '09:00:00', '18:00:00'),
(4, 4, '09:00:00', '18:00:00'), (4, 5, '09:00:00', '18:00:00');
