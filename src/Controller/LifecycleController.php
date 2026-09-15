<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ChecklistLifecycleService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;

class LifecycleController extends AppController
{
    /** Confirma a pausa com motivo obrigatório. */
    public function pause(string $id): ?Response
    {
        return $this->change($id, true);
    }

    /** Confirma a reativação com uma nova data inicial. */
    public function reactivate(string $id): ?Response
    {
        return $this->change($id, false);
    }

    /** GET apenas apresenta; POST valida o estado e grava atomicamente. */
    private function change(string $id, bool $pausing): ?Response
    {
        $this->requireAdmin();
        $checklist = $this->fetchTable('Checklists')->get($id);
        $schedule = $this->fetchTable('ChecklistSchedules')->find()->where(['checklist_id' => $id])
            ->orderBy(['id' => 'DESC'])->firstOrFail();
        if ($this->request->is('post')) {
            try {
                $service = new ChecklistLifecycleService();
                $args = [(int)$id, (int)$this->request->getData('schedule_id'),
                    (int)$this->Authentication->getIdentity()->getIdentifier(),
                    (string)$this->request->getData($pausing ? 'reason' : 'start_date', '')];
                if ($pausing) {
                    $service->pause(...$args);
                } else {
                    $service->reactivate(...$args);
                }
                $this->Flash->success($pausing ? 'Checklist pausado com sucesso.' : 'Checklist reativado com sucesso.');

                return $this->redirect(['controller' => 'Checklists', 'action' => 'view', $id]);
            } catch (BadRequestException $exception) {
                $this->Flash->error($exception->getMessage());
                $this->response = $this->response->withStatus(422);
            }
        }
        $this->set(compact('checklist', 'schedule', 'pausing'));
        $this->render('confirm');

        return null;
    }
}
