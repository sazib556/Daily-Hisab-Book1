<?php
require 'config.php';

$from = valid_date($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = valid_date($_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');

$days = q("SELECT entry_date,
             SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END) AS inc,
             SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS exp
           FROM entries WHERE entry_date BETWEEN ? AND ?
           GROUP BY entry_date ORDER BY entry_date", 'ss', [$from, $to]);

$by_cat = q("SELECT category, SUM(amount) AS total FROM entries
             WHERE type = 'expense' AND entry_date BETWEEN ? AND ?
             GROUP BY category ORDER BY total DESC", 'ss', [$from, $to]);

$total_in = total('income', $from, $to);
$total_out = total('expense', $from, $to);
$net = $total_in - $total_out;

$page_title = $T['nav_report'];
$active = 'report';
require 'header.php';
?>

<form method="get" class="datebar no-print">
  <label><?= $T['from'] ?> <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label><?= $T['to'] ?> <input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn small"><?= $T['view'] ?></button>
  <span class="spacer"></span>
  <a class="btn small alt" href="export.php?from=<?= e($from) ?>&to=<?= e($to) ?>"><?= $T['rep_excel'] ?></a>
  <button type="button" class="btn small alt" onclick="window.print()"><?= $T['rep_pdf'] ?></button>
</form>

<h1 class="report-title"><?= $T['rep_title'] ?></h1>
<p class="muted"><?= tf('range', showdate($from), showdate($to)) ?></p>

<section class="stats">
  <div class="stat in"><span><?= $T['rep_total_income'] ?></span><b><?= taka($total_in) ?></b></div>
  <div class="stat out"><span><?= $T['rep_total_expense'] ?></span><b><?= taka($total_out) ?></b></div>
  <div class="stat <?= $net >= 0 ? 'in' : 'out' ?>">
    <span><?= $net >= 0 ? $T['rep_total_profit'] : $T['rep_total_loss'] ?></span><b><?= taka(abs($net)) ?></b>
  </div>
  <div class="stat cash"><span><?= tf('rep_cash_at', showdate($to)) ?></span><b><?= taka(cash_in_hand($to)) ?></b></div>
</section>

<section class="box">
  <h2><?= $T['rep_by_day'] ?></h2>
  <?php if (mysqli_num_rows($days) == 0): ?>
    <p class="muted"><?= $T['rep_none'] ?></p>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <tr>
          <th><?= $T['date'] ?></th>
          <th class="r"><?= $T['income'] ?></th>
          <th class="r"><?= $T['expense'] ?></th>
          <th class="r"><?= $T['rep_profit_loss'] ?></th>
        </tr>
        <?php while ($d = mysqli_fetch_assoc($days)):
          $p = $d['inc'] - $d['exp']; ?>
          <tr>
            <td><?= showdate($d['entry_date']) ?></td>
            <td class="r"><?= taka($d['inc']) ?></td>
            <td class="r"><?= taka($d['exp']) ?></td>
            <td class="r <?= $p >= 0 ? 'plus' : 'minus' ?>"><?= $p >= 0 ? $T['profit'] : $T['loss'] ?>     <?= taka(abs($p)) ?>
            </td>
          </tr>
        <?php endwhile; ?>
        <tr class="sum">
          <td><?= $T['total'] ?></td>
          <td class="r"><?= taka($total_in) ?></td>
          <td class="r"><?= taka($total_out) ?></td>
          <td class="r <?= $net >= 0 ? 'plus' : 'minus' ?>"><?= $net >= 0 ? $T['profit'] : $T['loss'] ?>
            <?= taka(abs($net)) ?>
          </td>
        </tr>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if (mysqli_num_rows($by_cat) > 0): ?>
  <section class="box">
    <h2><?= $T['rep_by_cat'] ?></h2>
    <div class="table-scroll">
      <table>
        <tr>
          <th><?= $T['rep_cat'] ?></th>
          <th class="r"><?= $T['amount'] ?></th>
        </tr>
        <?php while ($c = mysqli_fetch_assoc($by_cat)): ?>
          <tr>
            <td><?= e(cat_name($c['category'])) ?></td>
            <td class="r"><?= taka($c['total']) ?></td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php require 'footer.php'; ?>