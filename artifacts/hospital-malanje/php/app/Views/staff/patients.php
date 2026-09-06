<?php
// A listagem já vem limitada pelo papel da conta; estes mapas traduzem enums
// guardados na base para etiquetas legíveis no ecrã.
$active = 'patients';
$status = $filters['status'] ?? '';
$sexLabels = [
    'female' => 'Feminino',
    'male' => 'Masculino',
    'other' => 'Outro',
    'not_informed' => 'Não indicado',
];
$patientTypes = [
    'general' => 'Geral',
    'child' => 'Criança',
    'pregnant' => 'Gestante',
    'chronic' => 'Doença crónica',
];
$activeCount = count(array_filter($patients, static fn (array $patient): bool => (bool) ($patient['active'] ?? true)));
$inactiveCount = count($patients) - $activeCount;
?>
<div class="flex min-h-screen">
    <div class="hidden md:block"><?php require __DIR__ . '/../partials/staff-sidebar.php'; ?></div>
    <div class="min-w-0 flex-1">
        <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-teal">Registos clínicos</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-[-.04em]">Pacientes</h1>
                    <p class="mt-2 text-sm text-slate-500"><?= count($patients) ?> registos disponíveis para a equipa autorizada.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="<?= url('patients') ?>" class="text-xs font-bold text-slate-500 hover:text-teal">Limpar filtros</a>
                    <a href="<?= url('logout') ?>" class="text-xs font-bold text-slate-500 hover:text-teal">Sair</a>
                </div>
            </div>

            <section class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-[#e1d8ca] bg-white p-5"><p class="text-xs text-slate-500">Registos apresentados</p><p class="mt-2 text-3xl font-bold"><?= count($patients) ?></p></div>
                <div class="rounded-xl border border-[#e1d8ca] bg-[#e8f0e8] p-5"><p class="text-xs text-slate-500">Pacientes activos</p><p class="mt-2 text-3xl font-bold text-teal"><?= $activeCount ?></p></div>
                <div class="rounded-xl border border-[#e1d8ca] bg-[#f5eee3] p-5"><p class="text-xs text-slate-500">Registos inactivos</p><p class="mt-2 text-3xl font-bold text-[#a85f2f]"><?= $inactiveCount ?></p></div>
            </section>

            <section class="mt-6 rounded-2xl border border-[#e1d8ca] bg-white p-5">
                <div class="flex items-start justify-between gap-4"><div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Pesquisa de registos</p><h2 class="mt-1 text-lg font-bold">Encontrar paciente</h2></div><span class="hidden text-xs text-slate-400 sm:block">Nome, processo ou telefone</span></div>
                <form method="get" class="mt-4 grid gap-3 sm:grid-cols-[1fr_13rem_auto]">
                    <input type="hidden" name="page" value="patients">
                    <label class="text-xs font-bold text-slate-500">Pesquisa<input name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Ex.: Maria, HM-2026 ou 923..." class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                    <label class="text-xs font-bold text-slate-500">Estado<select name="status" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"><option value="">Todos</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Activos</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactivos</option></select></label>
                    <div class="flex items-end"><button class="w-full rounded-lg bg-teal px-5 py-3 text-sm font-bold text-white">Pesquisar</button></div>
                </form>
            </section>

            <?php if ($canManagePatients): ?>
                <section class="mt-6">
                    <details class="rounded-2xl border border-[#e1d8ca] bg-white">
                        <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4"><div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Recepção</p><h2 class="mt-1 text-lg font-bold">+ Novo paciente</h2></div><span class="text-xs font-bold text-teal">Abrir formulário</span></summary>
                        <form method="post" class="grid gap-3 border-t border-[#e1d8ca] p-5 sm:grid-cols-2">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create-patient">
                            <label class="text-xs font-bold text-slate-500 sm:col-span-2">Nome completo<input name="name" required maxlength="190" autocomplete="name" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                            <label class="text-xs font-bold text-slate-500">Sexo<select name="sex" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"><?php foreach ($sexLabels as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <label class="text-xs font-bold text-slate-500">Classificação clínica<select name="patient_type" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"><?php foreach ($patientTypes as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <label class="text-xs font-bold text-slate-500">Data de nascimento<input type="date" name="birth_date" required max="<?= e((new DateTimeImmutable())->format('Y-m-d')) ?>" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                            <label class="text-xs font-bold text-slate-500">Telefone<input name="phone" required maxlength="40" autocomplete="tel" placeholder="923 000 000" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                            <label class="text-xs font-bold text-slate-500">Bairro/localidade<input name="neighborhood" maxlength="160" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"></label>
                            <p class="text-xs leading-5 text-slate-500 sm:col-span-2">O número de processo será atribuído automaticamente. O sistema impede registos duplicados com o mesmo nome, data de nascimento e telefone.</p>
                            <button class="rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white sm:col-span-2">Criar paciente</button>
                        </form>
                    </details>
                </section>
            <?php endif; ?>

            <section class="mt-6 overflow-hidden rounded-2xl border border-[#e1d8ca] bg-white">
                <div class="border-b border-[#e1d8ca] px-5 py-4"><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Registos clínicos</p><h2 class="mt-1 text-lg font-bold">Lista de pacientes</h2></div>
                <?php if (!$patients): ?>
                    <p class="px-5 py-12 text-center text-sm text-slate-500">Não existem pacientes para os filtros seleccionados.</p>
                <?php else: ?>
                    <?php foreach ($patients as $patient): ?>
                        <div class="border-b border-[#eee7dc] px-5 py-4 last:border-0">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <a href="<?= url('patient', ['id' => $patient['id']]) ?>" class="min-w-0 flex-1 transition hover:text-teal">
                                    <div class="flex flex-wrap items-center gap-2"><p class="text-sm font-bold"><?= e($patient['name']) ?></p><span class="rounded-full px-2.5 py-1 text-[10px] font-bold <?= ($patient['active'] ?? true) ? 'bg-[#e8f0e8] text-teal' : 'bg-slate-100 text-slate-500' ?>"><?= ($patient['active'] ?? true) ? 'Activo' : 'Inactivo' ?></span><span class="rounded-full bg-[#fff0dc] px-2.5 py-1 text-[10px] font-bold text-[#a85f2f]"><?= e($patientTypes[$patient['patient_type'] ?? 'general'] ?? 'Geral') ?></span></div>
                                    <p class="mt-1 font-mono-ui text-[10px] text-slate-500"><?= e($patient['medical_record_number']) ?> · <?= e($patient['phone']) ?> · <?= age_from_date($patient['birth_date']) ?> anos</p>
                                    <p class="mt-2 text-xs text-slate-500"><?php if ($patient['next_appointment_date']): ?>Próxima consulta: <strong><?= e((new DateTimeImmutable($patient['next_appointment_date']))->format('d/m/Y')) ?> · <?= e($patient['next_appointment_time']) ?></strong><?php elseif ($patient['last_appointment_date']): ?>Última consulta: <?= e((new DateTimeImmutable($patient['last_appointment_date']))->format('d/m/Y')) ?><?php else: ?>Ainda sem consultas registadas<?php endif; ?><?php if ($patient['next_doctor_name']): ?> · <?= e($patient['next_doctor_name']) ?><?php endif; ?></p>
                                </a>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="<?= url('patient', ['id' => $patient['id']]) ?>" class="rounded-lg border border-[#d7cebf] bg-white px-3 py-2 text-xs font-bold text-slate-600 hover:border-teal hover:text-teal">Ver ficha</a>
                                    <?php if ($canManagePatients): ?>
                                        <details class="relative"><summary class="cursor-pointer list-none rounded-lg border border-[#d7cebf] bg-white px-3 py-2 text-xs font-bold text-slate-600">Editar</summary><form method="post" class="absolute right-0 z-10 mt-2 grid w-[min(92vw,25rem)] gap-3 rounded-xl border border-[#d7cebf] bg-white p-4 text-left shadow-soft"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update-patient"><input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>"><label class="text-xs font-bold text-slate-500">Nome<input name="name" value="<?= e($patient['name']) ?>" required maxlength="190" class="mt-1 w-full rounded-lg border border-[#d7cebf] px-3 py-2 text-sm"></label><div class="grid grid-cols-2 gap-2"><label class="text-xs font-bold text-slate-500">Sexo<select name="sex" required class="mt-1 w-full rounded-lg border border-[#d7cebf] px-2 py-2 text-sm"><?php foreach ($sexLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $patient['sex'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label class="text-xs font-bold text-slate-500">Nascimento<input type="date" name="birth_date" value="<?= e($patient['birth_date']) ?>" required class="mt-1 w-full rounded-lg border border-[#d7cebf] px-2 py-2 text-sm"></label></div><label class="text-xs font-bold text-slate-500">Classificação<select name="patient_type" required class="mt-1 w-full rounded-lg border border-[#d7cebf] px-3 py-2 text-sm"><?php foreach ($patientTypes as $value => $label): ?><option value="<?= e($value) ?>" <?= ($patient['patient_type'] ?? 'general') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><label class="text-xs font-bold text-slate-500">Telefone<input name="phone" value="<?= e($patient['phone']) ?>" required maxlength="40" class="mt-1 w-full rounded-lg border border-[#d7cebf] px-3 py-2 text-sm"></label><label class="text-xs font-bold text-slate-500">Bairro<input name="neighborhood" value="<?= e($patient['neighborhood'] ?? '') ?>" maxlength="160" class="mt-1 w-full rounded-lg border border-[#d7cebf] px-3 py-2 text-sm"></label><button class="rounded-lg bg-teal px-3 py-2 text-xs font-bold text-white">Guardar dados</button></form></details>
                                        <form method="post" onsubmit="return confirm('Alterar o estado deste paciente?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle-patient"><input type="hidden" name="patient_id" value="<?= (int) $patient['id'] ?>"><input type="hidden" name="active" value="<?= ($patient['active'] ?? true) ? '0' : '1' ?>"><button class="rounded-lg border border-[#d7cebf] px-3 py-2 text-xs font-bold text-slate-600"><?= ($patient['active'] ?? true) ? 'Desactivar' : 'Activar' ?></button></form>
                                    <?php endif; ?>
                                    <span class="text-lg text-teal">→</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>