<?php
session_start();
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib/helpers.php';

// Init CSRF
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$CSRF = $_SESSION['csrf'];

// Handle POST centrally
$flash = null; $flash_err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require __DIR__.'/lib/actions.php';
}

// Router
$page = $_GET['page'] ?? 'results';
$valid = ['results','suites','manage', 'upload','toolrelease_form', 'flags'];
if (!in_array($page, $valid, true)) { $page = 'results'; }

// Shared lookups
$all_suites = suites($pdo);
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Suites DB</title>
  <style>
    body { font-family: system-ui, -apple-system, Arial, sans-serif; margin: 24px; }
    h1 { margin: 0 0 12px; }
    .nav { display:flex; gap:10px; margin: 12px 0 24px; flex-wrap: wrap; }
    .nav a { padding:8px 12px; border:1px solid #ddd; border-radius:8px; text-decoration:none; color:#222; }
    .nav a.active { background:#0d6efd; color:#fff; border-color:#0d6efd; }
    table { border-collapse: collapse; width: 100%; font-size: 14px; }
    th, td { border: 1px solid #ddd; padding: 6px 10px; vertical-align: top; }
    th { background: #f4f4f4; text-align: left; }
    tr:hover { background: #fafafa; }
    .mono { font-family: ui-monospace, Menlo, Consolas, monospace; }
    a { color: #0645AD; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px,1fr)); gap:16px; }
    .card { border:1px solid #ddd; border-radius:12px; padding:12px; }
    .muted { color:#666; font-size:12px; }
    form.inline { display:inline; }
    input[type=text], input[type=date], input[type=number], textarea, select { width:100%; box-sizing:border-box; padding:8px; border:1px solid #ccc; border-radius:8px; }
    textarea { min-height: 80px; }
    .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .row3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; }
    .btn { padding:8px 12px; border:1px solid #0d6efd; background:#0d6efd; color:#fff; border-radius:8px; cursor:pointer; }
    .btn.alt { background:#eee; color:#222; border-color:#ccc; }
    .flash { padding:10px 12px; border-radius:8px; margin-bottom:12px; }
    .flash.ok { background:#e7f5ff; border:1px solid #91d5ff; }
    .flash.err { background:#fff1f0; border:1px solid #ffa39e; }
    details>summary { cursor:pointer; font-weight:600; margin-bottom:8px; }
  </style>
</head>
<body>

<h1>Benchmarker Experimental Results</h1>
<div class="nav">
  <?php
    foreach ([
      'results'    => 'Results',
      'suites'     => 'Suites',
      // 'flags'      => 'Flag Reviews',
      // 'manage'     => 'Add / Edit',
      // 'upload' => 'Upload Run',
    ] as $k=>$label):
      $cls = $page===$k ? 'active' : '';
      echo '<a class="'.$cls.'" href="index.php?page='.$k.'">'.h($label).'</a>';
    endforeach;
  ?>
</div>

<?php if($flash): ?><div class="flash ok"><?=h($flash)?></div><?php endif; ?>
<?php if($flash_err): ?><div class="flash err"><?=h($flash_err)?></div><?php endif; ?>

<?php require __DIR__.'/pages/'.$page.'.php'; ?>

</body>
</html>
