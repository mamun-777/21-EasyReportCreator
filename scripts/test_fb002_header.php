<?php
/** FB-002: title-block values must come from the uploaded DCF, not stale template values. */
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/website/inc/bootstrap.php';

$dcf = $argv[1] ?? ($root . '/samples/P220049-Morssinkhof/ProcessPower.dcf');
if (!is_file($dcf)) {
    fwrite(STDERR, "Missing DCF: {$dcf}\n");
    exit(2);
}

$pdo = ErcDcf::connect($dcf);
$details = ErcProject::details($pdo);
$factory = ErcTemplates::load('valve_list');

// Simulate stale company / previous-project values baked into the template.
$factory['header']['fields'] = [
    ['key' => 'Project_Name', 'label' => 'Project name', 'value' => 'MN-O-STH-PID-0001'],
    ['key' => 'Project_Number', 'label' => 'Project number', 'value' => 'MN-O-STH-PID-0001'],
    ['key' => 'Project_Description', 'label' => 'Project description', 'value' => 'Wrong old description'],
    ['key' => 'S88_Locatie', 'label' => 'Location', 'value' => 'Opjager Stamerahoef'],
];
$factory['header']['document_number'] = 'MN-P-RHN-PID-0001-AL';
$factory['revision_table'] = [[
    'rev' => 'A',
    'date' => '2026-08-19',
    'desc' => 'Demo issue from ProcessPower.dcf',
    'drawn' => 'EasyReportCreator',
]];

$merged = ErcProject::mergeHeader($factory, $details);
$byKey = [];
foreach ($merged['header']['fields'] as $f) {
    $byKey[$f['key']] = $f['value'];
}

$failed = 0;
$expectName = $details['values']['Project_Name'] ?? '';
$expectNumber = $details['values']['Project_Number'] ?? '';
$expectDesc = $details['values']['Project_Description'] ?? '';

echo "Project from DCF: {$expectName} / {$expectNumber}\n";
echo "Merged Project_Name={$byKey['Project_Name']}\n";
echo "Merged Project_Number={$byKey['Project_Number']}\n";
echo "Merged Project_Description={$byKey['Project_Description']}\n";
echo "Merged S88_Locatie=" . ($byKey['S88_Locatie'] ?? '') . " (expect empty on Morssinkhof)\n";
echo "Document={$merged['header']['document_number']}\n";
echo "Rev desc=" . ($merged['revision_table'][0]['desc'] ?? '') . "\n";

if (($byKey['Project_Name'] ?? '') !== $expectName) {
    echo "FAIL Project_Name\n";
    $failed++;
}
if (($byKey['Project_Number'] ?? '') !== $expectNumber) {
    echo "FAIL Project_Number\n";
    $failed++;
}
if (($byKey['Project_Description'] ?? '') !== $expectDesc) {
    echo "FAIL Project_Description\n";
    $failed++;
}
if (($byKey['S88_Locatie'] ?? '') !== '') {
    echo "FAIL S88_Locatie should be empty for this DCF\n";
    $failed++;
}
if (!str_starts_with((string) $merged['header']['document_number'], $expectNumber . '-')) {
    echo "FAIL document_number should start with project number\n";
    $failed++;
}
if (str_contains(strtolower((string) ($merged['revision_table'][0]['desc'] ?? '')), 'demo issue')) {
    echo "FAIL revision still has demo text\n";
    $failed++;
}

// Vitens still fills its own name
$vitens = $root . '/samples/MN-P-RHN-PID-0001/ProcessPower.dcf';
if (is_file($vitens)) {
    $vd = ErcProject::details(ErcDcf::connect($vitens));
    $vm = ErcProject::mergeHeader($factory, $vd);
    $vname = '';
    foreach ($vm['header']['fields'] as $f) {
        if ($f['key'] === 'Project_Name') {
            $vname = $f['value'];
        }
    }
    echo "Vitens Project_Name={$vname}\n";
    if ($vname !== ($vd['values']['Project_Name'] ?? '')) {
        echo "FAIL Vitens Project_Name\n";
        $failed++;
    }
}

echo $failed === 0 ? "FB-002 PASSED\n" : "FB-002 FAILED ({$failed})\n";
exit($failed === 0 ? 0 : 1);
