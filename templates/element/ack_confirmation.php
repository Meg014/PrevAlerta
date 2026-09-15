<?php
$delay = max(0, (new \App\Service\RecurrenceCalendar())->daysUntil($occurrence->scheduled_date, $today));
$returnTo = $returnTo ?? 'dashboard';
?>
<h2 id="ack-title-<?= (int)$occurrence->id ?>" class="text-xl font-bold text-slate-900">Confirmar CIENTE</h2>
<p class="mt-3 text-sm leading-relaxed">Confirma que o aviso deste checklist foi tratado?</p>
<dl class="my-5 space-y-3 rounded-xl bg-slate-50 p-4 text-sm">
    <div><dt class="text-slate-500">Checklist</dt><dd class="mt-1 break-words font-semibold"><?= h($checklist->name) ?></dd></div>
    <div><dt class="text-slate-500">Código</dt><dd class="mt-1 break-words font-medium"><?= h($checklist->code) ?></dd></div>
    <div><dt class="text-slate-500">Data prevista</dt><dd class="mt-1 font-semibold"><?= h($occurrence->scheduled_date->format('d/m/Y')) ?></dd></div>
    <div><dt class="text-slate-500">Situação</dt><dd class="mt-1 font-semibold <?= $delay > 0 ? 'text-red-700' : 'text-orange-800' ?>"><?= $delay > 0 ? 'Atrasado há ' . $delay . ($delay === 1 ? ' dia' : ' dias') : 'Vence hoje' ?></dd></div>
</dl>
<p class="mb-5 text-xs leading-relaxed text-slate-500">O CIENTE registra somente o tratamento deste lembrete. Não abre O.S. e não altera a programação nem confirma outros ciclos.</p>
<?= $this->Form->create(null, ['url' => ['controller' => 'Occurrences', 'action' => 'acknowledge', $occurrence->id, '_method' => 'POST'], 'data-ack-submit' => true]) ?>
<?= $this->Form->hidden('confirmed', ['value' => '1']) ?>
<?= $this->Form->hidden('return_to', ['value' => $returnTo]) ?>
<div class="flex flex-wrap justify-end gap-3">
    <?php if ($modal ?? false): ?><button type="button" data-ack-close class="btn-secondary" autofocus>Cancelar</button><?php else: ?><a href="<?= $this->Url->build($returnTo === 'history' ? ['controller' => 'History', 'action' => 'checklist', $checklist->id] : '/') ?>" class="btn-secondary">Cancelar</a><?php endif; ?>
    <?= $this->Form->button('Confirmar CIENTE', ['class' => 'btn']) ?>
</div>
<?= $this->Form->end() ?>
