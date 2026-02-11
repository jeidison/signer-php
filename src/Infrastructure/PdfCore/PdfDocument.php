<?php

declare(strict_types=1);

namespace SignerPHP\Infrastructure\PdfCore;

use DateTime;
use SignerPHP\Infrastructure\PdfCore\Exception\PdfCoreStructureException;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValue;
use SignerPHP\Infrastructure\PdfCore\PdfValue\PDFValueString;
use SignerPHP\Infrastructure\PdfCore\Service\DocumentMetadataUpdater;
use SignerPHP\Infrastructure\PdfCore\Service\ObjectStreamResolver;
use SignerPHP\Infrastructure\PdfCore\Service\PdfObjectReader;
use SignerPHP\Infrastructure\PdfCore\Service\TrailerObjectResolver;
use SignerPHP\Infrastructure\PdfCore\Utils\Date;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 **/
class PdfDocument
{
    /** @var array<PDFObject> */
    protected array $pdfObjects = [];

    protected string $pdfVersion;

    protected PDFValue $trailerObject;

    protected int $xrefPosition;

    protected array $xrefTable;

    /** @var array<int, XrefEntry> */
    protected array $xrefEntries = [];

    protected string $xrefTableVersion;

    protected array $revisions;

    protected Buffer $buffer;

    protected int $maxOid = 0;

    protected PageInfo $pageInfo;

    private ?TrailerObjectResolver $trailerObjectResolver = null;

    private ?DocumentMetadataUpdater $documentMetadataUpdater = null;

    private ?PdfObjectReader $objectReader = null;

    private ?ObjectStreamResolver $objectStreamResolver = null;

    public function getPdfVersion(): string
    {
        return $this->pdfVersion;
    }

    public function setPdfVersion(string $pdfVersion): void
    {
        $this->pdfVersion = $pdfVersion;
    }

    public function getTrailerObject(): PDFValue
    {
        return $this->trailerObject;
    }

    public function setTrailerObject(PDFValue $trailerObject): void
    {
        $this->trailerObject = $trailerObject;
    }

    public function getXrefPosition(): int
    {
        return $this->xrefPosition;
    }

    public function setXrefPosition(int $xrefPosition): void
    {
        $this->xrefPosition = $xrefPosition;
    }

    public function getXrefTable(): array
    {
        return $this->xrefTable;
    }

    /**
     * @param  array<int, int|array{stmoid:int,pos:int}|null>  $xrefTable
     */
    public function setXrefTable(array $xrefTable): void
    {
        $this->xrefTable = $xrefTable;
        $this->xrefEntries = [];

        foreach ($xrefTable as $oid => $entry) {
            $this->xrefEntries[(int) $oid] = XrefEntry::fromLegacyValue($entry);
        }
    }

    /**
     * @return array<int, XrefEntry>
     */
    public function getXrefEntries(): array
    {
        return $this->xrefEntries;
    }

    public function getXrefTableVersion(): string
    {
        return $this->xrefTableVersion;
    }

    public function setXrefTableVersion(string $xrefTableVersion): void
    {
        $this->xrefTableVersion = $xrefTableVersion;
    }

    public function getRevisions(): array
    {
        return $this->revisions;
    }

    public function setRevisions(array $revisions): void
    {
        $this->revisions = $revisions;
    }

    public function getBuffer(): Buffer
    {
        return $this->buffer;
    }

    public function setBufferFromString(string $buffer): void
    {
        $this->buffer = new Buffer($buffer);
    }

    public function getPdfObjects(): array
    {
        return $this->pdfObjects;
    }

    public function setPdfObjects(array $pdfObjects): void
    {
        $this->pdfObjects = $pdfObjects;
    }

    public function getMaxOid(): int
    {
        return $this->maxOid;
    }

    public function setMaxOid(int $maxOid): void
    {
        $this->maxOid = $maxOid;
    }

    protected function getNewOid(): int
    {
        $this->maxOid++;

        return $this->maxOid;
    }

    public function createObject($value = [], $class = PDFObject::class, $autoAdd = true): PDFObject
    {
        $classPdfObject = new $class($this->getNewOid(), $value);
        if ($autoAdd === true) {
            $this->addObject($classPdfObject);
        }

        return $classPdfObject;
    }

    public function addObject(PDFObject $pdfObject): bool
    {
        $oid = $pdfObject->getOid();

        if (isset($this->pdfObjects[$oid]) && $this->pdfObjects[$oid]->getGeneration() > $pdfObject->getGeneration()) {
            return false;
        }

        $this->pdfObjects[$oid] = $pdfObject;

        if ($oid > $this->maxOid) {
            $this->maxOid = $oid;
        }

        return true;
    }

    public function getObject(int $oid, bool $originalVersion = false): ?PDFObject
    {
        if ($originalVersion) {
            return $this->findObject($oid) ?? ($this->pdfObjects[$oid] ?? null);
        }

        return $this->pdfObjects[$oid] ?? $this->findObject($oid);
    }

    public function findObject(int $oid): ?PDFObject
    {
        if ($oid === 0) {
            return null;
        }

        if (! isset($this->xrefEntries[$oid])) {
            return null;
        }

        $entry = $this->xrefEntries[$oid];

        if ($entry->isFree()) {
            return null;
        }

        if ($entry->isDirectOffset()) {
            return $this->findObjectAtPos($oid, (int) $entry->offset());
        }

        return $this->findObjectInObjStm((int) $entry->objectStreamId(), (int) $entry->objectStreamPosition(), $oid);
    }

    public function findObjectInObjStm(int $objstmOid, int $objpos, int $oid): PDFObject
    {
        return $this->objectStreamResolver()->resolveFromObjectStream($this, $objstmOid, $objpos, $oid);
    }

    public function findObjectAtPos(int $oid, int $objectOffset): PDFObject
    {
        $offsetEnd = 0;
        $object = $this->objectFromString($oid, $objectOffset, $offsetEnd);
        $this->objectStreamResolver()->attachObjectStreamIfPresent($this, $object, $offsetEnd, $oid);

        return $object;
    }

    public function objectFromString(int|string|null $expectedObjId, int $offset = 0, int &$offsetEnd = 0): PDFObject
    {
        return $this->objectReader()->objectFromBuffer((string) $this->buffer, $expectedObjId, $offset, $offsetEnd);
    }

    public function parseObjectDefinitionString(string $objectDefinition, int $expectedOid): PDFObject
    {
        return $this->objectReader()->parseObjectDefinitionString($objectDefinition, $expectedOid);
    }

    public function updateModifyDate(?DateTime $date = null): bool
    {
        $rootObj = $this->trailerObjectResolver()->resolveRootObject($this);

        $date ??= new DateTime;

        if (isset($rootObj['Metadata'])) {
            $metadata = $rootObj['Metadata'];
            if ((($referenced = $metadata->asObjectReferenceOrNull()) !== null) && (! is_array($referenced))) {
                $metadata = $this->getObject($referenced);
                if ($metadata === null) {
                    throw new PdfCoreStructureException('Invalid metadata object');
                }
                $this->documentMetadataUpdater()->updateModifyDates($metadata, $date);
                $this->addObject($metadata);
            }
        }

        $infoObj = $this->trailerObjectResolver()->resolveInfoObject($this);

        $infoObj['ModDate'] = new PDFValueString(Date::toPdfDateString($date));
        $infoObj['Producer'] = 'Modifier with PHP Signer';
        $this->addObject($infoObj);

        return true;
    }

    public function acquirePagesInfo(): void
    {
        $this->pageInfo = PageInfo::new()
            ->withPdfDocument($this)
            ->acquirePagesInfo();
    }

    public function getPageInfo(): PageInfo
    {
        return $this->pageInfo;
    }

    private function trailerObjectResolver(): TrailerObjectResolver
    {
        $this->trailerObjectResolver ??= new TrailerObjectResolver;

        return $this->trailerObjectResolver;
    }

    private function documentMetadataUpdater(): DocumentMetadataUpdater
    {
        $this->documentMetadataUpdater ??= new DocumentMetadataUpdater;

        return $this->documentMetadataUpdater;
    }

    private function objectReader(): PdfObjectReader
    {
        $this->objectReader ??= new PdfObjectReader;

        return $this->objectReader;
    }

    private function objectStreamResolver(): ObjectStreamResolver
    {
        $this->objectStreamResolver ??= new ObjectStreamResolver;

        return $this->objectStreamResolver;
    }
}
