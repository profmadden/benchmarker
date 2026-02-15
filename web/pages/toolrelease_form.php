<?php
// pages/toolrelease_form.php
if (!isset($pdo)) {
  require_once __DIR__ . '/../config.php';
  require_once __DIR__ . '/../lib/helpers.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  // Came from upload.php (GET params)
  $tool            = strtolower(trim($_GET['tool'] ?? ''));
  $suite           = strtolower(trim($_GET['suite'] ?? ''));
  $suite_variation = strtolower(trim($_GET['suite_variation'] ?? ''));
  $bench           = strtolower(trim($_GET['benchmark'] ?? ''));
  $fom             = (float)($_GET['primary_fom'] ?? 0);
  $fom1_label      = strtolower(trim($_GET['fom1_label'] ?? ''));
  $artifact        = $_GET['artifact_url'] ?? '';
  $sha256          = $_GET['sha256'] ?? '';
  $tool_ver        = strtolower(trim($_GET['tool_version'] ?? ''));
  $notes           = trim($_GET['notes'] ?? '');
  $tool_image_tmp  = trim($_GET['tool_image_tmp'] ?? ''); // relative path, if any

  // Ensure/create tool (compare in lowercase)
  $sel = $pdo->prepare("SELECT tool_id FROM tool WHERE LOWER(name)=? LIMIT 1");
  $sel->execute([$tool]);
  $tool_id = (int)($sel->fetchColumn() ?: 0);
  if (!$tool_id) {
    $ins = $pdo->prepare("INSERT INTO tool (name) VALUES (?)");
    $ins->execute([$tool]); // store lowercased
    $tool_id = (int)$pdo->lastInsertId();
  }

} else {
  // Final submit: save tool + release + suite/benchmark + result
  check_csrf();
  $tool_id         = (int)$_POST['tool_id'];
  $tool            = strtolower(trim($_POST['tool']));
  $suite           = strtolower(trim($_POST['suite']));
  $suite_variation = strtolower(trim($_POST['suite_variation'] ?? ''));
  $bench           = strtolower(trim($_POST['benchmark']));
  $fom             = (float)$_POST['primary_fom'];
  $fom1_label      = strtolower(trim($_POST['fom1_label']));
  $artifact        = $_POST['artifact_url'];
  $sha256          = $_POST['sha256'];
  $tool_ver        = strtolower(trim($_POST['tool_version']));
  $notes           = trim($_POST['notes']);
  $tool_image_tmp  = trim($_POST['tool_image_tmp'] ?? '');

  $release_name = trim($_POST['release_name']);        // display text
  $release_ver  = strtolower(trim($_POST['release_version'])); // normalize for compare
  $release_date = trim($_POST['release_date']);
  $release_url  = trim($_POST['release_url']);
  $desc         = trim($_POST['text_description']);

  // --- update tool with shared fields (URL/description) ---
  $pdo->prepare("UPDATE tool SET URL=COALESCE(?, URL), text_description=COALESCE(?, text_description) WHERE tool_id=?")
      ->execute([$release_url ?: null, $desc ?: null, $tool_id]);

  // --- tool image (priority: new upload > temp image from step 1) ---
  $image_id = null;
  if (!empty($_FILES['tool_image']['tmp_name']) && is_uploaded_file($_FILES['tool_image']['tmp_name'])) {
    $imgData = file_get_contents($_FILES['tool_image']['tmp_name']);
    $stmt = $pdo->prepare("INSERT INTO images (data) VALUES (?)");
    $stmt->bindParam(1, $imgData, PDO::PARAM_LOB);
    $stmt->execute();
    $image_id = (int)$pdo->lastInsertId();
  } elseif ($tool_image_tmp !== '') {
    $baseDir  = realpath(__DIR__ . '/../') ?: dirname(__DIR__);
    $absPath  = $baseDir . '/' . ltrim($tool_image_tmp, '/');
    if (is_file($absPath)) {
      $imgData = file_get_contents($absPath);
      $stmt = $pdo->prepare("INSERT INTO images (data) VALUES (?)");
      $stmt->bindParam(1, $imgData, PDO::PARAM_LOB);
      $stmt->execute();
      $image_id = (int)$pdo->lastInsertId();
      @unlink($absPath); // cleanup temp
    }
  }
  if ($image_id) {
    $pdo->prepare("UPDATE tool SET image_id=? WHERE tool_id=?")->execute([$image_id, $tool_id]);
  }

  // --- insert or reuse toolrelease (by tool_id + lower(version)) ---
  $sel = $pdo->prepare("SELECT tool_release_id FROM toolrelease WHERE tool_id=? AND LOWER(tool_release_version)=? LIMIT 1");
  $sel->execute([$tool_id, $release_ver]);
  $tool_release_id = (int)($sel->fetchColumn() ?: 0);
  if (!$tool_release_id) {
    $ins = $pdo->prepare("INSERT INTO toolrelease
      (tool_id, name, URL, date, text_description, tool_release_version)
      VALUES (?,?,?,?,?,?)");
    $ins->execute([$tool_id, ($release_name ?: $tool), $release_url ?: null, $release_date, $desc ?: null, $release_ver]);
    $tool_release_id = (int)$pdo->lastInsertId();
  }

  // --- ensure/create benchmarksuite (name + variation, lowercase compare) ---
  $sel = $pdo->prepare(
    "SELECT suite_id, fom1_label FROM benchmarksuite
     WHERE LOWER(name)=? AND COALESCE(LOWER(variation),'')=COALESCE(?, '')
     LIMIT 1");
  $sel->execute([$suite, $suite_variation]);
  $row = $sel->fetch(PDO::FETCH_ASSOC);
  $suite_id = $row ? (int)$row['suite_id'] : 0;

  if (!$suite_id) {
    $ins = $pdo->prepare("INSERT INTO benchmarksuite (name, variation, fom1_label, date)
                          VALUES (?,?,?,CURRENT_DATE())");
    $ins->execute([$suite, $suite_variation, $fom1_label ?: null]);
    $suite_id = (int)$pdo->lastInsertId();
  } else {
    if ($row && empty($row['fom1_label']) && $fom1_label !== '') {
      $pdo->prepare("UPDATE benchmarksuite SET fom1_label=? WHERE suite_id=?")
          ->execute([$fom1_label, $suite_id]);
    }
  }

  // --- ensure/create benchmark (per suite, lowercase compare) ---
  $sel = $pdo->prepare("SELECT benchmark_id FROM benchmark WHERE suite_id=? AND LOWER(name)=? LIMIT 1");
  $sel->execute([$suite_id, $bench]);
  $benchmark_id = (int)($sel->fetchColumn() ?: 0);

  if (!$benchmark_id) {
    $ins = $pdo->prepare("INSERT INTO benchmark (suite_id, name) VALUES (?,?)");
    $ins->execute([$suite_id, $bench]);
    $benchmark_id = (int)$pdo->lastInsertId();
  }

  // --- insert result ---
  $ins = $pdo->prepare("
    INSERT INTO result
      (tool_id, tool_release_id, run_version, suite_id, benchmark_id,
       fom1, URL, file_hash, text_description, date)
    VALUES
      (:tool_id, :tool_release_id, :run_version, :suite_id, :benchmark_id,
       :fom1, :url, :file_hash, :notes, CURRENT_DATE())");
  $ins->execute([
    ':tool_id'         => $tool_id,
    ':tool_release_id' => $tool_release_id ?: null,
    ':run_version'     => $tool_ver ?: null,
    ':suite_id'        => $suite_id,
    ':benchmark_id'    => $benchmark_id,
    ':fom1'            => $fom,
    ':url'             => $artifact,
    ':file_hash'       => $sha256,
    ':notes'           => $notes,
  ]);

  echo "<div class='flash ok'>✅ Tool, Release, and Result saved successfully.</div>";
  echo "<div class='muted'>Suite: ".h($suite)." / ".h($suite_variation)." &middot; Benchmark: ".h($bench)."</div>";
  echo "<div class='muted'>Artifact: <a href='".h($artifact)."' target='_blank'>".h($artifact)."</a></div>";
  echo "<div style='margin-top:8px;'><a class='btn' href='index.php?page=results'>View Results</a></div>";
  exit;
}
?>

<!-- === HTML FORM FOR TOOL + RELEASE === -->
<div class="card">
  <h3>Add Tool & Release Information</h3>

  <div class="muted" style="margin-bottom:8px;">
    <div><strong>Suite</strong>: <?=h($suite)?><?php if ($suite_variation) { echo " (".h($suite_variation).")"; } ?></div>
    <div><strong>Benchmark</strong>: <?=h($bench)?></div>
    <div><strong>FOM1 Label</strong>: <?=h($fom1_label ?: '—')?> | <strong>Primary FOM</strong>: <?=h($fom)?></div>
    <div><strong>Artifact</strong>: <a href="<?=h($artifact)?>" target="_blank"><?=h($artifact)?></a></div>
    <div><strong>SHA-256</strong>: <span class="mono"><?=h($sha256)?></span></div>
    <div><strong>Run Version</strong>: <span class="mono"><?=h($tool_ver)?></span></div>
  </div>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=$CSRF?>">
    <!-- Hidden fields from upload.php -->
    <input type="hidden" name="tool_id" value="<?=$tool_id?>">
    <input type="hidden" name="tool" value="<?=h($tool)?>">
    <input type="hidden" name="suite" value="<?=h($suite)?>">
    <input type="hidden" name="suite_variation" value="<?=h($suite_variation)?>">
    <input type="hidden" name="benchmark" value="<?=h($bench)?>">
    <input type="hidden" name="primary_fom" value="<?=h($fom)?>">
    <input type="hidden" name="fom1_label" value="<?=h($fom1_label)?>">
    <input type="hidden" name="artifact_url" value="<?=h($artifact)?>">
    <input type="hidden" name="sha256" value="<?=h($sha256)?>">
    <input type="hidden" name="tool_version" value="<?=h($tool_ver)?>">
    <input type="hidden" name="notes" value="<?=h($notes)?>">
    <input type="hidden" name="tool_image_tmp" value="<?=h($tool_image_tmp)?>">

    <div>
      <label>Tool Name</label>
      <input type="text" value="<?=h($tool)?>" disabled>
    </div>

    <div>
      <label>Tool / Release URL</label>
      <input type="url" name="release_url" placeholder="https://example.com/tool-or-release">
    </div>

    <div>
      <label>Tool / Release Description</label>
      <textarea name="text_description" placeholder="Short description"></textarea>
    </div>

    <div>
      <label>Tool Image (optional)</label>
      <input type="file" name="tool_image" accept=".png,.jpg,.jpeg,.gif,.webp,.bmp">
      <?php if (!empty($tool_image_tmp)): ?>
        <div class="muted">An image was uploaded in the previous step; uploading a new one here will replace it.</div>
      <?php endif; ?>
    </div>

    <hr>
    <h4>Release Information</h4>
    <div>
      <label>Release Name</label>
      <input type="text" name="release_name" placeholder="e.g., official v2.0" required>
    </div>

    <div>
      <label>Release Version</label>
      <input type="text" name="release_version" placeholder="e.g., 2.0.1 or v2.0" required>
      <div class="muted">Saved and compared in lowercase (e.g., <code>v2.0</code>).</div>
    </div>

    <div>
      <label>Release Date</label>
      <input type="date" name="release_date" required>
    </div>

    <div style="margin-top:12px;">
      <button class="btn" type="submit">Save Tool + Release + Result</button>
    </div>
  </form>
</div>
