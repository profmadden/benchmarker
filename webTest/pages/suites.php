<?php
$sql = "SELECT s.*, COUNT(b.benchmark_id) AS bench_count
        FROM benchmarksuite s LEFT JOIN benchmark b ON b.suite_id=s.suite_id
        GROUP BY s.suite_id ORDER BY s.name, s.variation";
$suites_with_bench = fetchAll($pdo,$sql);
?>
<div class="grid">
<?php foreach($suites_with_bench as $s): ?>
  <div class="card">
    <div><strong><?=h($s['name'])?></strong> <span class="muted"><?=h($s['variation'])?></span></div>
    <div class="muted">FOM labels: <?=h(implode(' | ', array_filter([$s['fom1_label'],$s['fom2_label'],$s['fom3_label'],$s['fom4_label']])) ?: '—')?></div>
    <?php if($s['url_benchmarks']): ?><div><a href="<?=h($s['url_benchmarks'])?>" target="_blank">Benchmarks site</a></div><?php endif; ?>
    <?php if($s['url_evaluator']): ?><div><a href="<?=h($s['url_evaluator'])?>" target="_blank">Evaluator</a></div><?php endif; ?>
    <div class="muted">Benchmarks: <?= (int)$s['bench_count'] ?></div>
    <?php $ben=benchmarksBySuite($pdo,$s['suite_id']); if($ben): ?>
      <ul>
        <?php foreach($ben as $b): ?>
          <li><a href="index.php?page=benchmarks&suite_id=<?=$s['suite_id']?>#b<?=$b['benchmark_id']?>"><?=h($b['name'])?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <div><a class="btn alt" href="index.php?page=manage&edit_suite=<?=$s['suite_id']?>">Edit suite</a></div>
  </div>
<?php endforeach; ?>
</div>
