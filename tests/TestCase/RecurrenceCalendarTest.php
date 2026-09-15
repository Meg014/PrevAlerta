<?php
declare(strict_types=1);

namespace App\Test\TestCase;

use App\Service\RecurrenceCalendar;
use Cake\I18n\Date;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecurrenceCalendarTest extends TestCase
{
    public static function dates(): array
    {
        return [
            '5 dias' => ['2026-09-10', 5, 3, '2026-09-25'],
            '15 dias' => ['2026-09-10', 15, 4, '2026-11-09'],
            '30 dias não é um mês' => ['2026-01-31', 30, 1, '2026-03-02'],
            'virada de mês' => ['2026-09-25', 15, 1, '2026-10-10'],
            'virada de ano' => ['2026-12-31', 15, 1, '2027-01-15'],
            'janeiro segundo ciclo' => ['2026-12-31', 15, 2, '2027-01-30'],
            'fevereiro seguinte' => ['2026-12-31', 15, 3, '2027-02-14'],
            'ano bissexto' => ['2028-02-28', 1, 1, '2028-02-29'],
            'data-base inclusiva' => ['2026-09-10', 15, 0, '2026-09-10'],
        ];
    }

    #[DataProvider('dates')]
    public function testDates(string $start, int $interval, int $cycle, string $expected): void
    {
        $actual = (new RecurrenceCalendar())->occurrence(new Date($start), $interval, $cycle);
        $this->assertSame($expected, $actual->format('Y-m-d'));
    }

    public static function nextDates(): array
    {
        return [
            'exatamente hoje' => ['2026-09-10', 15, '2026-09-25', '2026-09-25'],
            'amanhã' => ['2026-09-10', 15, '2026-09-24', '2026-09-25'],
            'início futuro' => ['2026-10-01', 5, '2026-09-10', '2026-10-01'],
            'ciclo de 30 dias' => ['2026-09-10', 30, '2026-09-11', '2026-10-10'],
        ];
    }

    #[DataProvider('nextDates')]
    public function testNextCalendarDate(string $start, int $interval, string $today, string $expected): void
    {
        $date = (new RecurrenceCalendar())->nextOnOrAfter(new Date($start), $interval, new Date($today));
        $this->assertSame($expected, $date->format('Y-m-d'));
    }

    public function testLocalMidnightAndYearBoundary(): void
    {
        $calendar = new RecurrenceCalendar();
        $this->assertSame('2026-12-31', $calendar->today(new DateTimeImmutable('2027-01-01T02:59:59Z'))->format('Y-m-d'));
        $this->assertSame('2027-01-01', $calendar->today(new DateTimeImmutable('2027-01-01T03:00:00Z'))->format('Y-m-d'));
    }

    public function testClassificationAndCivilDays(): void
    {
        $calendar = new RecurrenceCalendar();
        foreach ([-2 => 'ATRASADO', 0 => 'HOJE', 1 => 'PROXIMOS', 7 => 'PROXIMOS', 8 => 'EM_DIA'] as $days => $expected) {
            $this->assertSame($expected, $calendar->situation($days));
        }
        $this->assertSame(1, $calendar->daysUntil(new Date('2018-11-03'), new Date('2018-11-04')));
        $this->assertSame(-2, $calendar->daysUntil(new Date('2027-01-02'), new Date('2026-12-31')));
    }
}
