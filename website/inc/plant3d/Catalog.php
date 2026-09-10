<?php
declare(strict_types=1);

/**
 * Class tree + property catalogue from Plant 3D PnP metadata (FB-001 / R9).
 * Ports app/plant3d/catalog.py for project-agnostic column discovery.
 */
final class ErcCatalog
{
    public const SOURCE_ROOT_CLASS = [
        'drawings' => 'PnPDrawings',
        'equipment' => 'Equipment',
        'valves' => 'HandValves',
        'control_valves' => 'Gestuurdeafsluiters',
        'instruments' => 'Instrumentation',
        'lines' => 'PipeLines',
        'line_summary' => 'PipeLineGroup',
        'components' => 'EngineeringItems',
    ];

    /** Alternate physical table names when Vitens-specific classes are absent. */
    public const SOURCE_TABLE_ALIASES = [
        'control_valves' => ['Gestuurdeafsluiters', 'ControlValves', 'ControlValvesHand', 'ActuatedValves'],
    ];

    private const ENGLISH_LABELS = [
        'ClassName' => 'Class Name',
        'Status' => 'Status',
        'Tag' => 'Tag',
        'PnPID' => 'PnPID',
        'Size' => 'Size',
        'Spec' => 'Spec',
        'Material' => 'Material',
        'Mat' => 'Material',
        'Omschrijving' => 'Description',
        'Description' => 'Description',
        'ProcMed' => 'Medium',
        'ProcAanslDiam' => 'Process connection diameter',
        'OudeTagNummer' => 'Old tag',
        'Tagopmerking' => 'Tag remark',
        'Spanning' => 'Voltage (V)',
        'Stroom' => 'Nominal current (A)',
        'EVerm' => 'Electrical power',
        'Eindcontacten' => 'End contacts',
        'NONC' => 'NO / NC',
        'Meetsignaal' => 'Measuring signal',
        'Alarmtypehoog' => 'Alarm type high',
        'Normally' => 'Normally',
        'EndConnections' => 'End connections',
        'Manufacturer' => 'Manufacturer',
        'Fabrikant' => 'Manufacturer',
        'Type' => 'Type',
        'Remarks' => 'Remarks',
        'Opm' => 'Remarks',
        'Opmerkingen' => 'Remarks',
    ];

    /** @var array<string, list<string>> */
    private static array $columnCache = [];

    private static function columnCacheKey(PDO $pdo, string $table): string
    {
        return spl_object_id($pdo) . "\0" . $table;
    }

    /** @return list<string> */
    public static function tableColumns(PDO $pdo, string $table): array
    {
        $key = self::columnCacheKey($pdo, $table);
        if (isset(self::$columnCache[$key])) {
            return self::$columnCache[$key];
        }
        if (!ErcDcf::tableExists($pdo, $table)) {
            return self::$columnCache[$key] = [];
        }
        $quoted = '"' . str_replace('"', '""', $table) . '"';
        $cols = [];
        foreach ($pdo->query('PRAGMA table_info(' . $quoted . ')') as $row) {
            $cols[] = (string) $row['name'];
        }
        return self::$columnCache[$key] = $cols;
    }

    public static function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        return in_array($column, self::tableColumns($pdo, $table), true);
    }

    public static function resolveSourceTable(PDO $pdo, string $source): ?string
    {
        $primary = self::SOURCE_ROOT_CLASS[$source] ?? null;
        $candidates = self::SOURCE_TABLE_ALIASES[$source] ?? [];
        if ($primary !== null) {
            array_unshift($candidates, $primary);
        }
        foreach (array_unique($candidates) as $name) {
            if (ErcDcf::tableExists($pdo, $name)) {
                return $name;
            }
        }
        return $primary;
    }

    /**
     * @return array{root: string, tree: array<string,mixed>, flat: list<array<string,mixed>>, node_count: int}
     */
    public static function classTree(PDO $pdo, string $root = 'EngineeringItems'): array
    {
        self::requireMeta($pdo);
        $tables = self::loadTables($pdo);
        $byName = [];
        foreach ($tables as $t) {
            $byName[$t['name']] = $t;
        }
        if (!isset($byName[$root])) {
            throw new InvalidArgumentException("Class '{$root}' not found in PnPTables");
        }

        $childrenMap = [];
        foreach ($tables as $t) {
            if ($t['base'] !== '') {
                $childrenMap[$t['base']][] = $t['name'];
            }
        }
        foreach ($childrenMap as &$names) {
            natcasesort($names);
            $names = array_values($names);
        }
        unset($names);

        $displays = self::tableDisplayNames($pdo);

        $build = function (string $name, int $depth) use (&$build, $byName, $childrenMap, $displays): array {
            $meta = $byName[$name];
            $node = [
                'id' => $name,
                'name' => $name,
                'display_name' => $displays[$name] ?? self::humanLabel($name),
                'abstract' => $meta['abstract'],
                'children' => [],
            ];
            foreach ($childrenMap[$name] ?? [] as $child) {
                $node['children'][] = $build($child, $depth + 1);
            }
            return $node;
        };

        $tree = $build($root, 0);
        $flat = self::flattenTree($tree);
        return [
            'root' => $root,
            'tree' => $tree,
            'flat' => $flat,
            'node_count' => count($flat),
            'status' => 'ok',
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function propertiesForClass(
        PDO $pdo,
        string $className,
        bool $includeInherited = true,
        bool $includeSystem = false,
        bool $includeHidden = true
    ): array {
        self::requireMeta($pdo);
        $tables = self::loadTables($pdo);
        $byName = [];
        foreach ($tables as $t) {
            $byName[$t['name']] = $t;
        }
        if (!isset($byName[$className])) {
            // Physical table may exist without PnPTables row — fall back to PRAGMA columns.
            return self::propertiesFromPhysicalTable($pdo, $className);
        }

        $chain = $includeInherited ? self::classAncestors($pdo, $className) : [$className];
        $merged = [];
        foreach (array_reverse($chain) as $table) {
            foreach (self::propertiesDefinedOn($pdo, $table) as $prop) {
                $merged[$prop['key']] = $prop;
            }
        }

        $items = array_values($merged);
        if (!$includeSystem) {
            $items = array_values(array_filter($items, static fn($p) => empty($p['is_system'])));
        }
        if (!$includeHidden) {
            $items = array_values(array_filter($items, static fn($p) => empty($p['is_hidden'])));
        }

        usort($items, static function ($a, $b) use ($className) {
            $aLocal = ($a['table_defined_on'] ?? '') === $className ? 0 : 1;
            $bLocal = ($b['table_defined_on'] ?? '') === $className ? 0 : 1;
            if ($aLocal !== $bLocal) {
                return $aLocal <=> $bLocal;
            }
            return strcasecmp((string) $a['label'], (string) $b['label'])
                ?: strcasecmp((string) $a['key'], (string) $b['key']);
        });

        return $items;
    }

    /**
     * @return array{source: string, class_name: string, display_name: string, ancestors: list<string>, properties: list<array<string,mixed>>, count: int, status: string}
     */
    public static function propertyCatalogueForSource(PDO $pdo, string $source): array
    {
        $className = self::resolveSourceTable($pdo, $source) ?? (self::SOURCE_ROOT_CLASS[$source] ?? null);
        if ($className === null) {
            throw new InvalidArgumentException("Unknown report source: {$source}");
        }
        $props = [];
        try {
            $props = self::propertiesForClass($pdo, $className);
        } catch (Throwable $e) {
            $props = self::propertiesFromPhysicalTable($pdo, $className);
        }

        // Always merge EngineeringItems props for item-based lists (shared attributes).
        if (in_array($source, ['equipment', 'valves', 'control_valves', 'instruments', 'lines', 'components'], true)
            && $className !== 'EngineeringItems'
            && ErcDcf::tableExists($pdo, 'EngineeringItems')
        ) {
            $byKey = [];
            foreach (self::propertiesForClass($pdo, 'EngineeringItems') as $p) {
                $byKey[$p['key']] = $p;
            }
            foreach ($props as $p) {
                $byKey[$p['key']] = $p;
            }
            $props = array_values($byKey);
            usort($props, static fn($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));
        }

        return [
            'source' => $source,
            'class_name' => $className,
            'display_name' => self::classDisplayName($pdo, $className),
            'ancestors' => self::safeAncestors($pdo, $className),
            'properties' => $props,
            'count' => count($props),
            'status' => 'ok',
        ];
    }

    /**
     * Merge live catalogue into template columns (adds missing props as hidden).
     *
     * @param array<string,mixed> $template
     * @return array<string,mixed>
     */
    public static function enrichTemplateColumns(PDO $pdo, array $template): array
    {
        $source = (string) ($template['source'] ?? '');
        if ($source === '') {
            return $template;
        }
        try {
            $cat = self::propertyCatalogueForSource($pdo, $source);
        } catch (Throwable $e) {
            $template['catalogue_error'] = $e->getMessage();
            return $template;
        }

        $byKey = [];
        foreach ($template['columns'] ?? [] as $col) {
            if (!empty($col['key'])) {
                $byKey[(string) $col['key']] = $col;
            }
        }
        foreach ($cat['properties'] as $prop) {
            $key = (string) $prop['key'];
            if ($key === '' || $key === 'PnPID') {
                continue;
            }
            if (!isset($byKey[$key])) {
                $byKey[$key] = [
                    'key' => $key,
                    'header' => $prop['label'],
                    'header_en' => $prop['label'],
                    'width' => 16,
                    'visible' => false,
                    'from_catalogue' => true,
                ];
            } else {
                $byKey[$key]['from_catalogue'] = true;
                if (empty($byKey[$key]['header']) || $byKey[$key]['header'] === $key) {
                    $byKey[$key]['header'] = $prop['label'];
                }
            }
        }
        $template['columns'] = array_values($byKey);
        $template['catalogue_count'] = $cat['count'];
        $template['catalogue_class'] = $cat['class_name'];
        return $template;
    }

    /** @return list<string> */
    public static function classAncestors(PDO $pdo, string $className): array
    {
        self::requireMeta($pdo);
        $byName = [];
        foreach (self::loadTables($pdo) as $t) {
            $byName[$t['name']] = $t;
        }
        $chain = [];
        $cur = $className;
        $seen = [];
        while ($cur !== '' && !isset($seen[$cur])) {
            if (!isset($byName[$cur])) {
                break;
            }
            $seen[$cur] = true;
            $chain[] = $cur;
            $cur = (string) ($byName[$cur]['base'] ?? '');
        }
        return $chain;
    }

    public static function classDisplayName(PDO $pdo, string $className): string
    {
        $displays = self::tableDisplayNames($pdo);
        return $displays[$className] ?? self::humanLabel($className);
    }

    public static function humanLabel(string $key): string
    {
        if (isset(self::ENGLISH_LABELS[$key])) {
            return self::ENGLISH_LABELS[$key];
        }
        $spaced = str_replace('_', ' ', $key);
        $out = '';
        $len = strlen($spaced);
        for ($i = 0; $i < $len; $i++) {
            $ch = $spaced[$i];
            if ($i > 0 && ctype_upper($ch) && ctype_lower($spaced[$i - 1])) {
                $out .= ' ';
            }
            $out .= $ch;
        }
        return $out;
    }

    private static function requireMeta(PDO $pdo): void
    {
        foreach (['PnPTables', 'PnPProperties'] as $name) {
            if (!ErcDcf::tableExists($pdo, $name)) {
                throw new RuntimeException("DCF is missing {$name}; cannot build class catalogue.");
            }
        }
    }

    /** @return list<array{name: string, base: string, abstract: bool, physical_name: string}> */
    private static function loadTables(PDO $pdo): array
    {
        $out = [];
        foreach ($pdo->query('SELECT TableName, BaseTable, Abstract, PhysicalName FROM PnPTables') as $row) {
            $out[] = [
                'name' => (string) $row['TableName'],
                'base' => (string) ($row['BaseTable'] ?? ''),
                'abstract' => self::truthy($row['Abstract'] ?? null),
                'physical_name' => self::clean($row['PhysicalName'] ?? ''),
            ];
        }
        return $out;
    }

    /** @return array<string, string> */
    private static function tableDisplayNames(PDO $pdo): array
    {
        if (!ErcDcf::tableExists($pdo, 'PnPTableAttributes')) {
            return [];
        }
        $out = [];
        $stmt = $pdo->query(
            "SELECT TableName, AttributeValue FROM PnPTableAttributes WHERE AttributeName = 'DisplayName'"
        );
        foreach ($stmt as $row) {
            $out[(string) $row['TableName']] = self::clean($row['AttributeValue'] ?? '');
        }
        return $out;
    }

    /** @return array<string, array<string, string>> */
    private static function columnAttributes(PDO $pdo, string $tableName): array
    {
        if (!ErcDcf::tableExists($pdo, 'PnPColumnAttributes')) {
            return [];
        }
        $attrs = [];
        $stmt = $pdo->prepare(
            'SELECT ColumnName, AttributeName, AttributeValue FROM PnPColumnAttributes WHERE TableName = ?'
        );
        $stmt->execute([$tableName]);
        foreach ($stmt as $row) {
            $col = (string) $row['ColumnName'];
            $attr = strtoupper((string) ($row['AttributeName'] ?? ''));
            $attrs[$col][$attr] = self::clean($row['AttributeValue'] ?? '');
        }
        return $attrs;
    }

    /** @return list<array<string,mixed>> */
    private static function propertiesDefinedOn(PDO $pdo, string $tableName): array
    {
        $attrs = self::columnAttributes($pdo, $tableName);
        $items = [];
        $stmt = $pdo->prepare(
            'SELECT PropertyName, PropertyType, IsSystem, IsExpression, Expression, Length, IsUnique
             FROM PnPProperties WHERE TableName = ? ORDER BY PropertyName'
        );
        $stmt->execute([$tableName]);
        foreach ($stmt as $row) {
            $key = (string) $row['PropertyName'];
            $meta = $attrs[$key] ?? [];
            $display = $meta['DISPLAYNAME'] ?? '';
            $items[] = [
                'key' => $key,
                'label' => $display !== '' ? $display : self::humanLabel($key),
                'type' => self::clean($row['PropertyType'] ?? '') ?: ($meta['TYPE'] ?? ''),
                'table_defined_on' => $tableName,
                'is_system' => self::truthy($row['IsSystem'] ?? null),
                'is_expression' => self::truthy($row['IsExpression'] ?? null),
                'expression' => self::clean($row['Expression'] ?? ''),
                'length' => $row['Length'],
                'is_unique' => self::truthy($row['IsUnique'] ?? null),
                'is_readonly' => self::truthy($meta['ISREADONLY'] ?? null),
                'is_hidden' => self::truthy($meta['ISHIDDEN'] ?? null),
                'description' => $meta['DESCRIPTION'] ?? '',
                'picklist' => $meta['PICKLISTNAME'] ?? '',
            ];
        }
        return $items;
    }

    /** @return list<array<string,mixed>> */
    private static function propertiesFromPhysicalTable(PDO $pdo, string $table): array
    {
        $items = [];
        foreach (self::tableColumns($pdo, $table) as $col) {
            if ($col === 'PnPID') {
                continue;
            }
            $items[] = [
                'key' => $col,
                'label' => self::humanLabel($col),
                'type' => '',
                'table_defined_on' => $table,
                'is_system' => false,
                'is_expression' => false,
                'expression' => '',
                'length' => null,
                'is_unique' => false,
                'is_readonly' => false,
                'is_hidden' => false,
                'description' => '',
                'picklist' => '',
            ];
        }
        return $items;
    }

    /** @return list<string> */
    private static function safeAncestors(PDO $pdo, string $className): array
    {
        try {
            return self::classAncestors($pdo, $className);
        } catch (Throwable $e) {
            return [$className];
        }
    }

    /** @param array<string,mixed> $node
     * @return list<array<string,mixed>>
     */
    private static function flattenTree(array $node, int $depth = 0): array
    {
        $item = [
            'id' => $node['id'],
            'name' => $node['name'],
            'display_name' => $node['display_name'],
            'abstract' => $node['abstract'] ?? false,
            'depth' => $depth,
            'child_count' => count($node['children'] ?? []),
        ];
        $out = [$item];
        foreach ($node['children'] ?? [] as $child) {
            $out = array_merge($out, self::flattenTree($child, $depth + 1));
        }
        return $out;
    }

    private static function truthy(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y'], true);
    }

    private static function clean(mixed $value): string
    {
        if ($value === null || is_array($value)) {
            return '';
        }
        if (is_resource($value)) {
            return '';
        }
        return trim((string) $value);
    }
}
