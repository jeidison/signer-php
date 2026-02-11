<?php

namespace PdfSigner\Infrastructure\PdfCore;

use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueSimple;
use PdfSigner\Infrastructure\PdfCore\PdfValue\PDFValueString;
use PdfSigner\Infrastructure\PdfCore\Utils\Date;

class SignatureObject extends PDFObject
{
    const BYTE_RANGE_SIZE = 68;

    public const SUBFILTER_PKCS7_DETACHED = '/adbe.pkcs7.detached';

    public const SUBFILTER_ETSI_CADES_DETACHED = '/ETSI.CAdES.detached';

    protected int $prevContentSize = 0;

    protected ?int $postContentSize = null;

    public function __construct($oid)
    {
        parent::__construct($oid, [
            'Filter' => '/Adobe.PPKLite',
            'Type' => '/Sig',
            'SubFilter' => self::SUBFILTER_PKCS7_DETACHED,
            'ByteRange' => new PDFValueSimple(str_repeat(' ', self::BYTE_RANGE_SIZE)),
            'Contents' => '<'.str_repeat('0', Signature::SIGNATURE_MAX_LENGTH).'>',
            'M' => new PDFValueString(Date::toPdfDateString()),
        ]);
    }

    public function withSubFilter(string $subFilter): self
    {
        if ($subFilter !== '') {
            $this->value['SubFilter'] = $subFilter;
        }

        return $this;
    }

    public function withName(?string $name): self
    {
        if ($name) {
            $this->value['Name'] = "$name ";
        }

        return $this;
    }

    public function withReason(?string $reason): self
    {
        if ($reason) {
            $this->value['Reason'] = "$reason ";
        }

        return $this;
    }

    public function withLocation(?string $location): self
    {
        if ($location) {
            $this->value['Location'] = "$location ";
        }

        return $this;
    }

    public function withContactInfo(?string $contact): self
    {
        if ($contact) {
            $this->value['ContactInfo'] = "$contact ";
        }

        return $this;
    }

    public function withSizes($prevContentSize, ?int $postContentSize = null): self
    {
        $this->prevContentSize = $prevContentSize;
        $this->postContentSize = $postContentSize;

        return $this;
    }

    public function getSignatureMarkerOffset(): int
    {
        $tmpOutput = parent::toPdfEntry();
        $marker = '/Contents';
        $position = strpos($tmpOutput, $marker);

        return $position + strlen($marker);
    }

    public function toPdfEntry(): string
    {
        $signatureSize = strlen(parent::toPdfEntry());
        $offset = $this->getSignatureMarkerOffset();
        $startingSecondPart = $this->prevContentSize + $offset + Signature::SIGNATURE_MAX_LENGTH + 2;

        $contentsSize = strlen(''.$this->value['Contents']);

        $byteRangeStr = '[ 0 '.
            ($this->prevContentSize + $offset).' '.
            ($startingSecondPart).' '.
            ($this->postContentSize !== null ? $this->postContentSize + ($signatureSize - $contentsSize - $offset) : 0).' ]';

        $this->value['ByteRange'] = new PDFValueSimple($byteRangeStr.str_repeat(' ', self::BYTE_RANGE_SIZE - strlen($byteRangeStr) + 1));

        return parent::toPdfEntry();
    }
}
