<?php
require 'config.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
if (!valid_date($from) || !valid_date($to)) {
    http_response_code(400);
    exit('Invalid date');
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="hisab-' . $from . '-to-' . $to . '.csv"');

echo "\xEF\xBB\xBF";   // BOM: eta na dile Excel-e Bangla lekha ulta-palta dekhay
$out = fopen('php://output', 'w');

fputcsv($out, [$T['date'], $T['income'], $T['expense'], $T['profit'] . '/' . $T['loss']], ',', '"', '');
$days = q("SELECT entry_date,
             SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END) AS inc,
             SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS exp
           FROM entries WHERE entry_date BETWEEN ? AND ?
           GROUP BY entry_date ORDER BY entry_date", 'ss', [$from, $to]);
while ($r = mysqli_fetch_assoc($days)) {
    fputcsv($out, [$r['entry_date'], $r['inc'], $r['exp'], $r['inc'] - $r['exp']], ',', '"', '');
}
$in = total('income', $from, $to);
$ex = total('expense', $from, $to);
fputcsv($out, [$T['total'], $in, $ex, $in - $ex], ',', '"', '');

fputcsv($out, [], ',', '"', '');
fputcsv($out, [$T['date'], $T['exp_type'], $T['category'], $T['note'], $T['amount']], ',', '"', '');
$rows = q('SELECT * FROM entries WHERE entry_date BETWEEN ? AND ? ORDER BY entry_date, id', 'ss', [$from, $to]);
while ($r = mysqli_fetch_assoc($rows)) {
    fputcsv($out, [
        $r['entry_date'],
        $r['type'] == 'income' ? $T['income'] : $T['expense'],
        cat_name($r['category']),
        $r['note'],
        $r['amount']
    ], ',', '"', '');
}
fclose($out);
