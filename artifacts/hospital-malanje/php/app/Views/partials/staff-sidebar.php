<?php
// A navegação é construída a partir da matriz de permissões para não mostrar
// ligações que a conta actual não pode abrir.
$items = [
    'dashboard' => ['Visão geral', '⌂', 'dashboard'],
    'appointments' => ['Consultas', '◷', 'appointments'],
    'patients' => ['Pacientes', '♙', 'patients'],
    'messages' => ['Mensagens', '✉', 'messages'],
];
if (can_access($user, 'reports')) $items['reports'] = ['Relatórios', '▤', 'reports'];
if (can_access($user, 'settings')) $items['settings'] = ['Definições', '⚙', 'settings'];
if (can_access($user, 'settings')) $items['directory'] = ['Médicos e serviços', '⚕', 'settings'];
?>
<aside class="hidden w-64 shrink-0 bg-[#184b49] text-[#f8f4e9] md:block">
    <div class="flex h-full min-h-screen flex-col p-5">
        <a href="<?= url('dashboard') ?>" class="flex items-center gap-3 border-b border-white/10 pb-6"><img src="<?= e(asset_url('logo.svg')) ?>" class="size-9 rounded-xl" alt=""><div><p class="text-sm font-bold">Hospital de Malanje</p><p class="font-mono-ui text-[8px] uppercase tracking-[.14em] text-white/50">Gestão de consultas</p></div></a>
        <p class="mb-2 mt-8 px-2 font-mono-ui text-[9px] uppercase tracking-[.18em] text-white/45">Operação</p>
        <nav class="space-y-1">
            <?php foreach ($items as $key => [$label, $icon, $permission]): ?>
                <?php if (can_access($user, $permission)): ?><a href="<?= url($key) ?>" class="<?= ($active ?? '') === $key ? 'bg-[#e8a36f] text-[#184b49]' : 'text-white/75 hover:bg-white/10' ?> flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold"><span class="text-lg"><?= $icon ?></span><?= $label ?><?php if ($key === 'messages' && ($unreadCount ?? 0) > 0): ?><span class="ml-auto rounded-full bg-coral px-2 py-0.5 text-[10px] text-ink"><?= (int) $unreadCount ?></span><?php endif; ?></a><?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php if (can_access($user, 'users')): ?><a href="<?= url('users') ?>" class="mt-5 flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold text-white/75 hover:bg-white/10"><span class="text-lg">♙</span>Utilizadores</a><?php endif; ?>
        <div class="mt-auto rounded-xl border border-white/10 bg-white/5 p-4"><p class="text-xs font-bold"><?= e($user['name']) ?></p><p class="mt-1 text-[11px] text-white/55"><?= e(role_label((string) $user['role'])) ?></p></div>
    </div>
</aside>