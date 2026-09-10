<?php
/**
 * FB-001 acceptance: run all list sources + catalogue on one or more DCFs.
 * Usage: php scripts/test_fb001_samples.php [path-to.dcf ...]
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/website/inc/bootstrap.php';

$paths = array_slice($argv, 1);
if ($paths === []) {
    $paths = [
        $root . '/samples/_incoming/ProcessPower.dcf',
        $root . '/samples/MN-P-RHN-PID-0001/ProcessPower.dcf',
    ];
}

$sources = [
    'drawings',
    'equipment',
    'valves',
    'control_valves',
    'instruments',
    'lines',
    'line_summary',
    'components',
];

$failed = 0;
foreach ($paths as $path) {
    echo "=== {$path} ===\n";
    if (!is_file($path)) {
        echo "MISSING\n";
        $failed++;
        continue;
    }
    try {
        $proj = ErcDcf::loadProject($path);
        $p = $proj['project'];
        echo "Project: {$p['name']} / {$p['number']}\n";
        echo 'Counts: ' . json_encode($p['counts'], JSON_UNESCAPED_UNICODE) . "\n";
        $pdo = ErcDcf::connect($path);

        foreach ($sources as $source) {
            $t0 = microtime(true);
            $rows = ErcQueries::run($pdo, $source);
            $ms = (int) ((microtime(true) - $t0) * 1000);
            $keys = $rows ? count($rows[0]) : 0;
            $sampleKeys = $rows ? implode(',', array_slice(array_keys($rows[0]), 0, 8)) : '';
            echo sprintf("  %-16s rows=%-5d keys=%-3d %dms  [%s]\n", $source, count($rows), $keys, $ms, $sampleKeys);

            $cat = ErcCatalog::propertyCatalogueForSource($pdo, $source);
            echo sprintf("    catalogue class=%s props=%d\n", $cat['class_name'], $cat['count']);
            if ($cat['count'] < 1 && $source !== 'control_valves') {
                echo "    FAIL: empty catalogue\n";
                $failed++;
            }
        }

        // Vitens regression counts when this is the Rhenen sample
        if (str_contains($path, 'MN-P-RHN-PID-0001')) {
            $expect = [
                'drawings' => 30,
                'equipment' => 80,
                'hand_valves' => 344,
                'control_valves' => 43,
                'instruments' => 126,
                'pipe_lines' => 611,
                'line_groups' => 386,
                'components' => 897,
            ];
            foreach ($expect as $k => $n) {
                $got = (int) ($p['counts'][$k] ?? -1);
                if ($got !== $n) {
                    echo "  REGRESSION FAIL {$k}: expected {$n} got {$got}\n";
                    $failed++;
                } else {
                    echo "  REGRESSION OK {$k}={$got}\n";
                }
            }
        }

        // Second sample: must produce valves + non-empty EI props on a valve row
        if (str_contains($path, '_incoming') || str_contains(strtolower($p['name'] ?? ''), 'morssinkhof')) {
            $valves = ErcQueries::run($pdo, 'valves');
            if (count($valves) < 1) {
                echo "  FAIL: expected hand valves on second sample\n";
                $failed++;
            } else {
                $row = $valves[0];
                $hasNative = isset($row['ClassName']) || isset($row['Omschrijving']) || isset($row['Opmerkingen']) || isset($row['Proces']);
                if (!$hasNative) {
                    echo "  FAIL: valve row missing native EI properties\n";
                    echo '  keys=' . implode(',', array_keys($row)) . "\n";
                    $failed++;
                } else {
                    echo "  OK second-sample valve row has native EI fields\n";
                }
            }
            $tree = ErcCatalog::classTree($pdo, 'EngineeringItems');
            echo "  class tree nodes={$tree['node_count']}\n";
            if ($tree['node_count'] < 5) {
                echo "  FAIL: Engineering Items tree too small\n";
                $failed++;
            }
        }
    } catch (Throwable $e) {
        echo 'FAIL: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
        $failed++;
    }
    echo "\n";
}

echo $failed === 0 ? "ALL PASSED\n" : "FAILED ({$failed})\n";
exit($failed === 0 ? 0 : 1);
