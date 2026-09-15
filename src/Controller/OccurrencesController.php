<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\AcknowledgementService;
use App\Service\RecurrenceCalendar;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Response;

class OccurrencesController extends AppController
{
    /** Confirmação acessível também sem JavaScript, sem gravar dados em GET. */
    public function confirm(string $id): void
    {
        $this->request->allowMethod(['get']);
        $occurrence = $this->fetchTable('ChecklistOccurrences')->get($id, contain: ['ChecklistSchedules.Checklists']);
        $checklist = $occurrence->checklist_schedule->checklist;
        $today = (new RecurrenceCalendar())->today();
        if (
            $occurrence->status !== 'PENDENTE' || $occurrence->scheduled_date > $today ||
            $checklist->status !== 'ATIVO' || $occurrence->checklist_schedule->ended_at !== null
        ) {
            throw new BadRequestException('Esta ocorrência não está disponível para confirmação.');
        }
        $returnTo = $this->request->getQuery('return') === 'history' ? 'history' : 'dashboard';
        $this->set(compact('occurrence', 'checklist', 'today', 'returnTo'));
    }

    /** Persiste apenas o ID selecionado; usuário e instante vêm do servidor. */
    public function acknowledge(string $id): Response
    {
        $this->request->allowMethod(['post']);
        if ($this->request->getData('confirmed') !== '1') {
            throw new BadRequestException('Confirme o tratamento do aviso antes de continuar.');
        }
        $userId = (int)$this->Authentication->getIdentity()->getIdentifier();
        $changed = (new AcknowledgementService())->acknowledge((int)$id, $userId);
        $this->Flash->success($changed
            ? 'Ocorrência marcada como ciente com sucesso.'
            : 'Esta ocorrência já foi marcada como ciente. O registro original foi preservado.');
        if ($this->request->getData('return_to') === 'history') {
            $occurrence = $this->fetchTable('ChecklistOccurrences')->get($id, contain: ['ChecklistSchedules']);

            return $this->redirect([
                'controller' => 'History', 'action' => 'checklist', $occurrence->checklist_schedule->checklist_id,
                '_method' => 'GET',
            ]);
        }

        return $this->redirect('/');
    }
}
