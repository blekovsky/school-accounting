<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

session_start();
if (isset($_GET['clear_session'])) {
    session_destroy();
    header("Location: /");
    exit;
}
// --- ИНИЦИАЛИЗАЦИЯ ТЕСТОВЫХ ДАННЫХ В СЕССИИ (ИМИТАЦИЯ БД С УЧЕТОМ SOFT DELETE) ---
if (!isset($_SESSION['school_db_initialized'])) {
    // 1. Организации (с поддержкой логического удаления 'is_deleted') [FR-1.1]
    $_SESSION['organizations'] = [
        1 => ['id' => 1, 'full_name' => 'ООО "Вектор-Инвест"', 'short_name' => 'ООО "Вектор"', 'inn' => '7701020304', 'kpp' => '770101001', 'ogrn' => '1027739012345', 'actual_address' => 'г. Москва, ул. Ленина, д. 10', 'is_deleted' => false, 'created_at' => '10.09.2026, 09:15', 'created_by' => 'Егоров А.Р. (администратор)', 'updated_at' => '25.09.2026, 14:30', 'updated_by' => 'Егоров А.Р. (администратор)'],
        2 => ['id' => 2, 'full_name' => 'АО "Прогресс-Технолоджи"', 'short_name' => 'АО "Прогресс"', 'inn' => '5003214569', 'kpp' => '500301001', 'ogrn' => '1035001234567', 'actual_address' => 'г. Санкт-Петербург, Невский пр., д. 45', 'is_deleted' => false, 'created_at' => '12.09.2026, 11:20', 'created_by' => 'Егоров А.Р. (администратор)', 'updated_at' => '12.09.2026, 11:20', 'updated_by' => 'Егоров А.Р. (методист)']
    ];

    // 2. Сотрудники [FR-2.1]
    $_SESSION['employees'] = [
        1 => ['id' => 1, 'last_name' => 'Иванов', 'first_name' => 'Иван', 'middle_name' => 'Иванович', 'education_level' => 'Высшее', 'status' => 'Активен', 'org_id' => 1, 'position' => 'Главный инженер', 'dismissed_at' => null],
        2 => ['id' => 2, 'last_name' => 'Петров', 'first_name' => 'Петр', 'middle_name' => 'Сергеевич', 'education_level' => 'Нет', 'status' => 'Активен', 'org_id' => 1, 'position' => 'Техник-электрик', 'dismissed_at' => null],
        3 => ['id' => 3, 'last_name' => 'Сидоров', 'first_name' => 'Сидор', 'middle_name' => 'Петрович', 'education_level' => 'Высшее', 'status' => 'Уволен', 'org_id' => 2, 'position' => 'Старший менеджер', 'dismissed_at' => '01.10.2026']
    ];

    // 3. Программы обучения [FR-3.1]
    $_SESSION['courses'] = [
        1 => ['id' => 1, 'title' => 'Пожарно-технический минимум', 'required_education' => 'Высшее', 'periodicity_years' => 1, 'price' => 4500],
        2 => ['id' => 2, 'title' => 'Охрана труда на предприятии', 'required_education' => 'Не требуется', 'periodicity_years' => 3, 'price' => 3200]
    ];

    // 4. Группы обучения и записи [FR-4.1, FR-5.1]
    $_SESSION['e_learning'] = [
        [
            'id' => 101,
            'employee_id' => 1,
            'course_id' => 1,
            'passed_at' => date('Y-m-d', strtotime('-11 months')),
            'next_training_at' => date('Y-m-d', strtotime('+20 days')),
            'status' => 'Прошел обучение',
            'doc_num' => 'Д-2026-0041'
        ]
    ];

    // 5. Системный журнал изменений [FR-9.2, FR-10.2]
    $_SESSION['sys_logs'] = [
        ['time' => '10.09.2026, 09:15', 'user' => 'Егоров А.Р. (администратор)', 'action' => 'Создание карточки организации ООО "Вектор-Инвест"'],
        ['time' => '25.09.2026, 14:30', 'user' => 'Егоров А.Р. (методист)', 'action' => 'Изменение реквизитов КПП ООО "Вектор-Инвест"'],
        ['time' => '05.10.2026, 10:00', 'user' => 'Егоров А.Р. (администратор)', 'action' => 'Инициализация базовых учебных программ']
    ];

    $_SESSION['school_db_initialized'] = true;
}

// --- СИМУЛЯЦИЯ СКАЧИВАНИЯ ДОГОВОРА (.DOCX / .PDF) [FR-7.1] ---
if (isset($_GET['action']) && $_GET['action'] === 'download_contract') {
    $doc_num = htmlspecialchars($_GET['doc_num'] ?? 'Д-2026-0000');
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="contract_' . $doc_num . '.docx"');
    echo "=========================================================================\n";
    echo "                 ГОСУДАРСТВЕННЫЙ ДОГОВОР НА ОБУЧЕНИЕ № $doc_num\n";
    echo "=========================================================================\n\n";
    echo "Дата формирования: " . date('d.m.Y') . " г.\n";
    echo "Заказчик: ООО \"Вектор-Инвест\" (ИНН: 7701020304, КПП: 770101001)\n";
    echo "Исполнитель: Автономная Образовательная Информационная Система\n\n";
    echo "Предмет договора: Оказание образовательных услуг по программе переподготовки.\n";
    echo "Основание: Техническое задание от 2026 года.\n";
    exit;
}

// --- ОБРАБОТКА ФОРМЫ: БИЗНЕС-ЛОГИКА ЗАЧИСЛЕНИЯ С ПРОВЕРКОЙ ЦЕНЗА [FR-4.3] ---
$alert_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_enroll'])) {
    $emp_id = intval($_POST['employee_id']);
    $course_id = intval($_POST['course_id']);

    $employee = $_SESSION['employees'][$emp_id] ?? null;
    $course = $_SESSION['courses'][$course_id] ?? null;

    if ($employee && $course) {
        if ($employee['status'] === 'Уволен') {
            $alert_message = "<div class='alert alert-warning'>⚠️ <strong>Предупреждение:</strong> Невозможно зачислить сотрудника {$employee['last_name']} {$employee['first_name']}, так как он имеет статус «Уволен»!</div>";
        }
        elseif ($course['required_education'] !== 'Не требуется' && $employee['education_level'] !== $course['required_education']) {
            $alert_message = "<div class='alert alert-danger'>❌ <strong>Предупреждение ценза:</strong> Уровень образования сотрудника ({$employee['education_level']}) не соответствует жестким требованиям программы обучения («{$course['required_education']}»)!</div>";
        } 
        else {
            $next_date = date('Y-m-d', strtotime("+" . $course['periodicity_years'] . " year"));
            $new_doc = "Д-" . date('Y') . "-" . rand(1000, 9999);
            
            $_SESSION['e_learning'][] = [
                'id' => rand(200, 999),
                'employee_id' => $emp_id,
                'course_id' => $course_id,
                'passed_at' => date('Y-m-d'),
                'next_training_at' => $next_date,
                'status' => 'Зачислен',
                'doc_num' => $new_doc
            ];

            $_SESSION['sys_logs'][] = [
                'time' => date('d.m.Y, H:i'),
                'user' => 'Егоров А.Р. (администратор)',
                'action' => "Зачисление сотрудника {$employee['last_name']} И. на курс '{$course['title']}'"
            ];

            $alert_message = "<div class='alert alert-success'>✅ <strong>Успех:</strong> Сотрудник успешно зачислен. Автоматически сформирован договор <strong>$new_doc</strong>.</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Учет обучающихся — Панель Управления</title>
    <style>
        :root { --primary: #2c3e50; --secondary: #34495e; --accent: #3498db; --success: #2ecc71; --warning: #f1c40f; --danger: #e74c3c; --bg: #f8f9fa; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; display: flex; background-color: var(--bg); color: #333; min-height: 100vh; }
        .sidebar { width: 280px; background-color: var(--primary); color: white; padding: 25px 15px; box-sizing: border-box; position: fixed; height: 100vh; }
        .sidebar h2 { font-size: 19px; margin-bottom: 35px; text-align: center; color: var(--accent); letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; }
        .sidebar a { display: block; color: #ecf0f1; padding: 12px 15px; text-decoration: none; border-radius: 5px; margin-bottom: 7px; font-size: 14px; transition: all 0.2s; font-weight: 500; }
        .sidebar a:hover, .sidebar a.active { background-color: var(--secondary); color: white; border-left: 4px solid var(--accent); padding-left: 19px; }
        .main-wrapper { margin-left: 280px; flex: 1; padding: 40px; box-sizing: border-box; }
        .card { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 35px; border: 1px solid #eef2f5; }
        h2 { color: var(--primary); margin-top: 0; padding-bottom: 12px; border-bottom: 2px solid #f1f3f5; font-size: 20px; font-weight: 600; }
        .dashboard-grid { display: flex; gap: 20px; margin-top: 20px; }
        .dash-box { flex: 1; padding: 25px; border-radius: 6px; color: white; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .dash-box h3 { margin: 0; font-size: 38px; font-weight: 700; }
        .dash-box p { margin: 6px 0 0 0; font-size: 14px; opacity: 0.9; font-weight: 500; }
        .bg-blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        .bg-green { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .bg-orange { background: linear-gradient(135deg, #e67e22, #d35400); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th, td { padding: 14px 16px; text-align: left; border-bottom: 1px solid #eef2f6; }
        th { background-color: #f8f9fa; color: #6a7a8c; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        .badge { padding: 5px 11px; border-radius: 4px; color: white; font-weight: bold; font-size: 12px; display: inline-block; }
        .bg-danger { background-color: var(--danger); }
        .bg-warning { background-color: var(--warning); color: #333; }
        .bg-success { background-color: var(--success); }
        .bg-info { background-color: #95a5a6; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-weight: 600; font-size: 13px; color: var(--secondary); margin-bottom: 6px; }
        select, button { width: 100%; max-width: 460px; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 5px; box-sizing: border-box; font-size: 14px; display: block; }
        button { background-color: var(--accent); color: white; border: none; font-weight: 600; cursor: pointer; margin-top: 22px; }
        button:hover { background-color: #2980b9; }
        .alert { padding: 15px 20px; margin-bottom: 25px; border-radius: 6px; font-size: 14px; border-left: 5px solid transparent; }
        .alert-success { background-color: #def7ec; color: #03543f; border-left-color: var(--success); }
        .alert-warning { background-color: #fef08a; color: #713f12; border-left-color: var(--warning); }
        .alert-danger { background-color: #fde8e8; color: #9b1c1c; border-left-color: var(--danger); }
        .btn-action { background-color: #10b981; color: white; padding: 7px 14px; text-decoration: none; border-radius: 4px; font-size: 12px; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; }
        .btn-action:hover { background-color: #059669; }
        .audit-footer { background-color: #1e293b; color: #38bdf8; font-family: 'Courier New', monospace; padding: 15px 20px; border-radius: 6px; font-size: 13px; margin-top: 20px; border-left: 4px solid #0284c7; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>🎓 АИС УЧЕБНЫЙ ЦЕНТР</h2>
    <a href="#section-dash" class="active">📊 Главная / Дашборд</a>
    <a href="#section-orgs">🏢 Карточки контрагентов</a>
    <a href="#section-enroll">📝 Распределение групп</a>
    <a href="#section-notify">🗓️ Kontrol увeдомлeний</a>
</div>

<div class="main-wrapper">
    <?= $alert_message ?>

    <div id="section-dash" class="card">
        <h2>📊 1. Главная страница и аналитический дашборд</h2>
        <div class="dashboard-grid">
            <div class="dash-box bg-blue">
                <h3><?= count($_SESSION['organizations']) ?></h3>
                <p>Организаций в реестре</p>
            </div>
            <div class="dash-box bg-green">
                <h3><?= count($_SESSION['employees']) ?></h3>
                <p>Активных студентов</p>
            </div>
            <div class="dash-box bg-orange">
                <h3><?= count($_SESSION['e_learning']) ?></h3>
                <p>Сформированных договоров</p>
            </div>
        </div>
    </div>

    <div id="section-orgs" class="card">
        <h2>🏢 2. Реестр контрагентов и карточки организаций</h2>
        <?php foreach ($_SESSION['organizations'] as $id => $org): ?>
            <div style="background-color: #f8fafc; padding: 20px; border-radius: 6px; margin-bottom: 25px; border: 1px solid #e2e8f0; border-top: 4px solid var(--accent);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 12px;">
                    <span style="font-size: 16px; font-weight: 700; color: var(--primary);"><?= $org['full_name'] ?></span>
                    <span style="font-size: 12px; color: #64748b;">ОГРН: <?= $org['ogrn'] ?></span>
                </div>
                <div style="font-size: 13px; color: #475569; margin-bottom: 15px;">
                    <strong>ИНН:</strong> <?= $org['inn'] ?> | <strong>КПП:</strong> <?= $org['kpp'] ?><br>
                    <strong>Юр. адрес:</strong> <?= $org['actual_address'] ?>
                </div>
                
                <h4 style="margin: 15px 0 8px 0; font-size: 13px; color: #64748b;">Персонал и образовательный ценз:</h4>
                <table>
                    <thead>
                        <tr>
                            <th>ФИО сотрудника</th>
                            <th>Должность</th>
                            <th>Уровень образования (Ценз)</th>
                            <th>Текущий статус</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_SESSION['employees'] as $emp): 
                            if ($emp['org_id'] == $id): ?>
                            <tr>
                                <td><strong><?= $emp['last_name'] . ' ' . $emp['first_name'] ?></strong></td>
                                <td><?= $emp['position'] ?></td>
                                <td><span class="badge bg-info">🎓 Диплом: <?= $emp['education_level'] ?> (Скан загружен)</span></td>
                                <td><span class="badge bg-success"><?= $emp['status'] ?></span></td>
                            </tr>
                        <?php endif; endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="section-enroll" class="card">
        <h2>📝 3. Инструмент распределения по группам обучения</h2>
        <form method="POST">
            <div class="form-group">
                <label>1. Выберите кандидата из реестра сотрудников:</label>
                <select name="employee_id">
                    <option value="2">Петров Петр Сергеевич (Диплом ценза: Нет, Статус: Активен)</option>
                    <option value="1">Иванов Иван Иванович (Диплом ценза: Высшее, Статус: Активен)</option>
                    <option value="3">Сидоров Сидор Петрович (Диплом ценза: Высшее, Статус: Уволен)</option>
                </select>
            </div>
            <div class="form-group" style="margin-top: 15px;">
                <label>2. Выберите целевую группу (программу обучения):</label>
                <select name="course_id">
                    <option value="1">Группа ПБ-01 — Пожарно-технический минимум (Требуется: Высшее)</option>
                    <option value="2">Группа ОТ-03 — Охрана труда на предприятии (Не требуется)</option>
                </select>
            </div>
            <button type="submit" name="action_enroll">Запустить проверку ценза и зачислить</button>
        </form>
    </div>

    <div id="section-notify" class="card">
        <h2>🗓️ 4. Интеллектуальный контроль сроков обучения и выгрузка договоров</h2>
        <table>
            <thead>
                <tr>
                    <th>ФИО сотрудника</th>
                    <th>Программа обучения (Группа)</th>
                    <th>Плановый повтор</th>
                    <th>Статус (Дней осталось)</th>
                    <th>Документ-основание (Печатная форма)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($_SESSION['e_learning'] as $el): 
                    $emp = $_SESSION['employees'][$el['employee_id']];
                    $crs = $_SESSION['courses'][$el['course_id']];
                    $days_left = round((strtotime($el['next_training_at']) - time()) / 86400);
                    $badge_class = ($days_left < 60) ? 'bg-danger' : 'bg-success';
                ?>
                <tr>
                    <td><strong><?= $emp['last_name'] . ' ' . $emp['first_name'] ?></strong></td>
                    <td><?= $crs['title'] ?></td>
                    <td><span style="font-family:monospace;"><?= date('d.m.Y', strtotime($el['next_training_at'])) ?></span></td>
                    <td><span class="badge <?= $badge_class ?>">⚠️ Критично! Осталось: <?= $days_left ?> дн.</span></td>
                    <td>
                        <a href="?action=download_contract&doc_num=<?= $el['doc_num'] ?>" class="btn-action">
                            💾 Выгрузить контракт <?= $el['doc_num'] ?> (.DOCX)
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>🔒 5. Журнал аудита системы (Безопасность ИС)</h2>
        <div style="background-color: #1e293b; color: #38bdf8; font-family: monospace; padding: 15px; border-radius: 6px; font-size: 13px;">
            <?php foreach (array_reverse($_SESSION['sys_logs']) as $log): ?>
                [<?= $log['time'] ?>] Логгер: <?= $log['user'] ?> -> Действие: <?= $log['action'] ?><br>
            <?php endforeach; ?>
        </div>
    </div>
</div>

</body>
</html>