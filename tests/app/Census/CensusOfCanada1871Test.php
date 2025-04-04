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

namespace Fisharebest\Webtrees\Census;

use Fisharebest\Webtrees\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CensusOfCanada1871::class)]
#[CoversClass(AbstractCensusColumn::class)]
class CensusOfCanada1871Test extends TestCase
{
    public function testPlaceAndDate(): void
    {
        $census = new CensusOfCanada1871();

        self::assertSame('Canada', $census->censusPlace());
        self::assertSame('02 APR 1871', $census->censusDate());
    }

    public function testColumns(): void
    {
        $census  = new CensusOfCanada1871();
        $columns = $census->columns();

        self::assertCount(14, $columns);
        self::assertInstanceOf(CensusColumnFullName::class, $columns[0]);
        self::assertInstanceOf(CensusColumnSexMF::class, $columns[1]);
        self::assertInstanceOf(CensusColumnAge::class, $columns[2]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[3]);
        self::assertInstanceOf(CensusColumnBirthPlaceSimple::class, $columns[4]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[5]);
        self::assertInstanceOf(CensusColumnNationality::class, $columns[6]);
        self::assertInstanceOf(CensusColumnOccupation::class, $columns[7]);
        self::assertInstanceOf(CensusColumnConditionCanadaMarriedWidowed::class, $columns[8]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[9]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[10]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[11]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[12]);
        self::assertInstanceOf(CensusColumnNull::class, $columns[13]);

        self::assertSame('Name', $columns[0]->abbreviation());
        self::assertSame('Sex', $columns[1]->abbreviation());
        self::assertSame('Age', $columns[2]->abbreviation());
        self::assertSame('Born', $columns[3]->abbreviation());
        self::assertSame('Birth Loc', $columns[4]->abbreviation());
        self::assertSame('Religion', $columns[5]->abbreviation());
        self::assertSame('Origin', $columns[6]->abbreviation());
        self::assertSame('Occupation', $columns[7]->abbreviation());
        self::assertSame('M/W', $columns[8]->abbreviation());
        self::assertSame('Recent Married', $columns[9]->abbreviation());
        self::assertSame('School', $columns[10]->abbreviation());
        self::assertSame('Read', $columns[11]->abbreviation());
        self::assertSame('Write', $columns[12]->abbreviation());
        self::assertSame('Date', $columns[13]->abbreviation());

        self::assertSame('Name', $columns[0]->title());
        self::assertSame('Sex', $columns[1]->title());
        self::assertSame('Age at last birthday', $columns[2]->title());
        self::assertSame('Born within last twelve months', $columns[3]->title());
        self::assertSame('Country or province of birth', $columns[4]->title());
        self::assertSame('Religion', $columns[5]->title());
        self::assertSame('Origin', $columns[6]->title());
        self::assertSame('Profession, occupation, or trade', $columns[7]->title());
        self::assertSame('Married or Widowed', $columns[8]->title());
        self::assertSame('Married within the last twelve months', $columns[9]->title());
        self::assertSame('Instruction - Going to school', $columns[10]->title());
        self::assertSame('Instruction - Over 20, unable to read', $columns[11]->title());
        self::assertSame('Instruction - Over 20, unable to write', $columns[12]->title());
        self::assertSame('Dates of Operations and Remarks', $columns[13]->title());
    }
}
