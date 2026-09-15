<?php
declare(strict_types=1);

namespace App\Controller;

use App\Authenticator\LocalAuthenticator;
use App\Model\Entity\User;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Event\EventInterface;

class UsersController extends AppController
{
    /**
     * @inheritDoc
     */
    public function beforeFilter(EventInterface $event): void
    {
        $this->Authentication->allowUnauthenticated(['login']);
        parent::beforeFilter($event);
    }

    /**
     * Autentica o usuário e limita tentativas por endereço de origem.
     *
     * @return \Cake\Http\Response|null
     */
    public function login()
    {
        if (Configure::read('LocalAccess.enabled')) {
            return $this->redirect('/');
        }
        $this->viewBuilder()->setLayout('auth');
        $key = 'login_' . hash('sha256', (string)$this->request->clientIp());
        $attempts = Cache::read($key, 'default') ?: ['count' => 0, 'until' => 0];
        if ($attempts['until'] < time()) {
            $attempts = ['count' => 0, 'until' => time() + 900];
        }
        if ($this->request->is('post') && $attempts['count'] >= 10) {
            $this->Authentication->logout();
            $this->Flash->error('Muitas tentativas. Aguarde 15 minutos e tente novamente.');
            $this->setResponse($this->response->withStatus(429));

            return null;
        }
        if ($this->Authentication->getResult()->isValid()) {
            if (!$this->Authentication->getIdentity()->get('active')) {
                $this->Authentication->logout();
            } else {
                Cache::delete($key, 'default');
                $this->request->getSession()->renew();

                return $this->redirect('/');
            }
        }
        if ($this->request->is('post')) {
            $attempts['count']++;
            Cache::write($key, $attempts, 'default');
            $this->Flash->error('E-mail ou senha inválidos, ou usuário inativo.');
        }
    }

    /**
     * Encerra a autenticação e destrói a sessão.
     *
     * @return \Cake\Http\Response|null
     */
    public function logout()
    {
        $this->request->allowMethod(['post']);
        if (Configure::read('LocalAccess.enabled')) {
            return $this->redirect('/');
        }
        $this->Authentication->logout();
        $this->request->getSession()->destroy();

        return $this->redirect('/login');
    }

    /**
     * Exibe a listagem permitida para o usuário autenticado.
     *
     * @return void
     */
    public function index()
    {
        $this->requireAdmin();
        $query = $this->fetchTable('Users')->find()->orderBy(['name' => 'ASC']);
        $this->set('users', $this->paginate($query, ['limit' => 20]));
    }

    /**
     * Valida e cadastra um novo registro.
     *
     * @return \Cake\Http\Response|null
     */
    public function add()
    {
        $this->requireAdmin();
        $user = $this->fetchTable('Users')->newEmptyEntity();
        $user->patch(['role' => 'USUARIO', 'active' => true], ['guard' => false]);

        return $this->saveUser($user);
    }

    /**
     * Valida e atualiza um registro existente.
     *
     * @return \Cake\Http\Response|null
     */
    public function edit(string $id)
    {
        $this->requireAdmin();

        return $this->saveUser($this->fetchTable('Users')->get($id));
    }

    /**
     * Persiste um usuário após validar campos e proteger o próprio acesso.
     *
     * @return \Cake\Http\Response|null
     */
    private function saveUser(User $user)
    {
        if (Configure::read('LocalAccess.enabled') && $user->email === LocalAuthenticator::EMAIL) {
            $this->Flash->info('PCM é a conta interna de auditoria e não precisa ser editada.');

            return $this->redirect(['action' => 'index']);
        }
        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            $data['email'] = mb_strtolower(trim((string)($data['email'] ?? '')));
            $data['name'] = trim((string)($data['name'] ?? ''));
            if (!$user->isNew() && ($data['password'] ?? '') === '') {
                unset($data['password']);
            }
            // Um administrador não pode desativar nem remover o próprio perfil.
            if ((int)$user->id === (int)$this->Authentication->getIdentity()->getIdentifier()) {
                $data['role'] = 'ADMIN';
                $data['active'] = true;
            }
            $table = $this->fetchTable('Users');
            $table->patchEntity($user, $data, [
                'fields' => ['name', 'email', 'password', 'role', 'active'],
                'accessibleFields' => ['role' => true, 'active' => true],
            ]);
            if ($table->save($user)) {
                $this->Flash->success('Usuário salvo com sucesso.');

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error('Revise os campos indicados.');
        }
        $this->set(compact('user'));
        $this->render('form');
    }
}
