<?php
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);
    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Dashboard', 'action' => 'index']);
        $builder->connect('/desktop/alert-summary', ['controller' => 'Desktop', 'action' => 'summary'])->setMethods(['GET']);
        $builder->connect('/login', ['controller' => 'Users', 'action' => 'login']);
        $builder->connect('/logout', ['controller' => 'Users', 'action' => 'logout'])->setMethods(['POST']);
        $builder->connect('/checklists', ['controller' => 'Checklists', 'action' => 'index']);
        $builder->connect('/checklists/novo', ['controller' => 'Checklists', 'action' => 'add']);
        $builder->connect('/checklists/{id}/historico', ['controller' => 'History', 'action' => 'checklist'])->setPatterns(['id' => '\d+'])->setPass(['id'])->setMethods(['GET']);
        $builder->connect('/historico', ['controller' => 'History', 'action' => 'index'])->setMethods(['GET']);
        $builder->connect('/ocorrencias/{id}/confirmar', ['controller' => 'Occurrences', 'action' => 'confirm'])->setPatterns(['id' => '\d+'])->setPass(['id'])->setMethods(['GET']);
        $builder->connect('/ocorrencias/{id}/ciente', ['controller' => 'Occurrences', 'action' => 'acknowledge'])->setPatterns(['id' => '\d+'])->setPass(['id'])->setMethods(['POST']);
        $builder->connect('/checklists/{id}', ['controller' => 'Checklists', 'action' => 'view'])->setPatterns(['id' => '\d+'])->setPass(['id']);
        $builder->connect('/checklists/{id}/editar', ['controller' => 'Checklists', 'action' => 'edit'])->setPatterns(['id' => '\d+'])->setPass(['id']);
        $builder->connect('/checklists/{id}/pausar', ['controller' => 'Lifecycle', 'action' => 'pause'])->setPatterns(['id' => '\d+'])->setPass(['id'])->setMethods(['GET', 'POST']);
        $builder->connect('/checklists/{id}/reativar', ['controller' => 'Lifecycle', 'action' => 'reactivate'])->setPatterns(['id' => '\d+'])->setPass(['id'])->setMethods(['GET', 'POST']);
        $builder->connect('/usuarios', ['controller' => 'Users', 'action' => 'index']);
        $builder->connect('/usuarios/novo', ['controller' => 'Users', 'action' => 'add']);
        $builder->connect('/usuarios/{id}/editar', ['controller' => 'Users', 'action' => 'edit'])->setPatterns(['id' => '\d+'])->setPass(['id']);
    });
};
