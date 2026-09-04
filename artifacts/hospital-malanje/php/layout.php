<?php
function page_start(string $title, ?array $user = null): void
{
    $notice = flash();
    ?>
    <!doctype html>
    <html lang="pt">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · Hospital de Malanje</title>
        <link rel="icon" href="/favicon.svg">
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = { theme: { extend: {
                colors: { ink: '#21413f', paper: '#fbf8f1', teal: '#1e706c', sand: '#eee6d8', coral: '#d88968' },
                fontFamily: { sans: ['DM Sans', 'ui-sans-serif', 'sans-serif'], mono: ['Space Mono', 'ui-monospace', 'monospace'] }
            }}};
        </script>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
        <style>body{font-family:'DM Sans',sans-serif;background:#fbf8f1;color:#21413f}.font-mono-ui{font-family:'Space Mono',monospace}.noise{background-image:radial-gradient(rgba(33,65,63,.05) .7px,transparent .7px);background-size:7px 7px}.shadow-soft{box-shadow:0 18px 45px rgba(33,65,63,.08)} </style>
    </head>
    <body class="min-h-screen">
    <?php if ($notice): ?><div class="fixed right-5 top-5 z-50 rounded-xl border border-teal/20 bg-white px-4 py-3 text-sm shadow-soft"><?= e($notice) ?></div><?php endif; ?>
    <?php
}

function page_end(): void
{
    ?></body></html><?php
}

function app_header(array $user): void
{
    ?>
    <header class="border-b border-[#e1d8ca] bg-white/80 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="<?= url($user['role'] === 'patient' ? 'portal' : 'dashboard') ?>" class="flex items-center gap-3">
                <img src="/logo.svg" class="size-10 rounded-xl" alt="">
                <div><p class="text-sm font-bold">Hospital de Malanje</p><p class="font-mono-ui text-[9px] uppercase tracking-[.16em] text-slate-500"><?= $user['role'] === 'patient' ? 'Área do paciente' : 'Central de consultas' ?></p></div>
            </a>
            <div class="flex items-center gap-3"><span class="hidden text-xs text-slate-500 sm:block">Olá, <?= e($user['name']) ?></span><a href="<?= url('logout') ?>" class="rounded-lg border border-[#e1d8ca] px-3 py-2 text-xs font-bold text-slate-600 hover:text-teal">Sair</a></div>
        </div>
    </header>
    <?php
}

function staff_sidebar(array $user, string $active): void
{
    $items = ['dashboard' => ['Visão geral', '⌂'], 'appointments' => ['Consultas', '◷'], 'patients' => ['Pacientes', '♙']];
    ?>
    <aside class="hidden w-64 shrink-0 bg-[#184b49] text-[#f8f4e9] md:block">
        <div class="flex h-full min-h-screen flex-col p-5">
            <a href="<?= url('dashboard') ?>" class="flex items-center gap-3 border-b border-white/10 pb-6"><img src="/logo.svg" class="size-9 rounded-xl" alt=""><div><p class="text-sm font-bold">Hospital de Malanje</p><p class="font-mono-ui text-[8px] uppercase tracking-[.14em] text-white/50">Gestão de consultas</p></div></a>
            <p class="mb-2 mt-8 px-2 font-mono-ui text-[9px] uppercase tracking-[.18em] text-white/45">Operação</p>
            <nav class="space-y-1"><?php foreach ($items as $key => [$label, $icon]): ?><a href="<?= url($key) ?>" class="<?= $active === $key ? 'bg-[#e8a36f] text-[#184b49]' : 'text-white/75 hover:bg-white/10' ?> flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold"><span class="text-lg"><?= $icon ?></span><?= $label ?></a><?php endforeach; ?></nav>
            <div class="mt-auto rounded-xl border border-white/10 bg-white/5 p-4"><p class="text-xs font-bold"><?= e($user['name']) ?></p><p class="mt-1 text-[11px] text-white/55"><?= e(ucfirst($user['role'])) ?></p></div>
        </div>
    </aside>
    <?php
}