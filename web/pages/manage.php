<?php
$edit_suite = getv('edit_suite') ? fetchOne($pdo,'SELECT * FROM benchmarksuite WHERE suite_id=?',[(int)getv('edit_suite')]) : null;
$edit_bench = getv('edit_benchmark') ? fetchOne($pdo,'SELECT * FROM benchmark WHERE benchmark_id=?',[(int)getv('edit_benchmark')]) : null;
$edit_tool  = getv('edit_tool') ? fetchOne($pdo,'SELECT * FROM tool WHERE tool_id=?',[(int)getv('edit_tool')]) : null;
?>
<div class="grid">
  <div class="card">
    <details open>
      <summary><?= $edit_suite? 'Edit Suite' : 'Add Suite' ?></summary>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <?php if($edit_suite): ?>
          <input type="hidden" name="action" value="update_suite">
          <input type="hidden" name="suite_id" value="<?=$edit_suite['suite_id']?>">
        <?php else: ?>
          <input type="hidden" name="action" value="add_suite">
        <?php endif; ?>

        <label>Name</label>
        <input type="text" name="name" value="<?=h($edit_suite['name'] ?? '')?>" required>

        <div class="row">
          <div><label>Variation</label><input type="text" name="variation" value="<?=h($edit_suite['variation'] ?? '')?>"></div>
          <div><label>Date</label><input type="date" name="date" value="<?=h($edit_suite['date'] ?? '')?>"></div>
        </div>

        <div class="row">
          <div><label>Benchmarks URL</label><input type="text" name="url_benchmarks" value="<?=h($edit_suite['url_benchmarks'] ?? '')?>"></div>
          <div><label>Evaluator URL</label><input type="text" name="url_evaluator" value="<?=h($edit_suite['url_evaluator'] ?? '')?>"></div>
        </div>

        <div class="row">
          <div><label>FOM1 Label</label><input type="text" name="fom1_label" value="<?=h($edit_suite['fom1_label'] ?? '')?>"></div>
          <div><label>FOM2 Label</label><input type="text" name="fom2_label" value="<?=h($edit_suite['fom2_label'] ?? '')?>"></div>
        </div>

        <div class="row">
          <div><label>FOM3 Label</label><input type="text" name="fom3_label" value="<?=h($edit_suite['fom3_label'] ?? '')?>"></div>
          <div><label>FOM4 Label</label><input type="text" name="fom4_label" value="<?=h($edit_suite['fom4_label'] ?? '')?>"></div>
        </div>

        <label>Description</label>
        <textarea name="text_description"><?=h($edit_suite['text_description'] ?? '')?></textarea>

        <div style="margin-top:8px"><button class="btn" type="submit">Save</button></div>
      </form>
    </details>

    <details style="margin-top:10px;">
      <summary>Quick list: Suites</summary>
      <ul>
        <?php foreach($all_suites as $s): ?>
          <li><?=h($s['name'].($s['variation']?(' — '.$s['variation']):''))?> · <a href="index.php?page=manage&edit_suite=<?=$s['suite_id']?>">edit</a></li>
        <?php endforeach; ?>
      </ul>
    </details>
  </div>

  <div class="card">
    <details open>
      <summary><?= $edit_bench? 'Edit Benchmark' : 'Add Benchmark' ?></summary>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <?php if($edit_bench): ?>
          <input type="hidden" name="action" value="update_benchmark">
          <input type="hidden" name="benchmark_id" value="<?=$edit_bench['benchmark_id']?>">
        <?php else: ?>
          <input type="hidden" name="action" value="add_benchmark">
        <?php endif; ?>

        <label>Suite</label>
        <select name="suite_id" required>
          <?php foreach($all_suites as $s): $sel = ($edit_bench['suite_id'] ?? 0)==$s['suite_id'] ? 'selected':''; ?>
            <option value="<?=$s['suite_id']?>" <?=$sel?>><?=h($s['name'].($s['variation']?(' — '.$s['variation']):''))?></option>
          <?php endforeach; ?>
        </select>

        <label>Name</label>
        <input type="text" name="name" value="<?=h($edit_bench['name'] ?? '')?>" required>

        <div class="row">
          <div><label>URL</label><input type="text" name="URL" value="<?=h($edit_bench['URL'] ?? '')?>"></div>
          <div><label>File Hash</label><input type="text" name="file_hash" value="<?=h($edit_bench['file_hash'] ?? '')?>"></div>
        </div>

        <label>Description</label>
        <textarea name="text_description"><?=h($edit_bench['text_description'] ?? '')?></textarea>

        <div style="margin-top:8px"><button class="btn" type="submit">Save</button></div>
      </form>
    </details>

    <details style="margin-top:10px;">
      <summary>Quick list: Benchmarks</summary>
      <ul>
        <?php foreach(benchmarksAll($pdo) as $b): ?>
          <li><?=h($b['suite_name'].' · '.$b['name'])?> · <a href="index.php?page=manage&edit_benchmark=<?=$b['benchmark_id']?>">edit</a></li>
        <?php endforeach; ?>
      </ul>
    </details>
  </div>

  <div class="card">
    <details open>
      <summary><?= $edit_tool? 'Edit Tool' : 'Add Tool' ?></summary>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <?php if($edit_tool): ?>
          <input type="hidden" name="action" value="update_tool">
          <input type="hidden" name="tool_id" value="<?=$edit_tool['tool_id']?>">
        <?php else: ?>
          <input type="hidden" name="action" value="add_tool">
        <?php endif; ?>

        <label>Name</label>
        <input type="text" name="name" value="<?=h($edit_tool['name'] ?? '')?>" required>

        <label>URL</label>
        <input type="text" name="URL" value="<?=h($edit_tool['URL'] ?? '')?>">

        <label>Description</label>
        <textarea name="text_description"><?=h($edit_tool['text_description'] ?? '')?></textarea>

        <div style="margin-top:8px"><button class="btn" type="submit">Save</button></div>
      </form>
    </details>

    <details style="margin-top:10px;">
      <summary>Add Tool Release</summary>
      <form method="post">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <input type="hidden" name="action" value="add_release">

        <label>Tool</label>
        <select name="tool_id" required>
          <?php foreach(tools($pdo) as $t): ?>
            <option value="<?=$t['tool_id']?>"><?=h($t['name'])?></option>
          <?php endforeach; ?>
        </select>

        <div class="row">
          <div><label>Release Name</label><input type="text" name="name"></div>
          <div><label>Date</label><input type="date" name="date"></div>
        </div>

        <label>URL</label>
        <input type="text" name="URL">

        <label>Description</label>
        <textarea name="text_description"></textarea>

        <div style="margin-top:8px"><button class="btn" type="submit">Add Release</button></div>
      </form>
    </details>

    <details style="margin-top:10px;">
      <summary>Quick list: Tools & releases</summary>
      <ul>
        <?php foreach(tools($pdo) as $t): ?>
          <li>
            <strong><?=h($t['name'])?></strong> · <a href="index.php?page=manage&edit_tool=<?=$t['tool_id']?>">edit</a>
            <?php $rels=releasesByTool($pdo,$t['tool_id']); if($rels): ?>
              <ul>
                <?php foreach($rels as $r): ?>
                  <li><?=h(($r['date']?:'—').' · '.($r['name']?:'Unnamed release'))?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </details>
  </div>

  <div class="card">
    <details open>
      <summary>Add Result</summary>
      <form method="post" id="add-result-form">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <input type="hidden" name="action" value="add_result">

        <div class="row">
          <div>
            <label>Suite (text)</label>
            <input type="text" name="suite" placeholder="e.g. ICCAD04" required>
            <div class="muted">Case-insensitive; will be created if not found.</div>
          </div>
          <div>
            <label>Benchmark (name)</label>
            <input type="text" name="benchmark" placeholder="e.g. ibm01" required>
            <div class="muted">Within the suite; will be created if not found.</div>
          </div>
        </div>

        <div class="row">
          <div>
            <label>Tool (name)</label>
            <input type="text" name="tool" placeholder="e.g. RePlAce" required>
            <div class="muted">Will be created if not found.</div>
          </div>
          <div>
            <label>Tool Release</label>
            <input type="number" name="tool_release_id" placeholder="ID (optional)">
            <input type="text"   name="tool_release"    placeholder="or Release name (optional)" style="margin-top:6px;">
            <div class="muted">If both are provided, the ID is used.</div>
          </div>
        </div>

        <div class="row3">
          <div><label>FOM1</label><input type="number" step="any" name="fom1" required></div>
          <div><label>FOM2</label><input type="number" step="any" name="fom2"></div>
          <div><label>FOM3</label><input type="number" step="any" name="fom3"></div>
        </div>

        <div class="row3">
          <div><label>FOM4</label><input type="number" step="any" name="fom4"></div>
          <div><label>Date</label><input type="date" name="date"></div>
          <div><label>File Hash</label><input type="text" name="file_hash"></div>
        </div>

        <label>URL</label>
        <input type="text" name="URL">

        <label>Evaluator Output</label>
        <textarea name="evaluator_output"></textarea>

        <label>Description</label>
        <textarea name="text_description"></textarea>

        <div style="margin-top:8px"><button class="btn" type="submit">Add Result</button></div>
      </form>
    </details>

  </div>
</div>
