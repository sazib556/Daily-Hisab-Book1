<!DOCTYPE html>
<html lang="<?= e($lang) ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title) ?> - Daily Hisab Book</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;500;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>

<body>
  <header class="topbar no-print">
    <div class="wrap bar">
      <strong class="brand">📒 Daily Hisab Book</strong>
      <nav>
        <a href="index.php" class="<?= $active == 'khata' ? 'on' : '' ?>"><?= $T['nav_khata'] ?></a>
        <a href="receipt.php" class="<?= $active == 'receipt' ? 'on' : '' ?>"><?= $T['nav_receipt'] ?></a>
        <a href="expense.php" class="<?= $active == 'expense' ? 'on' : '' ?>"><?= $T['nav_expense'] ?></a>
        <a href="report.php" class="<?= $active == 'report' ? 'on' : '' ?>"><?= $T['nav_report'] ?></a>
        <?php foreach ($LANG as $code => $L): ?>
          <?php if ($code != $lang):
            $qs = $_GET;
            $qs['lang'] = $code; ?>
            <a class="lang-btn" href="?<?= e(http_build_query($qs)) ?>">🌐 <?= e($L['name']) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    </div>
  </header>
  <main class="wrap">