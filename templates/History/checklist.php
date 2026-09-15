<?php $this->assign('title', 'Histórico de ' . $checklist->code); ?>
<a class="mb-5 inline-block text-sm text-slate-500" href="<?= $this->Url->build('/') ?>">← Voltar ao dashboard</a>
<div class="mb-7"><p class="mb-2 text-sm font-semibold text-brand-700"><?= h($checklist->code) ?></p><h1 class="page-title">Histórico de ocorrências</h1><p class="muted mt-2"><?= h($checklist->name) ?> · <?= h($checklist->area) ?></p></div>
<p class="muted mb-5">Datas e horários em America/Sao_Paulo. Um CIENTE no mesmo dia da data prevista é considerado no prazo, independentemente do horário.</p>
<?php if (count($lifecycleEntries)): ?><section class="panel mb-6 p-5"><h2 class="mb-4 font-semibold">Pausas e reativações</h2><ol class="space-y-4"><?php foreach ($lifecycleEntries as $entry): ?><li><time class="text-xs text-slate-500"><?= h($entry->created->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s')) ?></time><p class="mt-1 whitespace-pre-wrap break-words text-sm"><?= h($entry->description) ?></p></li><?php endforeach; ?></ol></section><?php endif; ?>
<section class="panel overflow-hidden">
    <form method="get" class="flex flex-wrap items-end gap-3 border-b border-slate-200 p-5"><div><label for="history-status">Ocorrências</label><select name="status" id="history-status"><option value="">Todas</option value="PENDENTE" <?= $status === 'PENDENTE' ? 'selected' : '' ?>>Pendentes</option><option value="RECONHECIDA" <?= $status === 'RECONHECIDA' ? 'selected' : '' ?>>Reconhecidas</option></select></div><button class="btn-secondary">Filtrar</button></form>
    <?php if (count($occurrences) === 0): ?><p class="p-8 text-center text-sm text-slate-500">Nenhuma ocorrência nesta seleção. Datas futuras não geram registros antecipados.</p><?php else: ?>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Histórico de ocorrências"><table class="w-full"><thead><tr><th>Data prevista</th><th>CIENTE em</th><th>Usuário</th><th>Situação</th><th>Dias de atraso</th><th>Registrada em</th><th>Ação</th></tr></thead><tbody>
    <?php foreach ($occurrences as $occurrence): $result = $presentation->describe($occurrence, $today);
        $audit = $occurrence->acknowledgement_audit?->new_value ? json_decode($occurrence->acknowledgement_audit->new_value, true) : [];
        $canAcknowledge = !$result['recognized'] && $occurrence->status === 'PENDENTE' && $checklist->status === 'ATIVO' && $occurrence->checklist_schedule->ended_at === null && $occurrence->scheduled_date <= $today; ?>
        <tr data-history-occurrence="<?= (int)$occurrence->id ?>">
            <td class="whitespace-nowrap font-semibold"><?= h($occurrence->scheduled_date->format('d/m/Y')) ?><?php if ($occurrence->checklist_schedule->ended_at !== null): ?><p class="mt-1 text-xs font-normal text-slate-500">Programação encerrada · fora dos alertas</p><?php endif; ?></td>
            <td class="whitespace-nowrap"><?= h($occurrence->acknowledged_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? '—') ?></td>
            <td><?= h($audit['user_name'] ?? $occurrence->user?->name ?? '—') ?></td>
            <td><span class="alert-badge <?= $result['days'] > 0 ? 'alert-overdue' : ($result['recognized'] ? 'alert-current' : 'alert-today') ?>"><?= h($result['label']) ?></span><?php if ($result['recognized']): ?><p class="mt-1 text-xs text-slate-500">CIENTE registrado</p><?php endif; ?></td>
            <td><?= $result['days'] ?> <?= $result['days'] === 1 ? 'dia' : 'dias' ?><?php if (!$result['recognized']): ?><p class="text-xs text-slate-500">Até hoje</p><?php endif; ?></td>
            <td class="whitespace-nowrap text-slate-500"><?= h($occurrence->created->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s')) ?></td>
            <td><?php if ($canAcknowledge): ?><?= $this->element('ack_button', ['occurrence' => $occurrence, 'checklist' => $checklist, 'today' => $today, 'returnTo' => 'history']) ?><?php else: ?><span class="text-slate-400">—</span><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?= $this->element('pagination') ?>
