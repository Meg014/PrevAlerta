<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;

class ChecklistLifecycleService
{
    use LocatorAwareTrait;

    /** Encerra a programação somente depois de preservar os ciclos devidos até a pausa. */
    public function pause(int $id, int $scheduleId, int $userId, string $reason, ?DateTime $instant = null): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 10000) {
            throw new BadRequestException('Informe o motivo da pausa, com até 10000 caracteres.');
        }
        $this->transition($id, $scheduleId, $userId, 'PAUSA', $reason, $instant);
    }

    /** Uma nova programação recebe seu próprio cursor; nenhuma ocorrência anterior é reutilizada. */
    public function reactivate(int $id, int $scheduleId, int $userId, string $start, ?DateTime $instant = null): void
    {
        if (
            !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $start, $parts) ||
            !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])
        ) {
            throw new BadRequestException('Informe uma nova data inicial válida.');
        }
        $this->transition($id, $scheduleId, $userId, 'REATIVACAO', $start, $instant);
    }

    /** Bloqueio compartilhado com recorrência, edição e CIENTE protege também requisições simultâneas. */
    private function transition(
        int $id,
        int $scheduleId,
        int $userId,
        string $action,
        string $value,
        ?DateTime $instant,
    ): void {
        $user = $this->fetchTable('Users')->get($userId);
        if (!$user->active || $user->role !== 'ADMIN') {
            throw new ForbiddenException('Somente administradores podem pausar ou reativar checklists.');
        }
        $checklists = $this->fetchTable('Checklists');
        $schedules = $this->fetchTable('ChecklistSchedules');
        $history = $this->fetchTable('ChecklistHistory');
        $checklists->getConnection()->transactional(function () use (
            $id,
            $scheduleId,
            $userId,
            $action,
            $value,
            $instant,
            $user,
            $checklists,
            $schedules,
            $history,
        ): void {
            $item = $checklists->find()->where(['id' => $id])->epilog('FOR UPDATE')->firstOrFail();
            $schedule = $schedules->find()->where(['checklist_id' => $id])->orderBy(['id' => 'DESC'])->firstOrFail();
            $pausing = $action === 'PAUSA';
            if (
                $item->status !== ($pausing ? 'ATIVO' : 'PAUSADO') || $schedule->id !== $scheduleId ||
                ($pausing && $schedule->ended_at !== null)
            ) {
                throw new BadRequestException('O checklist mudou ou esta ação já foi realizada. Recarregue a página.');
            }
            $now = ($instant ?? DateTime::now('UTC'))->setTimezone('UTC');
            $old = ['status' => $item->status, 'schedule_id' => $schedule->id,
                'start_date' => $schedule->start_date->format('Y-m-d'), 'interval_days' => $schedule->interval_days];
            if ($pausing) {
                (new RecurrenceService())->synchronize($id, (new RecurrenceCalendar())->today($now));
                $schedule = $schedules->get($schedule->id);
                $schedule->set('ended_at', $now);
                $schedules->saveOrFail($schedule);
                $item->patch(['status' => 'PAUSADO', 'paused_at' => $now, 'paused_by' => $userId,
                    'pause_reason' => $value], ['guard' => false]);
                $description = 'Checklist pausado por ' . $user->name . '. Motivo: ' . $value;
            } else {
                // Compatibilidade com registros pausados preparados nas fases anteriores.
                if ($schedule->ended_at === null) {
                    $schedule->set('ended_at', $now);
                    $schedules->saveOrFail($schedule);
                }
                $next = $schedules->newEntity(['start_date' => $value, 'interval_days' => $schedule->interval_days]);
                $next->patch([
                    'checklist_id' => $id, 'created_by' => $userId, 'materialized_cycle' => -1,
                ], ['guard' => false]);
                $schedules->saveOrFail($next);
                $schedule = $next;
                $item->patch(['status' => 'ATIVO', 'paused_at' => null, 'paused_by' => null,
                    'pause_reason' => null], ['guard' => false]);
                $description = 'Checklist reativado por ' . $user->name . '. Nova data inicial: ' .
                    $schedule->start_date->format('d/m/Y') . '.';
            }
            $checklists->saveOrFail($item);
            $entry = $history->newEmptyEntity();
            $entry->patch(['checklist_id' => $id, 'user_id' => $userId, 'action' => $action,
                'description' => $item->code . ' · ' . $item->name . ': ' . $description,
                'old_value' => json_encode($old, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'new_value' => json_encode(['status' => $item->status, 'schedule_id' => $schedule->id,
                    'start_date' => $schedule->start_date->format('Y-m-d'), 'interval_days' => $schedule->interval_days,
                    'reason' => $pausing ? $value : null, 'user_name' => $user->name,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created' => $now], ['guard' => false]);
            $history->saveOrFail($entry);
        });
    }
}
