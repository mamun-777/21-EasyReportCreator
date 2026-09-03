<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    match ($action) {
        'templates' => erc_json(['ok' => true, 'templates' => ErcTemplates::listTemplates()]),
        'upload' => handle_upload(),
        'project' => handle_project(),
        'details' => handle_details(),
        'report' => handle_report(),
        'export' => handle_export(),
        'clear' => handle_clear(),
        default => erc_error('Unknown action', 404),
    };
} catch (Throwable $e) {
    erc_error($e->getMessage(), 500);
}

function handle_upload(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        erc_error('POST required', 405);
    }
    if (empty($_FILES['file'])) {
        erc_error('No file uploaded.');
    }
    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        erc_error('Upload failed (error ' . ($file['error'] ?? '?') . ').');
    }
    $name = (string) ($file['name'] ?? 'ProcessPower.dcf');
    if (!preg_match('/\.dcf$/i', $name)) {
        erc_error('Please select a Plant 3D database file (.dcf).');
    }
    $maxBytes = 80 * 1024 * 1024;
    if (($file['size'] ?? 0) > $maxBytes) {
        erc_error('File is too large (max 80 MB).');
    }

    $dir = erc_session_upload_dir();
    foreach (glob($dir . '/*') ?: [] as $old) {
        @unlink($old);
    }
    $target = $dir . '/ProcessPower.dcf';
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        erc_error('Could not save uploaded file.');
    }
    ErcDcf::validate($target);
    $_SESSION['erc_dcf'] = $target;
    $_SESSION['erc_uploaded_name'] = $name;
    $loaded = ErcDcf::loadProject($target);
    erc_json([
        'ok' => true,
        'project' => $loaded['project'],
        'uploaded_filename' => $name,
    ]);
}

function handle_project(): never
{
    $dcf = erc_current_dcf();
    if (!$dcf) {
        erc_error('No project uploaded. Please upload ProcessPower.dcf first.', 400);
    }
    $loaded = ErcDcf::loadProject($dcf);
    erc_json([
        'ok' => true,
        'project' => $loaded['project'],
        'uploaded_filename' => $_SESSION['erc_uploaded_name'] ?? 'ProcessPower.dcf',
    ]);
}

function handle_details(): never
{
    $dcf = erc_current_dcf();
    if (!$dcf) {
        erc_error('No project uploaded.', 400);
    }
    $pdo = ErcDcf::connect($dcf);
    erc_json(['ok' => true] + ErcProject::details($pdo));
}

function handle_report(): never
{
    $dcf = erc_current_dcf();
    if (!$dcf) {
        erc_error('No project uploaded.', 400);
    }
    $templateId = (string) ($_GET['template_id'] ?? '');
    if ($templateId === '') {
        erc_error('template_id is required.');
    }
    $resolved = ErcTemplates::resolveId($templateId);
    $template = ErcTemplates::load($resolved);
    $pdo = ErcDcf::connect($dcf);
    $details = ErcProject::details($pdo);
    $merged = ErcProject::mergeHeader($template, $details);
    $raw = ErcQueries::run($pdo, (string) $merged['source']);
    $rows = ErcTemplates::apply($raw, $merged);
    $loaded = ErcDcf::loadProject($dcf);
    erc_json([
        'ok' => true,
        'template' => $merged,
        'template_id' => $templateId,
        'resolved_id' => $resolved,
        'project' => $loaded['project'],
        'row_count' => count($rows),
        'raw_count' => count($raw),
        'rows' => $rows,
    ]);
}

function handle_export(): never
{
    $dcf = erc_current_dcf();
    if (!$dcf) {
        erc_error('No project uploaded.', 400);
    }
    $templateId = (string) ($_GET['template_id'] ?? '');
    if ($templateId === '') {
        erc_error('template_id is required.');
    }
    $resolved = ErcTemplates::resolveId($templateId);
    $template = ErcTemplates::load($resolved);
    $pdo = ErcDcf::connect($dcf);
    $details = ErcProject::details($pdo);
    $merged = ErcProject::mergeHeader($template, $details);
    $raw = ErcQueries::run($pdo, (string) $merged['source']);
    $rows = ErcTemplates::apply($raw, $merged);
    $loaded = ErcDcf::loadProject($dcf);
    $bytes = ErcExcel::export($rows, $merged, $loaded['project']);
    $filename = ($loaded['project']['number'] ?: 'project') . '_' . $templateId . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
    exit;
}

function handle_clear(): never
{
    $dcf = erc_current_dcf();
    if ($dcf && is_file($dcf)) {
        @unlink($dcf);
    }
    unset($_SESSION['erc_dcf'], $_SESSION['erc_uploaded_name']);
    erc_json(['ok' => true]);
}
