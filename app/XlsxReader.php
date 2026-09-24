<?php
/**
 * Reads the first worksheet of an .xlsx file into plain rows of strings.
 *
 * There is no dependency manager in this codebase and PHP 5.4 has no
 * bundled spreadsheet library, so this reads the OOXML directly: an .xlsx
 * is a zip of XML parts, and the cell values live in xl/worksheets/sheet1.xml
 * (with text pooled in xl/sharedStrings.xml). Only what the import screens
 * need is implemented — no styles, no formulas, no multiple sheets.
 */
class XlsxReader
{
    /**
     * @param string $path
     * @return array list of rows, each a 0-indexed array of cell strings
     *               (missing cells filled with '' up to the row's last column)
     */
    public static function readFirstSheet($path)
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('เปิดไฟล์ Excel ไม่ได้');
        }

        $strings = self::sharedStrings($zip);
        $sheetPath = self::firstSheetPath($zip);
        $xml = $zip->getFromName($sheetPath);
        if ($xml === false) {
            $zip->close();
            throw new RuntimeException('ไม่พบข้อมูลในไฟล์ Excel');
        }

        $sheet = self::parseXml($xml);
        $zip->close();
        if ($sheet === false || !isset($sheet->sheetData)) {
            throw new RuntimeException('อ่านโครงสร้างไฟล์ Excel ไม่สำเร็จ');
        }

        $rows = array();
        foreach ($sheet->sheetData->row as $row) {
            $cells = array();
            foreach ($row->c as $c) {
                $col = self::columnIndex((string) $c['r']);
                $type = (string) $c['t'];
                if ($type === 'inlineStr') {
                    $value = isset($c->is->t) ? (string) $c->is->t : '';
                } else {
                    $value = isset($c->v) ? (string) $c->v : '';
                    if ($type === 's' && $value !== '') {
                        $value = isset($strings[(int) $value]) ? $strings[(int) $value] : '';
                    }
                }
                $cells[$col] = trim($value);
            }
            if (!$cells) {
                $rows[] = array();
                continue;
            }
            $max = max(array_keys($cells));
            $line = array();
            for ($i = 0; $i <= $max; $i++) {
                $line[$i] = isset($cells[$i]) ? $cells[$i] : '';
            }
            $rows[] = $line;
        }
        return $rows;
    }

    /**
     * @param string $xml
     * @return SimpleXMLElement|false
     */
    private static function parseXml($xml)
    {
        // Entity loading stays off for XML parsed from an uploaded file, so a
        // crafted DOCTYPE cannot make libxml read local files back in (XXE).
        // The function itself is deprecated from PHP 8.0 on, because libxml
        // >= 2.9 (bundled with PHP 8 on every supported platform) never loads
        // external entities in the first place — but PHP 5.4's older libxml
        // still can, so this must keep calling it there.
        if (PHP_MAJOR_VERSION < 8) {
            $prev = libxml_disable_entity_loader(true);
            $doc = simplexml_load_string($xml);
            libxml_disable_entity_loader($prev);
            return $doc;
        }
        return simplexml_load_string($xml);
    }

    private static function sharedStrings(ZipArchive $zip)
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return array();
        }
        $doc = self::parseXml($xml);
        if ($doc === false) {
            return array();
        }
        $strings = array();
        foreach ($doc->si as $si) {
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
                continue;
            }
            // Rich text: the run's own <t> nodes carry the text; the
            // formatting in between (<r><rPr>...) is of no interest here.
            $text = '';
            foreach ($si->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }
        return $strings;
    }

    /**
     * @param ZipArchive $zip
     * @return string
     */
    private static function firstSheetPath(ZipArchive $zip)
    {
        // Nearly every workbook keeps its first sheet at this fixed path;
        // resolve through the relationships only when that guess misses.
        if ($zip->locateName('xl/worksheets/sheet1.xml') !== false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $wbXml = $zip->getFromName('xl/workbook.xml');
        if ($relsXml === false || $wbXml === false) {
            throw new RuntimeException('ไม่พบชีทข้อมูลในไฟล์ Excel');
        }

        $rels = self::parseXml($relsXml);
        $wb = self::parseXml($wbXml);
        if ($rels === false || $wb === false || !isset($wb->sheets->sheet[0])) {
            throw new RuntimeException('ไม่พบชีทข้อมูลในไฟล์ Excel');
        }

        $ns = $wb->sheets->sheet[0]->attributes('r', true);
        $rId = isset($ns['id']) ? (string) $ns['id'] : '';
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rId) {
                return 'xl/' . ltrim((string) $rel['Target'], '/');
            }
        }
        throw new RuntimeException('ไม่พบชีทข้อมูลในไฟล์ Excel');
    }

    /**
     * "C7" -> 2 (0-indexed column)
     * @param string $ref
     * @return int
     */
    private static function columnIndex($ref)
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $ref);
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord(strtoupper($letters[$i])) - 64);
        }
        return $index - 1;
    }
}
