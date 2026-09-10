<?php
declare(strict_types=1);

/**
 * List queries — project-agnostic (FB-001).
 * Missing Vitens-only columns become NULL; EngineeringItems physical columns are always projected.
 */
final class ErcQueries
{
    private const LINE_REL_CTE = <<<'SQL'
WITH asset_line AS (
    SELECT r_asset.ROWID AS AssetId, r_line.ROWID AS LineId
    FROM PnPRowRelations r_asset
    JOIN PnPRowRelations r_line
      ON r_asset.RELID = r_line.RELID
     AND r_asset.RelationshipTypeName = r_line.RelationshipTypeName
     AND r_asset.ROWID <> r_line.ROWID
    WHERE r_asset.RelationshipTypeName IN ('LineInlineAsset', 'LineStartAsset', 'LineEndAsset')
),
line_group AS (
    SELECT r_pl.ROWID AS PipeLineId, r_g.ROWID AS GroupId
    FROM PnPRowRelations r_pl
    JOIN PnPRowRelations r_g
      ON r_pl.RELID = r_g.RELID
     AND r_pl.RelationshipTypeName = r_g.RelationshipTypeName
     AND r_pl.ROWID <> r_g.ROWID
    WHERE r_pl.RelationshipTypeName = 'PipeLineGroupRelationship'
),
dwg AS (
    SELECT RowId, MIN(DwgId) AS DwgId
    FROM PnPDataLinks
    GROUP BY RowId
)
SQL;

    /** @return list<array<string,mixed>> */
    public static function run(PDO $pdo, string $source): array
    {
        return match ($source) {
            'drawings' => self::drawings($pdo),
            'equipment' => self::equipment($pdo),
            'valves' => self::valves($pdo),
            'control_valves' => self::controlValves($pdo),
            'instruments' => self::instruments($pdo),
            'lines' => self::lines($pdo),
            'line_summary' => self::lineSummary($pdo),
            'components' => self::components($pdo),
            default => throw new InvalidArgumentException("Unknown report source: {$source}"),
        };
    }

    /** @return list<array<string,mixed>> */
    private static function fetch(PDO $pdo, string $sql): array
    {
        $out = [];
        foreach ($pdo->query($sql) as $row) {
            $item = [];
            foreach ($row as $k => $v) {
                if (is_int($k)) {
                    continue;
                }
                $item[$k] = self::clean($v);
            }
            $out[] = $item;
        }
        return $out;
    }

    private static function clean(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }

    private static function q(string $ident): string
    {
        return '"' . str_replace('"', '""', $ident) . '"';
    }

    /** table.col AS alias, or NULL AS alias when the physical column is missing. */
    private static function ref(PDO $pdo, string $table, string $col, string $alias, string $tableAlias): string
    {
        if (ErcCatalog::tableHasColumn($pdo, $table, $col)) {
            return $tableAlias . '.' . self::q($col) . ' AS ' . self::q($alias);
        }
        return 'NULL AS ' . self::q($alias);
    }

    /** @param list<array{0:string,1:string,2:string}> $candidates [table, column, alias] */
    private static function coalesce(PDO $pdo, array $candidates, string $as): string
    {
        $parts = [];
        foreach ($candidates as [$table, $col, $tableAlias]) {
            if (ErcCatalog::tableHasColumn($pdo, $table, $col)) {
                $parts[] = 'NULLIF(TRIM(' . $tableAlias . '.' . self::q($col) . "), '')";
            }
        }
        if ($parts === []) {
            return 'NULL AS ' . self::q($as);
        }
        if (count($parts) === 1) {
            return $parts[0] . ' AS ' . self::q($as);
        }
        return 'COALESCE(' . implode(', ', $parts) . ') AS ' . self::q($as);
    }

    /** Project all physical columns from a table (skip keys already selected elsewhere). */
    private static function allPhysical(PDO $pdo, string $table, string $tableAlias, array $skip = ['PnPID']): string
    {
        $parts = [];
        foreach (ErcCatalog::tableColumns($pdo, $table) as $col) {
            if (in_array($col, $skip, true)) {
                continue;
            }
            $parts[] = $tableAlias . '.' . self::q($col) . ' AS ' . self::q($col);
        }
        return $parts === [] ? 'NULL AS ' . self::q('_empty_' . $tableAlias) : implode(",\n            ", $parts);
    }

    private static function drawingTitleExpr(PDO $pdo): string
    {
        return self::coalesce($pdo, [
            ['PnPDrawings', 'Kader_Inh1', 'd'],
            ['PnPDrawings', 'Title', 'd'],
        ], 'DrawingTitle');
    }

    private static function drawings(PDO $pdo): array
    {
        if (!ErcDcf::tableExists($pdo, 'PnPDrawings')) {
            return [];
        }
        $title = self::coalesce($pdo, [
            ['PnPDrawings', 'Kader_Inh1', 'd'],
            ['PnPDrawings', 'Title', 'd'],
        ], 'Title');
        $sql = 'SELECT
            d.PnPID,
            ' . self::ref($pdo, 'PnPDrawings', 'PnID', 'PnID', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Dwg Name', 'DwgName', 'd') . ',
            ' . $title . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Inh2', 'Subtitle', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Procesopstal', 'Area', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Wijziging', 'Revision', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Stempel PID Status', 'Status', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Stempel PID Status Datum', 'StatusDate', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Getekend door', 'DrawnBy', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Datum getekend', 'DrawnDate', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Datum wijziging', 'RevDate', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'PnPRelativePath', 'Path', 'd') . ',
            ' . self::allPhysical($pdo, 'PnPDrawings', 'd', ['PnPID']) . '
        FROM PnPDrawings d
        ORDER BY d.' . (ErcCatalog::tableHasColumn($pdo, 'PnPDrawings', 'PnID') ? self::q('PnID') : self::q('PnPID'));
        return self::fetch($pdo, $sql);
    }

    private static function equipment(PDO $pdo): array
    {
        if (!ErcDcf::tableExists($pdo, 'Equipment')) {
            return [];
        }
        $ei = self::allPhysical($pdo, 'EngineeringItems', 'ei');
        $sql = self::LINE_REL_CTE . '
        SELECT
            e.PnPID,
            ' . self::ref($pdo, 'Equipment', 'Tag', 'Tag', 'e') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ClassName', 'ObjectType', 'ei') . ',
            ' . $ei . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Type_omschrijv', 'Type', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Cap', 'Capacity', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Inhoud_m3', 'Volume_m3', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Vermogen', 'Power', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'EVerm', 'Electrical_kW', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Spanning', 'Voltage', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Stroom', 'Current_A', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Mat', 'Material', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ProcMed', 'Medium', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ProcAanslDiam', 'ProcessSize', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Opm', 'Remarks', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Bladnummer', 'Sheet', 'ei') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'PnID', 'PnID', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Dwg Name', 'DwgName', 'd') . ',
            ' . self::drawingTitleExpr($pdo) . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Procesopstal', 'Area', 'd') . '
        FROM Equipment e
        JOIN EngineeringItems ei ON ei.PnPID = e.PnPID
        LEFT JOIN dwg ON dwg.RowId = e.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        ORDER BY Tag';
        return self::fetch($pdo, $sql);
    }

    private static function valves(PDO $pdo): array
    {
        if (!ErcDcf::tableExists($pdo, 'HandValves')) {
            return [];
        }
        $ei = self::allPhysical($pdo, 'EngineeringItems', 'ei');
        $hvExtra = self::allPhysical($pdo, 'HandValves', 'hv', ['PnPID', 'Tag']);
        $hasIa = ErcDcf::tableExists($pdo, 'InLineAssets');
        $size = self::coalesce($pdo, array_values(array_filter([
            $hasIa ? ['InLineAssets', 'Size', 'ia'] : null,
            ['EngineeringItems', 'ProcAanslDiam', 'ei'],
            ['EngineeringItems', 'Aansluiting', 'ei'],
        ])), 'Size');
        $nonc = self::coalesce($pdo, [
            ['HandValves', 'Normally', 'hv'],
            ['EngineeringItems', 'NONC', 'ei'],
        ], 'NONC');

        $sql = self::LINE_REL_CTE . '
        SELECT
            hv.PnPID,
            ' . self::ref($pdo, 'HandValves', 'Tag', 'Tag', 'hv') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ClassName', 'ObjectType', 'ei') . ',
            ' . $ei . ',
            ' . $hvExtra . ',
            ' . $size . ',
            ' . $nonc . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Bedieningssoort', 'Actuation', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Mat', 'Material', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ProcMed', 'Medium', 'ei') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'LineNumber', 'LineNumber', 'plg') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'Service', 'Service', 'plg') . ',
            ' . self::ref($pdo, 'PipeLines', 'Size', 'LineSize', 'pl') . ',
            ' . self::ref($pdo, 'PipeLines', 'Leidingmateriaal', 'LineMaterial', 'pl') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Type_omschrijv', 'Type', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Inbouwlengte_mm', 'FaceToFace_mm', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Opm', 'Remarks', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Bladnummer', 'Sheet', 'ei') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'PnID', 'PnID', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Dwg Name', 'DwgName', 'd') . ',
            ' . self::drawingTitleExpr($pdo) . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Procesopstal', 'Area', 'd') . '
        FROM HandValves hv
        JOIN EngineeringItems ei ON ei.PnPID = hv.PnPID
        ' . ($hasIa ? 'LEFT JOIN InLineAssets ia ON ia.PnPID = hv.PnPID' : '') . '
        LEFT JOIN dwg ON dwg.RowId = hv.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        LEFT JOIN asset_line rel ON rel.AssetId = hv.PnPID
        LEFT JOIN PipeLines pl ON pl.PnPID = rel.LineId
        LEFT JOIN line_group lg ON lg.PipeLineId = pl.PnPID
        LEFT JOIN PipeLineGroup plg ON plg.PnPID = lg.GroupId
        GROUP BY hv.PnPID
        ORDER BY Tag';
        return self::fetch($pdo, $sql);
    }

    private static function controlValves(PDO $pdo): array
    {
        $table = ErcCatalog::resolveSourceTable($pdo, 'control_valves');
        if ($table === null || !ErcDcf::tableExists($pdo, $table)) {
            return [];
        }
        $t = self::q($table);
        $ei = self::allPhysical($pdo, 'EngineeringItems', 'ei');
        $hasIa = ErcDcf::tableExists($pdo, 'InLineAssets');
        $size = self::coalesce($pdo, array_values(array_filter([
            $hasIa ? ['InLineAssets', 'Size', 'ia'] : null,
            ['EngineeringItems', 'ProcAanslDiam', 'ei'],
        ])), 'Size');

        $sql = self::LINE_REL_CTE . '
        SELECT
            cv.PnPID,
            ' . self::coalesce($pdo, [
                ['HandValves', 'Tag', 'hv'],
                ['Instrumentation', 'Tag', 'inst'],
            ], 'Tag') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ClassName', 'ObjectType', 'ei') . ',
            ' . $ei . ',
            ' . $size . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Bedieningssoort', 'Actuation', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'NONC', 'NONC', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Stuursignaal', 'ControlSignal', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Spanning', 'Voltage', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Eindcontacten', 'LimitSwitches', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Mat', 'Material', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ProcMed', 'Medium', 'ei') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'LineNumber', 'LineNumber', 'plg') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'Service', 'Service', 'plg') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Type_omschrijv', 'Type', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Opm', 'Remarks', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Bladnummer', 'Sheet', 'ei') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'PnID', 'PnID', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Dwg Name', 'DwgName', 'd') . ',
            ' . self::drawingTitleExpr($pdo) . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Procesopstal', 'Area', 'd') . '
        FROM ' . $t . ' cv
        JOIN EngineeringItems ei ON ei.PnPID = cv.PnPID
        LEFT JOIN HandValves hv ON hv.PnPID = cv.PnPID
        LEFT JOIN Instrumentation inst ON inst.PnPID = cv.PnPID
        ' . ($hasIa ? 'LEFT JOIN InLineAssets ia ON ia.PnPID = cv.PnPID' : '') . '
        LEFT JOIN dwg ON dwg.RowId = cv.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        LEFT JOIN asset_line rel ON rel.AssetId = cv.PnPID
        LEFT JOIN PipeLines pl ON pl.PnPID = rel.LineId
        LEFT JOIN line_group lg ON lg.PipeLineId = pl.PnPID
        LEFT JOIN PipeLineGroup plg ON plg.PnPID = lg.GroupId
        GROUP BY cv.PnPID
        ORDER BY Tag';
        return self::fetch($pdo, $sql);
    }

    private static function instruments(PDO $pdo): array
    {
        if (!ErcDcf::tableExists($pdo, 'Instrumentation')) {
            return [];
        }
        $ei = self::allPhysical($pdo, 'EngineeringItems', 'ei');
        $sql = self::LINE_REL_CTE . '
        SELECT
            i.PnPID,
            ' . self::ref($pdo, 'Instrumentation', 'Tag', 'Tag', 'i') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ClassName', 'ObjectType', 'ei') . ',
            ' . $ei . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Meetsignaal', 'Signal', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'BerMin', 'Range', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Alarmtypehoog', 'AlarmHigh', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Alarmtypelaag', 'AlarmLow', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Separateuitlezing', 'LocalIndication', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ProcAanslDiam', 'Size', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ProcMed', 'Medium', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Mat', 'Material', 'ei') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'LineNumber', 'LineNumber', 'plg') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'Service', 'Service', 'plg') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Type_omschrijv', 'Type', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Opm', 'Remarks', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Bladnummer', 'Sheet', 'ei') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'PnID', 'PnID', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Dwg Name', 'DwgName', 'd') . ',
            ' . self::drawingTitleExpr($pdo) . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Procesopstal', 'Area', 'd') . '
        FROM Instrumentation i
        JOIN EngineeringItems ei ON ei.PnPID = i.PnPID
        LEFT JOIN dwg ON dwg.RowId = i.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        LEFT JOIN asset_line rel ON rel.AssetId = i.PnPID
        LEFT JOIN PipeLines pl ON pl.PnPID = rel.LineId
        LEFT JOIN line_group lg ON lg.PipeLineId = pl.PnPID
        LEFT JOIN PipeLineGroup plg ON plg.PnPID = lg.GroupId
        GROUP BY i.PnPID
        ORDER BY Tag';
        return self::fetch($pdo, $sql);
    }

    private static function lines(PDO $pdo): array
    {
        if (!ErcDcf::tableExists($pdo, 'PipeLines')) {
            return [];
        }
        $ei = self::allPhysical($pdo, 'EngineeringItems', 'ei');
        $plExtra = self::allPhysical($pdo, 'PipeLines', 'pl', ['PnPID', 'Tag', 'Size', 'Spec']);
        $sql = self::LINE_REL_CTE . '
        SELECT
            pl.PnPID,
            ' . self::ref($pdo, 'PipeLines', 'Tag', 'Tag', 'pl') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'LineNumber', 'LineNumber', 'plg') . ',
            ' . self::ref($pdo, 'PipeLines', 'From', 'FromTag', 'pl') . ',
            ' . self::ref($pdo, 'PipeLines', 'To', 'ToTag', 'pl') . ',
            ' . self::ref($pdo, 'PipeLines', 'Size', 'Size', 'pl') . ',
            ' . self::ref($pdo, 'PipeLines', 'Leidingmateriaal', 'Material', 'pl') . ',
            ' . self::ref($pdo, 'PipeLines', 'Spec', 'Spec', 'pl') . ',
            ' . $ei . ',
            ' . $plExtra . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'Service', 'Service', 'plg') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ProcMed', 'Medium', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Bladnummer', 'Sheet', 'ei') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'PnID', 'PnID', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Dwg Name', 'DwgName', 'd') . ',
            ' . self::drawingTitleExpr($pdo) . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Procesopstal', 'Area', 'd') . '
        FROM PipeLines pl
        LEFT JOIN EngineeringItems ei ON ei.PnPID = pl.PnPID
        LEFT JOIN line_group lg ON lg.PipeLineId = pl.PnPID
        LEFT JOIN PipeLineGroup plg ON plg.PnPID = lg.GroupId
        LEFT JOIN dwg ON dwg.RowId = pl.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        GROUP BY pl.PnPID
        ORDER BY LineNumber, Tag';
        return self::fetch($pdo, $sql);
    }

    private static function lineSummary(PDO $pdo): array
    {
        if (!ErcDcf::tableExists($pdo, 'PipeLineGroup')) {
            return [];
        }
        $sql = self::LINE_REL_CTE . '
        SELECT
            plg.PnPID,
            COALESCE(NULLIF(TRIM(' . (ErcCatalog::tableHasColumn($pdo, 'PipeLineGroup', 'LineNumber') ? 'plg.' . self::q('LineNumber') : "''") . "), ''),
                     NULLIF(TRIM(" . (ErcCatalog::tableHasColumn($pdo, 'PipeLineGroup', 'Tag') ? 'plg.' . self::q('Tag') : "''") . "), ''),
                     '') AS " . self::q('LineNumber') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'Tag', 'Tag', 'plg') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'Service', 'Service', 'plg') . ',
            COALESCE(
                NULLIF(TRIM(' . (ErcCatalog::tableHasColumn($pdo, 'PipeLineGroup', 'NominalSize') ? 'plg.' . self::q('NominalSize') : "''") . "), ''),
                MAX(NULLIF(TRIM(" . (ErcCatalog::tableHasColumn($pdo, 'PipeLines', 'Size') ? 'pl.' . self::q('Size') : "''") . "), ''))
            ) AS " . self::q('Size') . ',
            COALESCE(
                NULLIF(TRIM(' . (ErcCatalog::tableHasColumn($pdo, 'PipeLineGroup', 'NominalSpec') ? 'plg.' . self::q('NominalSpec') : "''") . "), ''),
                MAX(NULLIF(TRIM(" . (ErcCatalog::tableHasColumn($pdo, 'PipeLines', 'Spec') ? 'pl.' . self::q('Spec') : "''") . "), ''))
            ) AS " . self::q('Spec') . ',
            ' . self::ref($pdo, 'PipeLineGroup', 'Status', 'Status', 'plg') . ',
            ' . (ErcCatalog::tableHasColumn($pdo, 'PnPDrawings', 'PnID')
                ? 'GROUP_CONCAT(DISTINCT d.' . self::q('PnID') . ')'
                : "''") . ' AS ' . self::q('PnID') . ',
            COUNT(DISTINCT pl.PnPID) AS ' . self::q('SegmentCount') . '
        FROM PipeLineGroup plg
        LEFT JOIN line_group lg ON lg.GroupId = plg.PnPID
        LEFT JOIN PipeLines pl ON pl.PnPID = lg.PipeLineId
        LEFT JOIN dwg ON dwg.RowId = pl.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        GROUP BY plg.PnPID
        ORDER BY LineNumber, Tag';
        return self::fetch($pdo, $sql);
    }

    private static function components(PDO $pdo): array
    {
        if (!ErcDcf::tableExists($pdo, 'EngineeringItems')) {
            return [];
        }
        $ei = self::allPhysical($pdo, 'EngineeringItems', 'ei');
        $joins = '';
        $tagParts = [];
        foreach (
            [
                ['HandValves', 'hv'],
                ['Equipment', 'eq'],
                ['Instrumentation', 'inst'],
                ['PipingSpecialtyItems', 'psi'],
                ['PipingFittings', 'pf'],
            ] as [$table, $alias]
        ) {
            if (ErcDcf::tableExists($pdo, $table)) {
                $joins .= "\n        LEFT JOIN " . self::q($table) . " {$alias} ON {$alias}.PnPID = ei.PnPID";
                if (ErcCatalog::tableHasColumn($pdo, $table, 'Tag')) {
                    $tagParts[] = "NULLIF(TRIM({$alias}." . self::q('Tag') . "), '')";
                }
            }
        }
        $tagExpr = $tagParts === []
            ? "'' AS " . self::q('Tag')
            : (count($tagParts) === 1
                ? $tagParts[0] . ' AS ' . self::q('Tag')
                : 'COALESCE(' . implode(', ', $tagParts) . ', \'\') AS ' . self::q('Tag'));

        // Exclude pipe-line rows by membership (works for EN/NL class names).
        $where = 'WHERE 1=1';
        if (ErcDcf::tableExists($pdo, 'PipeLines')) {
            $where .= ' AND NOT EXISTS (SELECT 1 FROM PipeLines _pl WHERE _pl.PnPID = ei.PnPID)';
        }
        if (ErcDcf::tableExists($pdo, 'PipeLineGroup')) {
            $where .= ' AND NOT EXISTS (SELECT 1 FROM PipeLineGroup _pg WHERE _pg.PnPID = ei.PnPID)';
        }
        $where .= " AND ei.ClassName NOT IN ('Minor Pipe Line', 'Major Pipe Line', 'Pipe Line Group')";

        $sql = self::LINE_REL_CTE . '
        SELECT
            ei.PnPID,
            ' . $tagExpr . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ClassName', 'ObjectType', 'ei') . ',
            ' . $ei . ',
            ' . self::ref($pdo, 'EngineeringItems', 'OudeTagNummer', 'OldTag', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ProcAanslDiam', 'Size', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Mat', 'Material', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'ProcMed', 'Medium', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Bedieningssoort', 'Actuation', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Spanning', 'Voltage', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Stroom', 'Current_A', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'EVerm', 'Electrical_kW', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Cap', 'Capacity', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Meetsignaal', 'Signal', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Type_omschrijv', 'Type', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Ingebruikvan', 'InServiceFrom', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Opm', 'Remarks', 'ei') . ',
            ' . self::ref($pdo, 'EngineeringItems', 'Bladnummer', 'Sheet', 'ei') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'PnID', 'PnID', 'd') . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Dwg Name', 'DwgName', 'd') . ',
            ' . self::drawingTitleExpr($pdo) . ',
            ' . self::ref($pdo, 'PnPDrawings', 'Kader_Procesopstal', 'Area', 'd') . '
        FROM EngineeringItems ei
        ' . $joins . '
        LEFT JOIN dwg ON dwg.RowId = ei.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        ' . $where . '
        GROUP BY ei.PnPID
        ORDER BY Tag, ObjectType';
        return self::fetch($pdo, $sql);
    }
}
