<?php
require 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action == 'shop') {
    foreach (['shop_name', 'shop_address', 'shop_phone'] as $key) {
      q(
        'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
        'ss',
        [$key, trim($_POST[$key] ?? '')]
      );
    }
    header('Location: receipt.php');
    exit;
  }

  if ($action == 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $eid = one('SELECT entry_id FROM receipts WHERE id = ?', 'i', [$id]);
    mysqli_begin_transaction($conn);
    if ($eid)
      q('DELETE FROM entries WHERE id = ?', 'i', [(int) $eid]);
    q('DELETE FROM receipts WHERE id = ?', 'i', [$id]);
    mysqli_commit($conn);
    header('Location: receipt.php');
    exit;
  }

  if ($action == 'create') {
    $date = $_POST['receipt_date'] ?? '';
    $cname = trim($_POST['customer_name'] ?? '');
    $cphone = trim($_POST['customer_phone'] ?? '');
    $names = (array) ($_POST['item_name'] ?? []);
    $qtys = (array) ($_POST['qty'] ?? []);
    $prices = (array) ($_POST['price'] ?? []);

    $items = [];
    $subtotal = 0;
    foreach ($names as $i => $n) {
      $n = trim($n);
      $qt = (float) ($qtys[$i] ?? 0);
      $pr = (float) ($prices[$i] ?? 0);
      if ($n === '' || $qt <= 0 || $pr < 0)
        continue;
      $items[] = [$n, $qt, $pr];
      $subtotal += $qt * $pr;
    }
    $subtotal = round($subtotal, 2);
    $discount = min(max(0, (float) ($_POST['discount'] ?? 0)), $subtotal);
    $total = $subtotal - $discount;
    $paid = min(max(0, (float) ($_POST['paid'] ?? 0)), $total);

    if (!valid_date($date) || !$items) {
      $error = $T['rc_err'];
    } else {
      mysqli_begin_transaction($conn);
      try {
        q('INSERT INTO receipts (receipt_date, customer_name, customer_phone, subtotal, discount, total, paid)
                   VALUES (?, ?, ?, ?, ?, ?, ?)', 'sssdddd', [$date, $cname, $cphone, $subtotal, $discount, $total, $paid]);
        $rid = mysqli_insert_id($conn);

        foreach ($items as $it) {
          q(
            'INSERT INTO receipt_items (receipt_id, name, qty, price) VALUES (?, ?, ?, ?)',
            'isdd',
            [$rid, $it[0], $it[1], $it[2]]
          );
        }

        if ($paid > 0) {
          $note = $T['rc_word'] . ' #' . $rid . ($cname != '' ? ' - ' . $cname : '');
          q(
            "INSERT INTO entries (entry_date, type, category, note, amount) VALUES (?, 'income', 'sales', ?, ?)",
            'ssd',
            [$date, $note, $paid]
          );
          q('UPDATE receipts SET entry_id = ? WHERE id = ?', 'ii', [mysqli_insert_id($conn), $rid]);
        }

        mysqli_commit($conn);
        header('Location: receipt_view.php?id=' . $rid);
        exit;
      } catch (Exception $ex) {
        mysqli_rollback($conn);
        $error = $T['rc_err_save'];
      }
    }
  }
}

$recent = q('SELECT * FROM receipts ORDER BY id DESC LIMIT 15');

$page_title = $T['nav_receipt'];
$active = 'receipt';
require 'header.php';
?>

<h1 class="report-title"><?= $T['rc_title'] ?></h1>
<p class="muted"><?= $T['rc_intro'] ?></p>

<section class="box">
  <h2><?= $T['rc_new'] ?></h2>
  <?php if ($error): ?>
    <p class="error"><?= e($error) ?></p><?php endif; ?>

  <form method="post" id="rform">
    <input type="hidden" name="action" value="create">

    <div class="grid-form top3">
      <label><?= $T['date'] ?> <input type="date" name="receipt_date" value="<?= date('Y-m-d') ?>" required></label>
      <label><?= $T['rc_cust'] ?> <input type="text" name="customer_name" maxlength="60"></label>
      <label><?= $T['rc_phone'] ?> <input type="tel" name="customer_phone" maxlength="20"></label>
    </div>

    <div class="table-scroll">
      <table class="items">
        <thead>
          <tr>
            <th><?= $T['rc_item'] ?></th>
            <th><?= $T['rc_qty'] ?></th>
            <th><?= $T['rc_price'] ?></th>
            <th class="r"><?= $T['total'] ?></th>
          </tr>
        </thead>
        <tbody id="rows">
          <?php for ($i = 0; $i < 4; $i++): ?>
            <tr>
              <td><input type="text" name="item_name[]" placeholder="<?= e($T['rc_item_ph']) ?>"></td>
              <td><input class="q" type="number" name="qty[]" min="0" step="any" value="1"></td>
              <td><input class="p" type="number" name="price[]" min="0" step="any"></td>
              <td class="r t">0</td>
            </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>
    <button type="button" class="btn small alt" id="addRow"><?= $T['rc_more'] ?></button>

    <div class="totals">
      <label><?= $T['rc_discount'] ?> <input type="number" id="discount" name="discount" min="0" step="any"
          value="0"></label>
      <label><?= $T['rc_paid'] ?> <input type="number" id="paid" name="paid" min="0" step="any" value="0"></label>
      <div class="sumbox">
        <div><?= $T['rc_total'] ?> <b id="totalTxt">0</b></div>
        <div><?= $T['rc_due'] ?> <b id="dueTxt" class="minus">0</b></div>
      </div>
    </div>

    <button type="submit" class="btn"><?= $T['rc_create'] ?></button>
  </form>
</section>

<section class="box">
  <h2><?= $T['rc_recent'] ?></h2>
  <?php if (mysqli_num_rows($recent) == 0): ?>
    <p class="muted"><?= $T['rc_none'] ?></p>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <tr>
          <th><?= $T['rc_no'] ?></th>
          <th><?= $T['date'] ?></th>
          <th><?= $T['rc_customer'] ?></th>
          <th class="r"><?= $T['total'] ?></th>
          <th class="r"><?= $T['rv_due'] ?></th>
          <th></th>
        </tr>
        <?php while ($r = mysqli_fetch_assoc($recent)):
          $due = $r['total'] - $r['paid']; ?>
          <tr>
            <td>#<?= bn(str_pad($r['id'], 4, '0', STR_PAD_LEFT)) ?></td>
            <td><?= showdate($r['receipt_date']) ?></td>
            <td><?= e($r['customer_name'] != '' ? $r['customer_name'] : '-') ?></td>
            <td class="r"><?= taka($r['total']) ?></td>
            <td class="r <?= $due > 0 ? 'minus' : '' ?>"><?= $due > 0 ? taka($due) : '-' ?></td>
            <td class="r actions">
              <a href="receipt_view.php?id=<?= (int) $r['id'] ?>"><?= $T['view'] ?></a>
              <form method="post" onsubmit="return confirm(<?= js($T['rc_confirm']) ?>)">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
  <h2><?= $T['rc_shop'] ?></h2>
  <form method="post" class="grid-form top3">
    <input type="hidden" name="action" value="shop">
    <label><?= $T['rc_shop_name'] ?> <input type="text" name="shop_name" value="<?= e(setting('shop_name')) ?>"
        required></label>
    <label><?= $T['rc_address'] ?> <input type="text" name="shop_address"
        value="<?= e(setting('shop_address')) ?>"></label>
    <label><?= $T['rc_phone_label'] ?> <input type="text" name="shop_phone"
        value="<?= e(setting('shop_phone')) ?>"></label>
    <button class="btn small"><?= $T['save'] ?></button>
  </form>
</section>

<script>
  var rows = document.getElementById('rows');
  var discount = document.getElementById('discount');
  var paid = document.getElementById('paid');
  var paidTouched = false;

  function fmt(n) { return (Math.round(n * 100) / 100).toString(); }

  function calc() {
    var sub = 0;
    var trs = rows.querySelectorAll('tr');
    for (var i = 0; i < trs.length; i++) {
      var q = parseFloat(trs[i].querySelector('.q').value) || 0;
      var p = parseFloat(trs[i].querySelector('.p').value) || 0;
      trs[i].querySelector('.t').textContent = fmt(q * p);
      sub += q * p;
    }
    var total = Math.max(sub - (parseFloat(discount.value) || 0), 0);
    if (!paidTouched) paid.value = fmt(total);
    var due = Math.max(total - (parseFloat(paid.value) || 0), 0);
    document.getElementById('totalTxt').textContent = fmt(total);
    document.getElementById('dueTxt').textContent = fmt(due);
  }

  document.getElementById('rform').addEventListener('input', function (e) {
    if (e.target === paid) paidTouched = true;
    calc();
  });

  document.getElementById('addRow').addEventListener('click', function () {
    var copy = rows.lastElementChild.cloneNode(true);
    var inputs = copy.querySelectorAll('input');
    for (var i = 0; i < inputs.length; i++) inputs[i].value = '';
    copy.querySelector('.q').value = 1;
    copy.querySelector('.t').textContent = '0';
    rows.appendChild(copy);
  });
  calc();
</script>

<?php require 'footer.php'; ?>