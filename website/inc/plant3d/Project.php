<?php
declare(strict_types=1);

final class ErcProject
{
    private const STANDARD_KEYS = [
        'Project_Name', 'Project_Description', 'Project_Number',
        'Project_Standard', 'Version', 'ToolPaletteGroupName', 'ToolPaletteGroupNameForPiping',
    ];

    private const LABELS = [
        'Project_Name' => 'Project name',
        'Project_Description' => 'Project description',
        'Project_Number' => 'Project number',
        'Project_Standard' => 'Project standard',
        'Version' => 'Version',
        'ToolPaletteGroupName' => 'Tool palette group',
        'ToolPaletteGroupNameForPiping' => 'Piping tool palette group',
        'S88_Projectcode' => 'Project code',
        'S88_Locatiesoort' => 'Location type',
        'S88_Locatie' => 'Location',
        'S88_Projectstatus' => 'Project status',
        'S88_Procesgroep' => 'Process group',
        'S88_Locatiecode' => 'Location code',
    ];

    /** @return array{catalogue: list<array<string,mixed>>, values: array<string,string>, revisions: list<array<string,string>>} */
    public static function details(PDO $pdo): array
    {
        $values = [];
        $catalogue = [];
        $revisions = [];
        if (!ErcDcf::tableExists($pdo, 'PnPProject')) {
            return ['catalogue' => [], 'values' => [], 'revisions' => []];
        }
        $row = $pdo->query('SELECT * FROM PnPProject LIMIT 1')->fetch();
        if (!$row) {
            return ['catalogue' => [], 'values' => [], 'revisions' => []];
        }

        $byRev = [];
        foreach ($row as $key => $value) {
            $clean = is_string($value) ? trim($value) : (string) ($value ?? '');
            if ($clean === '' || str_starts_with($key, 'PnP')) {
                continue;
            }
            $values[$key] = $clean;
            if (preg_match('/^Projectrevisie_Revisiedatum\s+(.+)$/', $key, $m)) {
                $byRev[$m[1]]['date'] = $clean;
                continue;
            }
            if (preg_match('/^Projectrevisie_Gewijzigd\s+(.+)$/', $key, $m)) {
                $byRev[$m[1]]['desc'] = $clean;
                continue;
            }
            $category = in_array($key, self::STANDARD_KEYS, true)
                ? 'standard'
                : (str_starts_with($key, 'S88_') ? 'S88' : 'custom');
            $catalogue[] = [
                'key' => $key,
                'label' => self::LABELS[$key] ?? str_replace('_', ' ', $key),
                'value' => $clean,
                'category' => $category,
                'category_label' => match ($category) {
                    'standard' => 'Standard project fields',
                    'S88' => 'Custom properties (S88)',
                    default => 'Other custom properties',
                },
            ];
        }

        foreach ($byRev as $rev => $item) {
            if (($item['date'] ?? '') === '' && ($item['desc'] ?? '') === '') {
                continue;
            }
            $revisions[] = [
                'rev' => (string) $rev,
                'date' => $item['date'] ?? '',
                'desc' => $item['desc'] ?? '',
                'drawn' => '',
                'checked' => '',
                'approved' => '',
            ];
        }

        return ['catalogue' => $catalogue, 'values' => $values, 'revisions' => $revisions];
    }

    /** @param array<string,mixed> $template */
    public static function mergeHeader(array $template, array $details): array
    {
        $header = $template['header'] ?? [];
        $values = $details['values'] ?? [];
        $fields = [];
        foreach ($header['fields'] ?? [] as $field) {
            $key = $field['key'] ?? '';
            $fields[] = [
                'key' => $key,
                'label' => $field['label'] ?? $key,
                'value' => $field['value'] ?? ($values[$key] ?? ''),
            ];
        }
        $header['fields'] = $fields;
        $template['header'] = $header;
        if (empty($template['revision_table']) && !empty($details['revisions'])) {
            $template['revision_table'] = $details['revisions'];
        }
        return $template;
    }
}
