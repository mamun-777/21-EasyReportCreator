<?php
declare(strict_types=1);

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

    private static function drawings(PDO $pdo): array
    {
        return self::fetch($pdo, <<<'SQL'
        SELECT
            PnPID, PnID,
            [Dwg Name] AS DwgName,
            COALESCE(NULLIF(TRIM(Kader_Inh1), ''), Title, '') AS Title,
            Kader_Inh2 AS Subtitle,
            Kader_Procesopstal AS Area,
            Kader_Wijziging AS Revision,
            [Kader_Stempel PID Status] AS Status,
            [Kader_Stempel PID Status Datum] AS StatusDate,
            [Kader_Getekend door] AS DrawnBy,
            [Kader_Datum getekend] AS DrawnDate,
            [Kader_Datum wijziging] AS RevDate,
            PnPRelativePath AS Path
        FROM PnPDrawings
        ORDER BY PnID
        SQL);
    }

    private static function equipment(PDO $pdo): array
    {
        return self::fetch($pdo, self::LINE_REL_CTE . <<<'SQL'
        SELECT
            e.PnPID, e.Tag,
            ei.ClassName AS ObjectType,
            ei.Omschrijving,
            ei.Type_omschrijv AS Type,
            ei.Cap AS Capacity,
            ei.Inhoud_m3 AS Volume_m3,
            ei.Vermogen AS Power,
            ei.EVerm AS Electrical_kW,
            ei.Spanning AS Voltage,
            ei.Stroom AS Current_A,
            ei.Mat AS Material,
            ei.ProcMed AS Medium,
            ei.ProcAanslDiam AS ProcessSize,
            ei.Procesdeel, ei.Procesmodule, ei.Procesasset,
            ei.Fabrikant, ei.Producent, ei.Serienummer, ei.Status,
            ei.Opm AS Remarks, ei.Bladnummer AS Sheet,
            d.PnID, d.[Dwg Name] AS DwgName,
            d.Kader_Inh1 AS DrawingTitle, d.Kader_Procesopstal AS Area
        FROM Equipment e
        JOIN EngineeringItems ei ON ei.PnPID = e.PnPID
        LEFT JOIN dwg ON dwg.RowId = e.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        ORDER BY e.Tag
        SQL);
    }

    private static function valves(PDO $pdo): array
    {
        return self::fetch($pdo, self::LINE_REL_CTE . <<<'SQL'
        SELECT
            hv.PnPID, hv.Tag,
            ei.ClassName AS ObjectType,
            ei.Omschrijving,
            COALESCE(NULLIF(ia.Size, ''), ei.ProcAanslDiam) AS Size,
            COALESCE(NULLIF(hv.Normally, ''), ei.NONC) AS NONC,
            ei.Bedieningssoort AS Actuation,
            ei.Mat AS Material,
            ei.ProcMed AS Medium,
            plg.LineNumber, plg.Service,
            pl.Size AS LineSize,
            pl.Leidingmateriaal AS LineMaterial,
            ei.Procesdeel, ei.Procesmodule, ei.Procesasset,
            ei.Fabrikant, ei.Type_omschrijv AS Type,
            ei.Inbouwlengte_mm AS FaceToFace_mm, ei.Status,
            ei.Opm AS Remarks, ei.Bladnummer AS Sheet,
            d.PnID, d.[Dwg Name] AS DwgName,
            d.Kader_Inh1 AS DrawingTitle, d.Kader_Procesopstal AS Area
        FROM HandValves hv
        JOIN EngineeringItems ei ON ei.PnPID = hv.PnPID
        LEFT JOIN InLineAssets ia ON ia.PnPID = hv.PnPID
        LEFT JOIN dwg ON dwg.RowId = hv.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        LEFT JOIN asset_line rel ON rel.AssetId = hv.PnPID
        LEFT JOIN PipeLines pl ON pl.PnPID = rel.LineId
        LEFT JOIN line_group lg ON lg.PipeLineId = pl.PnPID
        LEFT JOIN PipeLineGroup plg ON plg.PnPID = lg.GroupId
        GROUP BY hv.PnPID
        ORDER BY hv.Tag
        SQL);
    }

    private static function controlValves(PDO $pdo): array
    {
        if (!ErcDcf::tableExists($pdo, 'Gestuurdeafsluiters')) {
            return [];
        }
        return self::fetch($pdo, self::LINE_REL_CTE . <<<'SQL'
        SELECT
            cv.PnPID,
            COALESCE(hv.Tag, inst.Tag, '') AS Tag,
            ei.ClassName AS ObjectType,
            ei.Omschrijving,
            COALESCE(NULLIF(ia.Size, ''), ei.ProcAanslDiam) AS Size,
            ei.Bedieningssoort AS Actuation, ei.NONC,
            ei.Stuursignaal AS ControlSignal,
            ei.Spanning AS Voltage, ei.Eindcontacten AS LimitSwitches,
            ei.Mat AS Material, ei.ProcMed AS Medium,
            plg.LineNumber, plg.Service,
            ei.Procesdeel, ei.Procesmodule, ei.Procesasset,
            ei.Fabrikant, ei.Type_omschrijv AS Type, ei.Status,
            ei.Opm AS Remarks, ei.Bladnummer AS Sheet,
            d.PnID, d.[Dwg Name] AS DwgName,
            d.Kader_Inh1 AS DrawingTitle, d.Kader_Procesopstal AS Area
        FROM Gestuurdeafsluiters cv
        JOIN EngineeringItems ei ON ei.PnPID = cv.PnPID
        LEFT JOIN HandValves hv ON hv.PnPID = cv.PnPID
        LEFT JOIN Instrumentation inst ON inst.PnPID = cv.PnPID
        LEFT JOIN InLineAssets ia ON ia.PnPID = cv.PnPID
        LEFT JOIN dwg ON dwg.RowId = cv.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        LEFT JOIN asset_line rel ON rel.AssetId = cv.PnPID
        LEFT JOIN PipeLines pl ON pl.PnPID = rel.LineId
        LEFT JOIN line_group lg ON lg.PipeLineId = pl.PnPID
        LEFT JOIN PipeLineGroup plg ON plg.PnPID = lg.GroupId
        GROUP BY cv.PnPID
        ORDER BY Tag
        SQL);
    }

    private static function instruments(PDO $pdo): array
    {
        return self::fetch($pdo, self::LINE_REL_CTE . <<<'SQL'
        SELECT
            i.PnPID, i.Tag,
            ei.ClassName AS ObjectType, ei.Omschrijving,
            ei.Meetsignaal AS Signal, ei.BerMin AS Range,
            ei.Alarmtypehoog AS AlarmHigh, ei.Alarmtypelaag AS AlarmLow,
            ei.Separateuitlezing AS LocalIndication,
            ei.ProcAanslDiam AS Size, ei.ProcMed AS Medium, ei.Mat AS Material,
            plg.LineNumber, plg.Service,
            ei.Procesdeel, ei.Procesmodule, ei.Procesasset,
            ei.Fabrikant, ei.Type_omschrijv AS Type, ei.Status,
            ei.Opm AS Remarks, ei.Bladnummer AS Sheet,
            d.PnID, d.[Dwg Name] AS DwgName,
            d.Kader_Inh1 AS DrawingTitle, d.Kader_Procesopstal AS Area
        FROM Instrumentation i
        JOIN EngineeringItems ei ON ei.PnPID = i.PnPID
        LEFT JOIN dwg ON dwg.RowId = i.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        LEFT JOIN asset_line rel ON rel.AssetId = i.PnPID
        LEFT JOIN PipeLines pl ON pl.PnPID = rel.LineId
        LEFT JOIN line_group lg ON lg.PipeLineId = pl.PnPID
        LEFT JOIN PipeLineGroup plg ON plg.PnPID = lg.GroupId
        GROUP BY i.PnPID
        ORDER BY i.Tag
        SQL);
    }

    private static function lines(PDO $pdo): array
    {
        return self::fetch($pdo, self::LINE_REL_CTE . <<<'SQL'
        SELECT
            pl.PnPID, pl.Tag, plg.LineNumber,
            pl.[From] AS FromTag, pl.[To] AS ToTag,
            ei.Omschrijving, pl.Size,
            pl.Leidingmateriaal AS Material, pl.Spec,
            pl.Insulation, pl.Tracing, pl.PaintCode, plg.Service,
            pl.DesignPressure, pl.DesignTemperature,
            pl.OperatingPressure, pl.OperatingTemperature,
            pl.TestingFluid, pl.TestPressure,
            ei.ProcMed AS Medium, ei.Procesdeel, ei.Procesmodule,
            ei.Bladnummer AS Sheet,
            d.PnID, d.[Dwg Name] AS DwgName,
            d.Kader_Inh1 AS DrawingTitle, d.Kader_Procesopstal AS Area
        FROM PipeLines pl
        LEFT JOIN EngineeringItems ei ON ei.PnPID = pl.PnPID
        LEFT JOIN line_group lg ON lg.PipeLineId = pl.PnPID
        LEFT JOIN PipeLineGroup plg ON plg.PnPID = lg.GroupId
        LEFT JOIN dwg ON dwg.RowId = pl.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        GROUP BY pl.PnPID
        ORDER BY plg.LineNumber, pl.Tag
        SQL);
    }

    private static function lineSummary(PDO $pdo): array
    {
        return self::fetch($pdo, self::LINE_REL_CTE . <<<'SQL'
        SELECT
            plg.PnPID, plg.LineNumber, plg.Tag, plg.Service,
            plg.NominalSize AS Size, plg.NominalSpec AS Spec, plg.Status,
            GROUP_CONCAT(DISTINCT d.PnID) AS PnID,
            COUNT(DISTINCT pl.PnPID) AS SegmentCount
        FROM PipeLineGroup plg
        LEFT JOIN line_group lg ON lg.GroupId = plg.PnPID
        LEFT JOIN PipeLines pl ON pl.PnPID = lg.PipeLineId
        LEFT JOIN dwg ON dwg.RowId = pl.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        GROUP BY plg.PnPID
        ORDER BY plg.LineNumber
        SQL);
    }

    private static function components(PDO $pdo): array
    {
        $sql = self::LINE_REL_CTE . <<<'SQL'
        SELECT
            ei.PnPID,
            COALESCE(hv.Tag, eq.Tag, inst.Tag, psi.Tag, pf.Tag, '') AS Tag,
            ei.ClassName AS ObjectType, ei.Omschrijving, ei.Objectsoort,
            ei.OudeTagNummer AS OldTag, ei.ProcAanslDiam AS Size,
            ei.Mat AS Material, ei.ProcMed AS Medium, ei.NONC,
            ei.Bedieningssoort AS Actuation, ei.Spanning AS Voltage,
            ei.Stroom AS Current_A, ei.EVerm AS Electrical_kW, ei.Cap AS Capacity,
            ei.Meetsignaal AS Signal, ei.Procesdeel, ei.Procesmodule, ei.Procesasset,
            ei.Voorcode, ei.Tagcode, ei.Fabrikant, ei.Producent,
            ei.Type_omschrijv AS Type, ei.Serienummer, ei.Ingebruikvan AS InServiceFrom,
            ei.Status, ei.Opm AS Remarks, ei.Bladnummer AS Sheet,
            d.PnID, d.[Dwg Name] AS DwgName,
            d.Kader_Inh1 AS DrawingTitle, d.Kader_Procesopstal AS Area
        FROM EngineeringItems ei
        LEFT JOIN HandValves hv ON hv.PnPID = ei.PnPID
        LEFT JOIN Equipment eq ON eq.PnPID = ei.PnPID
        LEFT JOIN Instrumentation inst ON inst.PnPID = ei.PnPID
        LEFT JOIN PipingSpecialtyItems psi ON psi.PnPID = ei.PnPID
        LEFT JOIN PipingFittings pf ON pf.PnPID = ei.PnPID
        LEFT JOIN dwg ON dwg.RowId = ei.PnPID
        LEFT JOIN PnPDrawings d ON d.PnPID = dwg.DwgId
        WHERE ei.ClassName NOT IN ('Minor Pipe Line', 'Major Pipe Line', 'Pipe Line Group')
        GROUP BY ei.PnPID
        ORDER BY Tag, ei.ClassName
        SQL;
        return self::fetch($pdo, $sql);
    }
}
