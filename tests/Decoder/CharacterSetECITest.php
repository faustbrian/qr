<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Decoder;

use Cline\Qr\Decoder\Common\CharacterSetECI;
use PHPUnit\Framework\TestCase;

/**
 * @author Brian Faust <brian@cline.sh>
 * @internal
 */
final class CharacterSetECITest extends TestCase
{
    public function test_it_resolves_numeric_eci_values(): void
    {
        $this->assertInstanceOf(CharacterSetECI::class, CharacterSetECI::getCharacterSetECIByValue(CharacterSetECI::UTF8));
        $this->assertSame('UTF-8', CharacterSetECI::name());
    }
}
