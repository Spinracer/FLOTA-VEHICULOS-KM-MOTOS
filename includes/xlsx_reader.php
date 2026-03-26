<?php
/**
 * Lector XLSX ligero — Sin dependencias externas
 *
 * Utiliza la extensión zip (ya instalada) para leer archivos .xlsx
 * ya que XLSX es un ZIP de archivos XML.
 *
 * Uso:
 *   $reader = new SimpleXlsxReader('/path/to/file.xlsx');
 *   $sheets = $reader->getSheetNames();
 *   $rows   = $reader->getRows(0); // sheet index
 */

class SimpleXlsxReader {
    private string $filePath;
    private array $sharedStrings = [];
    private array $sheetNames = [];
    private array $sheetFiles = [];

    public function __construct(string $filePath) {
        if (!file_exists($filePath)) {
            throw new RuntimeException("Archivo no encontrado: $filePath");
        }
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("Extensión zip no disponible");
        }
        $this->filePath = $filePath;
        $this->parseWorkbook();
        $this->parseSharedStrings();
    }

    /**
     * Obtiene nombres de hojas del workbook
     */
    public function getSheetNames(): array {
        return $this->sheetNames;
    }

    /**
     * Lee todas las filas de una hoja
     * @param int $sheetIndex Índice de la hoja (0-based)
     * @return array Array de arrays (cada fila es un array de valores)
     */
    public function getRows(int $sheetIndex = 0): array {
        if (!isset($this->sheetFiles[$sheetIndex])) {
            throw new RuntimeException("Hoja $sheetIndex no existe");
        }

        $zip = new ZipArchive();
        if ($zip->open($this->filePath) !== true) {
            throw new RuntimeException("No se pudo abrir el archivo XLSX");
        }

        $xml = $zip->getFromName($this->sheetFiles[$sheetIndex]);
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException("No se pudo leer la hoja");
        }

        return $this->parseSheet($xml);
    }

    private function parseWorkbook(): void {
        $zip = new ZipArchive();
        if ($zip->open($this->filePath) !== true) {
            throw new RuntimeException("No se pudo abrir el archivo XLSX");
        }

        // Leer workbook.xml para nombres de hojas
        $wbXml = $zip->getFromName('xl/workbook.xml');
        if ($wbXml === false) {
            $zip->close();
            throw new RuntimeException("Archivo XLSX inválido (no contiene workbook.xml)");
        }

        $wb = new SimpleXMLElement($wbXml);
        $wb->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $sheets = $wb->xpath('//s:sheets/s:sheet');
        foreach ($sheets as $i => $sheet) {
            $attrs = $sheet->attributes();
            $this->sheetNames[] = (string)$attrs['name'];
            // Hoja estándar: sheet1.xml, sheet2.xml, etc.
            $this->sheetFiles[] = 'xl/worksheets/sheet' . ($i + 1) . '.xml';
        }

        // Verificar que los archivos de hoja existen, sino buscar por rels
        foreach ($this->sheetFiles as $idx => $path) {
            if ($zip->locateName($path) === false) {
                // Intentar encontrar el archivo real via rels
                $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
                if ($relsXml !== false) {
                    $rels = new SimpleXMLElement($relsXml);
                    foreach ($rels->Relationship as $rel) {
                        $relAttrs = $rel->attributes();
                        if (str_contains((string)$relAttrs['Type'], 'worksheet')) {
                            $target = (string)$relAttrs['Target'];
                            $fullPath = str_starts_with($target, '/') ? substr($target, 1) : 'xl/' . $target;
                            if ($zip->locateName($fullPath) !== false) {
                                $this->sheetFiles[$idx] = $fullPath;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $zip->close();
    }

    private function parseSharedStrings(): void {
        $zip = new ZipArchive();
        if ($zip->open($this->filePath) !== true) {
            return;
        }

        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        if ($ssXml === false) {
            return; // No shared strings (all inline)
        }

        $ss = new SimpleXMLElement($ssXml);
        $ss->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($ss->si as $si) {
            // Puede ser <t> directo o <r><t> (rich text)
            if (isset($si->t)) {
                $this->sharedStrings[] = (string)$si->t;
            } else {
                // Rich text: concatenar todos los <r><t>
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
                $this->sharedStrings[] = $text;
            }
        }
    }

    private function parseSheet(string $xml): array {
        $sheet = new SimpleXMLElement($xml);
        $sheet->registerXPathNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        $maxCol = 0;

        foreach ($sheet->sheetData->row as $row) {
            $rowData = [];
            foreach ($row->c as $cell) {
                $attrs = $cell->attributes();
                $ref = (string)$attrs['r']; // e.g. "A1", "B2"
                $colIndex = $this->colRefToIndex($ref);

                // Rellenar columnas vacías intermedias
                while (count($rowData) < $colIndex) {
                    $rowData[] = '';
                }

                $value = $this->getCellValue($cell);
                $rowData[$colIndex] = $value;

                if ($colIndex >= $maxCol) {
                    $maxCol = $colIndex + 1;
                }
            }

            $rows[] = $rowData;
        }

        // Normalizar: todas las filas con el mismo número de columnas
        foreach ($rows as &$row) {
            while (count($row) < $maxCol) {
                $row[] = '';
            }
        }
        unset($row);

        return $rows;
    }

    private function getCellValue(SimpleXMLElement $cell): string {
        $attrs = $cell->attributes();
        $type = (string)($attrs['t'] ?? '');
        $value = (string)($cell->v ?? '');

        if ($type === 's') {
            // Shared string
            $idx = (int)$value;
            return $this->sharedStrings[$idx] ?? '';
        }

        if ($type === 'inlineStr') {
            // Inline string
            return (string)($cell->is->t ?? '');
        }

        if ($type === 'b') {
            // Boolean
            return $value === '1' ? 'TRUE' : 'FALSE';
        }

        // Número o vacío
        return $value;
    }

    /**
     * Convierte referencia de celda (e.g. "AB12") a índice de columna (0-based)
     */
    private function colRefToIndex(string $ref): int {
        $letters = preg_replace('/[0-9]/', '', $ref);
        $index = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord(strtoupper($letters[$i])) - ord('A') + 1);
        }
        return $index - 1;
    }
}
