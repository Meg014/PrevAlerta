<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\ChecklistService;

class ChecklistsController extends AppController
{
    /**
     * Exibe a listagem permitida para o usuário autenticado.
     *
     * @return void
     */
    public function index()
    {
        $query = $this->fetchTable('Checklists')->find()->contain(['CurrentSchedule']);
        $search = trim((string)$this->request->getQuery('q', ''));
        $status = (string)$this->request->getQuery('status', '');
        if ($search !== '') {
            $query->where(['OR' => [
                'Checklists.name LIKE' => '%' . $search . '%',
                'Checklists.code LIKE' => '%' . $search . '%',
                'Checklists.area LIKE' => '%' . $search . '%',
            ]]);
        }
        if (in_array($status, ['ATIVO', 'PAUSADO'], true)) {
            $query->where(['Checklists.status' => $status]);
        }
        $checklists = $this->paginate($query->orderBy(['Checklists.name' => 'ASC']), ['limit' => 15, 'maxLimit' => 50]);
        $this->set(compact('checklists', 'search', 'status'));
    }

    /**
     * Exibe os dados do checklist solicitado.
     *
     * @return void
     */
    public function view(string $id)
    {
        $checklist = $this->fetchTable('Checklists')->get($id, contain: ['CurrentSchedule']);
        $schedule = $checklist->current_schedule ?? $this->fetchTable('ChecklistSchedules')->find()
            ->where(['checklist_id' => $id])->orderBy(['id' => 'DESC'])->first();
        $this->set(compact('schedule'));
        $this->set(compact('checklist'));
    }

    /**
     * Valida e cadastra um novo registro.
     *
     * @return \Cake\Http\Response|null
     */
    public function add()
    {
        $this->requireAdmin();
        $checklist = $this->fetchTable('Checklists')->newEmptyEntity();
        $schedule = $this->fetchTable('ChecklistSchedules')->newEmptyEntity();
        if ($this->request->is('post')) {
            $userId = (int)$this->Authentication->getIdentity()->getIdentifier();
            if ((new ChecklistService())->save($checklist, $schedule, $this->request->getData(), $userId)) {
                $this->Flash->success('Checklist cadastrado com sucesso.');

                return $this->redirect(['action' => 'view', $checklist->id]);
            }
            $this->Flash->error('Revise os campos indicados.');
        }
        $this->set(compact('checklist', 'schedule'));
        $this->render('form');
    }

    /**
     * Valida e atualiza um registro existente.
     *
     * @return \Cake\Http\Response|null
     */
    public function edit(string $id)
    {
        $this->requireAdmin();
        $checklist = $this->fetchTable('Checklists')->get($id, contain: ['CurrentSchedule']);
        $schedule = $checklist->current_schedule ?? $this->fetchTable('ChecklistSchedules')->find()
            ->where(['checklist_id' => $id])->orderBy(['id' => 'DESC'])->first();
        if (!$schedule) {
            $this->Flash->error('Programação não encontrada.');

            return $this->redirect(['action' => 'view', $id]);
        }
        if ($this->request->is(['post', 'put', 'patch'])) {
            $userId = (int)$this->Authentication->getIdentity()->getIdentifier();
            if ((new ChecklistService())->save($checklist, $schedule, $this->request->getData(), $userId)) {
                $this->Flash->success('Checklist atualizado com sucesso.');

                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error('Revise os campos indicados.');
        }
        $this->set(compact('checklist', 'schedule'));
        $this->render('form');
    }
}
