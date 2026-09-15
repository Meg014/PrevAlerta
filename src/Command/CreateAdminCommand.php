<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class CreateAdminCommand extends Command
{
    /**
     * @inheritDoc
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Cria o primeiro administrador, sem senha padrão.')
            ->addArgument('email', ['required' => true])->addArgument('name', ['required' => true]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $users = $this->fetchTable('Users');
        if ($users->exists(['role' => 'ADMIN', 'active' => true])) {
            $io->err('Já existe um administrador ativo. Use a tela Usuários.');

            return static::CODE_ERROR;
        }
        $password = getenv('PREV_ADMIN_PASSWORD');
        if (!$password) {
            $io->err('Defina PREV_ADMIN_PASSWORD ou use scripts/create-admin.ps1 para informar a senha com segurança.');

            return static::CODE_ERROR;
        }
        $user = $users->newEntity([
            'email' => mb_strtolower(trim($args->getArgument('email'))),
            'name' => $args->getArgument('name'),
            'password' => $password,
        ]);
        $user->patch(['role' => 'ADMIN', 'active' => true], ['guard' => false]);
        if (!$users->save($user)) {
            $io->err(json_encode($user->getErrors(), JSON_UNESCAPED_UNICODE));

            return static::CODE_ERROR;
        }
        $io->success('Administrador criado. Acesse /login.');

        return static::CODE_SUCCESS;
    }
}
