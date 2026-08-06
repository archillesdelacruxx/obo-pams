<?php

class XlsxWriter {
    private array $rows = [];
    private array $headers = [];
    private array $meta = [];
    private array $formats = [];
    private array $summary = [];
    private string $title;

    private const BRAND  = 'PAMS — Permit Application Management System';
    private const OFFICE = 'Office of the Building Official';

    public function __construct(string $title) {
        $this->title = $title;
    }

    public function setMeta(array $meta): void {
        $this->meta = array_values($meta);
    }

    public function setHeaders(array $headers): void {
        $this->headers = $headers;
    }

    public function setColumnFormats(array $formats): void {
        foreach ($formats as $idx => $fmt) {
            $this->formats[(int)$idx] = in_array($fmt, ['text', 'currency', 'number', 'int'], true) ? $fmt : 'text';
        }
    }

    public function setSummary(array $row): void {
        $this->summary = array_values($row);
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
        if ($tmp === false) {
            $this->outputFallback();
            return;
        }
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
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
        @unlink($tmp);
        exit;
    }

    private function formatOf(int $col): string {
        return $this->formats[$col] ?? 'text';
    }

    private function outputFallback(): void {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $this->title . '_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: no-cache, must-revalidate');

        $colspan = max(count($this->headers), 1);
        echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
        echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . htmlspecialchars($this->title) . '</x:Name></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
        echo '<style>';
        echo 'td,th{border:1px solid #d7dce4;padding:8px 12px;font-family:Arial,sans-serif;font-size:12px;text-align:center;}';
        echo 'th{background:#1D5FD6;color:#fff;font-weight:700;}';
        echo '.brand{background:#123A7C;color:#fff;font-weight:800;font-size:14px;text-align:center;}';
        echo '.office{background:#1D5FD6;color:#fff;font-weight:700;font-size:12px;text-align:center;}';
        echo '.title{font-weight:800;font-size:16px;color:#0F2742;border:0;text-align:left;}';
        echo '.subtitle{color:#6B7280;font-size:11px;border:0;text-align:left;}';
        echo '.spacer{border:0;}';
        echo '.summary{background:#E9EFFA;font-weight:700;color:#0F2742;}';
        echo 'tr:nth-child(even) td{background:#f7f8fa;}';
        echo '</style></head><body><table>';
        echo '<tr><td class="brand" colspan="' . $colspan . '">' . htmlspecialchars(self::BRAND) . '</td></tr>';
        echo '<tr><td class="office" colspan="' . $colspan . '">' . htmlspecialchars(self::OFFICE) . '</td></tr>';
        echo '<tr><td class="spacer" colspan="' . $colspan . '">&nbsp;</td></tr>';
        echo '<tr><td class="title" colspan="' . $colspan . '">' . htmlspecialchars($this->title) . '</td></tr>';
        foreach ($this->meta as $m) {
            echo '<tr><td class="subtitle" colspan="' . $colspan . '">' . htmlspecialchars((string)$m, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        echo '<tr><td class="spacer" colspan="' . $colspan . '">&nbsp;</td></tr>';
        echo '<tr>';
        foreach ($this->headers as $h) {
            echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr>';
        foreach ($this->rows as $row) {
            echo '<tr>';
            foreach ($row as $i => $val) {
                $fmt = $this->formatOf($i);
                $content = ($fmt === 'currency' && is_numeric($val)) ? '&#8369; ' . number_format((float)$val, 2) : htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
                $align = in_array($fmt, ['currency', 'number', 'int'], true) ? ' style="text-align:right;"' : '';
                echo '<td' . $align . '>' . $content . '</td>';
            }
            echo '</tr>';
        }
        if ($this->summary) {
            echo '<tr>';
            foreach ($this->summary as $val) {
                echo '<td class="summary">' . htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit;
    }

    private function isNumericValue(int $col, $val): bool {
        return in_array($this->formatOf($col), ['currency', 'number', 'int'], true) && is_numeric($val);
    }

    private function buildSharedStrings(): array {
        $strings = [self::BRAND, self::OFFICE, $this->title];
        foreach ($this->meta as $m) {
            $s = (string)($m ?? '');
            if (!in_array($s, $strings, true)) $strings[] = $s;
        }
        foreach ($this->headers as $h) {
            if (!in_array($h, $strings, true)) $strings[] = $h;
        }
        foreach ($this->rows as $row) {
            foreach ($row as $col => $val) {
                if ($this->isNumericValue((int)$col, $val)) continue;
                $s = (string)($val ?? '');
                if (!in_array($s, $strings, true)) $strings[] = $s;
            }
        }
        if ($this->summary) {
            foreach ($this->summary as $col => $val) {
                if (is_numeric($val)) continue;
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

    private function colLetter(int $i): string {
        $letter = '';
        while ($i >= 0) {
            $letter = chr(65 + ($i % 26)) . $letter;
            $i = intdiv($i, 26) - 1;
        }
        return $letter;
    }

    private function buildSheetXml(array &$strings): string {
        $colCount = count($this->headers);
        $widths = $this->calcColumnWidths();
        $lastColLetter = $this->colLetter($colCount - 1);

        $rowIndex = 1;
        $headerRow = 6 + count($this->meta);
        $mergeRefs = [
            'A1:' . $lastColLetter . '1',
            'A2:' . $lastColLetter . '2',
            'A4:' . $lastColLetter . '4',
        ];
        foreach ($this->meta as $i => $m) {
            $mergeRefs[] = 'A' . (5 + $i) . ':' . $lastColLetter . (5 + $i);
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0" showGridLines="0">';
        $xml .= '<pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/>';
        $xml .= '</sheetView></sheetViews>';

        $xml .= '<cols>';
        for ($i = 1; $i <= $colCount; $i++) {
            $w = max($widths[$i - 1] ?? 10, 7);
            $xml .= '<col min="' . $i . '" max="' . $i . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols>';

        if ($mergeRefs) {
            $xml .= '<mergeCells count="' . count($mergeRefs) . '">';
            foreach ($mergeRefs as $ref) {
                $xml .= '<mergeCell ref="' . $ref . '"/>';
            }
            $xml .= '</mergeCells>';
        }

        $xml .= '<sheetData>';

        $xml .= '<row r="1" ht="26" customHeight="1"><c r="A1" t="s" s="4"><v>' . $this->getIndex($strings, self::BRAND) . '</v></c></row>';
        $xml .= '<row r="2" ht="18" customHeight="1"><c r="A2" t="s" s="5"><v>' . $this->getIndex($strings, self::OFFICE) . '</v></c></row>';
        $xml .= '<row r="3" ht="6" customHeight="1"/>';
        $xml .= '<row r="4" ht="26" customHeight="1"><c r="A4" t="s" s="6"><v>' . $this->getIndex($strings, $this->title) . '</v></c></row>';

        foreach ($this->meta as $i => $m) {
            $r = 5 + $i;
            $xml .= '<row r="' . $r . '" ht="15" customHeight="1"><c r="A' . $r . '" t="s" s="7"><v>' . $this->getIndex($strings, (string)$m) . '</v></c></row>';
        }

        $xml .= '<row r="' . (5 + count($this->meta)) . '" ht="6" customHeight="1"/>';

        $r = $headerRow;
        $xml .= '<row r="' . $r . '" ht="24" customHeight="1">';
        foreach ($this->headers as $i => $h) {
            $idx = $this->getIndex($strings, $h);
            $xml .= '<c r="' . $this->colLetter($i) . $r . '" t="s" s="1"><v>' . $idx . '</v></c>';
        }
        $xml .= '</row>';
        $r++;

        $isEven = false;
        foreach ($this->rows as $row) {
            $xml .= '<row r="' . $r . '">';
            foreach ($row as $col => $val) {
                $style = 2;
                if ($isEven) $style = 3;
                $fmt = $this->formatOf((int)$col);
                if ($fmt === 'currency') $style = $isEven ? 10 : 9;
                elseif ($fmt === 'number') $style = $isEven ? 12 : 11;
                elseif ($fmt === 'int') $style = $isEven ? 14 : 13;
                $cellRef = $this->colLetter((int)$col) . $r;
                if ($this->isNumericValue((int)$col, $val)) {
                    $xml .= '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $val . '</v></c>';
                } else {
                    $s = (string)($val ?? '');
                    if ($s === '') {
                        $xml .= '<c r="' . $cellRef . '" s="' . $style . '"/>';
                    } else {
                        $idx = $this->getIndex($strings, $s);
                        $xml .= '<c r="' . $cellRef . '" t="s" s="' . $style . '"><v>' . $idx . '</v></c>';
                    }
                }
            }
            $xml .= '</row>';
            $r++;
            $isEven = !$isEven;
        }

        $lastDataRow = $r - 1;

        if ($this->summary) {
            $xml .= '<row r="' . $r . '" ht="20" customHeight="1">';
            foreach ($this->summary as $col => $val) {
                $fmt = $this->formatOf((int)$col);
                $style = 8;
                if ($fmt === 'currency') $style = 15;
                elseif ($fmt === 'number') $style = 16;
                elseif ($fmt === 'int') $style = 17;
                $cellRef = $this->colLetter((int)$col) . $r;
                if (is_numeric($val)) {
                    $xml .= '<c r="' . $cellRef . '" s="' . $style . '"><v>' . $val . '</v></c>';
                } else {
                    $idx = $this->getIndex($strings, (string)$val);
                    $xml .= '<c r="' . $cellRef . '" t="s" s="' . $style . '"><v>' . $idx . '</v></c>';
                }
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        $xml .= '<autoFilter ref="A' . $headerRow . ':' . $lastColLetter . $lastDataRow . '"/>';

        $xml .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>';
        $xml .= '<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0" horizontalDpi="300" verticalDpi="300"/>';

        $xml .= '</worksheet>';
        return $xml;
    }

    private function calcColumnWidths(): array {
        $widths = array_fill(0, count($this->headers), 0);
        foreach ($this->headers as $i => $h) {
            $len = mb_strlen($h, 'UTF-8');
            $widths[$i] = max($widths[$i], $len + 5);
        }
        foreach ($this->rows as $row) {
            foreach ($row as $i => $val) {
                if ($i >= count($widths)) break;
                $len = mb_strlen((string)($val ?? ''), 'UTF-8');
                $widths[$i] = max($widths[$i], $len + 5);
            }
        }
        return $widths;
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
            . '<numFmts count="3">'
            . '<numFmt numFmtId="164" formatCode="&quot;&#8369;&quot;#,##0.00"/>'
            . '<numFmt numFmtId="165" formatCode="#,##0.00"/>'
            . '<numFmt numFmtId="166" formatCode="#,##0"/>'
            . '</numFmts>'
            . '<fonts count="7">'
            . '<font><sz val="12"/><name val="Arial"/></font>'
            . '<font><b/><sz val="12"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            . '<font><b/><sz val="12"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>'
            . '<font><b/><sz val="16"/><color rgb="FF0F2742"/><name val="Arial"/></font>'
            . '<font><sz val="11"/><color rgb="FF6B7280"/><name val="Arial"/></font>'
            . '<font><b/><sz val="12"/><color rgb="FF0F2742"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="6">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF1D5FD6"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF123A7C"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF7F8FA"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE9EFFA"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FF000000"/></left>'
            . '<right style="thin"><color rgb="FF000000"/></right>'
            . '<top style="thin"><color rgb="FF000000"/></top>'
            . '<bottom style="thin"><color rgb="FF000000"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="18">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="6" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="166" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="164" fontId="6" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="165" fontId="6" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="166" fontId="6" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
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
