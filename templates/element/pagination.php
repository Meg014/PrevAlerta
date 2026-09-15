<?php if ($this->Paginator->total() > 1): ?>
<nav class="mt-5 flex flex-wrap items-center justify-between gap-4" aria-label="Paginação"><p class="muted"><?= $this->Paginator->counter('Página {{page}} de {{pages}} · {{count}} registros') ?></p><ul class="pagination"><?= $this->Paginator->prev('← Anterior') ?><?= $this->Paginator->numbers() ?><?= $this->Paginator->next('Próxima →') ?></ul></nav>
<?php endif; ?>
