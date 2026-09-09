<?php
declare(strict_types=1);

/**
 * Lightweight XLSX writer for issued Plant 3D lists (no Composer dependency).
 */
final class ErcExcel
{
    private const ENGLISH_BY_KEY = [
        'Tag' => 'Tag',
        'ObjectType' => 'Object type',
        'Omschrijving' => 'Description',
        'Size' => 'Size',
        'NONC' => 'NO/NC',
        'Actuation' => 'Actuation',
        'LineNumber' => 'Line number',
        'Service' => 'Service',
        'Medium' => 'Medium',
        'Material' => 'Material',
        'Procesdeel' => 'Process part',
        'Procesmodule' => 'Process module',
        'PnID' => 'Drawing',
        'Area' => 'Area',
        'Remarks' => 'Remarks',
        'FromTag' => 'From',
        'ToTag' => 'To',
        'Spec' => 'Spec',
        'Capacity' => 'Capacity',
        'Type' => 'Type',
        'ControlSignal' => 'Control signal',
        'LimitSwitches' => 'Limit switches',
        'Voltage' => 'Voltage',
        'Signal' => 'Signal',
        'Range' => 'Range',
        'DwgName' => 'File name',
        'Title' => 'Title',
        'Subtitle' => 'Subtitle',
        'Revision' => 'Revision',
        'StatusDate' => 'Status date',
        'DrawnBy' => 'Drawn by',
        'DrawnDate' => 'Drawn date',
        'SegmentCount' => 'Segments',
        'Status' => 'Status',
        'OldTag' => 'Old tag',
        'Fabrikant' => 'Manufacturer',
        'Producent' => 'Producer',
        'Procesasset' => 'Process asset',
        'Electrical_kW' => 'kW',
        'Current_A' => 'Current',
        'Serienummer' => 'Serial number',
        'InServiceFrom' => 'In service from',
        'Sheet' => 'Sheet',
    ];

    private const ENGLISH_BY_LABEL = [
        'Objectsoort' => 'Object type',
        'Omschrijving' => 'Description',
        'Maat' => 'Size',
        'Bediening' => 'Actuation',
        'Leidingnr' => 'Line number',
        'Leidingnummer' => 'Line number',
        'Materiaal' => 'Material',
        'Opmerking' => 'Remarks',
        'Blad' => 'Drawing',
        'Bladen' => 'Drawings',
        'Bestand' => 'File name',
        'Inhoud 1' => 'Title',
        'Inhoud 2' => 'Subtitle',
        'Wijziging' => 'Revision',
        'Stuursignaal' => 'Control signal',
        'Eindcontacten' => 'Limit switches',
        'Spanning' => 'Voltage',
        'Meetsignaal' => 'Signal',
        'Meetbereik' => 'Range',
        'Nominale maat' => 'Size',
        'Nominale spec' => 'Spec',
        'Segmenten' => 'Segments',
        'Procesopstal' => 'Area',
        'Oude tag' => 'Old tag',
        'Proces aansluiting' => 'Process connection',
        'Fabrikant' => 'Manufacturer',
        'Producent' => 'Producer',
        'Procesasset' => 'Process asset',
    ];

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $template
     * @param array<string,mixed> $project
     * @param array{include_logo?:bool,include_revision?:bool,include_pnpid?:bool}|null $options
     */
    public static function export(
        array $rows,
        array $template,
        array $project,
        ?string $logoPath = null,
        ?array $options = null
    ): string {
        $options = array_merge([
            'include_logo' => true,
            'include_revision' => true,
            'include_pnpid' => true,
        ], $options ?? []);

        $columns = array_values(array_filter(
            $template['columns'] ?? [],
            static fn($c) => ($c['visible'] ?? true) && !empty($c['key'])
        ));
        $headers = array_map([self::class, 'englishHeader'], $columns);
        $keys = array_map(static fn($c) => $c['key'], $columns);
        $includeId = (bool) ($options['include_pnpid'] ?? ($template['include_pnpid'] ?? true));

        $header = $template['header'] ?? [];
        $revisions = !empty($options['include_revision']) ? ($template['revision_table'] ?? []) : [];
        $title = (string) ($header['title'] ?? $template['name'] ?? 'Plant 3D List');
        $docNo = (string) ($header['document_number'] ?? (($project['number'] ?? '') . '-' . ($template['id'] ?? 'LST')));
        $rev = (string) ($header['revision'] ?? ($revisions[0]['rev'] ?? 'A'));
        $date = (string) ($header['date'] ?? date('Y-m-d'));
        $company = (string) ($header['company'] ?? 'COMPANY LOGO');
        $useLogo = !empty($options['include_logo']) && $logoPath && is_file($logoPath);
        $logoLabel = $useLogo ? ($company . ' [logo]') : $company;

        $sheetRows = [];
        $sheetRows[] = [$logoLabel, '', '', $title, '', '', !empty($options['include_revision']) ? 'REVISION HISTORY' : ''];
        $sheetRows[] = ['', '', '', 'Document', $docNo, '', !empty($options['include_revision']) ? 'Rev' : '', !empty($options['include_revision']) ? 'Date' : '', !empty($options['include_revision']) ? 'Description' : '', !empty($options['include_revision']) ? 'Drawn' : ''];
        $sheetRows[] = ['', '', '', 'Revision', $rev, '', '', '', '', ''];
        $sheetRows[] = ['', '', '', 'Date', $date, '', '', '', '', ''];

        $metaRow = 4;
        foreach ($header['fields'] ?? [] as $field) {
            $label = (string) ($field['label'] ?? $field['key'] ?? '');
            $value = (string) ($field['value'] ?? '');
            if ($label === '') {
                continue;
            }
            while (count($sheetRows) <= $metaRow) {
                $sheetRows[] = array_fill(0, 10, '');
            }
            $sheetRows[$metaRow][3] = $label;
            $sheetRows[$metaRow][4] = $value;
            $metaRow++;
        }

        if (!empty($options['include_revision'])) {
            foreach (array_slice($revisions, 0, 5) as $i => $revRow) {
                $r = 1 + $i;
                while (count($sheetRows) <= $r) {
                    $sheetRows[] = array_fill(0, 10, '');
                }
                $sheetRows[$r][6] = (string) ($revRow['rev'] ?? '');
                $sheetRows[$r][7] = (string) ($revRow['date'] ?? '');
                $sheetRows[$r][8] = (string) ($revRow['desc'] ?? '');
                $sheetRows[$r][9] = (string) ($revRow['drawn'] ?? '');
            }
        }

        while (count($sheetRows) < 8) {
            $sheetRows[] = array_fill(0, 10, '');
        }

        $colHeaders = $headers;
        if ($includeId) {
            $colHeaders[] = 'PnPID';
        }
        $sheetRows[] = $colHeaders;

        foreach ($rows as $row) {
            $line = [];
            foreach ($keys as $key) {
                $line[] = (string) ($row[$key] ?? '');
            }
            if ($includeId) {
                $line[] = (string) ($row['PnPID'] ?? '');
            }
            $sheetRows[] = $line;
        }

        return self::buildXlsx($sheetRows, (string) ($template['name'] ?? 'List'), $useLogo ? $logoPath : null);
    }

    /** @param array<string,mixed> $col */
    public static function englishHeader(array $col): string
    {
        if (!empty($col['header_en'])) {
            return (string) $col['header_en'];
        }
        $key = (string) ($col['key'] ?? '');
        $header = (string) ($col['header'] ?? $key);
        return self::ENGLISH_BY_KEY[$key] ?? self::ENGLISH_BY_LABEL[$header] ?? $header;
    }

    /** @param list<list<string>> $rows */
    private static function buildXlsx(array $rows, string $sheetName, ?string $logoPath = null): string
    {
        $sheetName = preg_replace('/[\\\\\\/*?:\\[\\]]/', '', $sheetName) ?: 'List';
        $sheetName = function_exists('mb_substr')
            ? mb_substr($sheetName, 0, 31)
            : substr($sheetName, 0, 31);

        $sheetXml = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'];
        $sheetXml[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">';
        $sheetXml[] = '<sheetData>';
        foreach ($rows as $rIdx => $cols) {
            $rowNum = $rIdx + 1;
            $sheetXml[] = '<row r="' . $rowNum . '">';
            foreach ($cols as $cIdx => $value) {
                $ref = self::colLetter($cIdx + 1) . $rowNum;
                $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $sheetXml[] = '<c r="' . $ref . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
            }
            $sheetXml[] = '</row>';
        }
        $sheetXml[] = '</sheetData>';
        $hasLogo = $logoPath && is_file($logoPath);
        if ($hasLogo) {
            $sheetXml[] = '<drawing r:id="rId1"/>';
        }
        $sheetXml[] = '</worksheet>';

        $tmp = tempnam(sys_get_temp_dir(), 'ercxlsx');
        if ($tmp === false) {
            throw new RuntimeException('Could not create temp file for Excel export.');
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not open zip for Excel export.');
        }

        $contentTypes = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
XML;
        if ($hasLogo) {
            $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
            $imgCt = $ext === 'png' ? 'image/png' : 'image/jpeg';
            $imgExt = $ext === 'png' ? 'png' : 'jpeg';
            $contentTypes .= "\n  <Default Extension=\"{$imgExt}\" ContentType=\"{$imgCt}\"/>";
            $contentTypes .= "\n  <Override PartName=\"/xl/drawings/drawing1.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.drawing+xml\"/>";
        }
        $contentTypes .= "\n</Types>";
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);
        $safeName = htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $zip->addFromString('xl/workbook.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="{$safeName}" sheetId="1" r:id="rId1"/></sheets>
</workbook>
XML);
        $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
</Relationships>
XML);
        $zip->addFromString('xl/worksheets/sheet1.xml', implode('', $sheetXml));

        if ($hasLogo) {
            $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION)) === 'png' ? 'png' : 'jpeg';
            $zip->addFile($logoPath, 'xl/media/image1.' . $ext);
            $zip->addFromString('xl/worksheets/_rels/sheet1.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
</Relationships>
XML);
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.{$ext}"/>
</Relationships>
XML);
            $zip->addFromString('xl/drawings/drawing1.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <xdr:twoCellAnchor editAs="oneCell">
    <xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>
    <xdr:to><xdr:col>2</xdr:col><xdr:colOff>0</xdr:colOff><xdr:row>3</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:to>
    <xdr:pic>
      <xdr:nvPicPr><xdr:cNvPr id="1" name="Logo"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>
      <xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>
      <xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="1200000" cy="500000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>
    </xdr:pic>
    <xdr:clientData/>
  </xdr:twoCellAnchor>
</xdr:wsDr>
XML);
        }

        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    private static function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }
}
