<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         0.10.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 * @var \App\View\AppView $this
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        Não foi possível concluir · PrevAgenda
    </title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css('app') ?>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <main class="mx-auto max-w-xl px-6 py-16">
        <p class="mb-6 text-xl font-bold text-brand-700">PrevAgenda</p>
        <div class="panel space-y-5 p-6">
        <?= $this->Flash->render() ?>
        <?= $this->fetch('content') ?>
        <?= $this->Html->link('Voltar ao início', '/', ['class' => 'btn']) ?>
        </div>
    </main>
</body>
</html>
