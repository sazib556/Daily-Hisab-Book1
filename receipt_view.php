<?php
require 'config.php';

$id = (int) ($_GET['id'] ?? 0);
$r = mysqli_fetch_assoc(q('SELECT * FROM receipts WHERE id = ?', 'i', [$id]));

if (!$r) {
  http_response_code(404);
  exit($T['rv_notfound'] . ' <a href="receipt.php">' . $T['rv_back'] . '</a>');
}

$items = q('SELECT * FROM receipt_items WHERE receipt_id = ? ORDER BY id', 'i', [$id]);
$due = $r['total'] - $r['paid'];

$page_title = $T['rc_word'] . ' #' . $id;
$active = 'receipt';
require 'header.php';
?>

<div class="datebar no-print">
  <button class="btn small" onclick="window.print()"><?= $T['rv_print'] ?></button>
  <a class="btn small alt" href="receipt.php"><?= $T['rc_new'] ?></a>
</div>

<article class="receipt">
  <header class="rc-head">
    <h2><?= e(setting('shop_name')) ?></h2>
    <?php if (setting('shop_address') != ''): ?>
      <div><?= e(setting('shop_address')) ?></div><?php endif; ?>
    <?php if (setting('shop_phone') != ''): ?>
      <div><?= $T['rv_phone'] ?>   <?= e(setting('shop_phone')) ?></div><?php endif; ?>
  </header>

  <div class="rc-meta">
    <span><?= $T['rv_no'] ?> <b><?= bn(str_pad($r['id'], 4, '0', STR_PAD_LEFT)) ?></b></span>
    <span><?= $T['rv_date'] ?> <b><?= showdate($r['receipt_date']) ?></b></span>
  </div>
  <?php if ($r['customer_name'] != '' || $r['customer_phone'] != ''): ?>
    <div class="rc-cust">
      <?= $T['rv_customer'] ?> <b><?= e($r['customer_name'] != '' ? $r['customer_name'] : '-') ?></b>
      <?php if ($r['customer_phone'] != ''): ?> (<?= e($r['customer_phone']) ?>)<?php endif; ?>
    </div>
  <?php endif; ?>

  <table class="rc-table">
    <tr>
      <th><?= $T['rc_item'] ?></th>
      <th class="r"><?= $T['rc_qty'] ?></th>
      <th class="r"><?= $T['rc_price'] ?></th>
      <th class="r"><?= $T['total'] ?></th>
    </tr>
    <?php while ($it = mysqli_fetch_assoc($items)): ?>
      <tr>
        <td><?= e($it['name']) ?></td>
        <td class="r"><?= numshow($it['qty']) ?></td>
        <td class="r"><?= taka($it['price']) ?></td>
        <td class="r"><?= taka($it['qty'] * $it['price']) ?></td>
      </tr>
    <?php endwhile; ?>
  </table>

  <div class="rc-sum">
    <div><span><?= $T['rv_subtotal'] ?></span><span><?= taka($r['subtotal']) ?></span></div>
    <?php if ($r['discount'] > 0): ?>
      <div><span><?= $T['rv_discount'] ?></span><span>-<?= taka($r['discount']) ?></span></div>
    <?php endif; ?>
    <div class="big"><span><?= $T['rv_grand'] ?></span><span><?= taka($r['total']) ?></span></div>
    <div><span><?= $T['rv_received'] ?></span><span><?= taka($r['paid']) ?></span></div>
    <?php if ($due > 0): ?>
      <div class="due"><span><?= $T['rv_due'] ?></span><span><?= taka($due) ?></span></div>
    <?php endif; ?>
  </div>

  <footer class="rc-foot"><?= $T['rv_thanks'] ?></footer>
</article>

<?php require 'footer.php'; ?>