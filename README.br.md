# Signer PHP (PDF Module)

Biblioteca PHP para assinar PDFs digitalmente com certificado A1 (`.pfx/.p12`), com API simples e foco em produtividade.

## O que este projeto resolve

Se você precisa assinar PDFs no backend com validade criptográfica, esta biblioteca oferece um fluxo direto para:

- aplicar assinatura digital em PDF
- incluir metadados do assinante
- adicionar assinatura visível (imagem)
- aplicar carimbo de tempo RFC3161 (TSA)
- assinar o mesmo PDF múltiplas vezes (fluxo incremental)

## Principais features

- Assinatura digital com PKCS#12 (`.pfx/.p12`)
- API fluida via builder (`PdfSigner::signer()`)
- Assinatura invisível
- Assinatura visível com imagem (`PNG`/`JPEG`)
- Aparência visível padrão automática (com fallback interno)
- Metadados de assinatura (`name`, `contactInfo`, `reason`, `location`)
- Certificação DocMDP (níveis 1, 2 e 3)
- Modo de política Brasil (`br-iti`) com preset para assinatura
- Perfil PAdES Baseline-B (SubFilter `ETSI.CAdES.detached`)
- Perfil PAdES Baseline-T (PAdES-B + timestamp obrigatório)
- Perfil PAdES Baseline-LT (PAdES-T + DSS/Certs embutidos)
- Perfil PAdES Baseline-LTA (PAdES-LT + timestamp arquivístico adicional)
- Múltiplas assinaturas no mesmo documento
- Carimbo de tempo RFC3161 opcional
- Carimbo de tempo RFC3161 com TSA público padrão quando habilitado (`withTimestamp()`)
- Proteção de PDF por permissões (ex.: bloquear cópia de conteúdo)
- Validação de assinaturas digitais já existentes no PDF

## Requisitos

- PHP `^8.4`
- `ext-openssl`
- `ext-curl`
- recomendado: `ext-zlib` e `ext-fileinfo`

## Instalação

Instale via Composer:

```bash
composer require jeidison/signer-php
```

## Como usar

### 1) Assinatura básica

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->sign();

file_put_contents('/tmp/output-signed.pdf', $signedPdf);
```

Por padrão, a biblioteca aplica uma aparência visível fallback com carimbo interno estilizado (imagem interna + posição padrão), para simplificar o uso.

Se você já tiver o PKCS#12 em memória, use conteúdo em vez de caminho:

```php
$pkcs12 = file_get_contents('/tmp/certificate.pfx');

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificateContent($pkcs12, 'secret-password')
 ->sign();
```

### 2) Assinatura com metadados

```php
<?php

use PdfSigner\Application\DTO\SignatureActorDto;
use PdfSigner\Application\DTO\SignatureMetadataDto;
use PdfSigner\Presentation\PdfSigner;

$metadata = new SignatureMetadataDto(
 reason: 'Contract approval',
 location: 'Sao Paulo - BR',
 actor: new SignatureActorDto(
    name: 'Jeidison Farias',
    contactInfo: 'jeidison@example.com'
 )
);

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withMetadata($metadata)
 ->sign();
```

### 3) Assinatura visível com imagem

```php
<?php

use PdfSigner\Application\DTO\SignatureAppearanceDto;
use PdfSigner\Presentation\PdfSigner;

$appearance = new SignatureAppearanceDto(
 imagePath: '/tmp/signature.png',
 rect: [350, 770, 500, 830], // [x1, y1, x2, y2]
 page: 0 // índice 0-based
);

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withAppearance($appearance)
 ->sign();
```

### 4) Assinatura visível com imagem em base64

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

### 4.1) Desabilitar aparência padrão (assinatura invisível)

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withoutDefaultAppearance()
 ->sign();
```

### 5) Múltiplas assinaturas no mesmo PDF

Use a saída assinada como entrada da próxima assinatura:

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

### 6) Assinatura com carimbo de tempo (RFC3161)

Para habilitar carimbo de tempo com configuração padrão, use `withTimestamp()`.
Nesse caso, a lib usa um TSA público padrão (`https://freetsa.org/tsr`).

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withTimestamp()
 ->sign();
```

Se quiser customizar o TSA apenas nesse fluxo, use `withTimestamp(new TimestampOptionsDto(...))`.

### 6.1) Trocar o TSA padrão para este fluxo

```php
<?php

use PdfSigner\Application\DTO\TimestampOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withDefaultTimestampProfile(new TimestampOptionsDto(
   tsaUrl: 'https://timestamp.seu-provedor.com',
   hashAlgorithm: 'sha256',
   certReq: true,
   username: null,
   password: null,
   timeoutSeconds: 15
 ))
 ->sign();
```

### 6.2) Desabilitar timestamp padrão

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withoutTimestamp()
 ->sign();
```

### 6.3) Habilitar perfil PAdES Baseline-B

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withPadesBaselineB()
 ->sign();
```

### 6.4) Habilitar perfil PAdES Baseline-T

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withPadesBaselineT()
 ->sign();
```

No perfil `PAdES-T`, a assinatura exige timestamp ativo (ex.: `withTimestamp(...)`).

### 6.5) Habilitar perfil PAdES Baseline-LT

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withPadesBaselineLT()
 ->sign();
```

No perfil `PAdES-LT`, além do timestamp, a lib aplica enriquecimento DSS com certificados extraídos das assinaturas CMS/RFC3161.

### 6.6) Habilitar perfil PAdES Baseline-LTA

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withPadesBaselineLTA()
 ->sign();
```

No perfil `PAdES-LTA`, após o enriquecimento LT, a lib aplica um novo `Document Timestamp` arquivístico sobre o documento final.

### 6.7) Definir certificação do documento (DocMDP)

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

Níveis disponíveis:

- `CertificationLevel::NoChangesAllowed` (`1`): não permite alterações após certificação.
- `CertificationLevel::FormFillAndSignatures` (`2`): permite preencher formulários e novas assinaturas.
- `CertificationLevel::FormFillSignaturesAndAnnotations` (`3`): permite formulários, assinaturas e anotações.

Padrão:

- Sem `withCertificationLevel(...)`, o DocMDP não é definido explicitamente (`null`).
- Em `withBrazilPolicy(...)`, a biblioteca força `CertificationLevel::FormFillAndSignatures` (nível `2`).

### 6.8) Modo política Brasil (br-iti)

```php
<?php

use PdfSigner\Application\DTO\BrazilSignaturePolicyOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withBrazilPolicy(new BrazilSignaturePolicyOptionsDto(
     tsaUrl: 'https://tsa.seu-provedor-icpbrasil.com',
     hashAlgorithm: 'sha256',
     timeoutSeconds: 20,
     certReq: true,
 ))
 ->sign();
```

Esse preset aplica `PAdES-LTA` + `DocMDP=2` + timestamp explícito de política.

Para trocar facilmente para outro TSA:

```php
$policy = BrazilSignaturePolicyOptionsDto::tsa('https://tsa.seu-provedor.com')
 ->withHashAlgorithm('sha256')
 ->withTimeoutSeconds(20);
```

Suporte SERPRO (testado):

- token OAuth2: `https://gateway.apiserpro.serpro.gov.br/token`
- carimbo ASN.1: `https://gateway.apiserpro.serpro.gov.br/apitimestamp/v1/stamps-asn1`

Exemplo com helper SERPRO:

```php
<?php

use PdfSigner\Application\DTO\BrazilSignaturePolicyOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$signedPdf = PdfSigner::signer()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withCertificatePath('/tmp/certificate.pfx', 'secret-password')
 ->withBrazilPolicy(BrazilSignaturePolicyOptionsDto::serpro(
     consumerKey: 'SEU_CONSUMER_KEY',
     consumerSecret: 'SEU_CONSUMER_SECRET',
     hashAlgorithm: 'sha256',
     timeoutSeconds: 20
 ))
 ->sign();
```

### 7) Proteger e Assinar PDF (bloquear cópia/impressão/modificação)

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

### 8) Fluxo recomendado: proteger e assinar no mesmo builder

Use este fluxo quando você quer evitar erro de ordem e garantir que a assinatura seja aplicada sobre a versão já protegida.

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

### 9) Validar assinaturas digitais de um PDF

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$validation = PdfSigner::validation()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->validate();

if (! $validation->hasSignatures) {
 // PDF sem assinaturas
}

if ($validation->allValid) {
 // todas as assinaturas verificadas
}
```

### 9.1) Validação com cadeia de confiança (trust store)

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$validation = PdfSigner::validation()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->enableTrustChainValidation('/etc/ssl/certs/ca-certificates.crt') // opcional; se omitido tenta bundle padrão do sistema
 ->validate();

foreach ($validation->entries as $entry) {
 var_dump($entry->trustValid); // true|false|null
}
```

### 9.2) Validação no modo política Brasil (br-iti)

```php
<?php

use PdfSigner\Presentation\PdfSigner;

$validation = PdfSigner::validation()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withBrazilPolicy('/caminho/icp-brasil-bundle.pem')
 ->validate();
```

Precedência do trust store no `withBrazilPolicy(...)`:

- Se `trustStorePath` for informado, ele é usado diretamente.
- Se `trustStorePath` for `null`, a lib monta/atualiza automaticamente um bundle ICP-Brasil em cache local e usa esse bundle.

Se `trustStorePath` for `null`, o modo `br-iti` monta automaticamente um bundle de trust anchors da ICP-Brasil em cache local:

- diretório padrão: `sys_get_temp_dir()/signer-php/trust-anchors`
- URLs padrão:
 - `http://acraiz.icpbrasil.gov.br/Certificado_AC_Raiz.crt`
 - `http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv2.crt`
 - `http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv5.crt`
 - `http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv6.crt`
 - `http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv7.crt`

No modo `br-iti`, a validação também verifica a LPA PAdES da ICP-Brasil:

- `https://politicas.icpbrasil.gov.br/LPA_PAdES.der`
- `https://politicas.icpbrasil.gov.br/LPA_PAdES.p7s`

Você pode sobrescrever essas URLs:

```php
<?php

use PdfSigner\Application\DTO\BrazilPolicyLpaUrlsDto;
use PdfSigner\Application\DTO\BrazilTrustAnchorsOptionsDto;
use PdfSigner\Presentation\PdfSigner;

$validation = PdfSigner::validation()
 ->withPdfContent(file_get_contents('/tmp/input.pdf'))
 ->withBrazilPolicy(
    '/caminho/icp-brasil-bundle.pem',
     new BrazilPolicyLpaUrlsDto(
         lpaUrlAsn1Pades: 'https://seu-endpoint/LPA_PAdES.der',
         lpaUrlAsn1SignaturePades: 'https://seu-endpoint/LPA_PAdES.p7s',
     ),
     new BrazilTrustAnchorsOptionsDto(
         directory: '/tmp/meu-cache-trust-anchors',
         urls: [
             'http://acraiz.icpbrasil.gov.br/Certificado_AC_Raiz.crt',
             'http://acraiz.icpbrasil.gov.br/credenciadas/RAIZ/ICP-Brasilv7.crt',
         ],
     ),
 )
 ->validate();
```

### 9.3) Como interpretar `trustValid` e `policyValid`

- `trustValid = true`: cadeia de certificados válida para a trust store usada.
- `trustValid = false`: cadeia inválida, incompleta ou não confiável para a trust store usada.
- `trustValid = null`: validação de cadeia não executada para aquela assinatura.
- `policyValid = true`: verificação da LPA PAdES (ICP-Brasil ou URLs sobrescritas) passou.
- `policyValid = false`: política/LPA não validou para a assinatura.
- `policyValid = null`: política não foi solicitada no fluxo.

## Inspeção de assinatura via CLI

Além do comando de assinatura, o projeto expõe `bin/signer-inspect` para diagnóstico técnico de PDFs assinados.

### Uso básico

```bash
php bin/signer-inspect --input=/tmp/output-signed.pdf
```

### Saída JSON

```bash
php bin/signer-inspect --input=/tmp/output-signed.pdf --json
```

Campos principais da inspeção:

- `inferred_profile`: perfil inferido (`pades-baseline-b`, `t`, `lt`, `lta`).
- `features`: presença de `DSS`, `VRI`, `DocMDP`, `OCSPs`, `CRLs`.
- `revocation_endpoints`: OCSP/CRL por certificado encontrado.
- `revocation_risk_summary`: flags de risco de conectividade e ausência de endpoints.

Essa inspeção ajuda a explicar avisos em validadores externos (ex.: indisponibilidade de CRL/OCSP).

## Fluxo recomendado (Brasil/ITI)

1. Assine com `--policy=br-iti` no `bin/signer-sign`.
2. Valide programaticamente com `PdfSigner::validation()->withBrazilPolicy(...)`.
3. Inspecione o PDF final com `bin/signer-inspect --json`.
4. Se houver ressalvas de revogação, confira `revocation_risk_summary` e a disponibilidade dos endpoints de OCSP/CRL.

## Assinando via linha de comando (CLI)

O projeto expõe os executáveis `bin/signer-sign` e `bin/signer-inspect`.

### Uso básico

```bash
php bin/signer-sign \
 --input=/tmp/input.pdf \
 --output=/tmp/output-signed.pdf \
 --cert=/tmp/certificate.pfx \
 --password='secret-password'
```

### Exemplo com PAdES Baseline-B e timestamp explícito

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

### Exemplo com certificação DocMDP

```bash
php bin/signer-sign \
 --input=/tmp/input.pdf \
 --output=/tmp/output-certified.pdf \
 --cert=/tmp/certificate.pfx \
 --password='secret-password' \
 --certification-level=2
```

### Exemplo com política Brasil no CLI

```bash
php bin/signer-sign \
 --input=/tmp/input.pdf \
 --output=/tmp/output-br-iti.pdf \
 --cert=/tmp/certificate.pfx \
 --password='secret-password' \
 --policy=br-iti \
 --policy-serpro-consumer-key='SEU_CONSUMER_KEY' \
 --policy-serpro-consumer-secret='SEU_CONSUMER_SECRET' \
 --policy-timestamp-hash='sha256' \
 --policy-timestamp-timeout=20
```

### Exemplo com PAdES Baseline-LT

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

### Exemplo com PAdES Baseline-LTA

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

### Ajuda de opções

```bash
php bin/signer-sign --help
```

## Exceções que você deve tratar

- `PdfSigner\\Domain\\Exception\\InvalidCertificateException`
- `PdfSigner\\Domain\\Exception\\SignProcessException`
- `PdfSigner\\Domain\\Exception\\PdfSignerException`

## Requisitos operacionais

- Para timestamp RFC3161, o host precisa ter `openssl` com suporte ao comando `openssl ts`.
- Para proteção de PDF por permissões, o host precisa ter `qpdf` instalado.
- Se usar TSA público padrão, considere disponibilidade/SLA em produção e prefira um provedor dedicado.

## Notas de implementação

- A assinatura digital cobre bytes específicos do PDF (`ByteRange`). Por isso, operações que regravem o arquivo devem ocorrer antes da assinatura.
- A validação verifica integridade (`ByteRange`) e validade criptográfica CMS/PKCS#7 com OpenSSL. Opcionalmente, pode validar cadeia de confiança via trust store (`enableTrustChainValidation(...)`) e política Brasil (`withBrazilPolicy(...)`).
- Perfis PAdES:
  - Baseline-B: `SubFilter /ETSI.CAdES.detached`
  - Baseline-T: Baseline-B + RFC3161
  - Baseline-LT: Baseline-T + `DSS` (`Certs`, `OCSPs`, `CRLs`, `VRI`) com coleta best-effort conforme disponibilidade dos endpoints da cadeia
  - Baseline-LTA: Baseline-LT + `Document Timestamp` arquivístico adicional
- A aparência padrão usa `page = 0` e retângulo interno padrão; para controle total, use `withAppearance(...)`.
- Algumas variações de PNG (filtros/modos específicos) podem ser rejeitadas.
- Escopo técnico atual do parser PDF:
  - Objetos com geração diferente de `0` não são suportados
  - Extended object streams não são suportados

## Executando os testes

```bash
vendor/bin/pest --configuration phpunit.xml tests
```

## Qualidade de código

```bash
composer pint:check
composer pint:fix
composer security:audit
composer tests:coverage
composer psalm
composer deptrac
composer infection
```
