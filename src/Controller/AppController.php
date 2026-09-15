<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;

class AppController extends Controller
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Flash');
        $this->loadComponent('Authentication.Authentication');
        $this->loadComponent('FormProtection');
    }

    /**
     * @inheritDoc
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $identity = $this->request->getAttribute('identity');
        if ($identity && !$identity->get('active')) {
            $this->Authentication->logout();
            $event->setResult($this->redirect('/login'));
            $event->stopPropagation();
        }
    }

    /**
     * @inheritDoc
     */
    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);
        $identity = $this->request->getAttribute('identity');
        $this->set('currentUser', $identity);
        $this->set('isAdmin', $identity && $identity->get('role') === 'ADMIN');
        $this->set('localAccess', (bool)Configure::read('LocalAccess.enabled'));
    }

    /**
     * Exige perfil de administrador para operações de gestão.
     *
     * @return void
     */
    protected function requireAdmin(): void
    {
        if ($this->request->getAttribute('identity')?->get('role') !== 'ADMIN') {
            throw new ForbiddenException('Esta ação é permitida somente para administradores.');
        }
    }
}
