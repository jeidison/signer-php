<?php

declare(strict_types=1);

namespace PdfSigner\Application\DTO;

enum SignatureProfile: string
{
    case PdfBasic = 'pdf-basic';
    case PadesBaselineB = 'pades-baseline-b';
    case PadesBaselineT = 'pades-baseline-t';
    case PadesBaselineLT = 'pades-baseline-lt';
    case PadesBaselineLTA = 'pades-baseline-lta';
}
