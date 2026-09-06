<?php
// Gestão de contas internas. A matriz abaixo explica permissões sem substituir
// as verificações de autorização feitas no backend.
$active = '';
?>
<div class="flex min-h-screen">
    <div class="hidden md:block"><?php require __DIR__ . '/../partials/staff-sidebar.php'; ?></div>
    <div class="min-w-0 flex-1">
        <main class="mx-auto max-w-5xl px-5 py-8 sm:px-8 sm:py-10">
            <p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-teal">Controlo de acesso</p>
            <h1 class="mt-2 text-3xl font-bold tracking-[-.04em]">Utilizadores da equipa</h1>
            <p class="mt-2 text-sm text-slate-500">Defina o papel, a associação clínica e o acesso de cada profissional.</p>
            <section class="mt-6 rounded-2xl border border-[#e1d8ca] bg-[#f5eee3] p-5">
                <p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Matriz de permissões</p>
                <h2 class="mt-1 text-lg font-bold">O que cada papel pode fazer</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-2">
                    <div class="rounded-xl bg-white p-4"><p class="text-sm font-bold">Paciente</p><p class="mt-1 text-xs leading-5 text-slate-500">Acede apenas ao próprio perfil, consultas, mensagens, documentos, receitas e resultados.</p></div>
                    <div class="rounded-xl bg-white p-4"><p class="text-sm font-bold">Recepção</p><p class="mt-1 text-xs leading-5 text-slate-500">Gere pacientes, agenda e pedidos. Não publica informação clínica nem gere utilizadores.</p></div>
                    <div class="rounded-xl bg-white p-4"><p class="text-sm font-bold">Médico</p><p class="mt-1 text-xs leading-5 text-slate-500">Vê apenas os próprios pacientes e consultas e pode publicar informação clínica associada.</p></div>
                    <div class="rounded-xl bg-white p-4"><p class="text-sm font-bold">Administrador</p><p class="mt-1 text-xs leading-5 text-slate-500">Gere utilizadores, médicos, departamentos, pacientes, consultas, definições e relatórios.</p></div>
                </div>
                <p class="mt-4 text-xs leading-5 text-slate-500">O papel define permissões. A classificação clínica do paciente é um dado separado e não altera o acesso ao sistema.</p>
            </section>

            <section class="mt-8 rounded-2xl border border-[#e1d8ca] bg-white p-5 sm:p-6">
                <h2 class="text-lg font-bold">Adicionar utilizador</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">Uma conta com o papel Médico tem de ficar associada ao registo do médico correspondente para receber as consultas certas.</p>
                <form method="post" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create-user">
                    <input required name="name" placeholder="Nome completo" class="rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm">
                    <input required type="email" name="email" placeholder="Email profissional" class="rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm">
                    <input required minlength="8" type="password" name="password" placeholder="Palavra-passe inicial" class="rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm">
                    <select name="role" class="rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm">
                        <option value="doctor">Médico</option>
                        <option value="receptionist">Recepção</option>
                        <option value="admin">Administrador</option>
                    </select>
                    <select name="doctor_id" class="rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm">
                        <option value="0">Associar ao médico (obrigatório para médico)</option>
                        <?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>"><?= e($doctor['name']) ?> · <?= e($doctor['specialty']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white sm:col-span-2">Criar acesso</button>
                </form>
            </section>

            <section class="mt-6 overflow-hidden rounded-2xl border border-[#e1d8ca] bg-white">
                <?php foreach ($staffAccounts as $staff): ?>
                    <form method="post" class="flex flex-col gap-3 border-b border-[#eee7dc] px-5 py-5 last:border-0 sm:flex-row sm:items-center">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="update-user">
                        <input type="hidden" name="account_id" value="<?= (int) $staff['id'] ?>">
                        <div class="min-w-0 flex-1"><p class="text-sm font-bold"><?= e($staff['name']) ?></p><p class="mt-1 text-xs text-slate-500"><?= e($staff['email']) ?></p></div>
                        <select name="role" class="rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-2 text-xs"><option value="admin" <?= $staff['role'] === 'admin' ? 'selected' : '' ?>>Administrador</option><option value="doctor" <?= $staff['role'] === 'doctor' ? 'selected' : '' ?>>Médico</option><option value="receptionist" <?= $staff['role'] === 'receptionist' ? 'selected' : '' ?>>Recepção</option></select>
                        <select name="doctor_id" class="rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-2 text-xs"><option value="0">Sem associação</option><?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>" <?= (int) ($staff['doctor_id'] ?? 0) === (int) $doctor['id'] ? 'selected' : '' ?>><?= e($doctor['name']) ?></option><?php endforeach; ?></select>
                        <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="active" <?= $staff['active'] ? 'checked' : '' ?>> Activo</label>
                        <button class="rounded-lg bg-teal px-4 py-2 text-xs font-bold text-white">Guardar</button>
                    </form>
                <?php endforeach; ?>
            </section>
        </main>
    </div>
</div>