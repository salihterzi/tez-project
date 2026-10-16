<?php

namespace App\Command;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel (.xlsx) tabanlı içe aktarım komutlarının (bkz. {@see ImportOrnekVeriCommand},
 * {@see ImportYbsAnketiCommand}) paylaştığı "başlık satırı esnek eşleme + satır satır okuma"
 * mantığı — 2026-10-13'te `ImportOrnekVeriCommand`'dan buraya çıkarıldı (ikinci bir komutun
 * aynı mantığa ihtiyaç duyması üzerine).
 */
trait SpreadsheetRowReaderTrait
{
    private function getSheet(Spreadsheet $spreadsheet, ?string $name = null): Worksheet
    {
        if (null === $name) {
            return $spreadsheet->getActiveSheet();
        }

        $sheet = $spreadsheet->getSheetByName($name);
        if (null === $sheet) {
            throw new \RuntimeException(sprintf('"%s" sayfası bulunamadı.', $name));
        }

        return $sheet;
    }

    /**
     * Sayfayı satır satır, `$fields`'teki alan adlarını anahtar olarak kullanan ilişkisel
     * dizilere (assoc array) dönüştürür. Tamamen boş satırlar atlanır.
     *
     * Başlık satırındaki sütun adlarının yazım biçimi (camelCase, snake_case, UPPER_SNAKE_CASE,
     * kebab-case, aralarda boşluk...) önemli değildir; eşleştirme {@see self::normalizeHeader()}
     * ile normalize edilerek yapılır — örn. "islemZamani" == "ISLEM_ZAMANI" == "islem-zamani".
     *
     * @param string[]              $fields         beklenen ZORUNLU alan adları (entity alan
     *                                               adlarıyla aynı, örn. "ogrenciNo") — sayfada
     *                                               yoksa hata fırlatılır
     * @param string[]              $optionalFields OPSİYONEL alan adları — sayfada yoksa hata
     *                                               verilmez, her satırda `null` olarak gelir
     * @param array<string,string[]> $headerAliases  alan adı -> takma ad listesi (bkz. çağıran
     *                                               tarafın kendi `HEADER_ALIASES` sabiti)
     *
     * @return iterable<array<string, mixed>>
     */
    private function readRows(Worksheet $sheet, array $fields, array $optionalFields = [], array $headerAliases = []): iterable
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        // Başlık satırındaki her hücreyi normalize edip sütun index'ine eşle.
        $columnsByNormalizedHeader = [];
        for ($col = 1; $col <= $highestColumn; ++$col) {
            $header = trim((string) $sheet->getCell([$col, 1])->getValue());
            if ('' !== $header) {
                $columnsByNormalizedHeader[$this->normalizeHeader($header)] = $col;
            }
        }

        // Her beklenen alan için, hangi sütuna karşılık geldiğini (normalize ederek, alias'ları
        // da deneyerek) bul. Zorunlu alan bulunamazsa hata; opsiyonel alan bulunamazsa sessizce
        // atlanır (o satırlarda `null` olarak gelir).
        $columnByField = [];
        foreach ($fields as $field) {
            $candidates = $headerAliases[$field] ?? [$field];

            $col = null;
            foreach ($candidates as $candidate) {
                $col = $columnsByNormalizedHeader[$this->normalizeHeader($candidate)] ?? null;
                if (null !== $col) {
                    break;
                }
            }

            if (null === $col) {
                throw new \RuntimeException(sprintf(
                    '"%s" sayfasında "%s" sütunu bulunamadı (denenen adlar: %s; başlık satırı: %s).',
                    $sheet->getTitle(),
                    $field,
                    implode(', ', $candidates),
                    implode(', ', array_keys($columnsByNormalizedHeader)) ?: '(boş)',
                ));
            }
            $columnByField[$field] = $col;
        }

        $optionalColumnByField = [];
        foreach ($optionalFields as $field) {
            $col = $columnsByNormalizedHeader[$this->normalizeHeader($field)] ?? null;
            if (null !== $col) {
                $optionalColumnByField[$field] = $col;
            }
        }

        for ($rowNum = 2; $rowNum <= $highestRow; ++$rowNum) {
            $row = [];
            foreach ($columnByField as $field => $col) {
                $row[$field] = $sheet->getCell([$col, $rowNum])->getValue();
            }
            foreach ($optionalFields as $field) {
                $row[$field] = isset($optionalColumnByField[$field])
                    ? $sheet->getCell([$optionalColumnByField[$field], $rowNum])->getValue()
                    : null;
            }

            $doluHucreSayisi = count(array_filter($row, static fn (mixed $v): bool => null !== $v && '' !== $v));
            if (0 === $doluHucreSayisi) {
                continue;
            }

            yield $row;
        }
    }

    /**
     * Bir başlık metnini harf büyüklüğü/alt çizgi/tire/boşluk farklarından bağımsız
     * karşılaştırılabilir hale getirir (yalnızca harf ve rakamlar kalır, küçük harfe çevrilir).
     * Örn. "ISLEM_ZAMANI", "islemZamani" ve "Islem Zamani" hepsi "islemzamani" olur.
     */
    private function normalizeHeader(string $header): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $header));
    }

    /**
     * Bir hücre değerini (biçimlendirilmiş hücrelerde PhpSpreadsheet `RichText` nesnesi
     * dönebiliyor — `Stringable` olduğu için `(string)` cast güvenli) trim'lenmiş bir
     * string'e çevirir.
     */
    private function toStr(mixed $value): string
    {
        return trim((string) $value);
    }

    /** {@see self::toStr()} ile aynı, ama boş değerler için `null` döner. */
    private function toStrOrNull(mixed $value): ?string
    {
        $str = $this->toStr($value);

        return '' !== $str ? $str : null;
    }

    /**
     * Bir hücre değerini int'e çevirir. Doğrudan `(int) $value` yerine önce {@see self::toStr()}
     * ile string'e çevrilir — aksi halde `$value` bir `RichText` nesnesiyse (metin sütunlarında
     * olduğu gibi, biçimlendirmeden dolayı) doğrudan `(int)` cast'i hataya yol açar.
     */
    private function toInt(mixed $value): int
    {
        return (int) $this->toStr($value);
    }
}
