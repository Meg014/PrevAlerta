<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\OccurrencePresentation;
use App\Service\RecurrenceCalendar;
use App\Service\RecurrenceService;

class HistoryController extends AppController
{
    /** Lista a auditoria geral, sem ações de alteração ou exclusão. */
    public function index(): void
    {
        $query = $this->fetchTable('ChecklistHistory')->find()
            ->contain(['Checklists', 'Users', 'ChecklistOccurrences']);
        $search = trim((string)$this->request->getQuery('q', ''));
        $action = (string)$this->request->getQuery('action', '');
        if ($search !== '') {
            $query->where(['OR' => [
                'Checklists.code LIKE' => '%' . $search . '%',
                'Checklists.name LIKE' => '%' . $search . '%',
                'ChecklistHistory.description LIKE' => '%' . $search . '%',
            ]]);
        }
        if (in_array($action, ['CIENTE', 'CRIACAO', 'EDICAO', 'MUDANCA_DATA_BASE', 'PAUSA', 'REATIVACAO'], true)) {
            $query->where(['ChecklistHistory.action' => $action]);
        }
        $entries = $this->paginate(
            $query->orderBy(['ChecklistHistory.created' => 'DESC', 'ChecklistHistory.id' => 'DESC']),
            ['limit' => 25, 'maxLimit' => 50],
        );
        $this->set(compact('entries', 'search', 'action'));
    }

    /** Lista cada ciclo do checklist e o resultado definitivo do reconhecimento. */
    public function checklist(string $id): void
    {
        $checklist = $this->fetchTable('Checklists')->get($id);
        $today = (new RecurrenceCalendar())->today();
        (new RecurrenceService())->synchronize($checklist->id, $today);
        $query = $this->fetchTable('ChecklistOccurrences')->find()
            ->contain(['Users', 'AcknowledgementAudit', 'ChecklistSchedules'])
            ->where(['ChecklistSchedules.checklist_id' => $checklist->id]);
        $status = (string)$this->request->getQuery('status', '');
        if (in_array($status, ['PENDENTE', 'RECONHECIDA'], true)) {
            $query->where(['ChecklistOccurrences.status' => $status]);
        }
        $occurrences = $this->paginate($query->orderBy([
            'ChecklistOccurrences.scheduled_date' => 'DESC', 'ChecklistOccurrences.id' => 'DESC',
        ]), ['limit' => 25, 'maxLimit' => 50]);
        $presentation = new OccurrencePresentation();
        $lifecycleEntries = $this->fetchTable('ChecklistHistory')->find()->where([
            'checklist_id' => $checklist->id, 'action IN' => ['PAUSA', 'REATIVACAO'],
        ])->orderBy(['created' => 'DESC', 'id' => 'DESC'])->all();
        $this->set(compact('lifecycleEntries'));
        $this->set(compact('checklist', 'occurrences', 'today', 'status', 'presentation'));
    }
}
