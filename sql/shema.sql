-- =====================================================
-- Вариант 23. Добавление новых таблиц и колонок
-- =====================================================

USE w95059ji_1;

-- 1. Лог изменений статуса записи
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

-- 2. Рабочие часы мастеров
CREATE TABLE IF NOT EXISTS WorkingHours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    mechanic_id INT NOT NULL,
    day_of_week INT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    FOREIGN KEY (mechanic_id) REFERENCES Mechanic(mechanic_id) ON DELETE CASCADE
);

-- 3. Вставляем рабочие часы
DELETE FROM WorkingHours;

INSERT INTO WorkingHours (mechanic_id, day_of_week, start_time, end_time) VALUES
(1, 1, '09:00:00', '18:00:00'), (1, 2, '09:00:00', '18:00:00'), (1, 3, '09:00:00', '18:00:00'),
(1, 4, '09:00:00', '18:00:00'), (1, 5, '09:00:00', '18:00:00'),
(2, 1, '09:00:00', '18:00:00'), (2, 2, '09:00:00', '18:00:00'), (2, 3, '09:00:00', '18:00:00'),
(2, 4, '09:00:00', '18:00:00'), (2, 5, '09:00:00', '18:00:00'),
(3, 1, '09:00:00', '18:00:00'), (3, 2, '09:00:00', '18:00:00'), (3, 3, '09:00:00', '18:00:00'),
(3, 4, '09:00:00', '18:00:00'), (3, 5, '09:00:00', '18:00:00'),
(4, 1, '09:00:00', '18:00:00'), (4, 2, '09:00:00', '18:00:00'), (4, 3, '09:00:00', '18:00:00'),
(4, 4, '09:00:00', '18:00:00'), (4, 5, '09:00:00', '18:00:00');
