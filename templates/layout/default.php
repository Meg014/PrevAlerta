<?php $controller = $this->request->getParam('controller'); ?>
<!DOCTYPE html><html lang="pt-BR"><head>
<?= $this->Html->charset() ?><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= h($this->fetch('title')) ?> · PrevAgenda</title>
<?= $this->Html->css('app') ?><?= $this->Html->script('app', ['defer' => true]) ?>
<?= $this->Html->script('acknowledgements', ['defer' => true]) ?>
</head><body>
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:z-50 focus:bg-white focus:p-4">Pular para o conteúdo</a>
<button id="sidebar-overlay" hidden class="fixed inset-0 z-30 bg-slate-950/50 lg:hidden" aria-label="Fechar menu"></button>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-brand-900 p-4 text-white" aria-label="Menu principal">
 <a href="<?= $this->Url->build('/') ?>" class="mb-9 mt-3 flex items-center gap-3 px-2" aria-label="PrevAgenda, início"><span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-600"><?= $this->element('icon', ['name' => 'list', 'class' => 'size-6']) ?></span><span class="sidebar-label text-xl font-bold tracking-tight">Prev<span class="text-emerald-300">Agenda</span></span></a>
 <button id="sidebar-close" class="mb-4 rounded-lg border border-white/20 p-2 text-sm lg:hidden">Fechar menu ×</button>
 <p class="sidebar-label mb-3 px-3 text-[10px] font-bold uppercase tracking-[.18em] text-emerald-200/60">Área de trabalho</p>
 <nav class="space-y-1">
 <?php foreach ([['/', 'grid', 'Dashboard', 'Dashboard'], ['/checklists', 'list', 'Checklists', 'Checklists']] as [$url, $icon, $label, $target]): ?>
 <a class="nav-link <?= $controller === $target ? 'active' : '' ?>" href="<?= $this->Url->build($url) ?>" title="<?= h($label) ?>" <?= $controller === $target ? 'aria-current="page"' : '' ?>><span class="nav-icon"><?= $this->element('icon', ['name' => $icon]) ?></span><span class="sidebar-label"><?= h($label) ?></span></a>
 <?php endforeach; ?>
 <a class="nav-link <?= $controller === 'History' ? 'active' : '' ?>" href="<?= $this->Url->build('/historico') ?>" title="Histórico"><span class="nav-icon"><?= $this->element('icon', ['name' => 'history']) ?></span><span class="sidebar-label">Histórico</span></a>
 <?php if ($isAdmin ?? false): ?><a class="nav-link <?= $controller === 'Users' ? 'active' : '' ?>" href="<?= $this->Url->build('/usuarios') ?>" title="Usuários"><span class="nav-icon"><?= $this->element('icon', ['name' => 'users']) ?></span><span class="sidebar-label">Usuários</span></a><?php endif; ?>
 </nav>
 <div class="sidebar-label mt-auto rounded-xl border border-white/10 bg-white/5 p-4"><?= $this->element('icon', ['name' => 'shield', 'class' => 'mb-3 size-6 text-emerald-300']) ?><p class="text-sm font-medium">Prevenir começa por lembrar.</p><p class="mt-2 text-xs leading-relaxed text-emerald-100/60">Organize seus checklists de manutenção preventiva em um só lugar.</p></div>
 <p class="sidebar-label px-3 pt-5 text-[10px] text-emerald-100/40">PREVAGENDA · FASE 4</p>
</aside>
<div id="main-shell" class="min-h-screen transition-[margin] lg:ml-64">
 <header class="flex h-20 items-center justify-between gap-4 border-b border-slate-200 bg-white px-4 sm:px-8">
 <div class="flex items-center gap-4"><button id="sidebar-toggle" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Alternar menu" aria-controls="sidebar" aria-expanded="true"><?= $this->element('icon', ['name' => 'menu']) ?></button><span class="text-sm text-slate-500">Controle de manutenção preventiva</span></div>
 <?php if ($currentUser ?? null): ?><div class="flex shrink-0 items-center gap-3"><span class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-sm font-bold text-brand-700"><?= h(mb_strtoupper(mb_substr($currentUser->get('name'), 0, 1))) ?></span><div class="hidden sm:block"><p class="text-sm font-semibold"><?= h($currentUser->get('name')) ?></p><p class="text-xs text-slate-500"><?= ($localAccess ?? false) ? 'Responsável interno' : ($isAdmin ? 'Administrador' : 'Usuário') ?></p></div><?php if (!($localAccess ?? false)): ?><?= $this->Form->postLink($this->element('icon', ['name' => 'logout']), '/logout', ['escape' => false, 'class' => 'p-2 text-slate-500', 'aria-label' => 'Sair', 'title' => 'Sair']) ?><?php endif; ?></div><?php endif; ?>
 </header>
 <main id="main" class="mx-auto max-w-7xl p-4 sm:p-8 lg:p-10"><?= $this->Flash->render() ?><?= $this->fetch('content') ?></main>
 <footer class="mx-auto max-w-7xl px-4 pb-6 text-xs text-slate-400 sm:px-8">PrevAgenda · Organização e acompanhamento de lembretes.</footer>
</div></body></html>
