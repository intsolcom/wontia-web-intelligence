<?php
namespace App\Services;

/**
 * ProductContentXlsService — exportación Excel 2003 (.xls) sin dependencias.
 *
 * Genera SpreadsheetML real (no CSV renombrado) para que el content manager
 * pueda abrirlo en Excel, Numbers o Google Sheets con formato y columnas.
 */
class ProductContentXlsService
{
    public function build(array $rows, array $summary = [], string $sheet = 'Contenido'): string
    {
        $cols = [
            'Producto', 'Handle', 'Marca', 'Tipo', 'Estado del contenido', 'Score actual', 'Alertas',
            'Estado del laboratorio', 'Confianza fuente', 'Fuente oficial', 'Recomendación', 'Acción',
            'Score generado', 'Video', 'URL del producto',
        ];
        $widths = [260, 150, 120, 110, 150, 80, 240, 150, 90, 300, 260, 190, 90, 180, 280];
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $x .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $x .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
        $x .= '<Styles>
            <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Top" ss:WrapText="1"/><Font ss:FontName="Calibri" ss:Size="10"/></Style>
            <Style ss:ID="hdr"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#111827" ss:Pattern="Solid"/><Alignment ss:Vertical="Center" ss:WrapText="1"/></Style>
            <Style ss:ID="title"><Font ss:FontName="Calibri" ss:Size="14" ss:Bold="1" ss:Color="#111827"/></Style>
            <Style ss:ID="muted"><Font ss:FontName="Calibri" ss:Size="9" ss:Color="#6B7280"/></Style>
            <Style ss:ID="bad"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#B42318"/></Style>
            <Style ss:ID="ok"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#067647"/></Style>
            <Style ss:ID="warn"><Font ss:FontName="Calibri" ss:Size="10" ss:Bold="1" ss:Color="#B45309"/></Style>
        </Styles>' . "\n";

        // Hoja 1: resumen
        $x .= '<Worksheet ss:Name="Resumen"><Table>';
        $x .= $this->row([$this->cell('SONIKA · Reporte de contenido', 'String', 'title')]);
        $x .= $this->row([$this->cell('Generado: ' . date('Y-m-d H:i'), 'String', 'muted')]);
        $x .= $this->row([]);
        $x .= $this->row([$this->cell('Métrica', 'String', 'hdr'), $this->cell('Valor', 'String', 'hdr')]);
        foreach ($summary as $k => $v) {
            if (is_array($v)) continue;
            $x .= $this->row([$this->cell((string)$k, 'String'), $this->cell((string)$v, is_numeric($v) ? 'Number' : 'String')]);
        }
        $x .= '</Table></Worksheet>' . "\n";

        // Hoja 2: detalle
        $x .= '<Worksheet ss:Name="' . $this->esc($sheet) . '"><Table>';
        foreach ($widths as $i => $w) $x .= '<Column ss:Index="' . ($i + 1) . '" ss:Width="' . $w . '"/>';
        $x .= $this->row([$this->cell('SONIKA · Content Fix Lab — productos a corregir', 'String', 'title')]);
        $x .= $this->row([$this->cell('Fuente: Tienda › Contenido. No modifica el catálogo; es una lista de trabajo para el content manager.', 'String', 'muted')]);
        $x .= $this->row([]);
        $x .= $this->row(array_map(fn($c) => $this->cell($c, 'String', 'hdr'), $cols));
        foreach ($rows as $r) {
            $state = (string)($r['estado_contenido'] ?? '');
            $style = str_contains(mb_strtolower($state), 'sin descripción') ? 'bad' : (str_contains(mb_strtolower((string)($r['estado_lab'] ?? '')), 'aplicada') ? 'ok' : '');
            $cells = [
                $this->cell((string)($r['producto'] ?? '')),
                $this->cell((string)($r['handle'] ?? '')),
                $this->cell((string)($r['marca'] ?? '')),
                $this->cell((string)($r['tipo'] ?? '')),
                $this->cell($state, 'String', $style),
                $this->cell((string)($r['score_actual'] ?? 0), 'Number'),
                $this->cell((string)($r['alerta'] ?? '')),
                $this->cell((string)($r['estado_lab'] ?? '')),
                $this->cell((string)($r['confianza_fuente'] ?? 0), 'Number'),
                $this->cell((string)($r['fuente_oficial'] ?? '')),
                $this->cell((string)($r['recomendacion'] ?? '')),
                $this->cell((string)($r['accion'] ?? '')),
                $this->cell((string)($r['score_generado'] ?? 0), 'Number'),
                $this->cell((string)($r['video'] ?? '')),
                $this->cell((string)($r['url_producto'] ?? '')),
            ];
            $x .= $this->row($cells);
        }
        $x .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><SplitHorizontal>5</SplitHorizontal><TopRowBottomPane>5</TopRowBottomPane><ActivePane>2</ActivePane></WorksheetOptions></Worksheet>' . "\n";
        $x .= '</Workbook>';
        return $x;
    }

    private function row(array $cells): string
    {
        if (!$cells) return '<Row/>';
        return '<Row>' . implode('', $cells) . '</Row>';
    }

    private function cell(string $value, string $type = 'String', string $style = ''): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        return '<Cell' . ($style !== '' ? ' ss:StyleID="' . $style . '"' : '') . '><Data ss:Type="' . $type . '">' . $this->esc($value) . '</Data></Cell>';
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
