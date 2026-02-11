<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Utils;

use Exception;
use SignerPHP\Infrastructure\PdfCore\StreamReader;

final class ImageParser
{
    private const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    private readonly BinaryStreamReader $reader;

    public function __construct(?BinaryStreamReader $reader = null)
    {
        $this->reader = $reader ?? new BinaryStreamReader;
    }

    /**
     * @return array{w:int,h:int,cs:string,bpc:int,f:string,data:string}
     */
    public function parseJpeg(string $fileContent): array
    {
        if (! str_starts_with($fileContent, "\xFF\xD8\xFF")) {
            throw new Exception('Missing or incorrect image');
        }

        set_error_handler(static function (): bool {
            return true;
        });
        try {
            $imageInfo = getimagesizefromstring($fileContent);
        } finally {
            restore_error_handler();
        }
        if (! is_array($imageInfo)) {
            throw new Exception('Missing or incorrect image');
        }

        if (($imageInfo[2] ?? null) !== 2) {
            throw new Exception('Not a JPEG image');
        }

        $channels = $imageInfo['channels'] ?? 3;
        $colorSpace = match ($channels) {
            4 => 'DeviceCMYK',
            3 => 'DeviceRGB',
            default => 'DeviceGray',
        };

        return [
            'w' => (int) $imageInfo[0],
            'h' => (int) $imageInfo[1],
            'cs' => $colorSpace,
            'bpc' => (int) ($imageInfo['bits'] ?? 8),
            'f' => 'DCTDecode',
            'data' => $fileContent,
        ];
    }

    /**
     * @return array{w:int,h:int,cs:string,bpc:int,f:string,dp:string,pal:string,trns:array<int,int>|string,data:string,smask?:string}
     */
    public function parsePng(string $fileContent): array
    {
        $stream = new StreamReader($fileContent);

        return $this->parsePngStream($stream);
    }

    /**
     * @return array{w:int,h:int,cs:string,bpc:int,f:string,dp:string,pal:string,trns:array<int,int>|string,data:string,smask?:string}
     */
    private function parsePngStream(StreamReader $stream): array
    {
        if ($this->reader->read($stream, 8) !== self::PNG_SIGNATURE) {
            throw new Exception('Not a PNG image');
        }

        $this->reader->read($stream, 4);
        if ($this->reader->read($stream, 4) !== 'IHDR') {
            throw new Exception('Incorrect PNG image');
        }

        $width = $this->reader->readInt($stream);
        $height = $this->reader->readInt($stream);
        $bitsPerChannel = ord($this->reader->read($stream, 1));

        if ($bitsPerChannel > 8) {
            throw new Exception('16-bit depth not supported');
        }

        $colorType = ord($this->reader->read($stream, 1));
        $colorSpace = match ($colorType) {
            0, 4 => 'DeviceGray',
            2, 6 => 'DeviceRGB',
            3 => 'Indexed',
            default => throw new Exception('Unknown color type'),
        };

        if (ord($this->reader->read($stream, 1)) !== 0) {
            throw new Exception('Unknown compression method');
        }

        if (ord($this->reader->read($stream, 1)) !== 0) {
            throw new Exception('Unknown filter method');
        }

        if (ord($this->reader->read($stream, 1)) !== 0) {
            throw new Exception('Interlacing not supported');
        }

        $this->reader->read($stream, 4);

        $palette = '';
        $transparency = '';
        $compressedData = '';

        do {
            $chunkLength = $this->reader->readInt($stream);
            $chunkType = $this->reader->read($stream, 4);

            if ($chunkType === 'PLTE') {
                $palette = $this->reader->read($stream, $chunkLength);
                $this->reader->read($stream, 4);

                continue;
            }

            if ($chunkType === 'tRNS') {
                $chunk = $this->reader->read($stream, $chunkLength);

                $transparency = match ($colorType) {
                    0 => [ord(substr($chunk, 1, 1))],
                    2 => [ord(substr($chunk, 1, 1)), ord(substr($chunk, 3, 1)), ord(substr($chunk, 5, 1))],
                    default => (($position = strpos($chunk, "\x00")) !== false) ? [$position] : '',
                };

                $this->reader->read($stream, 4);

                continue;
            }

            if ($chunkType === 'IDAT') {
                $compressedData .= $this->reader->read($stream, $chunkLength);
                $this->reader->read($stream, 4);

                continue;
            }

            if ($chunkType === 'IEND') {
                break;
            }

            $this->reader->read($stream, $chunkLength + 4);
        } while ($chunkLength > 0);

        if ($colorSpace === 'Indexed' && $palette === '') {
            throw new Exception('Missing palette in image');
        }

        $info = [
            'w' => $width,
            'h' => $height,
            'cs' => $colorSpace,
            'bpc' => $bitsPerChannel,
            'f' => 'FlateDecode',
            'dp' => '/Predictor 15 /Colors '.($colorSpace === 'DeviceRGB' ? 3 : 1).' /BitsPerComponent '.$bitsPerChannel.' /Columns '.$width,
            'pal' => $palette,
            'trns' => $transparency,
            'data' => $compressedData,
        ];

        if ($colorType >= 4) {
            $inflated = gzuncompress($compressedData);
            if ($inflated === false) {
                throw new Exception('failed to uncompress the image');
            }

            [$colorData, $alphaData] = $this->splitPngAlphaChannels($inflated, $width, $height, $colorType);
            $info['data'] = (string) gzcompress($colorData);
            $info['smask'] = (string) gzcompress($alphaData);
        }

        return $info;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitPngAlphaChannels(string $inflatedData, int $width, int $height, int $colorType): array
    {
        $color = '';
        $alpha = '';

        if ($colorType === 4) {
            $lineLength = 2 * $width;

            for ($lineIndex = 0; $lineIndex < $height; $lineIndex++) {
                $position = (1 + $lineLength) * $lineIndex;
                $color .= $inflatedData[$position];
                $alpha .= $inflatedData[$position];
                $line = substr($inflatedData, $position + 1, $lineLength);
                $color .= (string) preg_replace('/(.)./s', '$1', $line);
                $alpha .= (string) preg_replace('/.(.)/s', '$1', $line);
            }

            return [$color, $alpha];
        }

        $lineLength = 4 * $width;

        for ($lineIndex = 0; $lineIndex < $height; $lineIndex++) {
            $position = (1 + $lineLength) * $lineIndex;
            $color .= $inflatedData[$position];
            $alpha .= $inflatedData[$position];
            $line = substr($inflatedData, $position + 1, $lineLength);
            $color .= (string) preg_replace('/(.{3})./s', '$1', $line);
            $alpha .= (string) preg_replace('/.{3}(.)/s', '$1', $line);
        }

        return [$color, $alpha];
    }
}
