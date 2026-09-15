<?php
$paths = [
 'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
 'list' => '<rect x="5" y="4" width="15" height="17" rx="2"/><path d="M9 4V2h7v2M9 10l1 1 2-2m2 1h3m-8 6 1 1 2-2m2 1h3"/>',
 'history' => '<path d="M3 11a9 9 0 1 1 2.6 7M3 4v7h7m2-4v5l3 2"/>',
 'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m1-16a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 5v2"/>',
 'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
 'plus' => '<path d="M12 5v14M5 12h14"/>',
 'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM8 12l3 3 5-6"/>',
 'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
 'logout' => '<path d="M9 4H4v16h5m5-12 4 4-4 4m-6-4h12"/>',
];
?>
<svg class="<?= h($class ?? 'size-5') ?>" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= $paths[$name] ?? $paths['list'] ?></svg>
