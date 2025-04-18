<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2025 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Tests\Encodings;

use Fisharebest\Webtrees\Encodings\AbstractEncoding;
use Fisharebest\Webtrees\Encodings\UTF8;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function chr;

#[CoversClass(AbstractEncoding::class)]
#[CoversClass(UTF8::class)]
class UTF8Test extends TestCase
{
    public function testToUtf8(): void
    {
        $encoding = new UTF8();

        for ($i = 0; $i < 128; ++$i) {
            $char = chr($i);
            self::assertSame($char, $encoding->toUtf8($char));
        }
    }

    public function testFromUtf8(): void
    {
        $encoding = new UTF8();

        for ($i = 0; $i < 128; ++$i) {
            $char = chr($i);
            self::assertSame($char, $encoding->fromUtf8($char));
        }
    }
}
