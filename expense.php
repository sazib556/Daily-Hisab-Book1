<?php
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action == 'add_expense') {
    $date = $_POST['entry_date'] ?? '';
    $cat = $_POST['category'] ?? '';
    $amount = (float) ($_POST['amount'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if (!valid_date($date) || !in_array($cat, $EXPENSE_CATS, true) || $amount <= 0) {
      $error = $T['exp_err'];
    } else {
      q(
        "INSERT INTO entries (entry_date, type, category, note, amount) VALUES (?, 'expense', ?, ?, ?)",
        'sssd',
        [$date, $cat, $note, $amount]
      );
      header('Location: expense.php?month=' . substr($date, 0, 7));
      exit;
    }
  }

  if ($action == 'delete') {
    q("DELETE FROM entries WHERE id = ? AND type = 'expense'", 'i', [(int) ($_POST['id'] ?? 0)]);
    header('Location: expense.php?month=' . urlencode($_POST['month'] ?? ''));
    exit;
  }
}

$month = $_GET['month'] ?? date('Y-m');
if (!is_string($month) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month))
  $month = date('Y-m');

$from = $month . '-01';
$last = date('Y-m-t', strtotime($from));
$today = date('Y-m-d');
$to = ($today >= $from && $today < $last) ? $today : $last;

$daily = [];
for ($day = $from; $day <= $to; $day = date('Y-m-d', strtotime("$day +1 day"))) {
  $daily[$day] = 0;
}
$res = q("SELECT entry_date, SUM(amount) AS s FROM entries
          WHERE type = 'expense' AND entry_date BETWEEN ? AND ? GROUP BY entry_date", 'ss', [$from, $to]);
while ($r = mysqli_fetch_assoc($res))
  $daily[$r['entry_date']] = (float) $r['s'];

$day_count = count($daily);
$total = array_sum($daily);
$average = $day_count ? $total / $day_count : 0;
$max = $daily ? max($daily) : 0;
$max_day = $max > 0 ? array_search($max, $daily) : null;
$zero_days = count(array_filter($daily, function ($v) {
  return $v == 0; }));

$by_cat = q("SELECT category, SUM(amount) AS total FROM entries
               WHERE type = 'expense' AND entry_date BETWEEN ? AND ? GROUP BY category ORDER BY total DESC",
  'ss',
  [$from, $to]
);
$expenses = q("SELECT * FROM entries WHERE type = 'expense' AND entry_date BETWEEN ? AND ?
               ORDER BY entry_date DESC, id DESC", 'ss', [$from, $last]);

$page_title = $T['nav_expense'];
$active = 'expense';
require 'header.php';
?>

<h1 class="report-title"><?= $T['exp_title'] ?></h1>

<section class="box">
  <h2><?= $T['exp_add_title'] ?></h2>
  <?php if ($error): ?>
    <p class="error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="grid-form">
    <input type="hidden" name="action" value="add_expense">
    <label><?= $T['date'] ?>
      <input type="date" name="entry_date" value="<?= date('Y-m-d') ?>" required>
    </label>
    <label><?= $T['exp_what'] ?>
      <select name="category" required>
        <?php foreach ($EXPENSE_CATS as $c): ?>
          <option value="<?= $c ?>"><?= e(cat_name($c)) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label><?= $T['exp_how_much'] ?>
      <input type="number" name="amount" min="0.01" step="0.01" placeholder="0" required>
    </label>
    <label class="wide"><?= $T['note_opt'] ?>
      <input type="text" name="note" maxlength="100" placeholder="<?= e($T['exp_ph']) ?>">
    </label>
    <button type="submit" class="btn out-btn"><?= $T['exp_add_btn'] ?></button>
  </form>
</section>

<form method="get" class="datebar">
  <label><?= $T['exp_month'] ?>
    <input type="month" name="month" value="<?= e($month) ?>" onchange="this.form.submit()">
  </label>
  <span class="muted"><?= tf('range', showdate($from), showdate($to)) ?> (<?= tf('days', bn($day_count)) ?>)</span>
</form>

<section class="stats">
  <div class="stat out"><span><?= $T['rep_total_expense'] ?></span><b><?= taka($total) ?></b></div>
  <div class="stat cash"><span><?= $T['exp_avg'] ?></span><b><?= taka($average) ?></b></div>
  <div class="stat out">
    <span><?= $T['exp_max'] ?></span><b><?= taka($max) ?></b>
    <?php if ($max_day): ?><small class="muted"><?= showdate($max_day) ?></small><?php endif; ?>
  </div>
  <div class="stat in"><span><?= $T['exp_zero'] ?></span><b><?= tf('days', bn($zero_days)) ?></b></div>
</section>

<?php if (isset($daily[$today])): ?>
  <p class="avgline">
    <?= $T['exp_today'] ?> <b><?= taka($daily[$today]) ?></b> -
    <?php if ($daily[$today] > $average): ?>
      <?= tf('exp_today_more', '<b class="minus">' . taka($daily[$today] - $average) . '</b>') ?>
    <?php else: ?>
      <?= tf('exp_today_less', '<b class="plus">' . taka($average - $daily[$today]) . '</b>') ?>
    <?php endif; ?>
  </p>
<?php endif; ?>

<section class="box">
  <h2><?= $T['exp_by_day'] ?></h2>
  <p class="muted"><?= $T['exp_hint'] ?></p>
  <div class="table-scroll">
    <table class="daytable">
      <tr>
        <th><?= $T['date'] ?></th>
        <th><?= $T['expense'] ?></th>
        <th class="r"><?= $T['amount'] ?></th>
      </tr>
      <?php foreach (array_reverse($daily, true) as $day => $amount):
        $width = $max > 0 ? round($amount / $max * 100, 1) : 0; ?>
        <tr>
          <td><a href="index.php?date=<?= e($day) ?>"><?= showdate($day) ?></a></td>
          <td class="barcell">
            <div class="bar-track">
              <div class="bar-fill <?= $amount > $average ? 'over' : '' ?>" style="width: <?= $width ?>%"></div>
            </div>
          </td>
          <td class="r <?= $amount == 0 ? 'muted' : '' ?>"><?= taka($amount) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</section>

<?php if ($total > 0): ?>
  <section class="box">
    <h2><?= $T['rep_by_cat'] ?></h2>
    <div class="table-scroll">
      <table>
        <tr>
          <th><?= $T['rep_cat'] ?></th>
          <th class="r"><?= $T['amount'] ?></th>
          <th class="r"><?= $T['exp_share'] ?></th>
        </tr>
        <?php while ($c = mysqli_fetch_assoc($by_cat)): ?>
          <tr>
            <td><?= e(cat_name($c['category'])) ?></td>
            <td class="r"><?= taka($c['total']) ?></td>
            <td class="r"><?= bn(round($c['total'] / $total * 100)) ?>%</td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </section>
<?php else: ?>
  <section class="box">
    <p class="muted"><?= $T['exp_none'] ?></p>
  </section>
<?php endif; ?>

<?php if (mysqli_num_rows($expenses) > 0): ?>
  <section class="box">
    <h2><?= $T['exp_list'] ?></h2>
    <div class="table-scroll">
      <table>
        <tr>
          <th><?= $T['date'] ?></th>
          <th><?= $T['rep_cat'] ?></th>
          <th><?= $T['note'] ?></th>
          <th class="r"><?= $T['amount'] ?></th>
          <th></th>
        </tr>
        <?php while ($x = mysqli_fetch_assoc($expenses)): ?>
          <tr>
            <td><?= showdate($x['entry_date']) ?></td>
            <td><?= e(cat_name($x['category'])) ?></td>
            <td><?= e($x['note']) ?></td>
            <td class="r minus"><?= taka($x['amount']) ?></td>
            <td class="r">
              <form method="post" onsubmit="return confirm(<?= js($T['exp_confirm']) ?>)">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $x['id'] ?>">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <button class="link-del"><?= $T['delete'] ?></button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </section>
<?php endif; ?>

<?php require 'footer.php'; ?>