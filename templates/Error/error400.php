<?php
/**
 * @var \App\View\AppView $this
 * @var string $message
 * @var string $url
 */
use Cake\Core\Configure;

$this->setLayout('error');

if (Configure::read('debug')) :
    $this->setLayout('dev_error');

    $this->assign('title', $message);
    $this->assign('templateName', 'error400.php');

    $this->start('file');
    echo $this->element('auto_table_warning');
    $this->end();
endif;
?>
<h1 class="page-title"><?= h(Configure::read('debug') ? $message : match ($this->getResponse()->getStatusCode()) {
    403 => 'Acesso não permitido',
    404 => 'Página não encontrada',
    405 => 'Ação não permitida',
    default => 'Não foi possível acessar esta página',
}) ?></h1>
<p class="muted">
    Não foi possível acessar esta página. Verifique o endereço e se sua conta tem permissão para esta ação.
</p>
