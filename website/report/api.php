<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/inc/bootstrap.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

$publicActions = ['login', 'register', 'me', 'logout'];

try {
    if (!in_array($action, $publicActions, true)) {
        ErcAuth::requireUser();
    }

    match ($action) {
        'me' => handle_me(),
        'login' => handle_login(),
        'register' => handle_register(),
        'logout' => handle_logout(),
        'profile' => handle_profile(),
        'templates' => erc_json(['ok' => true, 'templates' => ErcTemplates::listTemplates()]),
        'template' => handle_template_get(),
        'upload' => handle_upload(),
        'project' => handle_project(),
        'details' => handle_details(),
        'report' => handle_report(),
        'export' => handle_export(),
        'property_catalogue' => handle_property_catalogue(),
        'class_tree' => handle_class_tree(),
        'apply_template' => handle_apply_template(),
        'save_template' => handle_save_template(),
        'logo' => handle_logo(),
        'clear' => handle_clear(),
        default => erc_error('Unknown action', 404),
    };
} catch (InvalidArgumentException $e) {
    erc_error($e->getMessage(), 400);
} catch (Throwable $e) {
    erc_error($e->getMessage(), 500);
}

function handle_me(): never
{
    $user = ErcAuth::currentUser();
    if (!$user) {
        erc_json(['ok' => true, 'authenticated' => false, 'user' => null]);
    }
    $profile = erc_load_company_profile();
    erc_json([
        'ok' => true,
        'authenticated' => true,
        'user' => $user,
        'profile' => $profile,
        'has_logo' => erc_logo_path() !== null,
        'has_project' => erc_current_dcf() !== null,
    ]);
}

function handle_login(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        erc_error('POST required', 405);
    }
    $body = erc_json_body();
    $result = ErcAuth::login((string) ($body['email'] ?? ''), (string) ($body['password'] ?? ''));
    erc_json(['ok' => true] + $result + [
        'profile' => erc_load_company_profile(),
        'has_logo' => erc_logo_path() !== null,
    ]);
}

function handle_register(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        erc_error('POST required', 405);
    }
    $body = erc_json_body();
    $result = ErcAuth::register(
        (string) ($body['company_name'] ?? ''),
        (string) ($body['email'] ?? ''),
        (string) ($body['password'] ?? ''),
        (string) ($body['display_name'] ?? '')
    );
    $profile = erc_load_company_profile();
    $profile['company_name'] = $result['user']['company_name'];
    $profile['header']['company'] = $result['user']['company_name'];
    erc_save_company_profile($profile);
    erc_json(['ok' => true] + $result + ['profile' => $profile, 'has_logo' => false]);
}

function handle_logout(): never
{
    ErcAuth::logout();
    erc_json(['ok' => true]);
}

function handle_profile(): never
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        erc_json([
            'ok' => true,
            'profile' => erc_load_company_profile(),
            'has_logo' => erc_logo_path() !== null,
        ]);
    }
    if ($method !== 'POST') {
        erc_error('GET or POST required', 405);
    }
    $body = erc_json_body();
    $current = erc_load_company_profile();
    if (isset($body['company_name'])) {
        $current['company_name'] = trim((string) $body['company_name']);
    }
    if (isset($body['header']) && is_array($body['header'])) {
        $current['header'] = array_replace_recursive($current['header'], $body['header']);
    }
    if (isset($body['export']) && is_array($body['export'])) {
        $current['export'] = array_replace_recursive($current['export'], $body['export']);
        foreach (['include_logo', 'include_revision', 'include_pnpid', 'remember'] as $key) {
            if (array_key_exists($key, $body['export'])) {
                $current['export'][$key] = (bool) $body['export'][$key];
            }
        }
    }
    $saved = erc_save_company_profile($current);
    // Refresh working templates so all sheets pick up company header defaults.
    if (!empty($_SESSION['erc_working']) && is_array($_SESSION['erc_working'])) {
        foreach ($_SESSION['erc_working'] as $id => $tpl) {
            if (is_array($tpl)) {
                $_SESSION['erc_working'][$id] = erc_apply_company_defaults($tpl, $saved);
            }
        }
    }
    erc_json(['ok' => true, 'profile' => $saved]);
}

function handle_template_get(): never
{
    $id = (string) ($_GET['id'] ?? '');
    $variant = (string) ($_GET['variant'] ?? 'auto');
    if ($id === '') {
        erc_error('id is required.');
    }
    if ($variant === 'working') {
        $working = erc_working_template($id);
        if ($working) {
            erc_json(['ok' => true, 'template' => erc_apply_company_defaults($working, erc_load_company_profile())]);
        }
        $variant = 'auto';
    }
    $template = ErcTemplates::load($id, $variant);
    erc_json(['ok' => true, 'template' => erc_apply_company_defaults($template, erc_load_company_profile())]);
}

function handle_upload(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        erc_error('POST required', 405);
    }
    if (empty($_FILES['file'])) {
        $postMax = ini_get('post_max_size') ?: 'unknown';
        erc_error(
            "No file received. The upload may exceed the server limit (post_max_size={$postMax}). "
            . 'Try a smaller .dcf or raise the PHP upload limits.'
        );
    }
    $file = $_FILES['file'];
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        erc_error(erc_upload_error_message($err));
    }
    $name = (string) ($file['name'] ?? 'ProcessPower.dcf');
    if (!preg_match('/\.dcf$/i', $name)) {
        erc_error('Please select a Plant 3D database file (.dcf).');
    }
    if (($file['size'] ?? 0) > 80 * 1024 * 1024) {
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
    unset($_SESSION['erc_working']);
    $loaded = ErcDcf::loadProject($target);
    erc_json([
        'ok' => true,
        'project' => $loaded['project'],
        'uploaded_filename' => $name,
    ]);
}

function erc_upload_error_message(int $code): string
{
    $uploadMax = ini_get('upload_max_filesize') ?: 'unknown';
    $postMax = ini_get('post_max_size') ?: 'unknown';
    return match ($code) {
        UPLOAD_ERR_INI_SIZE => "File is larger than the server upload_max_filesize limit ({$uploadMax}). "
            . 'Raise upload_max_filesize and post_max_size (see website/.user.ini), then retry.',
        UPLOAD_ERR_FORM_SIZE => 'File is larger than the form size limit.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was selected.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server temp folder is missing. Contact hosting support.',
        UPLOAD_ERR_CANT_WRITE => 'Could not write the uploaded file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the upload.',
        default => "Upload failed (error {$code}). Limits: upload_max_filesize={$uploadMax}, post_max_size={$postMax}.",
    };
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
        'has_logo' => erc_logo_path() !== null,
    ]);
}

function handle_details(): never
{
    $dcf = erc_current_dcf();
    if (!$dcf) {
        erc_error('No project uploaded.', 400);
    }
    $pdo = ErcDcf::connect($dcf);
    $details = ErcProject::details($pdo);
    $groups = [];
    foreach ($details['catalogue'] as $item) {
        $cat = $item['category'] ?? 'custom';
        $groups[$cat] = $groups[$cat] ?? [
            'category' => $cat,
            'category_label' => $item['category_label'] ?? $cat,
            'items' => [],
        ];
        $groups[$cat]['items'][] = $item;
    }
    erc_json(['ok' => true] + $details + ['groups' => array_values($groups)]);
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
    $loadedTpl = erc_load_active_template($templateId);
    $template = $loadedTpl['template'];
    $pdo = ErcDcf::connect($dcf);
    $template = ErcCatalog::enrichTemplateColumns($pdo, $template);
    $details = ErcProject::details($pdo);
    $merged = ErcProject::mergeHeader($template, $details);
    $raw = ErcQueries::run($pdo, (string) $merged['source']);
    $rows = ErcTemplates::apply($raw, $merged);
    $project = ErcDcf::loadProject($dcf)['project'];
    erc_json([
        'ok' => true,
        'template' => $merged,
        'template_id' => $templateId,
        'resolved_id' => $loadedTpl['resolved_id'],
        'project' => $project,
        'row_count' => count($rows),
        'raw_count' => count($raw),
        'rows' => $rows,
        'available_keys' => $raw ? array_keys($raw[0]) : [],
        'has_logo' => erc_logo_path() !== null,
        'profile' => erc_load_company_profile(),
    ]);
}

function handle_property_catalogue(): never
{
    $dcf = erc_current_dcf();
    if (!$dcf) {
        erc_error('No project uploaded.', 400);
    }
    $source = (string) ($_GET['source'] ?? '');
    $templateId = (string) ($_GET['template_id'] ?? '');
    $pdo = ErcDcf::connect($dcf);
    if ($source === '' && $templateId !== '') {
        $tpl = erc_load_active_template($templateId)['template'];
        $source = (string) ($tpl['source'] ?? '');
    }
    if ($source === '') {
        erc_error('source or template_id is required.');
    }
    $cat = ErcCatalog::propertyCatalogueForSource($pdo, $source);
    erc_json(['ok' => true] + $cat);
}

function handle_class_tree(): never
{
    $dcf = erc_current_dcf();
    if (!$dcf) {
        erc_error('No project uploaded.', 400);
    }
    $root = (string) ($_GET['root'] ?? 'EngineeringItems');
    $pdo = ErcDcf::connect($dcf);
    $tree = ErcCatalog::classTree($pdo, $root);
    erc_json(['ok' => true] + $tree);
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
    $profile = erc_load_company_profile();
    $exportOpts = $profile['export'] ?? [];
    // Optional one-shot overrides via query
    foreach (['include_logo', 'include_revision', 'include_pnpid'] as $key) {
        if (isset($_GET[$key])) {
            $exportOpts[$key] = !in_array(strtolower((string) $_GET[$key]), ['0', 'false', 'no'], true);
        }
    }
    $loadedTpl = erc_load_active_template($templateId);
    $template = $loadedTpl['template'];
    $pdo = ErcDcf::connect($dcf);
    $template = ErcCatalog::enrichTemplateColumns($pdo, $template);
    $details = ErcProject::details($pdo);
    $merged = ErcProject::mergeHeader($template, $details);
    $raw = ErcQueries::run($pdo, (string) $merged['source']);
    $rows = ErcTemplates::apply($raw, $merged);
    $project = ErcDcf::loadProject($dcf)['project'];
    $logo = !empty($exportOpts['include_logo']) ? erc_logo_path() : null;
    $bytes = ErcExcel::export($rows, $merged, $project, $logo, $exportOpts);
    $base = preg_replace('/_standard$/', '', $templateId) ?: $templateId;
    $filename = ($project['number'] ?: 'project') . '_' . $base . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store');
    echo $bytes;
    exit;
}

function handle_apply_template(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        erc_error('POST required', 405);
    }
    $body = erc_json_body();
    if (empty($body['template']) || !is_array($body['template'])) {
        erc_error('JSON body with template is required.');
    }
    $template = $body['template'];
    if (empty($template['id'])) {
        erc_error('template.id is required.');
    }
    erc_store_working_template($template);
    erc_json(['ok' => true, 'template' => $template]);
}

function handle_save_template(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        erc_error('POST required', 405);
    }
    $body = erc_json_body();
    $mode = (string) ($body['mode'] ?? 'standard');
    $baseId = preg_replace('/_standard$/', '', (string) ($body['base_id'] ?? $body['template']['id'] ?? '')) ?: '';
    if ($baseId === '') {
        erc_error('base_id is required.');
    }

    if ($mode === 'reset') {
        $factory = ErcTemplates::load($baseId, 'base');
        ErcTemplates::deleteCompanyStandard($baseId);
        $stdId = ErcTemplates::standardId($baseId);
        unset($_SESSION['erc_working'][$stdId], $_SESSION['erc_working'][$baseId]);
        $factory = erc_apply_company_defaults($factory, erc_load_company_profile());
        erc_json(['ok' => true, 'mode' => 'reset', 'template' => $factory]);
    }

    $template = $body['template'] ?? null;
    if (!is_array($template)) {
        $working = erc_working_template($baseId);
        $template = $working ?: ErcTemplates::load(ErcTemplates::resolveId($baseId));
    }
    $stdId = ErcTemplates::standardId($baseId);
    $template['id'] = $stdId;
    $name = (string) ($template['name'] ?? $baseId);
    $nameNl = (string) ($template['name_nl'] ?? $name);
    if (!str_contains($name, '(company standard)')) {
        $template['name'] = trim(preg_replace('/\s*\(company standard\)\s*/', '', $name) . ' (company standard)');
    }
    if (!str_contains($nameNl, '(bedrijfsstandaard)')) {
        $template['name_nl'] = trim(preg_replace('/\s*\(bedrijfsstandaard\)\s*/', '', $nameNl) . ' (bedrijfsstandaard)');
    }
    $exists = ErcTemplates::companyStandardExists($baseId);
    $overwrite = $mode === 'overwrite' || !$exists;
    if ($mode === 'standard' && $exists && empty($body['overwrite']) && ($body['force'] ?? false) !== true) {
        erc_error('Company standard already exists. Choose overwrite.', 409);
    }
    $saved = ErcTemplates::save($template, $overwrite);
    erc_store_working_template($saved);

    // Also persist company-wide header defaults from this template.
    if (!empty($body['save_company_header'])) {
        $profile = erc_load_company_profile();
        $header = $saved['header'] ?? [];
        $profile['header']['company'] = (string) ($header['company'] ?? $profile['header']['company']);
        $profile['header']['fields'] = $header['fields'] ?? $profile['header']['fields'];
        if (!empty($saved['revision_table'])) {
            $profile['header']['revision_table'] = $saved['revision_table'];
        }
        if (!empty($profile['header']['company'])) {
            $profile['company_name'] = $profile['header']['company'];
        }
        erc_save_company_profile($profile);
    }

    erc_json(['ok' => true, 'mode' => $mode, 'template' => $saved]);
}

function handle_logo(): never
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method === 'GET') {
        $path = erc_logo_path();
        if (!$path) {
            http_response_code(404);
            header('Content-Type: text/plain');
            echo 'No logo';
            exit;
        }
        $mime = str_ends_with(strtolower($path), '.png') ? 'image/png' : 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }
    if ($method !== 'POST') {
        erc_error('GET or POST required', 405);
    }
    if (empty($_FILES['file'])) {
        erc_error('No logo file uploaded.');
    }
    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        erc_error(erc_upload_error_message((int) $file['error']));
    }
    $name = (string) ($file['name'] ?? 'logo.png');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
        erc_error('Please upload a PNG or JPEG logo.');
    }
    $dir = erc_company_ensure_dirs();
    foreach ([$dir . '/logo.png', $dir . '/logo.jpg'] as $old) {
        if (is_file($old)) {
            @unlink($old);
        }
    }
    $target = $dir . '/' . ($ext === 'png' ? 'logo.png' : 'logo.jpg');
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        erc_error('Could not save logo.');
    }
    erc_json(['ok' => true, 'url' => 'api.php?action=logo&t=' . time()]);
}

function handle_clear(): never
{
    erc_purge_session_uploads();
    erc_json(['ok' => true]);
}

/** @return array<string,mixed> */
function erc_json_body(): array
{
    $raw = file_get_contents('php://input');
    $body = json_decode($raw ?: '[]', true);
    if (!is_array($body)) {
        erc_error('JSON body required.');
    }
    return $body;
}
