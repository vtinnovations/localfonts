<?php

declare(strict_types=1);

/*
 * Local Fonts
 *
 * Package: vtinnovations/localfonts
 * Copyright: V&T Innovations
 * Licence: LGPL-3.0-or-later
 * Website: https://www.v-t.one
 */

namespace VTinnovations\LocalFonts\Tests\Service;

use PHPUnit\Framework\TestCase;
use VTinnovations\LocalFonts\Service\DomainInventory;

/**
 * Representation-only normalization, tested via reflection so it does not
 * require a booted Contao framework/database. Framework-integrated behaviour
 * (reading real root pages, including the blank-`dns`-crashes-the-frontend
 * regression this class was fixed for) is covered by the live SSH
 * verification run against a real Contao instance, documented in the
 * completion report — a mocked PageModel adapter would only prove the mock
 * behaves as programmed, not that the real framework does.
 */
final class DomainInventoryNormalizationTest extends TestCase
{
    private \ReflectionMethod $normalize;
    private DomainInventory $subject;

    protected function setUp(): void
    {
        $ref = new \ReflectionClass(DomainInventory::class);
        $this->subject = $ref->newInstanceWithoutConstructor();
        $this->normalize = $ref->getMethod('normalize');
        $this->normalize->setAccessible(true);
    }

    private function normalize(string $host): ?string
    {
        return $this->normalize->invoke($this->subject, $host);
    }

    public function testBlankHostDoesNotThrow(): void
    {
        // The regression: idn_to_ascii('') throws a ValueError in PHP 8, and a
        // blank root-page `dns` field is the Contao default for a single-site
        // install. This must degrade to null, not crash the frontend request.
        self::assertNull($this->normalize(''));
    }

    public function testWhitespaceOnlyHostDoesNotThrow(): void
    {
        self::assertNull($this->normalize('   '));
    }

    public function testDotOnlyHostDoesNotThrow(): void
    {
        self::assertNull($this->normalize('.'));
    }

    public function testLowercasesTheHost(): void
    {
        self::assertSame('example.com', $this->normalize('Example.COM'));
    }

    public function testStripsExactlyOneTrailingDot(): void
    {
        self::assertSame('example.com', $this->normalize('example.com.'));
    }

    public function testStripsAPastedSchemeAndPath(): void
    {
        self::assertSame('example.com', $this->normalize('https://example.com/some/path'));
    }

    public function testStripsAnApprovedPort(): void
    {
        self::assertSame('example.com', $this->normalize('example.com:8443'));
    }

    public function testDoesNotStripANonNumericPortSuffix(): void
    {
        self::assertNull($this->normalize('example.com:notaport'));
    }

    public function testRejectsUserinfo(): void
    {
        self::assertNull($this->normalize('user@example.com'));
    }

    public function testConvertsIdnToPunycode(): void
    {
        self::assertSame('xn--mnchen-3ya.de', $this->normalize('münchen.de'));
    }

    public function testNeverStripsWww(): void
    {
        self::assertSame('www.example.com', $this->normalize('www.example.com'));
    }

    public function testRejectsAWildcardEntry(): void
    {
        self::assertNull($this->normalize('*.example.com'));
    }

    public function testRejectsALeadingHyphenLabel(): void
    {
        self::assertNull($this->normalize('-bad.example.com'));
    }

    public function testRejectsAnEmptyLabel(): void
    {
        self::assertNull($this->normalize('a..b.com'));
    }

    public function testRejectsAHostnameOverTheLengthLimit(): void
    {
        self::assertNull($this->normalize(str_repeat('x', 70) . '.com'));
    }

    public function testNeverThrowsForAnyOfALargeSetOfAdversarialInputs(): void
    {
        $inputs = ['', '.', ' ', "\t", '://', 'http://', '[::1]', '[::1]:80', '999.999.999.999', str_repeat('a', 1000), "example.com\0", '%00', "example.com\n"];

        foreach ($inputs as $input) {
            try {
                $this->normalize($input);
                self::assertTrue(true);
            } catch (\Throwable $e) {
                self::fail(\sprintf('normalize(%s) threw %s: %s', var_export($input, true), $e::class, $e->getMessage()));
            }
        }
    }
}
