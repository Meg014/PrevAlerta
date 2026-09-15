<?php $this->assign('title', 'Histórico geral'); $actions = ['PAUSA' => 'Pausa', 'REATIVACAO' => 'Reativação', 'CIENTE' => 'CIENTE', 'CRIACAO' => 'Criação', 'EDICAO' => 'Edição', 'MUDANCA_DATA_BASE' => 'Mudança de data-base']; ?>
<h1 class="page-title">Histórico geral</h1><p class="muted mb-7 mt-2">Auditoria de checklists e reconhecimentos · horários em America/Sao_Paulo.</p>
<section class="panel overflow-hidden">
    <form method="get" class="flex flex-wrap items-end gap-3 border-b border-slate-200 p-5"><div class="min-w-48 flex-1"><label for="audit-search">Buscar no histórico</label><input type="search" id="audit-search" name="q" value="<?= h($search) ?>" placeholder="Checklist, código ou descrição..."></div><div><label for="audit-action">Ação</label><select id="audit-action" name="action"><option value="">Todas</option><?php foreach ($actions as $key => $label): ?><option value="<?= h($key) ?>" <?= $action === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div><button class="btn-secondary">Filtrar</button></form>
    <?php if (count($entries) === 0): ?><p class="p-8 text-center text-sm text-slate-500">Nenhum registro encontrado.</p><?php else: ?>
    <ol class="divide-y divide-slate-100">
        <?php foreach ($entries as $entry): ?>
        <li class="p-5" data-audit-id="<?= (int)$entry->id ?>"><div class="flex flex-wrap items-center justify-between gap-2"><span class="badge"><?= h($actions[$entry->action] ?? $entry->action) ?></span><time class="text-xs text-slate-500"><?= h($entry->created->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s')) ?></time></div><a class="mt-3 inline-block font-semibold text-brand-700" href="<?= $this->Url->build(['controller' => 'History', 'action' => 'checklist', $entry->checklist_id]) ?>"><?= h($entry->checklist->code) ?> · <?= h($entry->checklist->name) ?></a><p class="mt-2 break-words text-sm leading-relaxed"><?= h($entry->description) ?></p>
        <?php if ($entry->checklist_occurrence): ?><p class="mt-2 text-xs text-slate-500">Data prevista: <?= h($entry->checklist_occurrence->scheduled_date->format('d/m/Y')) ?> · CIENTE: <?= h($entry->checklist_occurrence->acknowledged_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s')) ?></p><?php else: ?><p class="mt-2 text-xs text-slate-500">Por <?= h($entry->user?->name ?? '—') ?></p><?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol><?php endif; ?>
</section>
<?= $this->element('pagination') ?>
