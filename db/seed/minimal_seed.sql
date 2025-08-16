-- Insert the benchmarksuite
INSERT INTO benchmarksuite (name, fom1)
SELECT 'ICCAD04', 'wirelength'
WHERE NOT EXISTS (SELECT 1 FROM benchmarksuite WHERE name = 'ICCAD04');

-- Insert tools
INSERT INTO tool (name)
SELECT x.name FROM (
  SELECT 'Uplace' AS name UNION ALL
  SELECT 'FS5.1' UNION ALL
  SELECT 'NTUplace3' UNION ALL
  SELECT 'NTUplaceic04' UNION ALL
  SELECT 'CT' UNION ALL
  SELECT 'SA' UNION ALL
  SELECT 'RePlAce'
) AS x
WHERE NOT EXISTS (SELECT 1 FROM tool t WHERE t.name = x.name);

-- Insert benchmarks for ICCAD04
INSERT INTO benchmark (suite_id, name)
SELECT s.suite_id, x.name
FROM benchmarksuite s
JOIN (
  SELECT 'IBM01' AS name UNION ALL SELECT 'IBM02' UNION ALL SELECT 'IBM03' UNION ALL
  SELECT 'IBM04' UNION ALL SELECT 'IBM05' UNION ALL SELECT 'IBM06' UNION ALL
  SELECT 'IBM07' UNION ALL SELECT 'IBM08' UNION ALL SELECT 'IBM09' UNION ALL
  SELECT 'IBM10' UNION ALL SELECT 'IBM11' UNION ALL SELECT 'IBM12' UNION ALL
  SELECT 'IBM13' UNION ALL SELECT 'IBM14' UNION ALL SELECT 'IBM15' UNION ALL
  SELECT 'IBM16' UNION ALL SELECT 'IBM17' UNION ALL SELECT 'IBM18'
) AS x
WHERE s.name = 'ICCAD04'
  AND NOT EXISTS (
    SELECT 1 FROM benchmark b
    WHERE b.suite_id = s.suite_id AND b.name = x.name
  );

-- Insert results with wirelength (fom1)
WITH src(tool_name, bench_name, wl) AS (
  SELECT 'Uplace','IBM01', 2450000 UNION ALL
  SELECT 'FS5.1','IBM01', 2420000 UNION ALL
  SELECT 'NTUplace3','IBM01', 2170000 UNION ALL
  SELECT 'NTUplaceic04','IBM01', 2230000 UNION ALL
  SELECT 'CT','IBM01', 3370000 UNION ALL
  SELECT 'SA','IBM01', 2550000 UNION ALL
  SELECT 'RePlAce','IBM01', 2240000 UNION ALL

  SELECT 'Uplace','IBM02', 5380000 UNION ALL
  SELECT 'FS5.1','IBM02', 5010000 UNION ALL
  SELECT 'NTUplace3','IBM02', 4630000 UNION ALL
  SELECT 'NTUplaceic04','IBM02', 4670000 UNION ALL
  SELECT 'CT','IBM02', 6280000 UNION ALL
  SELECT 'SA','IBM02', 5120000 UNION ALL
  SELECT 'RePlAce','IBM02', 5270000 UNION ALL

  -- (continue the IBM03 … IBM18 rows exactly as you already have them)
)
INSERT INTO result (tool_id, suite_id, benchmark_id, fom1, fom2, fom3, fom4)
SELECT t.tool_id,
       s.suite_id,
       b.benchmark_id,
       src.wl AS fom1,
       NULL, NULL, NULL
FROM src
JOIN tool t           ON t.name = src.tool_name
JOIN benchmarksuite s ON s.name = 'ICCAD04'
JOIN benchmark b      ON b.name = src.bench_name AND b.suite_id = s.suite_id
LEFT JOIN result r0   ON r0.tool_id = t.tool_id AND r0.benchmark_id = b.benchmark_id AND r0.suite_id = s.suite_id
WHERE r0.result_id IS NULL;
