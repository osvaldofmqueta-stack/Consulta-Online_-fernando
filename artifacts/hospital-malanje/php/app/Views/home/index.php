<main class="noise min-h-screen">
    <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-6 sm:px-8">
        <a href="<?= url('home') ?>" class="flex items-center gap-3">
            <img src="<?= e(asset_url('logo.svg')) ?>" class="size-10 rounded-xl" alt="">
            <div>
                <p class="text-sm font-bold">Hospital de Malanje</p>
                <p class="font-mono-ui text-[9px] uppercase tracking-wider text-slate-500">Portal de consultas</p>
            </div>
        </a>
        <a href="<?= url('login') ?>" class="rounded-lg border border-[#d7cebf] bg-white px-4 py-2 text-sm font-bold hover:border-teal hover:text-teal">Entrar</a>
    </header>
    <section class="mx-auto grid max-w-6xl gap-10 px-5 pb-20 pt-16 sm:px-8 lg:grid-cols-[1.1fr_.9fr] lg:items-center">
        <div>
            <p class="font-mono-ui text-[10px] font-bold uppercase tracking-[.2em] text-teal">Cuidado mais próximo, informação mais clara</p>
            <h1 class="mt-4 max-w-2xl text-4xl font-bold leading-[1.05] tracking-[-.05em] sm:text-6xl">A sua saúde, acompanhada com confiança.</h1>
            <p class="mt-6 max-w-xl text-base leading-7 text-slate-500 sm:text-lg">Consulte marcações, histórico de atendimentos e comunique-se com a equipa do Hospital de Malanje numa área segura.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="<?= url('register') ?>" class="rounded-lg bg-teal px-5 py-3 text-center text-sm font-bold text-white hover:bg-[#185b58]">Criar conta →</a>
                <a href="<?= url('login') ?>" class="rounded-lg border border-[#d7cebf] bg-white px-5 py-3 text-center text-sm font-bold hover:border-teal hover:text-teal">Já tenho conta</a>
            </div>
        </div>
        <div class="rounded-3xl border border-[#e1d8ca] bg-white p-6 shadow-soft sm:p-8">
            <p class="font-mono-ui text-[10px] uppercase tracking-wider text-slate-500">O seu portal</p>
            <h2 class="mt-2 text-xl font-bold">Tudo num só lugar</h2>
            <div class="mt-6 space-y-3">
                <?php foreach (['Próxima consulta', 'Histórico clínico', 'Mensagens seguras'] as $index => $label): ?>
                    <div class="flex items-center gap-3 rounded-xl border border-[#eee7dc] p-4"><span class="font-mono-ui text-[10px] text-teal">0<?= $index + 1 ?></span><span class="text-sm font-bold"><?= $label ?></span><span class="ml-auto text-slate-400">›</span></div>
                <?php endforeach; ?>
            </div>
            <div class="mt-5 rounded-xl bg-[#f5eee3] p-4"><p class="text-xs font-bold">Acesso protegido</p><p class="mt-1 text-xs leading-5 text-slate-500">Apenas você e os profissionais autorizados podem consultar os dados.</p></div>
        </div>
    </section>
    <footer class="mx-auto max-w-6xl border-t border-[#e1d8ca] px-5 py-6 text-xs text-slate-500 sm:px-8">Hospital de Malanje · Central de consultas</footer>
</main>