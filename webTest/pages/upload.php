<?php
// pages/upload.php
// Step 1: save archive under /files/<suite>/ and redirect to toolrelease_form.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_once __DIR__ . '/../lib/helpers.php';
  check_csrf();

  // --- read & normalize fields ---
  $suite           = strtolower(trim($_POST['suite'] ?? ''));
  $suite_variation = strtolower(trim($_POST['suite_variation'] ?? ''));
  $benchmark       = strtolower(trim($_POST['benchmark'] ?? ''));
  $tool            = strtolower(trim($_POST['tool'] ?? ''));
  $primary_fom     = isset($_POST['primary_fom']) ? (float)$_POST['primary_fom'] : 0.0;
  $fom1_label      = strtolower(trim($_POST['fom1_label'] ?? '')); // NEW: label for suite
  $tool_version    = strtolower(trim($_POST['tool_version'] ?? '')); // optional; if blank, use YYYYMMDD
  $notes           = trim($_POST['notes'] ?? '');

  $err = [];
  if ($suite==='')      $err[] = 'Suite is required';
  if ($benchmark==='')  $err[] = 'Benchmark is required';
  if ($tool==='')       $err[] = 'Tool is required';
  if ($primary_fom===0) $err[] = 'Primary FOM is required';
  if ($fom1_label==='') $err[] = 'FOM1 label is required (e.g., wirelength)';
  if (empty($_FILES['file']['name'])) $err[] = 'Select a .tgz or .tar file';

  if ($tool_version==='') { $tool_version = gmdate('Ymd'); }

  // validate archive file
  $fname = $_FILES['file']['name'] ?? '';
  $ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
  if (!in_array($ext, ['tgz','gz','tar'], true)) {
    $err[] = 'Please upload a .tgz (or .tar.gz/.tar) file';
  }

  if (!$err) {
    // --- build dest path and final name: bench.tool.version.(tgz|tar) ---
    $safeSuite = preg_replace('/[^a-z0-9._-]/','_', $suite);
    $safeBench = preg_replace('/[^a-z0-9._-]/','_', $benchmark);
    $safeTool  = preg_replace('/[^a-z0-9._-]/','_', $tool);
    $safeVer   = preg_replace('/[^a-z0-9._-]/','_', $tool_version);

    // keep original type: .tar stays .tar; .tgz/.gz saved as .tgz
    $suffix = ($ext === 'tar') ? '.tar' : '.tgz';
    $finalName = "{$safeBench}.{$safeTool}.{$safeVer}{$suffix}";

    $baseDir  = realpath(__DIR__ . '/../') ?: dirname(__DIR__);
    $filesDir = $baseDir . '/files/' . $safeSuite;
    if (!is_dir($filesDir)) { @mkdir($filesDir, 0777, true); }

    $destPath = $filesDir . '/' . $finalName;

    $tmp = $_FILES['file']['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
      $err[] = 'Upload failed (no temp file).';
    } else if (!@move_uploaded_file($tmp, $destPath)) {
      $err[] = 'Could not save uploaded file.';
    } else {
      // compute sha256
      $sha256 = hash_file('sha256', $destPath);

      // public URL for the file
      $scheme = (!empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] :
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off' ? 'https' : 'http'));
      $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); // e.g. /Website_Frontend
      $artifact_url = $scheme.'://'.$_SERVER['HTTP_HOST'].$basePath.'/files/'.$safeSuite.'/'.$finalName;

      // OPTIONAL: accept a tool image here, stash temporarily, and pass a token
      $tool_image_tmp = '';
      if (!empty($_FILES['tool_image']['name']) && !empty($_FILES['tool_image']['tmp_name'])) {
        $tmpImg = $_FILES['tool_image']['tmp_name'];
        if (is_uploaded_file($tmpImg)) {
          $tmpDir = $baseDir . '/files/_tmp';
          if (!is_dir($tmpDir)) { @mkdir($tmpDir, 0777, true); }
          $imgExt = strtolower(pathinfo($_FILES['tool_image']['name'], PATHINFO_EXTENSION));
          if (in_array($imgExt, ['png','jpg','jpeg','gif','webp','bmp'], true)) {
            $tool_image_tmp = $tmpDir . '/' . uniqid('toolimg_', true) . '.' . $imgExt;
            @move_uploaded_file($tmpImg, $tool_image_tmp);
          }
        }
      }

      // redirect to toolrelease_form.php with all collected data
      $params = [
        'suite'            => $suite,
        'suite_variation'  => $suite_variation,
        'benchmark'        => $benchmark,
        'tool'             => $tool,
        'primary_fom'      => $primary_fom,
        'fom1_label'       => $fom1_label,     // NEW
        'artifact_url'     => $artifact_url,
        'sha256'           => $sha256,
        'tool_version'     => $tool_version,
        'notes'            => $notes,
      ];
      if ($tool_image_tmp) {
        // pass a relative path (from site root) to avoid leaking server paths
        $params['tool_image_tmp'] = str_replace($baseDir.'/', '', $tool_image_tmp);
      }

      header("Location: index.php?page=toolrelease_form&" . http_build_query($params));
      exit;
    }
  }
}
?>
<div class="card">
  <h3 style="margin-top:0">Upload a benchmark archive</h3>
  <?php if (!empty($err)): ?>
    <div class="flash err"><?php foreach($err as $e){ echo '<div>'.h($e).'</div>'; } ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?=$CSRF?>">
    <div class="row3">
      <div>
        <label>Suite</label>
        <input type="text" name="suite" placeholder="e.g., iccad04" required>
      </div>
      <div>
        <label>Suite Variation (optional)</label>
        <input type="text" name="suite_variation" placeholder="e.g., eq-5, wt-10">
      </div>
      <div>
        <label>Benchmark</label>
        <input type="text" name="benchmark" placeholder="e.g., ibm01" required>
      </div>
    </div>

    <div class="row3">
      <div>
        <label>Tool</label>
        <input type="text" name="tool" placeholder="e.g., replace" required>
      </div>
      <div>
        <label>Primary FOM (value)</label>
        <input type="number" step="any" name="primary_fom" placeholder="e.g., 2241590" required>
      </div>
      <div>
        <label>Run Version (optional)</label>
        <input type="text" name="tool_version" placeholder="e.g., 20250906">
      </div>
    </div>

    <div class="row3">
      <div>
        <label>FOM1 Label (for suite)</label>
        <input type="text" name="fom1_label" placeholder="e.g., wirelength" required>
      </div>
      <div>
        <label>Tool Image (optional)</label>
        <input type="file" name="tool_image" accept=".png,.jpg,.jpeg,.gif,.webp,.bmp">
      </div>
    </div>

    <label>Notes (optional)</label>
    <textarea name="notes" placeholder="run_by=bob flags=--timing"></textarea>

    <label style="margin-top:8px;">Archive file</label>
    <input type="file" name="file" accept=".tgz,.tar,.gz" required>

    <div style="margin-top:12px;">
      <button class="btn" type="submit">Upload &amp; Continue</button>
    </div>
  </form>
</div>
