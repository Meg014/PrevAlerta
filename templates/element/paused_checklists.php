<table class="w-full"><thead><tr><th>Código / Checklist</th><th>Área / Setor</th><th>Periodicidade</th><th>Data da pausa</th><th>Motivo</th><th>Quem pausou</th><th>Situação / Ação</th></tr></thead><tbody>
<?php foreach ($rows as $row): $item = $row['checklist']; ?>
    <tr data-situation="PAUSADO">
        <td class="min-w-48"><a class="font-semibold text-brand-700" href="<?= $this->Url->build(['controller' => 'Checklists', 'action' => 'view', $item->id]) ?>"><?= h($item->name) ?></a><p class="mt-1 text-xs text-slate-500"><?= h($item->code) ?></p><a class="mt-2 inline-block text-xs text-brand-700" href="<?= $this->Url->build(['controller' => 'History', 'action' => 'checklist', $item->id]) ?>">Ver histórico</a></td>
        <td><?= h($item->area) ?></td><td class="whitespace-nowrap"><?= h($row['schedule']?->interval_days ?? '—') ?> dias</td>
        <td class="whitespace-nowrap"><?= h($item->paused_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? 'Não registrada') ?></td>
        <td class="min-w-48 whitespace-pre-wrap break-words"><?= h($item->pause_reason ?? 'Não registrado') ?></td><td><?= h($row['pausedBy'] ?? 'Não registrado') ?></td>
        <td><span class="badge badge-paused">PAUSADO</span><p class="mt-1 text-xs text-slate-500">Fora dos alertas</p><?php if ($isAdmin): ?><a class="btn-secondary mt-3" href="<?= $this->Url->build(['controller' => 'Lifecycle', 'action' => 'reactivate', $item->id]) ?>">REATIVAR</a><?php endif; ?></td>
    </tr>
<?php endforeach; ?>
</tbody></table>
