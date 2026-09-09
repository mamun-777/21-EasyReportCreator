<?php
declare(strict_types=1);

/**
 * Bootstrap for EasyReportCreator report engine (STRATO / PHP 8).
 */

const ERC_ROOT = __DIR__ . '/..';
const ERC_DATA = ERC_ROOT . '/data';
const ERC_UPLOADS = ERC_DATA . '/uploads';
const ERC_COMPANIES = ERC_DATA . '/companies';
const ERC_TEMPLATES = ERC_ROOT . '/report_templates';
/** @deprecated Global logo paths — use erc_logo_path() (company-scoped). */
const ERC_LOGO_PNG = ERC_DATA . '/logo.png';
const ERC_LOGO_JPG = ERC_DATA . '/logo.jpg';

require_once __DIR__ . '/plant3d/Dcf.php';
require_once __DIR__ . '/plant3d/Queries.php';
require_once __DIR__ . '/plant3d/Templates.php';
require_once __DIR__ . '/plant3d/Project.php';
require_once __DIR__ . '/plant3d/Excel.php';
require_once __DIR__ . '/plant3d/Auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function erc_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function erc_error(string $message, int $status = 400): never
{
    erc_json(['ok' => false, 'detail' => $message], $status);
}

function erc_ensure_dirs(): void
{
    foreach ([ERC_DATA, ERC_UPLOADS, ERC_COMPANIES] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    foreach ([ERC_UPLOADS . '/.htaccess', ERC_COMPANIES . '/.htaccess', ERC_DATA . '/.htaccess'] as $ht) {
        if (!is_file($ht)) {
            file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
    }
    // IIS / Plesk on Windows — block script execution under runtime data.
    foreach ([ERC_UPLOADS . '/web.config', ERC_COMPANIES . '/web.config', ERC_DATA . '/web.config'] as $wc) {
        if (!is_file($wc)) {
            file_put_contents(
                $wc,
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<configuration>\n"
                . "  <system.webServer>\n"
                . "    <handlers><clear /></handlers>\n"
                . "    <defaultDocument enabled=\"false\" />\n"
                . "    <directoryBrowse enabled=\"false\" />\n"
                . "  </system.webServer>\n"
                . "</configuration>\n"
            );
        }
    }
}

function erc_company_id(): ?int
{
    $id = (int) ($_SESSION['erc_company_id'] ?? 0);
    return $id > 0 ? $id : null;
}

function erc_company_dir(?int $companyId = null): ?string
{
    $id = $companyId ?? erc_company_id();
    if (!$id) {
        return null;
    }
    return ERC_COMPANIES . '/' . $id;
}

function erc_company_ensure_dirs(?int $companyId = null): string
{
    erc_ensure_dirs();
    $dir = erc_company_dir($companyId);
    if ($dir === null) {
        throw new RuntimeException('No company in session.');
    }
    foreach ([$dir, $dir . '/templates'] as $path) {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
    return $dir;
}

function erc_company_templates_dir(?int $companyId = null): ?string
{
    $dir = erc_company_dir($companyId);
    return $dir ? $dir . '/templates' : null;
}

/** @return array<string,mixed> */
function erc_default_company_profile(): array
{
    return [
        'company_name' => '',
        'header' => [
            'company' => '',
            'fields' => [
                ['key' => 'Project_Name', 'label' => 'Project name'],
                ['key' => 'Project_Description', 'label' => 'Project description'],
                ['key' => 'Project_Number', 'label' => 'Project number'],
                ['key' => 'S88_Projectstatus', 'label' => 'Project status'],
                ['key' => 'S88_Locatie', 'label' => 'Location'],
            ],
            'revision_table' => [],
        ],
        'export' => [
            'include_logo' => true,
            'include_revision' => true,
            'include_pnpid' => true,
            'remember' => true,
        ],
    ];
}

/** @return array<string,mixed> */
function erc_load_company_profile(?int $companyId = null): array
{
    $dir = erc_company_dir($companyId);
    $defaults = erc_default_company_profile();
    if (!$dir) {
        return $defaults;
    }
    $path = $dir . '/profile.json';
    if (!is_file($path)) {
        $user = ErcAuth::currentUser();
        if ($user) {
            $defaults['company_name'] = $user['company_name'];
            $defaults['header']['company'] = $user['company_name'];
        }
        return $defaults;
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return $defaults;
    }
    return array_replace_recursive($defaults, $data);
}

/** @param array<string,mixed> $profile */
function erc_save_company_profile(array $profile, ?int $companyId = null): array
{
    $dir = erc_company_ensure_dirs($companyId);
    $merged = array_replace_recursive(erc_default_company_profile(), $profile);
    $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($dir . '/profile.json', $json . "\n") === false) {
        throw new RuntimeException('Could not save company profile.');
    }
    return $merged;
}

/**
 * Apply company-wide header defaults onto a list template (all sheets share logo/fields/company).
 * List-specific title / document_number / revision / date are kept when already set.
 *
 * @param array<string,mixed> $template
 * @param array<string,mixed> $profile
 * @return array<string,mixed>
 */
function erc_apply_company_defaults(array $template, array $profile): array
{
    $header = $template['header'] ?? [];
    $companyHeader = $profile['header'] ?? [];
    if (!empty($companyHeader['company'])) {
        $header['company'] = $companyHeader['company'];
    } elseif (!empty($profile['company_name'])) {
        $header['company'] = $profile['company_name'];
    }
    if (!empty($companyHeader['fields']) && is_array($companyHeader['fields'])) {
        $header['fields'] = $companyHeader['fields'];
    }
    if (empty($template['revision_table']) && !empty($companyHeader['revision_table'])) {
        $template['revision_table'] = $companyHeader['revision_table'];
    }
    $template['header'] = $header;
    if (isset($profile['export']['include_pnpid'])) {
        $template['include_pnpid'] = (bool) $profile['export']['include_pnpid'];
    }
    return $template;
}

function erc_session_upload_dir(): string
{
    erc_ensure_dirs();
    $id = session_id() ?: bin2hex(random_bytes(8));
    $dir = ERC_UPLOADS . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

/** Delete the current session's uploaded .dcf folder (privacy). */
function erc_purge_session_uploads(): void
{
    $dcf = $_SESSION['erc_dcf'] ?? '';
    if (is_string($dcf) && $dcf !== '' && is_file($dcf)) {
        @unlink($dcf);
        $dir = dirname($dcf);
        if (is_dir($dir) && realpath($dir) !== realpath(ERC_UPLOADS)) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        }
    }
    unset($_SESSION['erc_dcf'], $_SESSION['erc_uploaded_name'], $_SESSION['erc_working']);
}

/**
 * Remove orphan upload folders older than $maxAgeSeconds (default 24 h).
 * Safe to call on each request; cheap when the uploads tree is small.
 */
function erc_cleanup_stale_uploads(int $maxAgeSeconds = 86400): int
{
    erc_ensure_dirs();
    $removed = 0;
    $cutoff = time() - max(3600, $maxAgeSeconds);
    foreach (glob(ERC_UPLOADS . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $mtime = @filemtime($dir);
        if ($mtime === false || $mtime > $cutoff) {
            continue;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        if (@rmdir($dir)) {
            $removed++;
        }
    }
    return $removed;
}

function erc_current_dcf(): ?string
{
    $path = $_SESSION['erc_dcf'] ?? '';
    if ($path && is_file($path)) {
        return $path;
    }
    return null;
}

function erc_logo_path(?int $companyId = null): ?string
{
    $dir = erc_company_dir($companyId);
    if ($dir) {
        foreach ([$dir . '/logo.png', $dir . '/logo.jpg'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
    }
    // Legacy global logos (pre-auth installs)
    foreach ([ERC_LOGO_PNG, ERC_LOGO_JPG] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

/** @param array<string,mixed> $template */
function erc_store_working_template(array $template): void
{
    $id = (string) ($template['id'] ?? '');
    if ($id === '') {
        return;
    }
    $_SESSION['erc_working'][$id] = $template;
    $base = preg_replace('/_standard$/', '', $id) ?: $id;
    $_SESSION['erc_working'][$base] = $template;
}

/** @return array<string,mixed>|null */
function erc_working_template(string $templateId): ?array
{
    $exact = $_SESSION['erc_working'][$templateId] ?? null;
    if (is_array($exact)) {
        return $exact;
    }
    $resolved = ErcTemplates::resolveId($templateId);
    $byResolved = $_SESSION['erc_working'][$resolved] ?? null;
    return is_array($byResolved) ? $byResolved : null;
}

/**
 * Load template preferring session working copy, then company/factory disk.
 * @return array{template: array<string,mixed>, resolved_id: string}
 */
function erc_load_active_template(string $templateId): array
{
    $working = erc_working_template($templateId);
    if ($working) {
        $tpl = erc_apply_company_defaults($working, erc_load_company_profile());
        return ['template' => $tpl, 'resolved_id' => (string) ($working['id'] ?? $templateId)];
    }
    $resolved = ErcTemplates::resolveId($templateId);
    $template = ErcTemplates::load($resolved);
    $template = erc_apply_company_defaults($template, erc_load_company_profile());
    return ['template' => $template, 'resolved_id' => $resolved];
}

// Opportunistic privacy sweep — orphaned ProcessPower.dcf folders after 24 h.
if (random_int(1, 20) === 1) {
    try {
        erc_cleanup_stale_uploads(86400);
    } catch (Throwable) {
        /* ignore */
    }
}
