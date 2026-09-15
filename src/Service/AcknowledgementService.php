<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;

/** Confirma uma ocorrência e sua auditoria atomicamente, preservando o calendário. */
class AcknowledgementService
{
    use LocatorAwareTrait;

    /** Retorna false em uma repetição, sem modificar o primeiro reconhecimento. */
    public function acknowledge(int $occurrenceId, int $userId, ?DateTime $instant = null): bool
    {
        $occurrences = $this->fetchTable('ChecklistOccurrences');
        $schedules = $this->fetchTable('ChecklistSchedules');
        $checklists = $this->fetchTable('Checklists');
        $history = $this->fetchTable('ChecklistHistory');
        $user = $this->fetchTable('Users')->get($userId);
        if (!$user->active || !in_array($user->role, ['ADMIN', 'USUARIO'], true)) {
            throw new ForbiddenException('Seu usuário não pode confirmar ocorrências.');
        }
        $reference = $occurrences->get($occurrenceId, contain: ['ChecklistSchedules']);
        $checklistId = $reference->checklist_schedule->checklist_id;

        return $occurrences->getConnection()->transactional(function () use (
            $occurrences,
            $schedules,
            $checklists,
            $history,
            $user,
            $occurrenceId,
            $checklistId,
            $instant,
        ): array {
            // Mesma ordem de bloqueio da sincronização: checklist, depois ocorrência.
            $checklist = $checklists->find()->where(['id' => $checklistId])->epilog('FOR UPDATE')->firstOrFail();
            $occurrence = $occurrences->find()->where(['id' => $occurrenceId])->epilog('FOR UPDATE')->firstOrFail();
            if ($occurrence->status === 'RECONHECIDA') {
                return ['changed' => false];
            }
            $schedule = $schedules->get($occurrence->checklist_schedule_id);
            if ($checklist->status !== 'ATIVO' || $schedule->ended_at !== null) {
                throw new BadRequestException('Esta programação não está ativa.');
            }
            if ($occurrence->status !== 'PENDENTE' || $occurrence->acknowledged_at !== null) {
                throw new BadRequestException('Esta ocorrência não está disponível para confirmação.');
            }
            $now = ($instant ?? DateTime::now('UTC'))->setTimezone('UTC');
            $calendar = new RecurrenceCalendar();
            $today = $calendar->today($now);
            if ($occurrence->scheduled_date > $today) {
                throw new BadRequestException('O CIENTE só é permitido na data prevista ou depois dela.');
            }
            $occurrence->patch([
                'status' => 'RECONHECIDA', 'acknowledged_by' => $user->id, 'acknowledged_at' => $now,
            ], ['guard' => false]);
            $occurrences->saveOrFail($occurrence);
            $entry = $history->newEmptyEntity();
            $entry->patch([
                'checklist_id' => $checklistId, 'checklist_occurrence_id' => $occurrence->id,
                'user_id' => $user->id, 'action' => 'CIENTE', 'created' => $now,
                'description' => sprintf(
                    'Ocorrência de %s — %s, prevista para %s, marcada como ciente por %s.',
                    $checklist->code,
                    $checklist->name,
                    $occurrence->scheduled_date->format('d/m/Y'),
                    $user->name,
                ),
                'old_value' => json_encode(['status' => 'PENDENTE'], JSON_THROW_ON_ERROR),
                'new_value' => json_encode([
                    'status' => 'RECONHECIDA', 'occurrence_id' => $occurrence->id,
                    'scheduled_date' => $occurrence->scheduled_date->format('Y-m-d'),
                    'acknowledged_at' => $now->format('Y-m-d\TH:i:s\Z'),
                    'acknowledged_by' => $user->id, 'user_name' => $user->name,
                    'checklist_code' => $checklist->code, 'checklist_name' => $checklist->name,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ], ['guard' => false]);
            $history->saveOrFail($entry);

            return ['changed' => true];
        })['changed'];
    }
}
