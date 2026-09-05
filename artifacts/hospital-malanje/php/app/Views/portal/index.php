<?php
$firstName = explode(' ', trim((string) $user['name']))[0] ?: 'Paciente';
$latestAppointment = $appointments[0] ?? null;
$lastMessage = $messages ? $messages[count($messages) - 1] : null;
$statusLabels = [
    'pending' => 'Pendente de confirmação',
    'scheduled' => 'Agendada',
    'waiting' => 'Em espera',
    'in_progress' => 'Em atendimento',
    'completed' => 'Concluída',
    'cancelled' => 'Cancelada',
];
$statusClasses = [
    'pending' => 'bg-[#fff0dc] text-[#a85f2f]',
    'scheduled' => 'bg-[#e8f0e8] text-teal',
    'waiting' => 'bg-[#fff0dc] text-[#a85f2f]',
    'in_progress' => 'bg-[#e8eefa] text-[#5268a3]',
    'completed' => 'bg-slate-100 text-slate-600',
    'cancelled' => 'bg-rose-50 text-rose-700',
];
$typeLabels = [
    'first_visit' => 'Primeira consulta',
    'follow_up' => 'Consulta de acompanhamento',
];
?>
<?php require __DIR__ . '/../partials/header.php'; ?>
<main class="noise mx-auto max-w-7xl px-5 py-8 sm:px-8 sm:py-12">
    <?php if (!$patient): ?>
        <div class="mb-8">
            <p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-teal">Primeiro acesso</p>
            <h1 class="mt-2 text-3xl font-bold tracking-[-.04em]">Vamos ligar o seu acompanhamento</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Confirme o seu processo clínico para desbloquear consultas, mensagens e informação pessoal.</p>
        </div>
        <section class="grid gap-5 lg:grid-cols-[1fr_.8fr]">
            <div class="rounded-2xl border border-[#e1d8ca] bg-white p-6 shadow-soft sm:p-8">
                <div class="flex size-11 items-center justify-center rounded-xl bg-[#e8f0e8] text-xl text-teal">↗</div>
                <h2 class="mt-5 text-xl font-bold">Ligue o seu registo clínico</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Use o número do processo e o telefone que estão registados no hospital.</p>
                <form method="post" class="mt-6 space-y-4">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="link-patient">
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Número do processo
                        <input required name="medical_record_number" placeholder="HM-2026-00142" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                    </label>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Telefone registado
                        <input required name="phone" placeholder="923 441 802" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                    </label>
                    <button class="w-full rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white hover:bg-[#185b58]">Ligar o meu registo</button>
                </form>
            </div>
            <div class="rounded-2xl border border-[#e1d8ca] bg-[#e8f0e8]/60 p-6 sm:p-8">
                <p class="font-mono-ui text-[10px] uppercase tracking-wider text-teal">Acesso protegido</p>
                <h2 class="mt-4 text-lg font-bold">Uma área só sua</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Depois da confirmação, verá apenas o histórico associado ao seu processo clínico.</p>
                <div class="mt-8 space-y-3 text-sm text-slate-600">
                    <p class="flex items-center gap-3"><span class="flex size-7 items-center justify-center rounded-full bg-white text-teal">✓</span> Consultas e histórico</p>
                    <p class="flex items-center gap-3"><span class="flex size-7 items-center justify-center rounded-full bg-white text-teal">✓</span> Mensagens seguras</p>
                    <p class="flex items-center gap-3"><span class="flex size-7 items-center justify-center rounded-full bg-white text-teal">✓</span> Dados sempre privados</p>
                </div>
            </div>
        </section>
    <?php else: ?>
        <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
            <div>
                <p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-teal">Área do paciente</p>
                <h1 class="mt-2 text-3xl font-bold tracking-[-.04em] sm:text-4xl">Olá, <?= e($firstName) ?>.</h1>
                <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">Aqui encontra o seu acompanhamento, os próximos passos e uma linha directa para a equipa do Hospital de Malanje.</p>
            </div>
            <div class="rounded-xl border border-[#d7cebf] bg-white/80 px-4 py-3">
                <p class="font-mono-ui text-[9px] uppercase tracking-wider text-slate-400">Número do processo</p>
                <p class="mt-1 font-mono-ui text-sm font-bold text-ink"><?= e($patient['medical_record_number']) ?></p>
            </div>
        </div>

        <section class="mt-8 grid gap-5 lg:grid-cols-[1.35fr_.65fr]">
            <div class="relative overflow-hidden rounded-2xl bg-teal p-6 text-white shadow-soft sm:p-8">
                <div class="absolute -right-10 -top-16 size-48 rounded-full border-[24px] border-white/10"></div>
                <div class="relative">
                    <div class="flex items-center justify-between gap-4">
                        <p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-white/60"><?= $nextAppointment ? 'Próxima consulta' : 'Acompanhamento' ?></p>
                        <span class="rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold uppercase tracking-wider"><?= $nextAppointment ? 'Agendada' : 'Tudo em dia' ?></span>
                    </div>
                    <?php if ($nextAppointment): ?>
                        <p class="mt-8 text-3xl font-bold"><?= e((new DateTimeImmutable($nextAppointment['date']))->format('d/m/Y')) ?></p>
                        <p class="mt-1 text-sm text-white/70"><?= e($nextAppointment['time']) ?> · <?= e($nextAppointment['department_name']) ?></p>
                        <div class="mt-7 flex flex-wrap items-center gap-3">
                            <span class="rounded-lg bg-white/10 px-3 py-2 text-xs"><?= e($nextAppointment['doctor_name']) ?></span>
                            <a href="#consultas" class="rounded-lg bg-coral px-3 py-2 text-xs font-bold text-ink hover:bg-[#e39a77]">Ver detalhes</a>
                        </div>
                    <?php elseif ($latestAppointment): ?>
                        <p class="mt-8 text-xl font-bold">Não tem consultas futuras.</p>
                        <p class="mt-2 max-w-md text-sm leading-6 text-white/70">O seu último atendimento foi em <?= e((new DateTimeImmutable($latestAppointment['date']))->format('d/m/Y')) ?>. Fale com a equipa se precisar de novo acompanhamento.</p>
                        <a href="#mensagens" class="mt-6 inline-flex rounded-lg bg-coral px-4 py-3 text-xs font-bold text-ink hover:bg-[#e39a77]">Falar com a equipa</a>
                    <?php else: ?>
                        <p class="mt-8 text-xl font-bold">Ainda não há consultas.</p>
                        <p class="mt-2 max-w-md text-sm leading-6 text-white/70">Quando existir uma marcação, ela aparecerá aqui com data, hora e profissional.</p>
                        <a href="#mensagens" class="mt-6 inline-flex rounded-lg bg-coral px-4 py-3 text-xs font-bold text-ink hover:bg-[#e39a77]">Pedir apoio</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-[#e1d8ca] bg-white p-5">
                    <p class="text-xs text-slate-500">Consultas</p>
                    <p class="mt-3 text-3xl font-bold"><?= count($appointments) ?></p>
                    <p class="mt-1 text-[11px] text-slate-500">no seu histórico</p>
                </div>
                <div class="rounded-2xl border border-[#e1d8ca] bg-white p-5">
                    <p class="text-xs text-slate-500">Concluídas</p>
                    <p class="mt-3 text-3xl font-bold"><?= $completedAppointments ?></p>
                    <p class="mt-1 text-[11px] text-slate-500">atendimentos</p>
                </div>
                <div class="rounded-2xl border border-[#e1d8ca] bg-white p-5">
                    <p class="text-xs text-slate-500">Mensagens</p>
                    <p class="mt-3 text-3xl font-bold"><?= count($messages) ?></p>
                    <p class="mt-1 text-[11px] text-slate-500">conversa segura</p>
                </div>
                <div class="rounded-2xl border border-[#e1d8ca] bg-[#fff4e7] p-5">
                    <p class="text-xs text-slate-500">Documentos</p>
                    <p class="mt-3 text-3xl font-bold"><?= count($documents) ?></p>
                    <p class="mt-1 text-[11px] text-slate-500">ficheiros privados</p>
                </div>
                <div class="rounded-2xl border border-[#e1d8ca] bg-white p-5">
                    <p class="text-xs text-slate-500">Receitas</p>
                    <p class="mt-3 text-3xl font-bold"><?= count($prescriptions) ?></p>
                    <p class="mt-1 text-[11px] text-slate-500">prescrições activas</p>
                </div>
                <div class="rounded-2xl border border-[#e1d8ca] bg-white p-5">
                    <p class="text-xs text-slate-500">Resultados</p>
                    <p class="mt-3 text-3xl font-bold"><?= count($results) ?></p>
                    <p class="mt-1 text-[11px] text-slate-500">resultados publicados</p>
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-3 sm:grid-cols-5">
            <a href="#marcar-consulta" class="group rounded-xl border border-[#e1d8ca] bg-white p-4 transition hover:-translate-y-0.5 hover:border-teal"><span class="flex size-9 items-center justify-center rounded-lg bg-[#fff0dc] text-[#a85f2f]">+</span><p class="mt-3 text-sm font-bold">Marcar consulta</p><p class="mt-1 text-xs text-slate-500 group-hover:text-teal">Escolher horário</p></a>
            <a href="#consultas" class="group rounded-xl border border-[#e1d8ca] bg-white p-4 transition hover:-translate-y-0.5 hover:border-teal"><span class="flex size-9 items-center justify-center rounded-lg bg-[#e8f0e8] text-teal">◷</span><p class="mt-3 text-sm font-bold">As consultas</p><p class="mt-1 text-xs text-slate-500 group-hover:text-teal">Ver histórico</p></a>
            <a href="#mensagens" class="group rounded-xl border border-[#e1d8ca] bg-white p-4 transition hover:-translate-y-0.5 hover:border-teal"><span class="flex size-9 items-center justify-center rounded-lg bg-[#e8eefa] text-[#5268a3]">↗</span><p class="mt-3 text-sm font-bold">Falar com a equipa</p><p class="mt-1 text-xs text-slate-500 group-hover:text-teal">Enviar mensagem</p></a>
            <a href="#perfil" class="group rounded-xl border border-[#e1d8ca] bg-white p-4 transition hover:-translate-y-0.5 hover:border-teal"><span class="flex size-9 items-center justify-center rounded-lg bg-[#fff0dc] text-[#a85f2f]">♙</span><p class="mt-3 text-sm font-bold">O meu perfil</p><p class="mt-1 text-xs text-slate-500 group-hover:text-teal">Dados pessoais</p></a>
            <a href="#documentos" class="group rounded-xl border border-[#d7cebf] bg-white p-4 transition hover:-translate-y-0.5 hover:border-teal"><span class="flex size-9 items-center justify-center rounded-lg bg-slate-100 text-slate-500">▱</span><p class="mt-3 text-sm font-bold">Documentos</p><p class="mt-1 text-xs text-slate-500 group-hover:text-teal">Ver ficheiros</p></a>
        </section>

        <section id="marcar-consulta" class="mt-8 scroll-mt-6 overflow-hidden rounded-2xl border border-[#e1d8ca] bg-white">
            <div class="border-b border-[#e1d8ca] px-5 py-5 sm:px-6">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Novo pedido</p><h2 class="mt-1 text-lg font-bold">Marcar uma consulta</h2><p class="mt-1 text-xs text-slate-500"><?= e($settings['appointment_notice'] ?? 'Escolha o serviço e um horário disponível.') ?></p></div>
                    <span class="w-fit rounded-full bg-[#e8f0e8] px-3 py-1 text-[10px] font-bold text-teal">Pedido seguro</span>
                </div>
            </div>
            <form method="post" class="grid gap-4 px-5 py-5 sm:grid-cols-2 sm:px-6">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="book-appointment">
                <label class="text-xs font-bold text-slate-500">Departamento
                    <select id="booking-department" name="department_id" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                        <option value="">Escolha o departamento</option>
                        <?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>"><?= e($department['name']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-bold text-slate-500">Médico
                    <select id="booking-doctor" name="doctor_id" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                        <option value="">Escolha o médico</option>
                        <?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>" data-department="<?= (int) $doctor['department_id'] ?>"><?= e($doctor['name']) ?> · <?= e($doctor['specialty']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-bold text-slate-500">Data preferida
                    <input type="date" name="date" min="<?= e(date('Y-m-d')) ?>" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                </label>
                <label class="text-xs font-bold text-slate-500">Horário
                    <select name="time" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                        <option value="">Escolha o horário</option>
                        <?php foreach ($availableTimes as $availableTime): ?><option value="<?= e($availableTime) ?>"><?= e($availableTime) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="text-xs font-bold text-slate-500">Tipo de consulta
                    <select name="type" required class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                        <option value="first_visit">Primeira consulta</option>
                        <option value="follow_up">Consulta de acompanhamento</option>
                    </select>
                </label>
                <label class="text-xs font-bold text-slate-500">Nota para a equipa <span class="font-normal text-slate-400">(opcional)</span>
                    <input name="notes" maxlength="500" placeholder="Indique o motivo ou alguma preferência" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal">
                </label>
                <div class="flex flex-col justify-between gap-3 sm:col-span-2 sm:flex-row sm:items-center">
                    <p class="max-w-xl text-[11px] leading-5 text-slate-500">O pedido fica pendente de confirmação. Não é possível reservar o mesmo horário para o mesmo médico ou paciente.</p>
                    <button class="rounded-lg bg-teal px-5 py-3 text-sm font-bold text-white hover:bg-[#185b58]">Enviar pedido</button>
                </div>
            </form>
        </section>
        <script>
            (() => {
                const department = document.querySelector('#booking-department');
                const doctor = document.querySelector('#booking-doctor');
                if (!department || !doctor) return;
                const options = Array.from(doctor.querySelectorAll('option[data-department]'));
                const filterDoctors = () => {
                    options.forEach((option) => {
                        const visible = !department.value || option.dataset.department === department.value;
                        option.hidden = !visible;
                        option.disabled = !visible;
                    });
                    if (doctor.selectedOptions[0]?.disabled) doctor.value = '';
                };
                department.addEventListener('change', filterDoctors);
                filterDoctors();
            })();
        </script>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1.25fr_.75fr]">
            <section id="consultas" class="scroll-mt-6 overflow-hidden rounded-2xl border border-[#e1d8ca] bg-white">
                <div class="flex items-start justify-between gap-4 border-b border-[#e1d8ca] px-5 py-5 sm:px-6">
                    <div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Linha do tempo</p><h2 class="mt-1 text-lg font-bold">As suas consultas</h2><p class="mt-1 text-xs text-slate-500">Acompanhe os seus atendimentos mais recentes.</p></div>
                    <span class="rounded-full bg-[#e8f0e8] px-3 py-1 text-[10px] font-bold text-teal"><?= count($appointments) ?> registos</span>
                </div>
                <?php if (!$appointments): ?>
                    <p class="px-6 py-12 text-center text-sm text-slate-500">Ainda não existem consultas associadas.</p>
                <?php else: ?>
                    <div class="px-5 py-2 sm:px-6">
                        <?php foreach ($appointments as $appointment): ?>
                            <?php $status = $appointment['status']; ?>
                            <div class="relative flex gap-4 border-b border-[#eee7dc] py-5 last:border-0">
                                <div class="relative flex w-7 shrink-0 justify-center"><span class="z-10 mt-1 flex size-3 rounded-full border-4 border-[#e8f0e8] bg-teal"></span><span class="absolute left-1/2 top-4 h-full w-px -translate-x-1/2 bg-[#e1d8ca] last:hidden"></span></div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start"><div><p class="text-sm font-bold"><?= e($appointment['department_name']) ?></p><p class="mt-1 text-xs text-slate-500"><?= e((new DateTimeImmutable($appointment['date']))->format('d/m/Y')) ?> às <?= e($appointment['time']) ?> · <?= e($appointment['doctor_name']) ?></p></div><span class="w-fit rounded-full px-3 py-1 text-[10px] font-bold uppercase tracking-wider <?= $statusClasses[$status] ?? 'bg-slate-100 text-slate-600' ?>"><?= e($statusLabels[$status] ?? $status) ?></span></div>
                                    <?php if (!empty($appointment['notes'])): ?><p class="mt-3 rounded-lg bg-[#fbf8f1] px-3 py-2 text-xs leading-5 text-slate-600"><?= e($appointment['notes']) ?></p><?php endif; ?>
                                    <div class="mt-2 flex flex-wrap items-center justify-between gap-3"><p class="text-[10px] uppercase tracking-wider text-slate-400"><?= e($typeLabels[$appointment['type']] ?? $appointment['type']) ?></p><?php if (in_array($status, ['pending', 'scheduled'], true) && $appointment['date'] >= date('Y-m-d')): ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="cancel-appointment"><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><button class="text-[10px] font-bold text-rose-600 hover:underline">Cancelar pedido</button></form><?php endif; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="space-y-6">
                <section id="perfil" class="scroll-mt-6 rounded-2xl border border-[#e1d8ca] bg-white p-5 sm:p-6">
                    <div class="flex items-center justify-between"><div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Dados pessoais</p><h2 class="mt-1 text-lg font-bold">O meu perfil</h2></div><span class="flex size-10 items-center justify-center rounded-full bg-[#e8f0e8] text-lg font-bold text-teal"><?= e(strtoupper(substr((string) $patient['name'], 0, 1))) ?></span></div>
                    <div class="mt-5 grid grid-cols-2 gap-x-4 gap-y-4 text-sm"><div><p class="text-[11px] text-slate-400">Nome</p><p class="mt-1 font-semibold"><?= e($patient['name']) ?></p></div><div><p class="text-[11px] text-slate-400">Idade</p><p class="mt-1 font-semibold"><?= age_from_date((string) $patient['birth_date']) ?> anos</p></div></div>
                    <form method="post" class="mt-5 space-y-3 border-t border-[#eee7dc] pt-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update-profile"><label class="block text-[11px] font-bold text-slate-500">Telefone<input required name="phone" value="<?= e($patient['phone']) ?>" class="mt-1 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-2 text-sm font-normal outline-none focus:border-teal"></label><label class="block text-[11px] font-bold text-slate-500">Bairro<input name="neighborhood" value="<?= e($patient['neighborhood']) ?>" class="mt-1 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-2 text-sm font-normal outline-none focus:border-teal"></label><button class="rounded-lg border border-teal px-3 py-2 text-xs font-bold text-teal hover:bg-[#e8f0e8]">Actualizar dados</button></form>
                </section>

                <section id="mensagens" class="scroll-mt-6 overflow-hidden rounded-2xl border border-[#e1d8ca] bg-white">
                    <div class="border-b border-[#e1d8ca] px-5 py-5 sm:px-6"><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Contacto seguro</p><h2 class="mt-1 text-lg font-bold">Falar com a equipa</h2><p class="mt-1 text-xs text-slate-500">Envie uma mensagem sobre o seu acompanhamento.</p></div>
                    <div class="max-h-96 space-y-3 overflow-y-auto px-5 py-5 sm:px-6">
                        <?php if (!$messages): ?><p class="rounded-xl bg-[#fbf8f1] px-4 py-3 text-xs leading-5 text-slate-500">Ainda não iniciou uma conversa. A equipa poderá responder aqui.</p><?php else: ?><?php foreach ($messages as $message): ?><div class="<?= $message['sender_role'] === 'patient' ? 'ml-6 bg-[#e8f0e8]' : 'mr-6 bg-[#fbf8f1]' ?> rounded-xl px-4 py-3"><div class="flex justify-between gap-3 text-[10px] text-slate-400"><span><?= $message['sender_role'] === 'patient' ? 'Você' : 'Equipa do hospital' ?></span><span><?= e((new DateTimeImmutable($message['created_at']))->format('d/m/Y H:i')) ?></span></div><p class="mt-2 text-sm leading-6"><?= nl2br(e($message['body'])) ?></p></div><?php endforeach; ?><?php endif; ?>
                    </div>
                    <div class="px-5 pb-5 sm:px-6">
                        <form method="post" class="mt-4 space-y-3"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="message"><textarea name="body" required maxlength="2000" rows="3" placeholder="Escreva a sua mensagem…" class="w-full resize-none rounded-xl border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"></textarea><button class="w-full rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white hover:bg-[#185b58]">Enviar mensagem</button></form>
                    </div>
                </section>
                 <section class="mt-6 rounded-2xl border border-[#e1d8ca] bg-white p-5 sm:p-6">
                     <div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Segurança</p><h2 class="mt-1 text-lg font-bold">Palavra-passe</h2><p class="mt-1 text-xs leading-5 text-slate-500">Altere regularmente a palavra-passe da sua conta.</p></div>
                     <form method="post" class="mt-5 space-y-3"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="change-password"><label class="block text-[11px] font-bold text-slate-500">Palavra-passe actual<input required type="password" name="current_password" class="mt-1 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-2 text-sm font-normal outline-none focus:border-teal"></label><label class="block text-[11px] font-bold text-slate-500">Nova palavra-passe<input required minlength="8" type="password" name="new_password" class="mt-1 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-2 text-sm font-normal outline-none focus:border-teal"></label><label class="block text-[11px] font-bold text-slate-500">Confirmar nova palavra-passe<input required minlength="8" type="password" name="new_password_confirmation" class="mt-1 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-2 text-sm font-normal outline-none focus:border-teal"></label><button class="rounded-lg border border-teal px-3 py-2 text-xs font-bold text-teal hover:bg-[#e8f0e8]">Actualizar palavra-passe</button></form>
                 </section>
            </div>
        </div>

        <section id="documentos" class="mt-6 scroll-mt-6 rounded-2xl border border-[#e1d8ca] bg-white p-5 sm:p-6">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"><div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Ficheiros privados</p><h2 class="mt-1 text-lg font-bold">Os seus documentos</h2><p class="mt-1 text-xs text-slate-500">Ficheiros publicados pela equipa clínica, disponíveis apenas para si.</p></div><span class="rounded-full bg-[#e8f0e8] px-3 py-1 text-[10px] font-bold text-teal"><?= count($documents) ?> ficheiros</span></div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2"><?php foreach ($documents as $document): ?><a href="<?= url('document-download', ['id' => $document['id']]) ?>" class="flex items-center justify-between rounded-xl border border-[#eee7dc] bg-[#fbf8f1] px-4 py-3 hover:border-teal"><span><strong class="block text-sm"><?= e($document['title']) ?></strong><span class="mt-1 block text-[11px] text-slate-500"><?= e($document['file_name']) ?> · <?= number_format((int) $document['file_size'] / 1024, 0) ?> KB</span></span><span class="font-bold text-teal">↓</span></a><?php endforeach; ?><?php if (!$documents): ?><p class="rounded-xl bg-[#fbf8f1] px-4 py-4 text-xs text-slate-500 sm:col-span-2">Ainda não existem documentos publicados pela equipa.</p><?php endif; ?></div>
        </section>
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <section id="receitas" class="scroll-mt-6 rounded-2xl border border-[#e1d8ca] bg-white p-5 sm:p-6"><div class="flex items-start justify-between gap-3"><div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Prescrição clínica</p><h2 class="mt-1 text-lg font-bold">As suas receitas</h2></div><span class="rounded-full bg-[#fff0dc] px-3 py-1 text-[10px] font-bold text-[#a85f2f]"><?= count($prescriptions) ?></span></div><div class="mt-5 space-y-3"><?php foreach ($prescriptions as $prescription): ?><article class="rounded-xl bg-[#fbf8f1] px-4 py-3"><div class="flex justify-between gap-3"><p class="text-sm font-bold"><?= e($prescription['medication']) ?></p><span class="text-[10px] text-slate-400"><?= e((new DateTimeImmutable($prescription['created_at']))->format('d/m/Y')) ?></span></div><p class="mt-1 text-xs text-slate-600"><?= e($prescription['dosage']) ?> · <?= e($prescription['frequency']) ?> · <?= e($prescription['duration']) ?></p><?php if ($prescription['instructions']): ?><p class="mt-2 text-xs leading-5 text-slate-500"><?= e($prescription['instructions']) ?></p><?php endif; ?><p class="mt-2 text-[10px] text-slate-400">Prescrito por <?= e($prescription['doctor_name'] ?: 'Equipa clínica') ?></p></article><?php endforeach; ?><?php if (!$prescriptions): ?><p class="rounded-xl bg-[#fbf8f1] px-4 py-4 text-xs text-slate-500">Ainda não existem receitas publicadas.</p><?php endif; ?></div></section>
            <section id="resultados" class="scroll-mt-6 rounded-2xl border border-[#e1d8ca] bg-white p-5 sm:p-6"><div class="flex items-start justify-between gap-3"><div><p class="font-mono-ui text-[9px] uppercase tracking-[.18em] text-teal">Acompanhamento clínico</p><h2 class="mt-1 text-lg font-bold">Resultados</h2></div><span class="rounded-full bg-[#e8eefa] px-3 py-1 text-[10px] font-bold text-[#5268a3]"><?= count($results) ?></span></div><div class="mt-5 space-y-3"><?php foreach ($results as $result): ?><article class="rounded-xl bg-[#fbf8f1] px-4 py-3"><div class="flex justify-between gap-3"><p class="text-sm font-bold"><?= e($result['title']) ?></p><span class="text-[10px] text-slate-400"><?= e((new DateTimeImmutable($result['created_at']))->format('d/m/Y')) ?></span></div><p class="mt-2 whitespace-pre-line text-xs leading-5 text-slate-600"><?= e($result['result_text']) ?></p><p class="mt-2 text-[10px] text-slate-400">Publicado por <?= e($result['doctor_name'] ?: 'Equipa clínica') ?></p></article><?php endforeach; ?><?php if (!$results): ?><p class="rounded-xl bg-[#fbf8f1] px-4 py-4 text-xs text-slate-500">Ainda não existem resultados publicados.</p><?php endif; ?></div></section>
        </div>
    <?php endif; ?>
</main>