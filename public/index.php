<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/functions.php';

$version = '1.0.0';
$currentDate = date('d.m.Y');
$dataPath = __DIR__ . '/../dataset';
$pageError = null;

try {
    $employees = loadEmployees($dataPath . '/employees.csv');
    $departments = array_values(array_unique(array_column($employees, 'department')));
    sort($departments);
    $caseResults = runFormCases($dataPath . '/form_cases.json', $departments);
} catch (Throwable $exception) {
    $employees = [];
    $departments = [];
    $caseResults = [];
    $pageError = $exception->getMessage();
}

$selectedDepartment = trim((string) ($_GET['department'] ?? ''));
if ($selectedDepartment !== '' && !in_array($selectedDepartment, $departments, true)) {
    $selectedDepartment = '';
}

$visibleEmployees = $employees;
if ($selectedDepartment !== '') {
    $visibleEmployees = array_values(array_filter(
        $employees,
        static fn (array $employee): bool => $employee['department'] === $selectedDepartment
    ));
}

$formErrors = [];
$formValues = ['name' => '', 'email' => '', 'department' => '', 'experience' => ''];
$successEmployee = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formValues as $key => $defaultValue) {
        $formValues[$key] = trim((string) ($_POST[$key] ?? $defaultValue));
    }

    $formErrors = validateEmployee($formValues, $departments);
    if ($formErrors === []) {
        $successEmployee = $formValues;
        $formValues = ['name' => '', 'email' => '', 'department' => '', 'experience' => ''];
    }
}

$totalEmployees = count($employees);
$departmentCount = count($departments);
$passedCases = count(array_filter($caseResults, static fn (array $case): bool => $case['passed']));
$allCasesPassed = $caseResults !== [] && $passedCases === count($caseResults);
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Практическая работа № 4 — PHP и формы</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php"><span class="brand-mark">04</span> PHP-практикум</a>
    <span class="topbar-note">ТРПО · Практическая работа № 4</span>
</header>

<main class="container">
    <section class="hero">
        <div class="eyebrow">ОСНОВЫ PHP · GET / POST · CSV</div>
        <h1>Учёт сотрудников</h1>
        <p>Небольшое серверное приложение: загружает сотрудников из CSV, фильтрует таблицу и проверяет данные формы на стороне PHP.</p>
        <div class="hero-meta"><span>Версия <?= e($version) ?></span><span>Дата запуска: <?= e($currentDate) ?></span></div>
    </section>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-error"><?= e($pageError) ?></div>
    <?php endif; ?>

    <section class="stats">
        <article class="stat-card"><span>Записей в dataset</span><strong><?= $totalEmployees ?></strong><small>employees.csv</small></article>
        <article class="stat-card"><span>Отделов</span><strong><?= $departmentCount ?></strong><small>Получены из CSV</small></article>
        <article class="stat-card"><span>Тест-кейсов пройдено</span><strong><?= $passedCases ?>/<?= count($caseResults) ?></strong><small><?= $allCasesPassed ? 'Проверка успешна' : 'Есть замечания' ?></small></article>
    </section>

    <section class="panel">
        <div class="section-heading">
            <div><div class="eyebrow">ДАННЫЕ ИЗ ФАЙЛА</div><h2>Сотрудники</h2></div>
            <span class="counter"><?= count($visibleEmployees) ?> из <?= $totalEmployees ?></span>
        </div>
        <form class="filter-form" method="get" action="index.php">
            <label for="department">Фильтр по отделу</label>
            <select id="department" name="department">
                <option value="">Все отделы</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= e($department) ?>" <?= $selectedDepartment === $department ? 'selected' : '' ?>><?= e($department) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button button-secondary" type="submit">Применить</button>
            <?php if ($selectedDepartment !== ''): ?><a class="text-link" href="index.php">Сбросить</a><?php endif; ?>
        </form>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Сотрудник</th><th>Отдел</th><th>Опыт</th><th>Email</th></tr></thead>
                <tbody>
                <?php if ($visibleEmployees === []): ?>
                    <tr><td colspan="5" class="empty">По выбранному фильтру записей нет.</td></tr>
                <?php else: ?>
                    <?php foreach ($visibleEmployees as $employee): ?>
                        <tr>
                            <td><?= $employee['id'] ?></td><td class="person"><?= e($employee['name']) ?></td>
                            <td><span class="tag"><?= e($employee['department']) ?></span></td>
                            <td><?= $employee['experience'] ?> лет</td><td><?= e($employee['email']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="hint">Таблица формируется из <code>dataset/employees.csv</code> при каждом открытии страницы.</p>
    </section>

    <section class="two-columns">
        <article class="panel">
            <div class="eyebrow">POST · СЕРВЕРНАЯ ПРОВЕРКА</div>
            <h2>Добавить заявку сотрудника</h2>
            <p class="muted">Форма демонстрирует обработку и проверку данных. Запись в исходный CSV не выполняется.</p>

            <?php if ($successEmployee !== null): ?>
                <div class="alert alert-success">Заявка для <strong><?= e($successEmployee['name']) ?></strong> успешно проверена.</div>
                <div class="result-list"><span><?= e($successEmployee['department']) ?></span><span><?= e($successEmployee['email']) ?></span><span>Опыт: <?= e($successEmployee['experience']) ?> лет</span></div>
            <?php endif; ?>

            <?php if ($formErrors !== []): ?>
                <div class="alert alert-error"><strong>Проверьте поля формы:</strong><ul><?php foreach ($formErrors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>

            <form method="post" action="index.php" class="stack-form">
                <label for="name">ФИО</label>
                <input id="name" name="name" type="text" maxlength="80" required value="<?= e($formValues['name']) ?>" placeholder="Например, Иван Петров">
                <?php if (isset($formErrors['name'])): ?><small class="field-error"><?= e($formErrors['name']) ?></small><?php endif; ?>

                <label for="email">Email</label>
                <input id="email" name="email" type="email" required value="<?= e($formValues['email']) ?>" placeholder="name@example.test">
                <?php if (isset($formErrors['email'])): ?><small class="field-error"><?= e($formErrors['email']) ?></small><?php endif; ?>

                <label for="department-select">Отдел</label>
                <select id="department-select" name="department" required>
                    <option value="">Выберите отдел</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= e($department) ?>" <?= $formValues['department'] === $department ? 'selected' : '' ?>><?= e($department) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['department'])): ?><small class="field-error"><?= e($formErrors['department']) ?></small><?php endif; ?>

                <label for="experience">Опыт работы, лет</label>
                <input id="experience" name="experience" type="number" min="0" max="40" step="1" required value="<?= e($formValues['experience']) ?>" placeholder="0">
                <?php if (isset($formErrors['experience'])): ?><small class="field-error"><?= e($formErrors['experience']) ?></small><?php endif; ?>
                <button class="button" type="submit">Проверить заявку</button>
            </form>
        </article>

        <article class="panel">
            <div class="eyebrow">АВТОМАТИЧЕСКИЕ ПРОВЕРКИ</div>
            <h2>Сценарии из JSON</h2>
            <p class="muted">Результаты вычисляются PHP-функцией validateEmployee на основе <code>form_cases.json</code>.</p>
            <div class="case-list">
                <?php foreach ($caseResults as $case): ?>
                    <div class="case-row">
                        <div><strong><?= e($case['case']) ?></strong><small>Ожидалось: <?= $case['expected'] ? 'корректно' : 'ошибка' ?></small></div>
                        <span class="status <?= $case['passed'] ? 'status-ok' : 'status-bad' ?>"><?= $case['passed'] ? 'Пройден' : 'Не пройден' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="note-box"><strong>Безопасный вывод</strong><p>Значения, которые выводятся в HTML, проходят через <code>htmlspecialchars()</code>. Это не позволяет браузеру интерпретировать введённый текст как HTML-разметку.</p></div>
        </article>
    </section>
    <footer class="footer">Практическая работа № 4 · PHP выполняется на сервере, браузер получает готовый HTML.</footer>
</main>
</body>
</html>
