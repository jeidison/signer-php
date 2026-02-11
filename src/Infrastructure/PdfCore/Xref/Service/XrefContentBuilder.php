<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Xref\Service;

final class XrefContentBuilder
{
    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $offsets
     * @return array{W:array<int,int>,Index:string,stream:string}
     */
    public function buildXref15(array $offsets): array
    {
        unset($offsets[0]);

        $objectIds = array_keys($offsets);
        sort($objectIds);

        $indexEntries = [];
        $stream = '';
        $firstInRange = 0;
        $lastInRange = 0;
        $rangeCount = 0;

        foreach ($objectIds as $index => $objectId) {
            if ($index === 0 || $objectId !== ($lastInRange + 1)) {
                if ($index !== 0) {
                    $indexEntries[] = sprintf('%s %d', $firstInRange, $rangeCount);
                }

                $firstInRange = $objectId;
                $rangeCount = 0;
            }

            $rangeCount++;
            $lastInRange = $objectId;

            $offset = $offsets[$objectId];
            $stream .= $this->encodeEntry($offset);
        }

        if ($objectIds !== []) {
            $indexEntries[] = sprintf('%s %d', $firstInRange, $rangeCount);
        }

        return [
            'W' => [1, 4, 1],
            'Index' => implode(' ', $indexEntries),
            'stream' => $stream,
        ];
    }

    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $offsets
     */
    public function buildXref14(array $offsets): string
    {
        $objectIds = array_keys($offsets);
        sort($objectIds);

        $startObject = 0;
        $lastObject = 0;
        $rangeCount = 1;
        $result = '';
        $references = "0000000000 65535 f \n";

        foreach ($objectIds as $objectId) {
            if ($objectId === 0) {
                continue;
            }

            if ($objectId === ($lastObject + 1)) {
                $rangeCount++;
            } else {
                $result .= sprintf('%s %d%s%s', $startObject, $rangeCount, PHP_EOL, $references);
                $rangeCount = 1;
                $startObject = $objectId;
                $references = '';
            }

            $references .= sprintf("%010d 00000 n \n", (int) $offsets[$objectId]);
            $lastObject = $objectId;
        }

        $result .= sprintf('%s %d%s%s', $startObject, $rangeCount, PHP_EOL, $references);

        return "xref\n".$result;
    }

    /**
     * @param  int|array{stmoid:int,pos:int}|null  $offset
     */
    private function encodeEntry(int|array|null $offset): string
    {
        if (is_array($offset)) {
            return pack('C', 2).pack('N', $offset['stmoid']).pack('C', $offset['pos']);
        }

        if ($offset === null) {
            return pack('C', 0).pack('N', 0).pack('C', 0);
        }

        return pack('C', 1).pack('N', $offset).pack('C', 0);
    }
}
