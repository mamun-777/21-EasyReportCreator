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

    /**
     * Fill title-block field values from the current DCF Project Details.
     * Company field *selection* (keys/labels) is kept; values always come from this project.
     *
     * @param array<string,mixed> $template
     * @param array{catalogue?: list<array<string,mixed>>, values?: array<string,string>, revisions?: list<array<string,string>>} $details
     * @return array<string,mixed>
     */
    public static function mergeHeader(array $template, array $details): array
    {
        $header = $template['header'] ?? [];
        $values = $details['values'] ?? [];
        $fields = [];
        foreach ($header['fields'] ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $fields[] = [
                'key' => $key,
                'label' => $field['label'] ?? (self::LABELS[$key] ?? str_replace('_', ' ', $key)),
                // FB-002: never keep stale values from company profile / previous project.
                'value' => (string) ($values[$key] ?? ''),
            ];
        }
        $header['fields'] = $fields;

        $projectNumber = trim((string) ($values['Project_Number'] ?? ''));
        if ($projectNumber === '') {
            $projectNumber = trim((string) ($values['Project_Name'] ?? ''));
        }
        if ($projectNumber !== '') {
            $listCode = self::documentListCode($template);
            $header['document_number'] = $projectNumber . '-' . $listCode;
        }

        $template['header'] = $header;

        if (!empty($details['revisions'])) {
            $template['revision_table'] = $details['revisions'];
        } elseif (self::isStaleDemoRevision($template['revision_table'] ?? [])) {
            $template['revision_table'] = [[
                'rev' => (string) ($header['revision'] ?? 'A'),
                'date' => date('Y-m-d'),
                'desc' => 'Issued from ' . (trim((string) ($values['Project_Name'] ?? 'uploaded project')) ?: 'uploaded project'),
                'drawn' => '',
                'checked' => '',
                'approved' => '',
            ]];
        }

        return $template;
    }

    /** @param array<string,mixed> $template */
    private static function documentListCode(array $template): string
    {
        $map = [
            'drawings' => 'DL',
            'equipment' => 'EL',
            'valves' => 'AL',
            'control_valves' => 'CV',
            'instruments' => 'IL',
            'lines' => 'LL',
            'line_summary' => 'LS',
            'components' => 'CL',
        ];
        $source = (string) ($template['source'] ?? '');
        if (isset($map[$source])) {
            return $map[$source];
        }
        $id = preg_replace('/_standard$/', '', (string) ($template['id'] ?? 'LST')) ?: 'LST';
        $parts = explode('_', $id);
        $letters = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= strtoupper($part[0]);
            }
        }
        return $letters !== '' ? $letters : 'LST';
    }

    /** @param list<array<string,mixed>>|mixed $rows */
    private static function isStaleDemoRevision(mixed $rows): bool
    {
        if (!is_array($rows) || $rows === []) {
            return true;
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $desc = strtolower((string) ($row['desc'] ?? ''));
            if (str_contains($desc, 'demo issue') || str_contains($desc, 'processpower.dcf')) {
                return true;
            }
        }
        return false;
    }
}
