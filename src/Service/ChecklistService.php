<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\Entity\Checklist;
use App\Model\Entity\ChecklistSchedule;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;

class ChecklistService
{
    use LocatorAwareTrait;

    /** Cadastro, programação e auditoria sempre na mesma transação. */
    public function save(Checklist $checklist, ChecklistSchedule $schedule, array $data, int $userId): bool
    {
        $checklists = $this->fetchTable('Checklists');
        $schedules = $this->fetchTable('ChecklistSchedules');
        $history = $this->fetchTable('ChecklistHistory');
        $isNew = $checklist->isNew();
        $old = $isNew ? null : $this->snapshot($checklist, $schedule);
        foreach (['code', 'name', 'area', 'route', 'description', 'notes'] as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
            }
        }
        $checklists->patchEntity($checklist, $data, [
            'fields' => ['code', 'name', 'area', 'route', 'description', 'notes'],
        ]);
        $schedules->patchEntity($schedule, $data, ['fields' => ['start_date', 'interval_days']]);
        if ($checklist->hasErrors() || $schedule->hasErrors()) {
            return false;
        }
        if ($isNew) {
            $checklist->set('status', 'ATIVO');
            $schedule->set('created_by', $userId);
        }
        // Protege também ciclos vencidos antes da primeira visita ao dashboard.
        if (
            !$isNew && ($schedule->isDirty('start_date') || $schedule->isDirty('interval_days')) &&
            ($checklist->status === 'PAUSADO' || $schedule->ended_at !== null ||
            $old['start_date'] <= (new RecurrenceCalendar())->today()->format('Y-m-d') ||
            $this->fetchTable('ChecklistOccurrences')->exists(['checklist_schedule_id' => $schedule->id]))
        ) {
            $schedule->setError(
                'start_date',
                'A programação já iniciada não pode ser alterada. Seus ciclos previstos serão preservados.',
            );

            return false;
        }

        return $checklists->getConnection()->transactional(function () use (
            $checklists,
            $schedules,
            $history,
            $checklist,
            $schedule,
            $userId,
            $isNew,
            $old,
        ): bool {
            if (!$isNew) {
                $locked = $checklists->find()->where(['id' => $checklist->id])->epilog('FOR UPDATE')->firstOrFail();
                $current = $schedules->get($schedule->id);
                $latest = $schedules->find()->where(['checklist_id' => $checklist->id])
                    ->orderBy(['id' => 'DESC'])->firstOrFail();
                if (
                    $locked->status !== $old['status'] || $latest->id !== $schedule->id ||
                    $current->ended_at != $schedule->ended_at ||
                    $current->start_date->format('Y-m-d') !== $old['start_date'] ||
                    $current->interval_days !== $old['interval_days']
                ) {
                    $schedule->setError('start_date', 'A programação mudou. Recarregue a página antes de editar.');

                    return false;
                }
            }
            if (!$checklists->save($checklist)) {
                return false;
            }
            $schedule->set('checklist_id', $checklist->id);
            $schedules->saveOrFail($schedule);
            $new = $this->snapshot($checklist, $schedule);
            $entry = $history->newEmptyEntity();
            $entry->patch([
                'checklist_id' => $checklist->id, 'user_id' => $userId,
                'action' => $isNew ? 'CRIACAO' : 'EDICAO',
                'description' => $isNew ? 'Checklist cadastrado.' : 'Cadastro do checklist atualizado.',
                'old_value' => $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'new_value' => json_encode($new, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'created' => DateTime::now('UTC'),
            ], ['guard' => false]);
            $history->saveOrFail($entry);
            if ($old !== null && $old['start_date'] !== $new['start_date']) {
                $baseEntry = $history->newEmptyEntity();
                $baseEntry->patch([
                    'checklist_id' => $checklist->id, 'user_id' => $userId, 'action' => 'MUDANCA_DATA_BASE',
                    'description' => 'Data-base da programação inicial alterada.',
                    'old_value' => $old['start_date'], 'new_value' => $new['start_date'],
                    'created' => DateTime::now('UTC'),
                ], ['guard' => false]);
                $history->saveOrFail($baseEntry);
            }

            return true;
        });
    }

    /**
     * Captura os valores de cadastro e programação para auditoria.
     *
     * @return array
     */
    private function snapshot(Checklist $checklist, ChecklistSchedule $schedule): array
    {
        return $checklist->extract(['code', 'name', 'area', 'route', 'description', 'notes', 'status']) + [
            'start_date' => $schedule->start_date?->format('Y-m-d'),
            'interval_days' => $schedule->interval_days,
        ];
    }
}
