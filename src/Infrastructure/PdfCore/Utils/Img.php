<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore\Utils;

use finfo;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreParsingException;
use SignerPHP\Infrastructure\PdfCore\PDFObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueList;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueObject;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueReference;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueType;

final class Img
{
    /**
     * @param  array{w:int,h:int,cs:string,bpc:int,data:string,pal?:string,f?:string,dp?:string,trns?:array<int,int>,smask?:string}  $info
     */
    public static function create_image_objects($info, $objectFactory)
    {
        $objects = [];

        $image = call_user_func($objectFactory,
            [
                'Type' => '/XObject',
                'Subtype' => '/Image',
                'Width' => $info['w'],
                'Height' => $info['h'],
                'ColorSpace' => [],
                'BitsPerComponent' => $info['bpc'],
                'Length' => strlen((string) $info['data']),
            ]
        );

        switch ($info['cs']) {
            case 'Indexed':
                $data = gzcompress((string) $info['pal']);
                $streamobject = call_user_func($objectFactory, [
                    'Filter' => '/FlateDecode',
                    'Length' => strlen($data),
                ]);
                $streamobject->setStream($data);

                $image['ColorSpace']->push([
                    '/Indexed', '/DeviceRGB', (strlen((string) $info['pal']) / 3) - 1, new PDFValueReference($streamobject->getOid()),
                ]);
                $objects[] = $streamobject;
                break;
            case 'DeviceCMYK':
                $image['Decode'] = new PDFValueList([1, 0, 1, 0, 1, 0, 1, 0]);
            default:
                $image['ColorSpace'] = new PDFValueType($info['cs']);
                break;
        }

        if (isset($info['f'])) {
            $image['Filter'] = new PDFValueType($info['f']);
        }

        if (isset($info['dp'])) {
            $image['DecodeParms'] = PDFValueObject::fromString($info['dp']);
        }

        if (isset($info['trns']) && is_array($info['trns'])) {
            $image['Mask'] = new PDFValueList($info['trns']);
        }

        if (isset($info['smask'])) {
            $smaskinfo = [
                'w' => $info['w'],
                'h' => $info['h'],
                'cs' => 'DeviceGray',
                'bpc' => 8,
                'f' => $info['f'],
                'dp' => '/Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns '.$info['w'],
                'data' => $info['smask'],
            ];

            // In principle, it may return multiple objects
            $smasks = self::create_image_objects($smaskinfo, $objectFactory);
            foreach ($smasks as $smask) {
                $objects[] = $smask;
            }

            /** @var PDFObject $smask */
            $image['SMask'] = new PDFValueReference($smask->getOid());
        }

        $image->setStream($info['data']);
        array_unshift($objects, $image);

        return $objects;
    }

    public static function addImage(callable $objectFactory, string $filename, float|int $x = 0, float|int $y = 0, float|int $w = 0, float|int $h = 0, float|int $angle = 0, bool $keepProportions = true): array
    {
        $parser = new ImageParser;

        if (empty($filename)) {
            throw new PdfCoreParsingException('Invalid image name or stream');
        }

        if ($filename[0] === '@') {
            $filecontent = substr((string) $filename, 1);
        } elseif (Str::isBase64($filename)) {
            $filecontent = base64_decode((string) $filename, true);
            if ($filecontent === false) {
                throw new PdfCoreParsingException('Invalid base64 image payload');
            }
        } else {
            if (! is_file($filename)) {
                throw new PdfCoreParsingException('Failed to read image file');
            }

            $filecontent = file_get_contents($filename);
            if ($filecontent === false) {
                throw new PdfCoreParsingException('Failed to read image file');
            }
        }

        $finfo = new finfo;
        $contentType = $finfo->buffer($filecontent, FILEINFO_MIME_TYPE);
        if (! is_string($contentType) || $contentType === '') {
            throw new PdfCoreParsingException('Could not detect image MIME type.');
        }

        $ext = Mime::mimeToExt($contentType);

        $addAlpha = false;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $info = $parser->parseJpeg($filecontent);
                break;
            case 'png':
                $addAlpha = true;
                $info = $parser->parsePng($filecontent);
                break;
            default:
                throw new PdfCoreParsingException('Unsupported image mime type: '.$contentType);
        }

        // Generate a new identifier for the image
        $info['i'] = 'Im'.Str::random(4);

        if ($w === null) {
            $w = -96;
        }

        if ($h === null) {
            $h = -96;
        }

        if ($w < 0) {
            $w = -$info['w'] * 72 / $w;
        }

        if ($h < 0) {
            $h = -$info['h'] * 72 / $h;
        }

        if ($w === 0.0 || $w === 0) {
            $w = $h * $info['w'] / $info['h'];
        }

        if ($h === 0.0 || $h === 0) {
            $h = $w * $info['h'] / $info['w'];
        }

        /** @var PDFObject $imagesObjects */
        $imagesObjects = self::create_image_objects($info, $objectFactory);

        // Generate the command to translate and scale the image
        if ($keepProportions) {
            $angleRads = deg2rad($angle);
            $W = abs($w * cos($angleRads) + $h * sin($angleRads));
            $H = abs($w * sin($angleRads) + $h * cos($angleRads));

            $rW = ($w === 0.0 || $w === 0) ? 0 : $W / $w;
            $rH = ($h === 0.0 || $h === 0) ? 0 : $H / $h;
            $r = min($rW, $rH);
            $w = $W * $r;
            $h = $H * $r;
        }

        $data = 'q';
        $data .= ContentGeneration::tx($x, $y);
        $data .= ContentGeneration::sx($w, $h);
        if ($angle !== 0.0 && $angle !== 0) {
            $data .= ContentGeneration::tx(0.5, 0.5);
            $data .= ContentGeneration::rx($angle);
            $data .= ContentGeneration::tx(-0.5, -0.5);
        }

        $data .= sprintf(' /%s Do Q', $info['i']);

        $resources = new PDFValueObject([
            'ProcSet' => ['/PDF', '/Text', '/ImageB', '/ImageC', '/ImageI'],
            'XObject' => new PDFValueObject([
                $info['i'] => new PDFValueReference($imagesObjects[0]->getOid()),
            ]),
        ]);

        return ['image' => $imagesObjects[0], 'command' => $data, 'resources' => $resources, 'alpha' => $addAlpha];
    }
}
