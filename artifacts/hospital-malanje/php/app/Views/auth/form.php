<?php
$isLogin = $mode === 'login';
$isRegister = $mode === 'register';
$isForgot = $mode === 'forgot';
$isReset = $mode === 'reset';
?>
<main class="noise flex min-h-screen items-center justify-center px-5 py-12">
    <div class="w-full max-w-md">
        <a href="<?= url('home') ?>" class="mb-8 flex items-center justify-center gap-3">
            <img src="<?= e(asset_url('logo.svg')) ?>" class="size-10 rounded-xl" alt="">
            <div>
                <p class="text-sm font-bold">Hospital de Malanje</p>
                <p class="font-mono-ui text-[9px] uppercase tracking-wider text-slate-500">Portal de consultas</p>
            </div>
        </a>
        <div class="rounded-2xl border border-[#e1d8ca] bg-white p-6 shadow-soft sm:p-8">
            <p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-teal"><?= $isLogin ? 'Acesso seguro' : ($isRegister ? 'Nova conta' : 'Segurança da conta') ?></p>
            <h1 class="mt-2 text-2xl font-bold"><?= $isLogin ? 'Bem-vindo de volta' : ($isRegister ? 'Crie a sua conta' : ($isForgot ? 'Recuperar acesso' : 'Definir nova palavra-passe')) ?></h1>
            <?php if ($isForgot): ?>
                <p class="mt-2 text-sm leading-6 text-slate-500">Indique o email da conta para gerar um link local, válido durante 60 minutos e utilizável uma única vez.</p>
            <?php elseif ($isReset): ?>
                <p class="mt-2 text-sm leading-6 text-slate-500">Escolha uma nova palavra-passe com pelo menos 8 caracteres.</p>
            <?php endif; ?>
            <form method="post" class="mt-6 space-y-4">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <?php if ($isLogin): ?>
                    <input type="hidden" name="action" value="login">
                    <label class="block text-xs font-bold text-slate-500">Email<input type="email" name="email" autocomplete="email" required class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                    <label class="block text-xs font-bold text-slate-500">Palavra-passe<input type="password" name="password" autocomplete="current-password" required minlength="8" class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                    <button class="w-full rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white">Entrar no portal</button>
                <?php elseif ($isRegister): ?>
                    <input type="hidden" name="action" value="register">
                    <label class="block text-xs font-bold text-slate-500">Nome completo<input name="name" autocomplete="name" required class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                    <label class="block text-xs font-bold text-slate-500">Email<input type="email" name="email" autocomplete="email" required class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                    <label class="block text-xs font-bold text-slate-500">Palavra-passe<input type="password" name="password" autocomplete="new-password" required minlength="8" class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                    <button class="w-full rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white">Criar conta</button>
                <?php elseif ($isForgot): ?>
                    <input type="hidden" name="action" value="request-password-reset">
                    <label class="block text-xs font-bold text-slate-500">Email da conta<input type="email" name="email" autocomplete="email" required class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                    <button class="w-full rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white">Gerar link local</button>
                    <?php if (!empty($localResetUrl)): ?>
                        <div class="rounded-xl border border-[#e1d8ca] bg-[#fff4e7] p-4">
                            <p class="text-xs font-bold text-[#a85f2f]">Link local gerado</p>
                            <p class="mt-1 break-all text-[11px] leading-5 text-slate-600">Abra ou copie este link para definir a nova palavra-passe:</p>
                            <a href="<?= e($localResetUrl) ?>" class="mt-2 block break-all text-xs font-bold text-teal underline"><?= e($localResetUrl) ?></a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <input type="hidden" name="action" value="reset-password">
                    <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
                    <label class="block text-xs font-bold text-slate-500">Nova palavra-passe<input type="password" name="password" autocomplete="new-password" required minlength="8" class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                    <label class="block text-xs font-bold text-slate-500">Confirmar nova palavra-passe<input type="password" name="password_confirmation" autocomplete="new-password" required minlength="8" class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                    <button class="w-full rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white">Actualizar palavra-passe</button>
                <?php endif; ?>
            </form>
            <?php if ($isLogin): ?>
                <a class="mt-4 block text-center text-xs font-bold text-teal hover:underline" href="<?= url('forgot-password') ?>">Esqueci-me da palavra-passe</a>
            <?php elseif ($isForgot || $isReset): ?>
                <p class="mt-6 text-center text-xs text-slate-500"><a class="font-bold text-teal hover:underline" href="<?= url('login') ?>">Voltar ao acesso</a></p>
            <?php else: ?>
                <p class="mt-6 text-center text-xs text-slate-500">Já tem uma conta? <a class="font-bold text-teal hover:underline" href="<?= url('login') ?>">Entrar</a></p>
            <?php endif; ?>
        </div>
    </div>
</main>