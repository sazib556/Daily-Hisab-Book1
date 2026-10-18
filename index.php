<?php
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action == 'add') {
    $date = $_POST['entry_date'] ?? '';
    $type = $_POST['type'] ?? '';
    $cat = $_POST['category'] ?? '';
    $amount = (float) ($_POST['amount'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    $allowed = $type == 'income' ? $INCOME_CATS : ($type == 'expense' ? $EXPENSE_CATS : []);

    if (!valid_date($date) || !in_array($cat, $allowed, true) || $amount <= 0) {
      $error = $T['err_entry'];
    } else {
      q(
        'INSERT INTO entries (entry_date, type, category, note, amount) VALUES (?, ?, ?, ?, ?)',
        'ssssd',
        [$date, $type, $cat, $note, $amount]
      );
      header('Location: index.php?date=' . $date);
      exit;
    }
  }

  if ($action == 'delete') {
    q('DELETE FROM entries WHERE id = ?', 'i', [(int) ($_POST['id'] ?? 0)]);
    header('Location: index.php?date=' . urlencode($_POST['date'] ?? ''));
    exit;
  }

  if ($action == 'opening') {
    q("UPDATE settings SET value = ? WHERE name = 'opening_cash'", 's', [(string) (float) ($_POST['opening_cash'] ?? 0)]);
    header('Location: index.php?date=' . urlencode($_POST['date'] ?? ''));
    exit;
  }
}

$date = valid_date($_GET['date'] ?? '') ? $_GET['date'] : date('Y-m-d');

$income = total('income', $date, $date);
$expense = total('expense', $date, $date);
$profit = $income - $expense;
$cash = cash_in_hand($date);

$month_from = date('Y-m-01', strtotime($date));
$avg_expense = total('expense', $month_from, $date) / (int) date('j', strtotime($date));

$entries = q('SELECT * FROM entries WHERE entry_date = ? ORDER BY id DESC', 's', [$date]);

$page_title = $T['nav_khata'];
$active = 'khata';
require 'header.php';
?>

<form method="get" class="datebar">
  <label><?= $T['date'] ?>:
    <input type="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
  </label>
  <span class="muted"><?= tf('idx_day_title', showdate($date)) ?></span>
</form>

<section class="stats">
  <div class="stat in"><span><?= $T['income'] ?></span><b><?= taka($income) ?></b></div>
  <div class="stat out"><span><?= $T['expense'] ?></span><b><?= taka($expense) ?></b></div>
  <div class="stat <?= $profit >= 0 ? 'in' : 'out' ?>">
    <span><?= $profit >= 0 ? $T['profit'] : $T['loss'] ?></span><b><?= taka(abs($profit)) ?></b>
  </div>
  <div class="stat cash"><span><?= $T['cash'] ?></span><b><?= taka($cash) ?></b></div>
</section>

<p class="avgline">
  <?= $T['avg_label'] ?> <b><?= taka($avg_expense) ?></b>
  <?php if ($expense > 0 && $avg_expense > 0): ?>
    <?php if ($expense > $avg_expense): ?>
      <?= tf('avg_more', '<b class="minus">' . taka($expense - $avg_expense) . '</b>') ?>
    <?php else: ?>
      <?= tf('avg_less', '<b class="plus">' . taka($avg_expense - $expense) . '</b>') ?>
    <?php endif; ?>
  <?php endif; ?>
  <a href="expense.php?month=<?= e(date('Y-m', strtotime($date))) ?>"><?= $T['avg_detail'] ?></a>
</p>

<section class="box">
  <h2><?= $T['form_new'] ?></h2>
  <?php if ($error): ?>
    <p class="error"><?= e($error) ?></p><?php endif; ?>
  <form method="post" class="grid-form">
    <input type="hidden" name="action" value="add">

    <div class="typepick full">
      <label><input type="radio" name="type" value="income" checked><span
          class="in"><?= $T['type_income'] ?></span></label>
      <label><input type="radio" name="type" value="expense"><span class="out"><?= $T['type_expense'] ?></span></label>
    </div>

    <label><?= $T['date'] ?>
      <input type="date" name="entry_date" value="<?= e($date) ?>" required>
    </label>

    <label><?= $T['category'] ?>
      <select name="category" id="cat" required>
        <?php foreach ($INCOME_CATS as $c): ?>
          <option value="<?= $c ?>" data-type="income"><?= e(cat_name($c)) ?></option><?php endforeach; ?>
        <?php foreach ($EXPENSE_CATS as $c): ?>
          <option value="<?= $c ?>" data-type="expense"><?= e(cat_name($c)) ?></option><?php endforeach; ?>
      </select>
    </label>

    <label><?= $T['amount'] ?>
      <input type="number" name="amount" min="0.01" step="0.01" placeholder="0" required>
    </label>

    <label class="wide"><?= $T['note_opt'] ?>
      <input type="text" name="note" maxlength="100" placeholder="<?= e($T['ph_item']) ?>">
    </label>

    <button type="submit" class="btn"><?= $T['add'] ?></button>
  </form>
</section>

<section class="box">
  <h2><?= $T['list_title'] ?></h2>
  <?php if (mysqli_num_rows($entries) == 0): ?>
    <p class="muted"><?= $T['list_empty'] ?></p>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <tr>
          <th><?= $T['category'] ?></th>
          <th><?= $T['note'] ?></th>
          <th class="r"><?= $T['amount'] ?></th>
          <th class="no-print"></th>
        </tr>
        <?php while ($row = mysqli_fetch_assoc($entries)): ?>
          <tr>
            <td><?= e(cat_name($row['category'])) ?></td>
            <td><?= e($row['note']) ?></td>
            <td class="r <?= $row['type'] == 'income' ? 'plus' : 'minus' ?>">
              <?= $row['type'] == 'income' ? '+' : '-' ?>    <?= taka($row['amount']) ?>
            </td>
            <td class="r">
              <form method="post" onsubmit="return confirm(<?= js($T['confirm_delete_entry']) ?>)">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <button class="link-del"><?= $T['delete'] ?></button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="box">
  <h2><?= $T['open_title'] ?></h2>
  <form method="post" class="inline-form">
    <input type="hidden" name="action" value="opening">
    <input type="hidden" name="date" value="<?= e($date) ?>">
    <input type="number" name="opening_cash" min="0" step="0.01" value="<?= e(setting('opening_cash')) ?>">
    <button class="btn small"><?= $T['save'] ?></button>
  </form>
  <p class="muted"><?= $T['open_note'] ?></p>
</section>

<script>
  var sel = document.getElementById('cat');
  function filterCats() {
    var t = document.querySelector('input[name=type]:checked').value;
    var first = null;
    for (var i = 0; i < sel.options.length; i++) {
      var o = sel.options[i];
      var show = o.getAttribute('data-type') === t;
      o.hidden = !show;
      o.disabled = !show;
      if (show && !first) first = o;
    }
    if (sel.options[sel.selectedIndex].getAttribute('data-type') !== t) sel.value = first.value;
  }
  var radios = document.querySelectorAll('input[name=type]');
  for (var i = 0; i < radios.length; i++) radios[i].addEventListener('change', filterCats);
  filterCats();
</script>

<?php require 'footer.php'; ?>