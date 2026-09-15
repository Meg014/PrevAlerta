<?php
$this->assign('title', 'Dashboard');
$labels = ['ATRASADO' => 'Atrasados', 'HOJE' => 'Vencem hoje', 'PROXIMOS' => 'Próximos 7 dias', 'EM_DIA' => 'Em dia', 'PAUSADO' => 'Pausados'];
$rowLabels = ['ATRASADO' => 'ATRASADO', 'HOJE' => 'VENCE HOJE', 'PROXIMOS' => 'PRÓXIMOS 7 DIAS', 'EM_DIA' => 'EM DIA', 'PAUSADO' => 'PAUSADO'];
$colors = ['ATRASADO' => 'alert-overdue', 'HOJE' => 'alert-today', 'PROXIMOS' => 'alert-upcoming', 'EM_DIA' => 'alert-current', 'PAUSADO' => 'alert-paused'];
$urgent = $counts['ATRASADO'] + $counts['HOJE'];
$url = fn(array $params) => $this->Url->build(['controller' => 'Dashboard', 'action' => 'index', '?' => $params]);
?>
<div class="mb-7 flex flex-wrap items-center justify-between gap-4">
    <div><p class="mb-2 text-xs font-semibold uppercase tracking-widest text-brand-600">Manutenção preventiva</p><h1 class="page-title">Painel de alertas</h1><p class="muted mt-2"><?= h($today->format('d/m/Y')) ?> · America/Sao_Paulo</p></div>
    <?php if ($isAdmin): ?><a class="btn" href="<?= $this->Url->build('/checklists/novo') ?>"><?= $this->element('icon', ['name' => 'plus']) ?> Novo checklist</a><?php endif; ?>
</div>
<?php if ($urgent > 0): ?>
<section role="alert" class="mb-6 rounded-xl border-2 border-red-300 bg-red-50 p-5 text-red-950">
    <div class="flex items-start gap-3"><span class="mt-1 shrink-0"><?= $this->element('icon', ['name' => 'clock', 'class' => 'size-6']) ?></span><div><h2 class="text-lg font-bold">ATENÇÃO · <?= $urgent ?> <?= $urgent === 1 ? 'checklist exige' : 'checklists exigem' ?> atenção</h2><p class="mt-1 text-sm leading-relaxed"><?= $counts['ATRASADO'] ?> com ocorrência atrasada e <?= $counts['HOJE'] ?> com primeira pendência para hoje. Confira os lembretes e providencie a abertura de O.S. no sistema da empresa.</p></div></div>
</section>
<?php elseif (array_sum($counts) > $counts['PAUSADO']): ?>
<p role="status" class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">Nenhum checklist com ocorrência pendente até hoje. Acompanhe as próximas datas abaixo.</p>
<?php endif; ?>
<div class="mb-6 grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
<?php foreach ($labels as $key => $label): ?>
    <a href="<?= $url(['status' => $key]) ?>" class="alert-card <?= $colors[$key] ?> <?= $status === $key ? 'ring-2 ring-slate-800 ring-offset-2' : '' ?>" <?= $status === $key ? 'aria-current="page"' : '' ?> data-card="<?= $key ?>">
        <span class="text-xs font-bold uppercase tracking-wide"><?= h($label) ?></span><span class="mt-3 block text-3xl font-bold" data-count><?= $counts[$key] ?></span><span class="mt-1 block text-xs opacity-80">checklists →</span>
    </a>
<?php endforeach; ?>
</div>
<p class="muted mb-6">Os cards contam cada checklist uma vez, conforme sua pendência mais antiga. Abaixo, cada ciclo tem seu próprio CIENTE. A confirmação não altera o calendário.</p>
<?= $this->element('pending_occurrences', compact('pendingCount', 'pendingOccurrences', 'pendingPages', 'pendingPage', 'today', 'url', 'search', 'status')) ?>
<section id="checklist-summary" class="panel overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"><h2 class="font-semibold"><?= $status === '' ? 'Programação dos checklists ativos' : h($labels[$status]) ?> <span class="ml-1 font-normal text-slate-400">(<?= $totalRows ?>)</span></h2><a class="text-sm font-semibold text-brand-700" href="<?= $url([]) ?>">Ver todos os ativos</a></div>
    <form method="get" class="flex flex-wrap items-end gap-3 border-b border-slate-200 p-5">
        <div class="min-w-48 flex-1"><label for="dashboard-search">Buscar checklist</label><input id="dashboard-search" type="search" name="q" value="<?= h($search) ?>" placeholder="Código, nome, setor ou rota..."></div>
        <div><label for="dashboard-status">Situação</label><select id="dashboard-status" name="status"><option value="">Todos os ativos</option><?php foreach ($labels as $key => $label): ?><option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
        <button class="btn-secondary" type="submit">Filtrar</button>
    </form>
    <?php if (!$rows): ?>
        <div class="px-6 py-14 text-center"><h3 class="text-lg font-semibold">Nenhum checklist nesta seleção</h3><p class="muted mt-2"><?= array_sum($counts) === 0 ? 'Cadastre o primeiro checklist para acompanhar os vencimentos.' : 'Selecione outra situação ou ajuste a busca.' ?></p></div>
    <?php else: ?>
    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Tabela de alertas, deslize para ver todas as colunas">
    <?php if ($status === 'PAUSADO'): ?><?= $this->element('paused_checklists', compact('rows')) ?><?php else: ?>
    <table class="w-full"><thead><tr><th>Código / Checklist</th><th>Área / Setor</th><th>Rota</th><th>Periodicidade</th><th>Data inicial</th><th>Próxima ocorrência pendente</th><th>Situação</th><th>Dias restantes / atraso</th></tr></thead><tbody>
    <?php foreach ($rows as $row): $item = $row['checklist']; $schedule = $row['schedule']; ?>
        <tr data-situation="<?= $row['situation'] ?>" class="hover:bg-slate-50/60">
            <td class="min-w-56"><a class="font-semibold hover:text-brand-700" href="<?= $this->Url->build(['controller' => 'Checklists', 'action' => 'view', $item->id]) ?>"><?= h($item->name) ?></a><p class="mt-1 text-xs text-slate-500"><?= h($item->code) ?></p><a class="mt-2 inline-block text-xs font-semibold text-brand-700" href="<?= $this->Url->build(['controller' => 'History', 'action' => 'checklist', $item->id]) ?>">Ver histórico</a></td>
            <td><?= h($item->area) ?></td><td><?= h($item->route ?: '—') ?></td><td class="whitespace-nowrap"><?= $schedule ? h($schedule->interval_days) . ' dias' : '—' ?></td><td class="whitespace-nowrap"><?= h($schedule?->start_date?->format('d/m/Y') ?? '—') ?></td>
            <td class="whitespace-nowrap"><span class="font-semibold"><?= h($row['date']?->format('d/m/Y') ?? '—') ?></span><?php if ($row['pending'] > 1): ?><p class="mt-1 text-xs text-red-700"><?= $row['pending'] ?> ciclos pendentes</p><?php endif; ?></td>
            <td><span class="alert-badge <?= $colors[$row['situation']] ?>"><?= h($rowLabels[$row['situation']]) ?></span></td>
            <td class="whitespace-nowrap text-sm <?= $row['situation'] === 'ATRASADO' ? 'font-semibold text-red-700' : '' ?>"><?php if ($row['days'] === null): ?>Fora dos alertas<?php elseif ($row['days'] < 0): ?>Atrasado há <?= abs($row['days']) ?> <?= $row['days'] === -1 ? 'dia' : 'dias' ?><?php elseif ($row['days'] === 0): ?><strong class="text-orange-800">Vence hoje</strong><?php else: ?>Faltam <?= $row['days'] ?> <?= $row['days'] === 1 ? 'dia' : 'dias' ?><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table><?php endif; ?></div>
    <?php endif; ?>
</section>
<?php if ($pages > 1): ?><nav class="mt-5 flex flex-wrap items-center justify-between gap-3" aria-label="Paginação"><p class="muted">Página <?= $page ?> de <?= $pages ?> · <?= $totalRows ?> checklists</p><div class="flex gap-2"><?php if ($page > 1): ?><a class="btn-secondary" href="<?= $url(['status' => $status, 'q' => $search, 'page' => $page - 1]) ?>">← Anterior</a><?php endif; ?><?php if ($page < $pages): ?><a class="btn-secondary" href="<?= $url(['status' => $status, 'q' => $search, 'page' => $page + 1]) ?>">Próxima →</a><?php endif; ?></div></nav><?php endif; ?>
