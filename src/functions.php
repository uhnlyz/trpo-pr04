<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function normalizeEmployee(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => trim((string) ($row['name'] ?? '')),
        'department' => trim((string) ($row['department'] ?? '')),
        'experience' => (int) ($row['experience'] ?? 0),
        'email' => trim((string) ($row['email'] ?? '')),
    ];
}

function loadEmployees(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Не найден файл employees.csv.');
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('Не удалось открыть employees.csv.');
    }

    $headers = fgetcsv($handle, 0, ';');
    if ($headers === false) {
        fclose($handle);
        return [];
    }

    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]) ?? $headers[0];

    $employees = [];
    while (($values = fgetcsv($handle, 0, ';')) !== false) {
        if (count($values) !== count($headers)) {
            continue;
        }
        $row = array_combine($headers, $values);
        if ($row !== false) {
            $employees[] = normalizeEmployee($row);
        }
    }

    fclose($handle);
    return $employees;
}

function validateEmployee(array $input, array $allowedDepartments, bool $requireAllFields = true): array
{
    $errors = [];
    $name = trim((string) ($input['name'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $department = trim((string) ($input['department'] ?? ''));
    $experienceRaw = trim((string) ($input['experience'] ?? ''));

    if ($name === '') {
        $errors['name'] = 'Укажите имя сотрудника.';
    } elseif (preg_match_all('/./us', $name) > 80) {
        $errors['name'] = 'Имя должно содержать не более 80 символов.';
    }

    if ($requireAllFields || $email !== '') {
        if ($email === '') {
            $errors['email'] = 'Укажите email.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Введите корректный email.';
        }
    }

    if ($requireAllFields || $department !== '') {
        if ($department === '') {
            $errors['department'] = 'Выберите отдел.';
        } elseif (!in_array($department, $allowedDepartments, true)) {
            $errors['department'] = 'Выберите отдел из списка.';
        }
    }

    if ($experienceRaw === '' || filter_var($experienceRaw, FILTER_VALIDATE_INT) === false) {
        $errors['experience'] = 'Укажите целое число лет опыта.';
    } else {
        $experience = (int) $experienceRaw;
        if ($experience < 0 || $experience > 40) {
            $errors['experience'] = 'Опыт должен быть от 0 до 40 лет.';
        }
    }

    return $errors;
}

function runFormCases(string $path, array $allowedDepartments): array
{
    if (!is_file($path)) {
        throw new RuntimeException('Не найден файл form_cases.json.');
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException('Не удалось прочитать form_cases.json.');
    }

    $cases = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $results = [];

    foreach ($cases as $case) {
        $errors = validateEmployee($case, $allowedDepartments, false);
        $actualValid = $errors === [];
        $expectedValid = (bool) ($case['valid'] ?? false);

        $results[] = [
            'case' => (string) ($case['case'] ?? 'без названия'),
            'expected' => $expectedValid,
            'actual' => $actualValid,
            'passed' => $expectedValid === $actualValid,
        ];
    }

    return $results;
}
