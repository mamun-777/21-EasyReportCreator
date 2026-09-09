<?php
declare(strict_types=1);

final class ErcTemplates
{
    public static function safeId(string $templateId): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $templateId) ?: 'template';
    }

    public static function standardId(string $baseId): string
    {
        $base = preg_replace('/_standard$/', '', $baseId) ?: $baseId;
        return $base . '_standard';
    }

    public static function factoryPath(string $templateId): string
    {
        return ERC_TEMPLATES . '/' . self::safeId($templateId) . '.json';
    }

    public static function companyPath(string $templateId): ?string
    {
        $dir = erc_company_templates_dir();
        if (!$dir) {
            return null;
        }
        return $dir . '/' . self::safeId($templateId) . '.json';
    }

    public static function exists(string $templateId): bool
    {
        $company = self::companyPath($templateId);
        if ($company && is_file($company)) {
            return true;
        }
        return is_file(self::factoryPath($templateId));
    }

    public static function companyStandardExists(string $baseId): bool
    {
        $path = self::companyPath(self::standardId($baseId));
        return $path !== null && is_file($path);
    }

    /** @return list<array<string,mixed>> */
    public static function listTemplates(): array
    {
        $items = [];
        foreach (glob(ERC_TEMPLATES . '/*.json') ?: [] as $path) {
            $data = json_decode((string) file_get_contents($path), true);
            if (!is_array($data)) {
                continue;
            }
            $id = (string) ($data['id'] ?? pathinfo($path, PATHINFO_FILENAME));
            if (str_ends_with($id, '_standard')) {
                continue;
            }
            $items[] = [
                'id' => $id,
                'name' => $data['name'] ?? $id,
                'name_nl' => $data['name_nl'] ?? ($data['name'] ?? $id),
                'source' => $data['source'] ?? '',
                'description' => $data['description'] ?? '',
                'has_standard' => self::companyStandardExists($id),
            ];
        }
        usort($items, fn($a, $b) => strcmp($a['id'], $b['id']));
        return $items;
    }

    public static function resolveId(string $templateId): string
    {
        $base = preg_replace('/_standard$/', '', $templateId) ?: $templateId;
        $preferred = self::standardId($base);
        if ($templateId === $base && self::companyStandardExists($base)) {
            return $preferred;
        }
        return $templateId;
    }

    /** @return array<string,mixed> */
    public static function load(string $templateId, string $variant = 'exact'): array
    {
        if ($variant === 'auto') {
            $templateId = self::resolveId($templateId);
        } elseif ($variant === 'base') {
            $templateId = preg_replace('/_standard$/', '', $templateId) ?: $templateId;
        }

        // Company standards live only under the company folder.
        if (str_ends_with($templateId, '_standard')) {
            $company = self::companyPath($templateId);
            if ($company && is_file($company)) {
                return self::decodeFile($company, $templateId);
            }
            throw new RuntimeException("Company standard not found: {$templateId}");
        }

        // Factory base templates.
        $factory = self::factoryPath($templateId);
        if (is_file($factory)) {
            return self::decodeFile($factory, $templateId);
        }
        throw new RuntimeException("Template not found: {$templateId}");
    }

    /** @param array<string,mixed> $data */
    public static function save(array $data, bool $overwrite = true): array
    {
        $templateId = self::safeId((string) ($data['id'] ?? ''));
        if ($templateId === '' || $templateId === 'template') {
            throw new RuntimeException('Template id is required');
        }
        if (!str_ends_with($templateId, '_standard')) {
            throw new RuntimeException('Only company standards can be saved (id must end with _standard).');
        }
        erc_company_ensure_dirs();
        $path = self::companyPath($templateId);
        if ($path === null) {
            throw new RuntimeException('Sign in required to save a company standard.');
        }
        if (is_file($path) && !$overwrite) {
            throw new RuntimeException("Template already exists: {$templateId}");
        }
        $data['id'] = $templateId;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($path, $json . "\n") === false) {
            throw new RuntimeException('Could not write company template (check data/companies permissions).');
        }
        return $data;
    }

    public static function deleteCompanyStandard(string $baseId): void
    {
        $path = self::companyPath(self::standardId($baseId));
        if ($path && is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $template
     * @return list<array<string,mixed>>
     */
    public static function apply(array $rows, array $template): array
    {
        $columns = [];
        foreach ($template['columns'] ?? [] as $col) {
            if (($col['visible'] ?? true) && !empty($col['key'])) {
                $columns[] = $col['key'];
            }
        }
        $sortKeys = $template['sort'] ?? [];

        $filtered = $rows;
        if ($sortKeys) {
            usort($filtered, static function ($a, $b) use ($sortKeys) {
                foreach ($sortKeys as $key) {
                    $cmp = strcmp((string) ($a[$key] ?? ''), (string) ($b[$key] ?? ''));
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }
                return 0;
            });
        }

        $projected = [];
        foreach ($filtered as $row) {
            $item = [];
            foreach ($columns as $key) {
                $item[$key] = $row[$key] ?? '';
            }
            if (array_key_exists('PnPID', $row)) {
                $item['PnPID'] = $row['PnPID'];
            }
            $projected[] = $item;
        }
        return $projected;
    }

    /** @return array<string,mixed> */
    private static function decodeFile(string $path, string $templateId): array
    {
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid template: {$templateId}");
        }
        return $data;
    }
}
