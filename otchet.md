МИНИСТЕРСТВО ОБРАЗОВАНИЯ КУЗБАССА

**ГПОУ «ЮРГИНСКИЙ ТЕХНОЛОГИЧЕСКИЙ КОЛЛЕДЖ»**

**ИМ. ПАВЛЮЧКОВА Г.А.**

Отделение АиТ

**Проектирование и создание схемы реляционной базы данных**

ОТЧЕТ ПО ПРАКТИЧЕКОЙ РАБОТЕ № 1

УП01.01 УЧЕБНАЯ ПРАКТИКА

Специальность 09.02.09 Веб-разработка

Выполнил студент гр. 454

Утин М.А.

Проверил преподаватель

Поликарпочкин М.В.

2026 г.

Цель: Разработать реляционную базу данных для системы онлайн-записи в
велоремонтную мастерскую, обеспечивающую учёт простых и сложных услуг,
хранение характеристик велосипеда (рама, колёса, тип тормозов),
назначение приоритетов заказов для мастеров, управление запасными
частями с аналогами, контроль доступа мастеров к сложному ремонту (опыт
более двух лет) и формирование аналитического запроса о среднем времени
выполнения ремонта по каждому мастеру.

Ход работы:

1.  Сущности и атрибуты продемонстрированы в таблице 1.

  --------------------------------------------------------------------------
  **Сущность**   **Атрибуты**                             **Первичный ключ**
  -------------- ---------------------------------------- ------------------
  Client         client_id, full_name, phone, email       client_id

  Bike           bike_id, client_id (FK), frame_type,     bike_id
                 wheel_size, brake_type                   

  Service        service_id, service_name, type           service_id
                 ('simple','complex'), base_price         

  Mechanic       mechanic_id, full_name,                  mechanic_id
                 experience_years, priority               

  Appointment    app_id, client_id (FK), bike_id (FK),    app_id
                 service_id (FK), mechanic_id (FK),       
                 app_datetime, status, actual_minutes     

  Part           part_id, part_name, stock_quantity,      part_id
                 price                                    

  PartAnalog     part_id (FK), analog_part_id (FK)        составной
                                                          (part_id,
                                                          analog_part_id)

  OrderPart      app_id (FK), part_id (FK), quantity_used составной
  --------------------------------------------------------------------------

Таблица 1 - Сущности базы данных

Связи:

1.  Client (1) -- (M) Bike

2.  Client (1) -- (M) Appointment

3.  Bike (1) -- (M) Appointment

4.  Service (1) -- (M) Appointment

5.  Mechanic (1) -- (M) Appointment

6.  Appointment (M) -- (M) Part через OrderPart

7.  Part (1) -- (M) PartAnalog (как исходная деталь)

8.  Part (1) -- (M) PartAnalog (как аналог)

```{=html}
<!-- -->
```
2.  Реляционная схема (таблицы):

> Client
>
> client_id INT PRIMARY KEY AUTO_INCREMENT
>
> full_name VARCHAR(100) NOT NULL
>
> phone VARCHAR(20) UNIQUE NOT NULL
>
> email VARCHAR(100) UNIQUE
>
> Bike:
>
> bike_id INT PRIMARY KEY AUTO_INCREMENT
>
> client_id INT NOT NULL (FK → Client)
>
> frame_type ENUM(\'горная\',\'шоссейная\',\'городская\')
>
> wheel_size DECIMAL(4,1) CHECK (wheel_size BETWEEN 12 AND 29)
>
> brake_type ENUM(\'дисковые\',\'ободные\',\'барабанные\')
>
> Service:
>
> service_id INT PRIMARY KEY AUTO_INCREMENT
>
> service_name VARCHAR(100) UNIQUE NOT NULL
>
> type ENUM(\'simple\',\'complex\') NOT NULL
>
> base_price DECIMAL(8,2) CHECK (base_price \> 0)
>
> Mechanic:
>
> mechanic_id INT PRIMARY KEY AUTO_INCREMENT
>
> full_name VARCHAR(100) NOT NULL
>
> experience_years DECIMAL(3,1) CHECK (experience_years \>= 0)
>
> priority INT CHECK (priority BETWEEN 1 AND 10)
>
> Appointment
>
> app_id INT PRIMARY KEY AUTO_INCREMENT
>
> client_id INT NOT NULL (FK → Client)
>
> bike_id INT NOT NULL (FK → Bike)
>
> service_id INT NOT NULL (FK → Service)
>
> mechanic_id INT NOT NULL (FK → Mechanic)
>
> app_datetime DATETIME NOT NULL
>
> status
> ENUM(\'запланировано\',\'выполняется\',\'завершено\',\'отменено\')
> DEFAULT \'запланировано\'
>
> actual_minutes INT CHECK (actual_minutes IS NULL OR actual_minutes \>
> 0)
>
> UNIQUE KEY (mechanic_id, app_datetime) -- запрет двойной записи
> мастеру
>
> Part:
>
> part_id INT PRIMARY KEY AUTO_INCREMENT
>
> part_name VARCHAR(100) NOT NULL
>
> stock_quantity INT NOT NULL DEFAULT 0 CHECK (stock_quantity \>= 0)
>
> price DECIMAL(8,2) CHECK (price \>= 0)
>
> PartAnalog
>
> part_id INT (FK → Part)
>
> analog_part_id INT (FK → Part)
>
> PRIMARY KEY (part_id, analog_part_id)
>
> CHECK (part_id != analog_part_id) -- деталь не может быть аналогом
> самой себе
>
> OrderPart:
>
> app_id INT (FK → Appointment)
>
> part_id INT (FK → Part)
>
> quantity_used INT NOT NULL CHECK (quantity_used \> 0)
>
> PRIMARY KEY (app_id, part_id)

Нормализация:

Все таблицы находятся в 1НФ, так как каждый столбец содержит атомарные
значения (нет списков или массивов). Вторая нормальная форма (2НФ)
соблюдена, поскольку в таблицах с составными ключами (PartAnalog,
OrderPart) все неключевые столбцы зависят от полного ключа. В таблицах с
простым ключом 2НФ выполняется автоматически. Третья нормальная форма
(3НФ) достигнута, так как отсутствуют транзитивные зависимости:
например, в Appointment не хранится цена услуги или имя мастера, они
получаются через JOIN. Денормализация не применялась для сохранения
целостности

3.  После создания базы данных через веб интерфейс, создаем таблицы с
    помощью запросов в SQL, как продемонстрировано на рисунке 1.

    Рисунок 1 - Создание таблиц

4.  Создание индекса для поиска по датам, показанный на рисунке 2.

 
    Рисунок 2 - Создание индекса

5.  Добавляем данные для каждой таблицы, как показано на рисунке 3.


    Рисунок 3 -Добавление данных

### Выполнение запроса с 3 JOIN показывает все завершённые заказы с данными клиентов, велосипедов и мастеров, показанный на рисунке 4.


Рисунок 4 - Вывод запроса

### Выполнение запроса с группировкой показывает среднее время выполнения ремонта по каждому мастеру на рисунке 5.


Рисунок 5 -Вывод запроса

### Выполнение запроса с подзапросом, показывает мастеров, которые выполняли сложный ремонт, но их опыт меньше 2 лет, но поскольку таких мастеров нет запрос ничего не выдал, продемонстрирован данный запрос на рисунке 6.



Рисунок 6 - Вывод запроса

6.  Проверка уникальности показана на рисунке 7.


    Рисунок 7 - Проверка уникальности

7.  Нарушение внешнего ключа продемонстрировано на рисунке 8.


    Рисунок 8 - Нарушение внешнего ключа

### Нарушение CHECK-ограничения показана на рисунке 9.

Рисунок 9 - Нарушение CHECK-ограничения

ЗАКЛЮЧЕНИЕ

В ходе выполнения данной практической работы выяснилось что, основная
сложность при проектировании заключалась в реализации бизнес-правила
«для сложного ремонта мастер должен иметь опыт более двух лет» на уровне
схемы. CHECK-ограничения в MySQL не могут обращаться к другой таблице
(Service.type и Mechanic.experience_years одновременно). Поэтому это
правило пришлось бы выносить в триггер или приложение. Ещё одной
сложностью стала связь «аналоги запчастей» (рекурсивная внешняя ссылка),
которая требует аккуратности при вставке данных.

Разработанная схема полностью поддерживает онлайн-запись: хранение
клиентов, велосипедов, услуг, мастеров, заказов, запчастей и аналогов.
Обеспечена уникальность временных слотов для мастера, целостность
ссылок, автоматическая проверка допустимых значений через CHECK. Запросы
позволяют получить аналитику по среднему времени ремонта.
