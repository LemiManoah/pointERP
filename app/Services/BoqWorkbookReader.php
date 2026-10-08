<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use XMLReader;
use ZipArchive;

final class BoqWorkbookReader
{
    /** @return list<array{id: string, name: string, hidden: bool, rows: array<int, array<string, array{value: string, formula: bool, error: bool}>>}> */
    public function read(string $path): array
    {
        if (! class_exists(ZipArchive::class) || ! class_exists(XMLReader::class)) {
            throw ValidationException::withMessages(['file' => 'Enable the PHP zip and XMLReader extensions to import Excel files.']);
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'This is not a readable XLSX workbook.']);
        }

        $previousErrors = libxml_use_internal_errors(true);
        try {
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $size += $entry['size'] ?? 0;
                if ($size > 256 * 1024 * 1024 || ($entry['size'] ?? 0) > 64 * 1024 * 1024 || $zip->numFiles > 2000) {
                    throw ValidationException::withMessages(['file' => 'Workbook exceeds the supported decompressed size. Split it into smaller workbooks.']);
                }
            }

            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                $xml = $this->xml($zip, 'xl/sharedStrings.xml');
                while ($xml->read()) {
                    if ($xml->nodeType === XMLReader::ELEMENT && $xml->localName === 'si') {
                        $strings[] = $this->text($this->element($xml->readOuterXml()));
                    }
                }

                $this->assertValidXml();
                $xml->close();
            }

            $relationships = [];
            $rels = $this->element($this->entry($zip, 'xl/_rels/workbook.xml.rels'));
            foreach ($rels->children() ?: [] as $rel) {
                if ((string) $rel['TargetMode'] === 'External') {
                    continue;
                }

                $target = (string) $rel['Target'];
                $target = str_starts_with($target, '/') ? mb_ltrim($target, '/') : 'xl/'.$target;
                if (str_contains($target, '..')) {
                    continue;
                }

                if (str_contains($target, ':')) {
                    continue;
                }

                $relationships[(string) $rel['Id']] = $target;
            }

            $workbook = $this->element($this->entry($zip, 'xl/workbook.xml'));
            $sheets = [];
            $cells = 0;
            $nonemptyRows = 0;
            foreach ($workbook->sheets->sheet as $sheet) {
                if (count($sheets) >= 100) {
                    throw ValidationException::withMessages(['file' => 'Import at most 100 worksheets at a time.']);
                }

                $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $target = $relationships[$rid] ?? null;
                if ($target === null) {
                    throw ValidationException::withMessages(['file' => 'A worksheet has an unsupported relationship.']);
                }

                $rows = [];
                $xml = $this->xml($zip, $target);
                while ($xml->read()) {
                    if ($xml->nodeType !== XMLReader::ELEMENT) {
                        continue;
                    }

                    if ($xml->localName !== 'c') {
                        continue;
                    }

                    if ($xml->isEmptyElement) {
                        continue;
                    }

                    $reference = $xml->getAttribute('r') ?? '';
                    if (! preg_match('/^([A-Z]{1,3})([1-9]\d*)$/', $reference, $match)) {
                        continue;
                    }

                    $cell = $this->element($xml->readOuterXml());
                    $type = (string) $cell['t'];
                    $value = (string) $cell->v;
                    if ($type === 's') {
                        $value = $strings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = $this->text($cell);
                    }

                    $formula = property_exists($cell, 'f') && $cell->f !== null;
                    if ($value === '' && ! $formula) {
                        continue;
                    }

                    if (++$cells > 300000 || mb_strlen($value) > 20000) {
                        throw ValidationException::withMessages(['file' => 'Workbook contains too much data for one import.']);
                    }

                    $row = (int) $match[2];
                    if (! isset($rows[$row]) && ++$nonemptyRows > 30000) {
                        throw ValidationException::withMessages(['file' => 'Import at most 30,000 non-empty rows at a time.']);
                    }

                    $rows[$row][$match[1]] = ['value' => mb_trim($value), 'formula' => $formula, 'error' => $type === 'e'];
                }

                $this->assertValidXml();
                $xml->close();
                $sheets[] = ['id' => (string) $sheet['sheetId'], 'name' => (string) $sheet['name'], 'hidden' => (string) $sheet['state'] !== '' && (string) $sheet['state'] !== 'visible', 'rows' => $rows];
            }

            if (libxml_get_errors() !== []) {
                throw ValidationException::withMessages(['file' => 'Workbook XML could not be read completely.']);
            }

            return $sheets;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
            $zip->close();
        }
    }

    private function entry(ZipArchive $zip, string $path): string
    {
        $xml = $zip->getFromName($path);
        if ($xml === false || mb_stripos($xml, '<!DOCTYPE') !== false || mb_stripos($xml, '<!ENTITY') !== false) {
            throw ValidationException::withMessages(['file' => 'Workbook XML is missing or contains unsupported declarations.']);
        }

        return $xml;
    }

    private function xml(ZipArchive $zip, string $path): XMLReader
    {
        $reader = new XMLReader;
        $reader->XML($this->entry($zip, $path), null, LIBXML_NONET | LIBXML_COMPACT);

        return $reader;
    }

    private function element(string $xml): SimpleXMLElement
    {
        $this->assertValidXml();
        $previous = libxml_use_internal_errors(true);
        try {
            $node = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if ($node === false) {
                throw ValidationException::withMessages(['file' => 'Workbook contains invalid XML.']);
            }

            return $node;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function assertValidXml(): void
    {
        foreach (libxml_get_errors() as $error) {
            if ($error->level >= LIBXML_ERR_ERROR) {
                throw ValidationException::withMessages(['file' => 'Workbook XML could not be read completely.']);
            }
        }
    }

    private function text(SimpleXMLElement $node): string
    {
        return implode('', array_map(fn (SimpleXMLElement $text): string => (string) $text, $node->xpath('//*[local-name()="t"]') ?: []));
    }
}
