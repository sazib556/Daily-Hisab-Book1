<?php
/* Main configuration and shared helpers for the Hisab Core app. */
date_default_timezone_set('Asia/Dhaka');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = mysqli_connect('localhost', 'root', '', 'hisab_book');
} catch (Exception $ex) {
    die('Database connect hoy ni. XAMPP-e MySQL chalu ache? setup.sql import koreco? (' . $ex->getMessage() . ')');
}
mysqli_set_charset($conn, 'utf8mb4');

require __DIR__ . '/lang.php';

if (isset($_GET['lang']) && is_string($_GET['lang']) && isset($LANG[$_GET['lang']])) {
    setcookie('lang', $_GET['lang'], time() + 31536000, '/');
    $query = $_GET;
    unset($query['lang']);
    header('Location: ' . basename($_SERVER['PHP_SELF']) . ($query ? '?' . http_build_query($query) : ''));
    exit;
}
$lang = (isset($_COOKIE['lang']) && is_string($_COOKIE['lang']) && isset($LANG[$_COOKIE['lang']])) ? $_COOKIE['lang'] : 'bn';
$T = $LANG[$lang];

$INCOME_CATS = ['sales', 'due_collected', 'other_income'];
$EXPENSE_CATS = ['rent', 'electricity', 'stock', 'salary', 'other_expense'];

function q($sql, $types = '', $params = [])
{
    global $conn;
    $st = mysqli_prepare($conn, $sql);
    if ($types !== '')
        mysqli_stmt_bind_param($st, $types, ...$params);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    return $res ? $res : $st;
}

function one($sql, $types = '', $params = [])
{
    $row = mysqli_fetch_row(q($sql, $types, $params));
    return $row ? $row[0] : null;
}

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function js($s)
{
    return e(json_encode($s, JSON_UNESCAPED_UNICODE));
}

function bn($s)
{
    global $lang;
    if ($lang !== 'bn')
        return (string) $s;
    return strtr((string) $s, ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯']);
}

function tf($key, ...$args)
{
    global $T;
    return $args ? vsprintf($T[$key], $args) : $T[$key];
}

function cat_name($c)
{
    global $T;
    return $T['cat_' . $c] ?? $c;
}

function taka($n)
{
    $n = (float) $n;
    return '৳' . bn(floor($n) == $n ? number_format($n, 0) : number_format($n, 2));
}

function numshow($n)
{
    return bn(rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'));
}

function showdate($date)
{
    return bn(date('d-m-Y', strtotime($date)));
}

function valid_date($s)
{
    return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)
        && checkdate((int) substr($s, 5, 2), (int) substr($s, 8, 2), (int) substr($s, 0, 4));
}

function total($type, $from = '1000-01-01', $to = '9999-12-31')
{
    return (float) one(
        'SELECT COALESCE(SUM(amount), 0) FROM entries WHERE type = ? AND entry_date BETWEEN ? AND ?',
        'sss',
        [$type, $from, $to]
    );
}

function setting($name)
{
    return (string) one('SELECT value FROM settings WHERE name = ?', 's', [$name]);
}

function cash_in_hand($upto)
{
    return (float) setting('opening_cash') + total('income', '1000-01-01', $upto) - total('expense', '1000-01-01', $upto);
}
