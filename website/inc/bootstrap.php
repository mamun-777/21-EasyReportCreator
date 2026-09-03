<?php
declare(strict_types=1);

/**
 * Bootstrap for EasyReportCreator report engine (STRATO / PHP 8).
 */

const ERC_ROOT = __DIR__ . '/..';
const ERC_DATA = ERC_ROOT . '/data';
const ERC_UPLOADS = ERC_DATA . '/uploads';
const ERC_TEMPLATES = ERC_ROOT . '/report_templates';

require_once __DIR__ . '/plant3d/Dcf.php';
require_once __DIR__ . '/plant3d/Queries.php';
require_once __DIR__ . '/plant3d/Templates.php';
require_once __DIR__ . '/plant3d/Project.php';
require_once __DIR__ . '/plant3d/Excel.php';

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
    foreach ([ERC_DATA, ERC_UPLOADS] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    $ht = ERC_UPLOADS . '/.htaccess';
    if (!is_file($ht)) {
        file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
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

function erc_current_dcf(): ?string
{
    $path = $_SESSION['erc_dcf'] ?? '';
    if ($path && is_file($path)) {
        return $path;
    }
    return null;
}
