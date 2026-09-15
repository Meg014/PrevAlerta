<a class="btn" href="<?= $this->Url->build(['controller' => 'Occurrences', 'action' => 'confirm', $occurrence->id, '?' => ['return' => $returnTo ?? 'dashboard']]) ?>" data-ack-dialog="ack-<?= (int)$occurrence->id ?>" aria-haspopup="dialog">CIENTE</a>
<dialog id="ack-<?= (int)$occurrence->id ?>" aria-labelledby="ack-title-<?= (int)$occurrence->id ?>" class="m-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border-0 bg-white p-6 text-left text-slate-800 shadow-xl sm:p-8">
    <?= $this->element('ack_confirmation', ['occurrence' => $occurrence, 'checklist' => $checklist, 'today' => $today, 'returnTo' => $returnTo ?? 'dashboard', 'modal' => true]) ?>
</dialog>
