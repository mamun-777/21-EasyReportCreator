<?php
declare(strict_types=1);

final class ErcTemplates
{
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
                'has_standard' => is_file(ERC_TEMPLATES . '/' . $id . '_standard.json'),
            ];
        }
        usort($items, fn($a, $b) => strcmp($a['id'], $b['id']));
        return $items;
    }

    public static function resolveId(string $templateId): string
    {
        $base = preg_replace('/_standard$/', '', $templateId) ?: $templateId;
        $preferred = $base . '_standard';
        if ($templateId === $base && is_file(ERC_TEMPLATES . '/' . $preferred . '.json')) {
            return $preferred;
        }
        return $templateId;
    }

    /** @return array<string,mixed> */
    public static function load(string $templateId): array
    {
        $path = ERC_TEMPLATES . '/' . $templateId . '.json';
        if (!is_file($path)) {
            throw new RuntimeException("Template not found: {$templateId}");
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid template: {$templateId}");
        }
        return $data;
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
}
