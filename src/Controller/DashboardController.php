<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\RecurrenceService;

class DashboardController extends AppController
{
    /**
     * Exibe a listagem permitida para o usuário autenticado.
     *
     * @return void
     */
    public function index()
    {
        $data = (new RecurrenceService())->dashboard();
        $status = (string)$this->request->getQuery('status', '');
        if (!array_key_exists($status, $data['counts'])) {
            $status = '';
        }
        $search = trim((string)$this->request->getQuery('q', ''));
        $filtered = array_values(array_filter($data['rows'], static function (array $row) use ($status, $search): bool {
            $item = $row['checklist'];
            $matches = $status === '' ? $row['situation'] !== 'PAUSADO' : $row['situation'] === $status;
            $text = implode(' ', [$item->code, $item->name, $item->area, $item->route]);

            return $matches && ($search === '' || mb_stripos($text, $search) !== false);
        }));
        $totalRows = count($filtered);
        $pages = max(1, (int)ceil($totalRows / 25));
        $page = max(1, min($pages, (int)$this->request->getQuery('page', 1)));
        $data['rows'] = array_slice($filtered, ($page - 1) * 25, 25);
        foreach ($data['rows'] as &$row) {
            if ($row['situation'] === 'PAUSADO') {
                $row['schedule'] ??= $this->fetchTable('ChecklistSchedules')->find()
                    ->where(['checklist_id' => $row['checklist']->id])->orderBy(['id' => 'DESC'])->first();
                $row['pausedBy'] = $row['checklist']->paused_by
                    ? $this->fetchTable('Users')->get($row['checklist']->paused_by)->name : null;
            }
        }
        unset($row);
        $scheduleIds = [];
        foreach ($filtered as $row) {
            if ($row['situation'] !== 'PAUSADO' && $row['schedule']) {
                $scheduleIds[] = $row['schedule']->id;
            }
        }
        $pendingQuery = $this->fetchTable('ChecklistOccurrences')->find()
            ->contain(['ChecklistSchedules.Checklists'])->where([
                'ChecklistOccurrences.checklist_schedule_id IN' => $scheduleIds ?: [0],
                'ChecklistOccurrences.status' => 'PENDENTE', 'ChecklistOccurrences.scheduled_date <=' => $data['today'],
            ])->orderBy(['ChecklistOccurrences.scheduled_date' => 'ASC', 'ChecklistOccurrences.id' => 'ASC']);
        $pendingCount = $pendingQuery->count();
        $pendingPages = max(1, (int)ceil($pendingCount / 25));
        $pendingPage = max(1, min($pendingPages, (int)$this->request->getQuery('occurrence_page', 1)));
        $pendingOccurrences = $pendingQuery->limit(25)->offset(($pendingPage - 1) * 25)->all();
        $this->set($data + compact(
            'status',
            'search',
            'totalRows',
            'pages',
            'page',
            'pendingCount',
            'pendingPages',
            'pendingPage',
            'pendingOccurrences',
        ));
    }
}
