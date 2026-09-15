<section class="panel mb-7 overflow-hidden" id="pending-occurrences">
    <div class="border-b border-slate-100 p-5"><h2 class="text-lg font-semibold">Ocorrências pendentes <span class="font-normal text-slate-500">(<?= $pendingCount ?>)</span></h2><p class="muted mt-1">Cada data prevista é um lembrete separado. Confirme somente o ciclo que foi tratado.</p></div>
    <?php if ($pendingCount === 0): ?><p class="p-6 text-sm text-slate-500">Nenhuma ocorrência pendente nesta seleção.</p><?php else: ?>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Ocorrências pendentes">
    <table class="w-full"><thead><tr><th>Checklist</th><th>Data prevista</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
    <?php foreach ($pendingOccurrences as $occurrence): $checklist = $occurrence->checklist_schedule->checklist;
        $delay = max(0, (new \App\Service\RecurrenceCalendar())->daysUntil($occurrence->scheduled_date, $today)); ?>
        <tr data-occurrence="<?= (int)$occurrence->id ?>">
            <td class="min-w-52"><p class="font-semibold"><?= h($checklist->name) ?></p><p class="mt-1 text-xs text-slate-500"><?= h($checklist->code) ?> · <?= h($checklist->area) ?></p></td>
            <td class="whitespace-nowrap font-semibold"><?= h($occurrence->scheduled_date->format('d/m/Y')) ?></td>
            <td><span class="alert-badge <?= $delay > 0 ? 'alert-overdue' : 'alert-today' ?>"><?= $delay > 0 ? 'ATRASADO HÁ ' . $delay . ($delay === 1 ? ' DIA' : ' DIAS') : 'VENCE HOJE' ?></span></td>
            <td><div class="flex items-center gap-4">
                <?= $this->element('ack_button', compact('occurrence', 'checklist', 'today')) ?>
                <a class="text-sm font-semibold text-brand-700" href="<?= $this->Url->build(['controller' => 'History', 'action' => 'checklist', $checklist->id]) ?>">Histórico</a>
            </div></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php if ($pendingPages > 1): ?><nav aria-label="Paginação de ocorrências" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 p-5"><p class="muted">Página <?= $pendingPage ?> de <?= $pendingPages ?></p><div class="flex gap-2"><?php if ($pendingPage > 1): ?><a class="btn-secondary" href="<?= $url(['q' => $search, 'status' => $status, 'occurrence_page' => $pendingPage - 1]) ?>#pending-occurrences">← Anterior</a><?php endif; ?><?php if ($pendingPage < $pendingPages): ?><a class="btn-secondary" href="<?= $url(['q' => $search, 'status' => $status, 'occurrence_page' => $pendingPage + 1]) ?>#pending-occurrences">Próxima →</a><?php endif; ?></div></nav><?php endif; ?>
    <?php endif; ?>
</section>
