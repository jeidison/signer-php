# Signer PHP (PDF Module)

PHP library to digitally sign PDFs using A1 certificates (`.pfx/.p12`) with a simple, developer-friendly API.

## What problem it solves

If you need backend PDF signing with cryptographic validity, this library provides a direct flow to:

- apply digital signatures to PDF files
- include signer metadata
- add visible signatures (image)
- apply RFC3161 timestamping (TSA)
- sign the same PDF multiple times (incremental flow)

## Main features

- PKCS#12 (`.pfx/.p12`) digital signature
- Fluent builder API (`PdfSigner::signer()`)
- Invisible signature
- Visible signature with image (`PNG`/`JPEG`)
- Automatic default visible appearance (built-in fallback)
- Signature metadata (`name`, `contactInfo`, `reason`, `location`)
- DocMDP certification (levels 1, 2 and 3)
- Brazil policy mode (`br-iti`) signing preset
- PAdES Baseline-B profile mode (SubFilter `ETSI.CAdES.detached`)
- PAdES Baseline-T profile mode (PAdES-B + required timestamp)
- PAdES Baseline-LT profile mode (PAdES-T + embedded DSS/Certs)
- PAdES Baseline-LTA profile mode (PAdES-LT + extra archival timestamp)
- Multiple signatures in the same document
- Optional RFC3161 timestamping
- RFC3161 timestamping with public default TSA when enabled (`withTimestamp()`)
- PDF permission protection (for example, block content copying)
- Validation of existing digital signatures in PDF files

## Requirements

- PHP `^8.4`
- `ext-openssl`
- `ext-curl`
- recommended: `ext-zlib` and `ext-fileinfo`

## Installation

Install with Composer:

```bash
composer require jeidison/signer-php
```

## Usage

### 1) Basic signature

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->sign();

file_put_contents('/tmp/output-signed.pdf', $signedPdf);
```

By default, the library applies a fallback visible appearance with a styled built-in stamp (internal image + default position) for simpler usage.

If you already have PKCS#12 in memory, use content instead of a file path:

```php
$pkcs12 = file_get_contents('/tmp/certificate.pfx');

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificateContent($pkcs12, 'secret-password')
 ->sign();
```

### 2) Signature with metadata

```php
<?php

use PdfSigner\Application\DTO\SignatureActorDto;
use PdfSigner\Application\DTO\SignatureMetadataDto;
use PdfSigner\Presentation\PdfSigner;

$metadata = new SignatureMetadataDto(
   reason: 'Contract approval',
   location: 'Sao Paulo - BR',
   actor: new SignatureActorDto(
     name: 'Maria Silva',
     contactInfo: 'maria@company.com'
   )
);

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withMetadata($metadata)
 ->sign();
```

### 3) Visible signature with image

```php
<?php

use PdfSigner\Application\DTO\SignatureAppearanceDto;
use PdfSigner\Presentation\PdfSigner;

$appearance = new SignatureAppearanceDto(
 imagePath: '/tmp/signature.png',
 rect: [350, 770, 500, 830], // [x1, y1, x2, y2]
 page: 0 // 0-based index
);

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withAppearance($appearance)
 ->sign();
```

### 4) Visible signature with base64 image

```php
<?php

use PdfSigner\Application\DTO\SignatureAppearanceDto;
use PdfSigner\Presentation\PdfSigner;

$base64Image = base64_encode(file_get_contents('/tmp/signature.png'));

$appearance = new SignatureAppearanceDto(
 imagePath: $base64Image,
 rect: [350, 770, 500, 830],
 page: 0
);

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withAppearance($appearance)
 ->sign();
```

### 4.1) Disable default appearance (invisible signature)

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withoutDefaultAppearance()
 ->sign();
```

### 5) Multiple signatures in the same PDF

Use the signed output as input for the next signature:

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$step1 = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/signer-a.pfx', 'password-a')
 ->sign();

$step2 = PdfSigner::signer()
 ->withPdfContent($step1)
 ->withCertificatePath('/tmp/signer-b.pfx', 'password-b')
 ->sign();

file_put_contents('/tmp/output-multi-signed.pdf', $step2);
```

### 6) Signature with RFC3161 timestamp

To enable timestamping with default configuration, call `withTimestamp()`.
In this case, the library uses a public default TSA (`https://freetsa.org/tsr`).

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withTimestamp()
 ->sign();
```

If you want a custom TSA only for this signing flow, use `withTimestamp(new TimestampOptionsDto(...))`.
The `hashAlgorithm` field accepts `HashAlgorithm` (recommended) or a compatible `string` (`sha256`, `sha384`, `sha512`, `sha224`, `sha1`).

### 6.1) Override the default TSA for this flow

```php
<?php

use PdfSigner\Application\DTO\TimestampOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withDefaultTimestampProfile(new TimestampOptionsDto(
     tsaUrl: 'https://timestamp.your-provider.com',
     hashAlgorithm: 'sha256',
     certReq: true,
     username: null,
     password: null,
     timeoutSeconds: 15
 ))
 ->sign();
```

### 6.2) Disable default timestamping

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withoutTimestamp()
 ->sign();
```

### 6.3) Enable PAdES Baseline-B profile

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withPadesBaselineB()
 ->sign();
```

### 6.4) Enable PAdES Baseline-T profile

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withPadesBaselineT()
 ->sign();
```

In `PAdES-T` mode, timestamp must be active (for example, `withTimestamp(...)`).

### 6.5) Enable PAdES Baseline-LT profile

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withPadesBaselineLT()
 ->sign();
```

In `PAdES-LT` mode, besides timestamping, the library applies DSS enrichment with certificates extracted from CMS/RFC3161 signatures.

### 6.6) Enable PAdES Baseline-LTA profile

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withPadesBaselineLTA()
 ->sign();
```

In `PAdES-LTA` mode, after LT enrichment, the library adds one extra archival `Document Timestamp` on the final document revision.

### 6.7) Define document certification (DocMDP)

```php
<?php

use PdfSigner\Application\DTO\CertificationLevel;
use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withCertificationLevel(CertificationLevel::FormFillAndSignatures)
 ->sign();
```

Available levels:

- `CertificationLevel::NoChangesAllowed` (`1`): no changes allowed after certification.
- `CertificationLevel::FormFillAndSignatures` (`2`): allows form filling and additional signatures.
- `CertificationLevel::FormFillSignaturesAndAnnotations` (`3`): allows forms, signatures, and annotations.

Default behavior:

- Without `withCertificationLevel(...)`, DocMDP is not explicitly set (`null`).
- In `withBrazilPolicy(...)`, the library forces `CertificationLevel::FormFillAndSignatures` (level `2`).

### 6.8) Brazil policy mode (br-iti)

```php
<?php

use PdfSigner\Application\DTO\BrazilSignaturePolicyOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withBrazilPolicy(new BrazilSignaturePolicyOptionsDto(
     tsaUrl: 'https://tsa.your-icpbrasil-provider.com',
     hashAlgorithm: 'sha256',
     timeoutSeconds: 20,
     certReq: true,
 ))
 ->sign();
```

This preset applies `PAdES-LTA` + `DocMDP=2` + explicit policy timestamp.

To switch quickly to another TSA:

```php
$policy = BrazilSignaturePolicyOptionsDto::tsa('https://tsa.your-provider.com')
 ->withHashAlgorithm('sha256')
 ->withTimeoutSeconds(20);
```

SERPRO support (homologation):

- OAuth2 token: `https://gateway.apiserpro.serpro.gov.br/token`
- ASN.1 timestamp endpoint: `https://gateway.apiserpro.serpro.gov.br/apitimestamp/v1/stamps-asn1`

SERPRO helper example:

```php
<?php

use PdfSigner\Application\DTO\BrazilSignaturePolicyOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withBrazilPolicy(BrazilSignaturePolicyOptionsDto::serpro(
     consumerKey: 'YOUR_CONSUMER_KEY',
     consumerSecret: 'YOUR_CONSUMER_SECRET',
     hashAlgorithm: 'sha256',
     timeoutSeconds: 20
 ))
 ->sign();
```

### 7) Protect PDF (block copy/print/modify)

```php
<?php

use PdfSigner\Application\DTO\ProtectionOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$protectedPdf = PdfSigner::protection()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withProtection(ProtectionOptionsDto::preventCopy(
     ownerPassword: 'owner-secret',
     userPassword: ''
 ))
 ->protect();

file_put_contents('/tmp/output-protected.pdf', $protectedPdf);
```

### 8) Recommended flow: protect and sign in the same builder

Use this flow to avoid ordering mistakes and ensure the signature is applied on the already protected PDF.

```php
<?php

use PdfSigner\Application\DTO\ProtectionOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$signedProtectedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withProtection(ProtectionOptionsDto::preventCopy(
     ownerPassword: 'owner-secret',
     userPassword: ''
 ))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->protectThenSign();

file_put_contents('/tmp/output-protected-signed.pdf', $signedProtectedPdf);
```

### 9) Validate digital signatures in a PDF

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$validation = PdfSigner::validation()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->validate();

if (! $validation->hasSignatures) {
 // PDF has no signatures
}

if ($validation->allValid) {
 // all signatures were verified
}
```

### 9.1) Validation with trust chain (trust store)

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$validation = PdfSigner::validation()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->enableTrustChainValidation('/etc/ssl/certs/ca-certificates.crt') // optional; if omitted, tries the system default bundle
 ->validate();

foreach ($validation->entries as $entry) {
 var_dump($entry->trustValid); // true|false|null
}
```

### 9.2) Validation with Brazil policy mode (br-iti)

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$validation = PdfSigner::validation()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withBrazilPolicy('/path/to/icp-brasil-bundle.pem')
 ->validate();
```

`withBrazilPolicy(...)` trust store precedence:

- If `trustStorePath` is provided, it is used directly.
- If `trustStorePath` is `null`, the library automatically builds/updates a local cached ICP-Brasil bundle and uses it.

If `trustStorePath` is `null`, `br-iti` mode builds an ICP-Brasil trust anchors bundle automatically in local cache:

- default directory: `sys_get_temp_dir()/signer-php/trust-anchors`
- default URLs:
 - `http://acraiz.icpbrasil.gov.br/Certificado_AC_Raiz.crt`
 - `http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv2.crt`
 - `http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv5.crt`
 - `http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv6.crt`
 - `http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv7.crt`

In `br-iti` mode, validation also verifies ICP-Brasil PAdES policy list (LPA):

- `https://politicas.icpbrasil.gov.br/LPA_PAdES.der`
- `https://politicas.icpbrasil.gov.br/LPA_PAdES.p7s`

You can override these URLs:

```php
<?php

use PdfSigner\Application\DTO\BrazilPolicyLpaUrlsDto;
use PdfSigner\Application\DTO\BrazilTrustAnchorsOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$validation = PdfSigner::validation()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withBrazilPolicy(
   '/path/to/icp-brasil-bundle.pem',
   new BrazilPolicyLpaUrlsDto(
     lpaUrlAsn1Pades: 'https://your-endpoint/LPA_PAdES.der',
     lpaUrlAsn1SignaturePades: 'https://your-endpoint/LPA_PAdES.p7s',
   ),
   new BrazilTrustAnchorsOptionsDto(
     directory: '/tmp/my-trust-anchors-cache',
     urls: [
       'http://acraiz.icpbrasil.gov.br/Certificado_AC_Raiz.crt',
       'http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv7.crt',
     ],
   ),
 )
 ->validate();
```

### 9.3) How to interpret `trustValid` and `policyValid`

- `trustValid = true`: certificate chain is valid for the trust store in use.
- `trustValid = false`: chain is invalid, incomplete, or not trusted by the trust store in use.
- `trustValid = null`: trust chain validation was not executed for that signature.
- `policyValid = true`: PAdES LPA check (ICP-Brasil or overridden URLs) passed.
- `policyValid = false`: policy/LPA check failed for that signature.
- `policyValid = null`: policy mode was not requested in the flow.

## Signature inspection via CLI

Besides signing, the project provides `bin/signer-inspect` for technical diagnostics of signed PDFs.

### Basic usage

```bash
php bin/signer-inspect --input=/tmp/output-signed.pdf
```

### JSON output

```bash
php bin/signer-inspect --input=/tmp/output-signed.pdf --json
```

Main inspection fields:

- `inferred_profile`: inferred profile (`pades-baseline-b`, `t`, `lt`, `lta`).
- `features`: presence of `DSS`, `VRI`, `DocMDP`, `OCSPs`, `CRLs`.
- `revocation_endpoints`: OCSP/CRL endpoints per discovered certificate.
- `revocation_risk_summary`: connectivity/missing-endpoint risk flags.

This inspection helps explain warnings reported by external validators (for example CRL/OCSP connectivity issues).

## Recommended flow (Brazil/ITI)

1. Sign with `--policy=br-iti` using `bin/signer-sign`.
2. Validate programmatically with `PdfSigner::validation()->withBrazilPolicy(...)`.
3. Inspect final PDF with `bin/signer-inspect --json`.
4. If revocation warnings appear, check `revocation_risk_summary` and endpoint availability for OCSP/CRL URLs.

## Running with Docker Compose

This project already includes `docker-compose.yml` and `Dockerfile` for the `app` service.

### 1) Create external network (first time only)

```bash
docker network create kool_global
```

### 2) Start the service

```bash
docker compose up -d --build
```

### 3) Install dependencies

```bash
docker compose exec app composer install
```

### 4) Run tests

```bash
docker compose exec app php ./vendor/bin/pest --configuration phpunit.xml tests
```

### 5) Stop environment

```bash
docker compose down
```

## Sign using command line (CLI)

The project provides `bin/signer-sign` and `bin/signer-inspect`.

### Basic usage

```bash
php bin/signer-sign \
 --input=/tmp/input.pdf \
 --output=/tmp/output-signed.pdf \
 --cert=/tmp/certificate.pfx \
 --password='secret-password'
```

### Example with PAdES Baseline-B and explicit timestamp

```bash
php bin/signer-sign \
 --input=/tmp/input.pdf \
 --output=/tmp/output-signed.pdf \
 --cert=/tmp/certificate.pfx \
 --password='secret-password' \
 --pades-baseline-b \
 --timestamp-url='https://tsa.example.com' \
 --timestamp-hash='sha256' \
 --timestamp-timeout=20
```

### Example with DocMDP certification

```bash
php bin/signer-sign \
 --input=/tmp/input.pdf \
 --output=/tmp/output-certified.pdf \
 --cert=/tmp/certificate.pfx \
 --password='secret-password' \
 --certification-level=2
```

### Example with Brazil policy in CLI

```bash
php bin/signer-sign \
 --input=/tmp/input.pdf \
 --output=/tmp/output-br-iti.pdf \
 --cert=/tmp/certificate.pfx \
 --password='secret-password' \
 --policy=br-iti \
 --policy-serpro-consumer-key='YOUR_CONSUMER_KEY' \
 --policy-serpro-consumer-secret='YOUR_CONSUMER_SECRET' \
 --policy-timestamp-hash='sha256' \
 --policy-timestamp-timeout=20
```

### Example with PAdES Baseline-LT

```bash
php bin/signer-sign \
 --input=/tmp/input.pdf \
 --output=/tmp/output-signed-lt.pdf \
 --cert=/tmp/certificate.pfx \
 --password='secret-password' \
 --pades-baseline-lt \
 --timestamp-url='https://freetsa.org/tsr' \
 --timestamp-hash='sha256' \
 --timestamp-timeout=20
```

### Example with PAdES Baseline-LTA

```bash
php bin/signer-sign \
 --input=/tmp/input.pdf \
 --output=/tmp/output-signed-lta.pdf \
 --cert=/tmp/certificate.pfx \
 --password='secret-password' \
 --pades-baseline-lta \
 --timestamp-url='https://freetsa.org/tsr' \
 --timestamp-hash='sha256' \
 --timestamp-timeout=20
```

### Options help

```bash
php bin/signer-sign --help
```

## Exceptions you should handle

- `PdfSigner\\Domain\\Exception\\InvalidCertificateException`
- `PdfSigner\\Domain\\Exception\\SignProcessException`
- `PdfSigner\\Domain\\Exception\\PdfSignerException`

## Operational requirements

- For RFC3161 timestamping, host `openssl` must support `openssl ts`.
- For PDF permissions protection, host `qpdf` must be installed.
- If you use the public default TSA, consider availability/SLA for production and prefer a dedicated provider.

## Implementation notes

- A digital signature covers specific PDF bytes (`ByteRange`). Because of that, operations that rewrite file bytes should run before signing.
- Validation checks `ByteRange` integrity and CMS/PKCS#7 cryptographic validity with OpenSSL. Optionally, it can validate trust chain (`enableTrustChainValidation(...)`) and Brazil policy (`withBrazilPolicy(...)`).
- PAdES profiles:
  - Baseline-B: `SubFilter /ETSI.CAdES.detached`
  - Baseline-T: Baseline-B + RFC3161
  - Baseline-LT: Baseline-T + `DSS` (`Certs`, `OCSPs`, `CRLs`, `VRI`) with best-effort evidence collection depending on chain endpoint availability
  - Baseline-LTA: Baseline-LT + additional archival `Document Timestamp`
- Default appearance uses `page = 0` and an internal default rectangle; for full control use `withAppearance(...)`.
- Some PNG variations (specific filters/modes) may be rejected.
- Current PDF parser technical scope:
  - Objects with generation different from `0` are not supported
  - Extended object streams are not supported

## Running tests

```bash
vendor/bin/pest --configuration phpunit.xml tests
```

## Code quality

```bash
composer pint:check
composer pint:fix
composer security:audit
composer tests:coverage
composer psalm
composer deptrac
composer infection
```
