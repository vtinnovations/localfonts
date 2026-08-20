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
use VTinnovations\LocalFonts\Service\CanonicalJson;

/**
 * `vt-one/canonical-json-v1`: both sides of the signature must agree on the
 * exact same bytes for the exact same logical document.
 */
final class CanonicalJsonTest extends TestCase
{
    public function testSortsMapKeysBytewiseAscending(): void
    {
        self::assertSame('{"a":1,"b":2,"z":3}', CanonicalJson::encode(['z' => 3, 'a' => 1, 'b' => 2], []));
    }

    public function testSortsNestedMapKeysRecursively(): void
    {
        self::assertSame('{"n":{"a":2,"z":1}}', CanonicalJson::encode(['n' => ['z' => 1, 'a' => 2]], []));
    }

    public function testPreservesListOrderExactly(): void
    {
        self::assertSame('["z","a","m"]', CanonicalJson::encode(['z', 'a', 'm'], []));
    }

    public function testDropsOnlyTheExcludedTopLevelKey(): void
    {
        $json = CanonicalJson::encode(['a' => 1, 'signature' => 'drop-me', 'b' => 2]);

        self::assertSame('{"a":1,"b":2}', $json);
    }

    public function testDoesNotDropNestedSignatureFields(): void
    {
        // Exclusion is top-level only; a nested "signature" key is data, not the envelope's own signature.
        $json = CanonicalJson::encode(['outer' => ['signature' => 'keep-me']], ['signature']);

        self::assertSame('{"outer":{"signature":"keep-me"}}', $json);
    }

    public function testDoesNotEscapeSlashesOrUnicode(): void
    {
        $json = CanonicalJson::encode(['url' => 'https://example.com/path', 'name' => 'münchen'], []);

        self::assertSame('{"name":"münchen","url":"https://example.com/path"}', $json);
    }

    public function testPreservesScalarTypesExactly(): void
    {
        $json = CanonicalJson::encode(['b' => false, 'n' => null, 'i' => 1, 's' => '1'], []);

        self::assertSame('{"b":false,"i":1,"n":null,"s":"1"}', $json);
    }

    public function testIsNotPrettyPrinted(): void
    {
        $json = CanonicalJson::encode(['a' => 1], []);

        self::assertStringNotContainsString("\n", $json);
        self::assertStringNotContainsString('    ', $json);
    }

    public function testEncodingIsDeterministicRegardlessOfInputOrder(): void
    {
        $first = CanonicalJson::encode(['b' => 1, 'a' => 2], []);
        $second = CanonicalJson::encode(['a' => 2, 'b' => 1], []);

        self::assertSame($first, $second);
    }
}
