<?php
declare(strict_types=1);

const SITE = [
    'name' => 'EasyReportCreator',
    'domain' => 'easyreportcreator.com',
    'url' => 'https://easyreportcreator.com',
    'support_email' => 'support@easyreportcreator.com',
    'company' => 'TSPD',
    'company_url' => 'https://www.tspd.nl',
    'region' => 'Netherlands / EU',
    'product_version' => '1.0.0',
    'year' => '2026',
];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_active(string $page, string $current): bool
{
    return $page === $current;
}
