<?php

class XlsxWriter {
    private array $rows = [];
    private array $headers = [];
    private array $meta = [];
    private string $title;

    public function __construct(string $title) {
        $this->title = $title;
    }

    public function setMeta(array $meta): void {
        $this->meta = array_values($meta);
    }

    public function setHeaders(array $headers): void {
        $this->headers = $headers;
    }

    public function addRow(array $row): void {
        $this->rows[] = $row;
    }

    public function output(string $filename): void {
        if (!class_exists('ZipArchive')) {
            $this->outputFallback();
            return;
        }

        $zip = new ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($zip->open($tmp, ZipArchive::CREATE) !== true) {
            $this->outputFallback();
            return;
        }

        $sharedStrings = $this->buildSharedStrings();
        $sheetXml = $this->buildSheetXml($sharedStrings);

        $zip->addFromString('[Content_Types].xml', $this->getContentTypesXml());
        $zip->addFromString('_rels/.rels', $this->getRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->getWorkbookXml());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->getWorkbookRelsXml());
        $zip->addFromString('xl/styles.xml', $this->getStylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $this->buildSharedStringsXml($sharedStrings));
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        unlink($tmp);
        exit;
    }

    private function outputFallback(): void {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->title . '_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: no-cache, must-revalidate');

        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . htmlspecialchars($this->title) . '</x:Name></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>td,th{border:1px solid #ccc;padding:8px 12px;font-family:Calibri,sans-serif;font-size:11px;}';
        echo 'th{background:#1D5FD6;color:#fff;font-weight:700;border-color:#1651B8;}';
        echo '.x-title{background:#1D5FD6;color:#fff;font-weight:800;font-size:16px;border-color:#1651B8;}';
        echo '.x-subtitle{background:#E9EFFA;color:#374151;font-weight:700;font-size:11px;}';
        echo 'tr:nth-child(even) td{background:#f7f8fa;}';
        echo '</style></head><body><table>';
        foreach ($this->meta as $i => $m) {
            $cls = $i === 0 ? 'x-title' : 'x-subtitle';
            echo '<tr><td class="' . $cls . '" colspan="' . count($this->headers) . '">' . htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        echo '<tr>';
        foreach ($this->headers as $h) {
            echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr>';
        foreach ($this->rows as $row) {
            echo '<tr>';
            foreach ($row as $val) {
                echo '<td>' . htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

    private function buildSharedStrings(): array {
        $strings = [];
        foreach ($this->meta as $m) {
            $s = (string)($m ?? '');
            if (!in_array($s, $strings, true)) $strings[] = $s;
        }
        foreach ($this->headers as $h) {
            if (!in_array($h, $strings, true)) $strings[] = $h;
        }
        foreach ($this->rows as $row) {
            foreach ($row as $val) {
                $s = (string)($val ?? '');
                if (!in_array($s, $strings, true)) $strings[] = $s;
            }
        }
        return $strings;
    }

    private function getIndex(array $strings, string $value): int {
        $idx = array_search($value, $strings, true);
        return $idx !== false ? $idx : 0;
    }

    private function buildSheetXml(array &$strings): string {
        $colCount = count($this->headers);
        $widths = $this->calcColumnWidths();
        $metaCount = count($this->meta);
        $headerRow = $metaCount + 1;
        $totalRows = $headerRow + count($this->rows);
        $lastColLetter = $this->colLetter($colCount - 1);

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0">';
        $xml .= '<pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/>';
        $xml .= '</sheetView></sheetViews>';

        $xml .= '<cols>';
        for ($i = 1; $i <= $colCount; $i++) {
            $w = max($widths[$i - 1] ?? 10, 6);
            $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols>';

        if ($metaCount) {
            $xml .= '<mergeCells count="' . $metaCount . '">';
            for ($i = 0; $i < $metaCount; $i++) {
                $r = $i + 1;
                $xml .= '<mergeCell ref="A' . $r . ':' . $lastColLetter . $r . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        $xml .= '<sheetData>';
        $r = 1;
        foreach ($this->meta as $i => $m) {
            $idx = $this->getIndex($strings, (string)($m ?? ''));
            $style = $i === 0 ? 3 : 4;
            $height = $i === 0 ? 26 : 18;
            $xml .= '<row r="' . $r . '" ht="' . $height . '" customHeight="1">';
            $xml .= '<c r="A' . $r . '" t="s" s="' . $style . '"><v>' . $idx . '</v></c>';
            $xml .= '</row>';
            $r++;
        }
        $xml .= '<row r="' . $r . '">';
        $colIdx = 0;
        foreach ($this->headers as $h) {
            $idx = $this->getIndex($strings, $h);
            $cellRef = $this->colLetter($colIdx) . $r;
            $xml .= '<c r="' . $cellRef . '" t="s" s="1"><v>' . $idx . '</v></c>';
            $colIdx++;
        }
        $r++;
        $xml .= '</row>';
        foreach ($this->rows as $row) {
            $xml .= '<row r="' . $r . '">';
            $col = 0;
            foreach ($row as $val) {
                $s = (string)($val ?? '');
                $idx = $this->getIndex($strings, $s);
                $cellRef = $this->colLetter($col) . $r;
                $xml .= '<c r="' . $cellRef . '" t="s" s="2"><v>' . $idx . '</v></c>';
                $col++;
            }
            $r++;
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        $xml .= '<autoFilter ref="A' . $headerRow . ':' . $lastColLetter . $totalRows . '"/>';

        $xml .= '</worksheet>';
        return $xml;
    }

    private function calcColumnWidths(): array {
        $widths = array_fill(0, count($this->headers), 0);
        foreach ($this->headers as $i => $h) {
            $len = mb_strlen($h, 'UTF-8');
            $widths[$i] = max($widths[$i], $len + 3);
        }
        foreach ($this->rows as $row) {
            foreach ($row as $i => $val) {
                if ($i >= count($widths)) break;
                $len = mb_strlen((string)($val ?? ''), 'UTF-8');
                $widths[$i] = max($widths[$i], $len + 3);
            }
        }
        return $widths;
    }

    private function colLetter(int $i): string {
        return chr(65 + $i);
    }

    private function getContentTypesXml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . '</Types>';
    }

    private function getRelsXml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function getWorkbookXml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($this->title) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private function getWorkbookRelsXml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            . '</Relationships>';
    }

    private function getStylesXml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="15"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF374151"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="4">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1D5FD6"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE9EFFA"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color auto="1"/></left>'
            . '<right style="thin"><color auto="1"/></right>'
            . '<top style="thin"><color auto="1"/></top>'
            . '<bottom style="thin"><color auto="1"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }

    private function buildSharedStringsXml(array $strings): string {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">';
        foreach ($strings as $s) {
            $xml .= '<si><t>' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
        }
        $xml .= '</sst>';
        return $xml;
    }
}
