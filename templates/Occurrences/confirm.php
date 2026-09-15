<?php $this->assign('title', 'Confirmar CIENTE'); ?>
<section class="panel mx-auto max-w-lg p-6 sm:p-8">
    <?= $this->element('ack_confirmation', compact('occurrence', 'checklist', 'today', 'returnTo')) ?>
</section>
