<?php
declare(strict_types=1);

final class ErcDcf
{
    public static function validate(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('DCF file not found.');
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'dcf') {
            throw new RuntimeException('Please select a Plant 3D database file (.dcf).');
        }
        try {
            $pdo = self::connect($path);
            $hasEi = self::tableExists($pdo, 'EngineeringItems');
            $hasProj = self::tableExists($pdo, 'PnPProject');
            if (!$hasEi && !$hasProj) {
                throw new RuntimeException('This file does not look like a Plant 3D ProcessPower database.');
            }
        } catch (PDOException $e) {
            throw new RuntimeException('The selected file is not a valid SQLite database.');
        }
    }

    public static function connect(string $path): PDO
    {
        $real = realpath($path) ?: $path;
        $pdo = new PDO('sqlite:' . $real, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA query_only = ON');
        return $pdo;
    }

    public static function tableExists(PDO $pdo, string $name): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type='table' AND name=? LIMIT 1");
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();
    }

    public static function tableCount(PDO $pdo, string $name): int
    {
        if (!self::tableExists($pdo, $name)) {
            return 0;
        }
        return (int) $pdo->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', $name) . '"')->fetchColumn();
    }

    /** @return array{project: array<string,mixed>, dcf_path: string} */
    public static function loadProject(string $dcfPath): array
    {
        self::validate($dcfPath);
        $pdo = self::connect($dcfPath);
        $info = [
            'name' => '',
            'description' => '',
            'number' => '',
            'location' => '',
            'location_code' => '',
            'status' => '',
            'standard' => '',
            'palette' => '',
            's88_code' => '',
            'dcf_path' => $dcfPath,
            'source' => 'upload',
            'counts' => [
                'drawings' => self::tableCount($pdo, 'PnPDrawings'),
                'equipment' => self::tableCount($pdo, 'Equipment'),
                'hand_valves' => self::tableCount($pdo, 'HandValves'),
                'control_valves' => self::tableCount($pdo, 'Gestuurdeafsluiters'),
                'instruments' => self::tableCount($pdo, 'Instrumentation'),
                'pipe_lines' => self::tableCount($pdo, 'PipeLines'),
                'line_groups' => self::tableCount($pdo, 'PipeLineGroup'),
                'engineering_items' => self::tableCount($pdo, 'EngineeringItems'),
                'components' => self::componentCount($pdo),
            ],
        ];
        $info['drawing_count'] = $info['counts']['drawings'];

        if (self::tableExists($pdo, 'PnPProject')) {
            $row = $pdo->query('SELECT * FROM PnPProject LIMIT 1')->fetch();
            if ($row) {
                $info['name'] = (string) ($row['Project_Name'] ?? '');
                $info['description'] = (string) ($row['Project_Description'] ?? '');
                $info['number'] = (string) ($row['Project_Number'] ?? $info['name']);
                $info['location'] = (string) ($row['S88_Locatie'] ?? '');
                $info['location_code'] = (string) ($row['S88_Locatiecode'] ?? '');
                $info['status'] = (string) ($row['S88_Projectstatus'] ?? '');
                $info['standard'] = (string) ($row['ToolPaletteGroupName'] ?? $row['Project_Standard'] ?? '');
                $info['palette'] = (string) ($row['ToolPaletteGroupName'] ?? '');
                $info['s88_code'] = (string) ($row['S88_Projectcode'] ?? '');
            }
        }

        return ['project' => $info, 'dcf_path' => $dcfPath];
    }

    /** Count EngineeringItems excluding pipe-line classes (matches components query). */
    public static function componentCount(PDO $pdo): int
    {
        if (!self::tableExists($pdo, 'EngineeringItems')) {
            return 0;
        }
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM EngineeringItems
             WHERE ClassName NOT IN ('Minor Pipe Line', 'Major Pipe Line', 'Pipe Line Group')"
        )->fetchColumn();
    }
}
