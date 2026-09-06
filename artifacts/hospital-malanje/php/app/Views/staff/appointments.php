<?php
// Estado e filtros apresentados na agenda são preparados pelo StaffController.
// A vista apenas traduz valores para a interface e envia acções protegidas por CSRF.
$active = 'appointments';
$statusLabels = [
    'pending' => 'Pendente',
    'scheduled' => 'Confirmada',
    'confirmed' => 'Confirmada',
    'waiting' => 'Em espera',
    'in_progress' => 'Em atendimento',
    'completed' => 'Concluída',
    'cancelled' => 'Cancelada',
];
$statusClasses = [
    'pending' => 'bg-[#f8eadc] text-[#a85f2f]',
    'scheduled' => 'bg-[#e8f0e8] text-teal',
    'waiting' => 'bg-[#f8eadc] text-[#a85f2f]',
    'in_progress' => 'bg-[#e5e9f7] text-[#4c5ca8]',
    'completed' => 'bg-[#e8f0e8] text-teal',
    'cancelled' => 'bg-slate-100 text-slate-500',
];
$today = (new DateTimeImmutable())->format('Y-m-d');
$activeCount = count(array_filter($appointments, static fn (array $item): bool => !in_array($item['status'], ['completed', 'cancelled'], true)));
$waitingCount = count(array_filter($appointments, static fn (array $item): bool => $item['status'] === 'waiting'));
$inProgressCount = count(array_filter($appointments, static fn (array $item): bool => $item['status'] === 'in_progress'));
$filterQuery = array_filter([
    'date' => $filters['date'] ?? '',
    'from' => $filters['from'] ?? '',
    'to' => $filters['to'] ?? '',
    'department_id' => $filters['department_id'] ?? 0,
    'doctor_id' => $filters['doctor_id'] ?? 0,
    'status' => $filters['status'] ?? '',
    'search' => $filters['search'] ?? '',
], static fn (mixed $value): bool => $value !== '' && $value !== 0 && $value !== null);
$todayUrl = url('appointments', ['date' => $today]);
$clearUrl = url('appointments');
?>
<div class="flex min-h-screen">
    <div class="hidden md:block"><?php require __DIR__ . '/../partials/staff-sidebar.php'; ?></div>
    <div class="min-w-0 flex-1">
        <main class="mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-10">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-teal">Operação clínica</p>
                    <h1 class="mt-2 text-3xl font-bold tracking-[-.04em]">Consultas</h1>
                    <p class="mt-2 text-sm text-slate-500"><?= count($appointments) ?> marcações encontradas na agenda.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="<?= e($todayUrl) ?>" class="rounded-lg border border-[#d7cebf] bg-white px-3 py-2 text-xs font-bold hover:border-teal hover:text-teal">Hoje</a>
                    <a href="<?= e($clearUrl) ?>" class="text-xs font-bold text-slate-500 hover:text-teal">Limpar filtros</a>
                    <a href="<?= url('logout') ?>" class="text-xs font-bold text-slate-500 hover:text-teal">Sair</a>
                </div>
            </div>

            <section class="mt-6 grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-[#e1d8ca] bg-white p-5"><p class="text-xs text-slate-500">Consultas activas</p><p class="mt-2 text-3xl font-bold"><?= $activeCount ?></p></div>
                <div class="rounded-xl border border-[#e1d8ca] bg-[#f8eadc] p-5"><p class="text-xs text-slate-500">Em espera</p><p class="mt-2 text-3xl font-bold text-[#a85f2f]"><?= $waitingCount ?></p></div>
                <div class="rounded-xl border border-[#e1d8ca] bg-[#e5e9f7] p-5"><p class="text-xs text-slate-500">Em atendimento</p><p class="mt-2 text-3xl font-bold text-[#4c5ca8]"><?= $inProgressCount ?></p></div>
                <div class="rounded-xl border border-[#e1d8ca] bg-[#e8f0e8] p-5"><p class="text-xs text-slate-500">Total apresentado</p><p class="mt-2 text-3xl font-bold text-teal"><?= count($appointments) ?></p></div>
            </section>

            <section class="mt-6 rounded-2xl border border-[#e1d8ca] bg-white p-5">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Pesquisa operacional</p><h2 class="mt-1 text-lg font-bold">Filtrar agenda</h2></div>
                    <span class="hidden text-xs text-slate-400 sm:block">Pesquise por paciente, processo ou médico</span>
                </div>
                <form method="get" class="mt-4 grid gap-3 md:grid-cols-4">
                    <input type="hidden" name="page" value="appointments">
                    <label class="text-xs font-bold text-slate-500 md:col-span-2">Pesquisa
                        <input name="search" value="<?= e($filters['search'] ?? '') ?>" placeholder="Nome, nº de processo ou médico" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                    </label>
                    <label class="text-xs font-bold text-slate-500">Dia
                        <input type="date" name="date" value="<?= e($filters['date'] ?? '') ?>" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                    </label>
                    <label class="text-xs font-bold text-slate-500">Estado
                        <select name="status" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                            <option value="">Todos os estados</option>
                            <?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label class="text-xs font-bold text-slate-500">Departamento
                        <select name="department_id" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                            <option value="0">Todos os departamentos</option>
                            <?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>" <?= (int) ($filters['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <?php if (count($doctors) > 1): ?>
                        <label class="text-xs font-bold text-slate-500">Médico
                            <select name="doctor_id" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                                <option value="0">Todos os médicos</option>
                                <?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>" <?= (int) ($filters['doctor_id'] ?? 0) === (int) $doctor['id'] ? 'selected' : '' ?>><?= e($doctor['name']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label class="text-xs font-bold text-slate-500">De
                        <input type="date" name="from" value="<?= e($filters['from'] ?? '') ?>" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                    </label>
                    <label class="text-xs font-bold text-slate-500">Até
                        <input type="date" name="to" value="<?= e($filters['to'] ?? '') ?>" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                    </label>
                    <div class="flex items-end"><button class="w-full rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white">Aplicar filtros</button></div>
                </form>
            </section>

            <section class="mt-6 rounded-2xl border border-[#e1d8ca] bg-[#f5eee3] p-5">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Atendimento presencial</p><h2 class="mt-1 text-lg font-bold">Fila de atendimento</h2><p class="mt-1 text-xs text-slate-500">A fila segue a hora marcada e respeita os filtros activos.</p></div><span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-teal"><?= count($queue) ?> na fila</span></div>
                <?php if (!$queue): ?>
                    <p class="mt-4 rounded-xl border border-dashed border-[#d7cebf] bg-white/60 px-4 py-5 text-sm text-slate-500">Não existem pacientes em espera ou em atendimento para os filtros seleccionados.</p>
                <?php else: ?>
                    <div class="mt-4 grid gap-3">
                        <?php foreach ($queue as $position => $appointment): ?>
                            <div class="flex flex-col gap-3 rounded-xl border border-[#e1d8ca] bg-white px-4 py-4 sm:flex-row sm:items-center">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-teal text-sm font-bold text-white"><?= $position + 1 ?></span>
                                <div class="min-w-0 flex-1"><a href="<?= url('patient', ['id' => $appointment['patient_id']]) ?>" class="text-sm font-bold hover:text-teal"><?= e($appointment['patient_name']) ?></a><p class="mt-1 text-xs text-slate-500"><?= e($appointment['time']) ?> · <?= e($appointment['department_name']) ?> · <?= e($appointment['doctor_name']) ?></p></div>
                                <span class="rounded-full px-3 py-1 text-[10px] font-bold <?= $statusClasses[$appointment['status']] ?>"><?= e($statusLabels[$appointment['status']]) ?></span>
                                <?php if ($appointment['status'] === 'waiting'): ?>
                                    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update-status"><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><input type="hidden" name="status" value="in_progress"><button class="rounded-lg bg-teal px-3 py-2 text-xs font-bold text-white">Chamar paciente</button></form>
                                <?php else: ?>
                                    <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update-status"><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><input type="hidden" name="status" value="completed"><button class="rounded-lg bg-teal px-3 py-2 text-xs font-bold text-white">Concluir</button></form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="mt-6 overflow-hidden rounded-2xl border border-[#e1d8ca] bg-white">
                <div class="flex flex-col justify-between gap-3 border-b border-[#e1d8ca] px-5 py-4 sm:flex-row sm:items-center"><div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Agenda operacional</p><h2 class="mt-1 text-lg font-bold">Marcações</h2></div><details class="relative"><summary class="cursor-pointer list-none rounded-lg bg-teal px-4 py-2 text-center text-xs font-bold text-white">+ Nova consulta</summary><div class="absolute right-0 z-20 mt-3 w-[min(92vw,34rem)] rounded-2xl border border-[#d7cebf] bg-white p-5 text-left shadow-soft"><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Agendamento interno</p><h3 class="mt-1 text-lg font-bold">Criar consulta</h3><form method="post" class="mt-4 grid gap-3 sm:grid-cols-2"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create-appointment"><label class="text-xs font-bold text-slate-500 sm:col-span-2">Paciente<select name="patient_id" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm"><option value="">Seleccionar paciente</option><?php foreach ($patients as $patient): ?><option value="<?= (int) $patient['id'] ?>"><?= e($patient['name']) ?> · <?= e($patient['medical_record_number']) ?></option><?php endforeach; ?></select></label><label class="text-xs font-bold text-slate-500">Departamento<select id="appointment-department" name="department_id" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm"><option value="">Seleccionar</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?></select></label><label class="text-xs font-bold text-slate-500">Médico<select id="appointment-doctor" name="doctor_id" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm"><option value="">Seleccionar</option><?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>" data-department="<?= (int) $doctor['department_id'] ?>"><?= e($doctor['name']) ?> · <?= e($doctor['specialty']) ?></option><?php endforeach; ?></select></label><label class="text-xs font-bold text-slate-500">Data<input type="date" name="date" min="<?= e($today) ?>" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm"></label><label class="text-xs font-bold text-slate-500">Hora<select name="time" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm"><option value="">Seleccionar</option><?php foreach ($availableTimes as $time): ?><option value="<?= e($time) ?>"><?= e($time) ?></option><?php endforeach; ?></select></label><label class="text-xs font-bold text-slate-500">Tipo<select name="type" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm"><option value="first_visit">Primeira consulta</option><option value="follow_up">Seguimento</option></select></label><label class="text-xs font-bold text-slate-500 sm:col-span-2">Observações<textarea name="notes" maxlength="500" rows="2" class="mt-2 w-full resize-none rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm" placeholder="Informação útil para a recepção ou médico"></textarea></label><button class="rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white sm:col-span-2">Criar e confirmar consulta</button></form></div></details></div>
                <?php if (!$appointments): ?>
                    <p class="px-5 py-12 text-center text-sm text-slate-500">Não existem consultas para os filtros seleccionados.</p>
                <?php else: ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="border-b border-[#eee7dc] px-5 py-4 last:border-0">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                <a href="<?= url('patient', ['id' => $appointment['patient_id']]) ?>" class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2"><p class="text-sm font-bold"><?= e($appointment['patient_name']) ?></p><span class="rounded-full px-2.5 py-1 text-[10px] font-bold <?= e($statusClasses[$appointment['status']] ?? 'bg-slate-100 text-slate-500') ?>"><?= e($statusLabels[$appointment['status']] ?? $appointment['status']) ?></span></div>
                                    <p class="mt-1 text-xs text-slate-500"><?= e($appointment['department_name']) ?> · <?= e($appointment['date']) ?> · <?= e($appointment['time']) ?> · <?= e($appointment['doctor_name']) ?></p>
                                </a>
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="post" class="flex items-center gap-2"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update-status"><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><select name="status" class="rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-2 py-2 text-xs"><?php foreach ($statusLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= $appointment['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><button class="rounded-lg bg-teal px-3 py-2 text-xs font-bold text-white">Guardar estado</button></form>
                                    <?php if (!in_array($appointment['status'], ['completed', 'cancelled'], true)): ?>
                                        <details class="relative"><summary class="cursor-pointer list-none rounded-lg border border-[#d7cebf] bg-white px-3 py-2 text-xs font-bold text-slate-600">Reagendar</summary><form method="post" class="absolute right-0 z-10 mt-2 grid w-64 gap-2 rounded-xl border border-[#d7cebf] bg-white p-3 shadow-soft"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reschedule-appointment"><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><label class="text-[10px] font-bold text-slate-500">Nova data<input type="date" name="date" min="<?= e($today) ?>" required class="mt-1 w-full rounded-lg border border-[#d7cebf] px-2 py-2 text-xs"></label><label class="text-[10px] font-bold text-slate-500">Novo horário<select name="time" required class="mt-1 w-full rounded-lg border border-[#d7cebf] px-2 py-2 text-xs"><?php foreach ($availableTimes as $time): ?><option value="<?= e($time) ?>"><?= e($time) ?></option><?php endforeach; ?></select></label><button class="rounded-lg bg-teal px-3 py-2 text-xs font-bold text-white">Confirmar reagendamento</button></form></details>
                                        <form method="post" onsubmit="return confirm('Cancelar esta consulta?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel-staff-appointment"><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><button class="rounded-lg border border-[#e1b9a8] px-3 py-2 text-xs font-bold text-[#a85f2f]">Cancelar</button></form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </main>
    </div>
</div>
<script>
    // Mantém apenas médicos do departamento escolhido no formulário de consulta.
    // O backend repete esta validação; este filtro é apenas uma ajuda visual.
(() => {
    const department = document.querySelector('#appointment-department');
    const doctor = document.querySelector('#appointment-doctor');
    if (!department || !doctor) return;
    const updateDoctors = () => {
        [...doctor.options].forEach((option, index) => {
            if (index === 0) return;
            option.hidden = Boolean(department.value) && option.dataset.department !== department.value;
            if (option.hidden && option.selected) doctor.value = '';
        });
    };
    department.addEventListener('change', updateDoctors);
    updateDoctors();
})();
</script>