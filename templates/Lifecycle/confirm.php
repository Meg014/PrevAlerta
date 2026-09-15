<?php $label = $pausing ? 'Pausar checklist' : 'Reativar checklist'; $this->assign('title', $label); ?>
<section class="panel mx-auto max-w-2xl p-6 sm:p-8">
    <h1 class="page-title"><?= h($label) ?></h1>
    <p class="mt-4 font-semibold"><?= h($checklist->name) ?></p><p class="muted mt-1"><?= h($checklist->code) ?> · <?= h($schedule->interval_days) ?> dias</p>
    <p class="my-6 text-sm leading-relaxed"><?= $pausing ? 'Confirma a pausa deste checklist? As ocorrências anteriores serão preservadas no histórico e deixarão de gerar alertas.' : 'A recorrência começará na nova data informada. As pendências da programação anterior permanecerão somente no histórico.' ?></p>
    <?= $this->Form->create(null) ?>
    <?= $this->Form->hidden('schedule_id', ['value' => $schedule->id]) ?>
    <?php if ($pausing): ?>
        <?= $this->Form->control('reason', ['type' => 'textarea', 'label' => 'Motivo da pausa *', 'required' => true, 'maxlength' => 10000, 'value' => $this->request->getData('reason'), 'placeholder' => 'Ex.: equipamento fora de operação']) ?>
    <?php else: ?>
        <?= $this->Form->control('start_date', ['type' => 'date', 'label' => 'Nova data inicial *', 'required' => true, 'value' => $this->request->getData('start_date')]) ?>
    <?php endif; ?>
    <div class="mt-6 flex flex-wrap justify-end gap-3"><a class="btn-secondary" href="<?= $this->Url->build(['controller' => 'Checklists', 'action' => 'view', $checklist->id]) ?>">Cancelar</a><?= $this->Form->button($pausing ? 'Confirmar pausa' : 'Confirmar reativação', ['class' => 'btn']) ?></div>
    <?= $this->Form->end() ?>
</section>
