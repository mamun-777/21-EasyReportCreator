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
    ];

    private const ENGLISH_BY_LABEL = [
        'Objectsoort' => 'Object type',
        'Omschrijving' => 'Description',
        'Maat' => 'Size',
        'Bediening' => 'Actuation',
        'Leidingnr' => 'Line number',
        'Materiaal' => 'Material',
        'Opmerking' => 'Remarks',
        'Blad' => 'Drawing',
    ];

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,mixed> $template
     * @param array<string,mixed> $project
     */
    public static function export(array $rows, array $template, array $project): string
    {
        $columns = array_values(array_filter(
            $template['columns'] ?? [],
            static fn($c) => ($c['visible'] ?? true) && !empty($c['key'])
        ));
        $headers = array_map([self::class, 'englishHeader'], $columns);
        $keys = array_map(static fn($c) => $c['key'], $columns);
        $includeId = (bool) ($template['include_pnpid'] ?? true);

        $header = $template['header'] ?? [];
        $revisions = $template['revision_table'] ?? [];
        $title = (string) ($header['title'] ?? $template['name'] ?? 'Plant 3D List');
        $docNo = (string) ($header['document_number'] ?? (($project['number'] ?? '') . '-' . ($template['id'] ?? 'LST')));
        $rev = (string) ($header['revision'] ?? ($revisions[0]['rev'] ?? 'A'));
        $date = (string) ($header['date'] ?? date('Y-m-d'));

        $sheetRows = [];
        $sheetRows[] = [$title, '', '', 'Document', $docNo, '', 'REVISION HISTORY'];
        $sheetRows[] = ['', '', '', 'Revision', $rev, '', 'Rev', 'Date', 'Description', 'Drawn'];
        $sheetRows[] = ['', '', '', 'Date', $date, '', '', '', '', ''];

        $metaRow = 3;
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

        foreach (array_slice($revisions, 0, 5) as $i => $revRow) {
            $r = 2 + $i;
            while (count($sheetRows) <= $r) {
                $sheetRows[] = array_fill(0, 10, '');
            }
            $sheetRows[$r][6] = (string) ($revRow['rev'] ?? '');
            $sheetRows[$r][7] = (string) ($revRow['date'] ?? '');
            $sheetRows[$r][8] = (string) ($revRow['desc'] ?? '');
            $sheetRows[$r][9] = (string) ($revRow['drawn'] ?? '');
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

        return self::buildXlsx($sheetRows, (string) ($template['name'] ?? 'List'));
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
    private static function buildXlsx(array $rows, string $sheetName): string
    {
        $sheetName = preg_replace('/[\\\\\\/*?:\\[\\]]/', '', $sheetName) ?: 'List';
        $sheetName = mb_substr($sheetName, 0, 31);

        $sheetXml = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'];
        $sheetXml[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
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
        $sheetXml[] = '</sheetData></worksheet>';

        $tmp = tempnam(sys_get_temp_dir(), 'ercxlsx');
        if ($tmp === false) {
            throw new RuntimeException('Could not create temp file for Excel export.');
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not open zip for Excel export.');
        }
        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML);
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
