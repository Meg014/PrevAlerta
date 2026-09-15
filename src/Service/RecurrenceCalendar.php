<?php
declare(strict_types=1);

namespace App\Service;

use Cake\I18n\Date;
use Cake\I18n\DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

/** Cálculo civil: dias de calendário, nunca segundos de duração. */
class RecurrenceCalendar
{
    public const TIMEZONE = 'America/Sao_Paulo';

    /** Retorna a data local do instante informado ou do relógio da aplicação. */
    public function today(?DateTimeInterface $instant = null): Date
    {
        $now = $instant === null ? DateTime::now(self::TIMEZONE) : new DateTime($instant);

        return new Date($now->setTimezone(self::TIMEZONE)->format('Y-m-d'));
    }

    /** Calcula diretamente um ciclo, sem depender do ciclo anterior. */
    public function occurrence(Date $start, int $interval, int $cycle): Date
    {
        if ($interval < 1 || $cycle < 0) {
            throw new InvalidArgumentException('Periodicidade positiva e ciclo não negativo são obrigatórios.');
        }

        return $start->addDays($interval * $cycle);
    }

    /** Diferença de datas civis, com sinal positivo para datas futuras. */
    public function daysUntil(Date $today, Date $date): int
    {
        $from = new DateTimeImmutable($today->format('Y-m-d'), new DateTimeZone('UTC'));
        $to = new DateTimeImmutable($date->format('Y-m-d'), new DateTimeZone('UTC'));

        return (int)$from->diff($to)->format('%r%a');
    }

    /** Último ciclo que chegou à data prevista; -1 quando a programação é futura. */
    public function dueCycle(Date $start, int $interval, Date $today): int
    {
        if ($interval < 1) {
            throw new InvalidArgumentException('A periodicidade deve ser positiva.');
        }
        $elapsed = $this->daysUntil($start, $today);

        return $elapsed < 0 ? -1 : intdiv($elapsed, $interval);
    }

    /** Próxima data do calendário, incluindo hoje; não quita pendências anteriores. */
    public function nextOnOrAfter(Date $start, int $interval, Date $today): Date
    {
        $last = $this->dueCycle($start, $interval, $today);
        if ($last < 0) {
            return $start;
        }
        $date = $this->occurrence($start, $interval, $last);

        return $date->equals($today) ? $date : $this->occurrence($start, $interval, $last + 1);
    }

    /** Classifica a ocorrência pendente mais antiga. */
    public function situation(int $days): string
    {
        return match (true) {
            $days < 0 => 'ATRASADO',
            $days === 0 => 'HOJE',
            $days <= 7 => 'PROXIMOS',
            default => 'EM_DIA',
        };
    }
}
