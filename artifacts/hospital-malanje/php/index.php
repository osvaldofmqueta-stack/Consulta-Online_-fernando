<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/layout.php';

$pdo = db();
$page = $_GET['page'] ?? (current_user() ? (current_user()['role'] === 'patient' ? 'portal' : 'dashboard') : 'home');
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'register') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
            flash('Preencha o nome, um email válido e uma palavra-passe com pelo menos 8 caracteres.');
            redirect_to(url('register'));
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO php_accounts (name, email, password_hash) VALUES (?, ?, ?) RETURNING id');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['account_id'] = (int) $stmt->fetchColumn();
            flash('Conta criada. Ligue agora o seu registo clínico.');
            redirect_to(url('portal'));
        } catch (PDOException) {
            flash('Este email já está registado.');
            redirect_to(url('register'));
        }
    }
    if ($action === 'login') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $stmt = $pdo->prepare('SELECT * FROM php_accounts WHERE email = ?');
        $stmt->execute([$email]);
        $account = $stmt->fetch();
        if (!$account || !password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
            flash('Email ou palavra-passe incorretos.');
            redirect_to(url('login'));
        }
        $_SESSION['account_id'] = (int) $account['id'];
        redirect_to(url($account['role'] === 'patient' ? 'portal' : 'dashboard'));
    }
    if ($action === 'link-patient') {
        $user = require_user();
        $stmt = $pdo->prepare('SELECT id FROM patients WHERE UPPER(medical_record_number) = UPPER(?) AND phone = ?');
        $stmt->execute([trim((string) $_POST['medical_record_number']), trim((string) $_POST['phone'])]);
        $patientId = $stmt->fetchColumn();
        if (!$patientId) {
            flash('Não encontrámos um registo com esses dados.');
        } else {
            $stmt = $pdo->prepare('UPDATE php_accounts SET patient_id = ? WHERE id = ?');
            $stmt->execute([(int) $patientId, $user['id']]);
            flash('Registo clínico ligado à sua conta.');
        }
        redirect_to(url('portal'));
    }
    if ($action === 'message') {
        $user = require_user();
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($user['patient_id'] && mb_strlen($body) > 0 && mb_strlen($body) <= 2000) {
            $stmt = $pdo->prepare('SELECT doctor_id FROM appointments WHERE patient_id = ? ORDER BY date DESC, time DESC LIMIT 1');
            $stmt->execute([$user['patient_id']]);
            $doctorId = $stmt->fetchColumn();
            $stmt = $pdo->prepare('INSERT INTO patient_messages (patient_id, doctor_id, sender_clerk_user_id, sender_role, body) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$user['patient_id'], $doctorId ?: null, 'php:' . $user['id'], 'patient', $body]);
            flash('Mensagem enviada à equipa.');
        }
        redirect_to(url('portal'));
    }
    if ($action === 'update-status') {
        require_staff();
        $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
        $stmt->execute([(string) $_POST['status'], (int) $_POST['appointment_id']]);
        flash('Estado da consulta atualizado.');
        redirect_to(url('appointments'));
    }
}

if ($page === 'logout') {
    session_destroy();
    redirect_to(url('home'));
}

if ($page === 'home' && $user) {
    redirect_to(url($user['role'] === 'patient' ? 'portal' : 'dashboard'));
}

if (in_array($page, ['portal', 'messages'], true)) {
    $user = require_user();
    $patient = null;
    $appointments = [];
    $messages = [];
    if ($user['patient_id']) {
        $stmt = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
        $stmt->execute([$user['patient_id']]);
        $patient = $stmt->fetch();
        $stmt = $pdo->prepare('SELECT a.*, d.name AS department_name, doc.name AS doctor_name FROM appointments a JOIN departments d ON d.id = a.department_id JOIN doctors doc ON doc.id = a.doctor_id WHERE a.patient_id = ? ORDER BY a.date DESC, a.time DESC');
        $stmt->execute([$user['patient_id']]);
        $appointments = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT pm.*, doc.name AS doctor_name FROM patient_messages pm LEFT JOIN doctors doc ON doc.id = pm.doctor_id WHERE pm.patient_id = ? ORDER BY pm.created_at ASC');
        $stmt->execute([$user['patient_id']]);
        $messages = $stmt->fetchAll();
    }
    page_start('Portal do paciente', $user);
    app_header($user);
    ?>
    <main class="noise mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-12">
        <p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-teal">Portal privado</p>
        <h1 class="mt-2 text-3xl font-bold tracking-[-.04em]">O seu acompanhamento</h1>
        <p class="mt-2 text-sm text-slate-500">Consulte as suas consultas e mantenha o contacto com a equipa.</p>
        <?php if (!$patient): ?>
            <section class="mt-8 grid gap-5 lg:grid-cols-[1fr_.8fr]">
                <div class="rounded-2xl border border-[#e1d8ca] bg-white p-6 shadow-soft sm:p-8"><div class="flex size-11 items-center justify-center rounded-xl bg-[#e8f0e8] text-teal">↗</div><h2 class="mt-5 text-xl font-bold">Ligue o seu registo clínico</h2><p class="mt-2 text-sm leading-6 text-slate-500">Confirme o número do processo e o telefone registado no hospital para consultar o seu histórico.</p><form method="post" class="mt-6 space-y-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="link-patient"><label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Número do processo<input required name="medical_record_number" placeholder="HM-2026-00142" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"></label><label class="block text-xs font-bold uppercase tracking-wider text-slate-500">Telefone registado<input required name="phone" placeholder="923 441 802" class="mt-2 w-full rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"></label><button class="w-full rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white hover:bg-[#185b58]">Ligar o meu registo</button></form></div>
                <div class="rounded-2xl border border-[#e1d8ca] bg-[#e8f0e8]/60 p-6 sm:p-8"><p class="font-mono-ui text-[10px] uppercase tracking-wider text-teal">Acesso protegido</p><h2 class="mt-4 text-lg font-bold">Os seus dados ficam privados</h2><p class="mt-2 text-sm leading-6 text-slate-500">A sua conta é separada da consola hospitalar e só mostra o registo clínico que confirmar.</p></div>
            </section>
        <?php else: ?>
            <section class="mt-8 grid gap-4 sm:grid-cols-3"><div class="rounded-xl border border-[#e1d8ca] bg-white p-5"><p class="text-xs text-slate-500">Paciente</p><p class="mt-2 font-bold"><?= e($patient['name']) ?></p><p class="mt-1 font-mono-ui text-[10px] text-slate-500"><?= e($patient['medical_record_number']) ?></p></div><div class="rounded-xl border border-[#e1d8ca] bg-white p-5"><p class="text-xs text-slate-500">Consultas</p><p class="mt-2 text-2xl font-bold"><?= count($appointments) ?></p></div><div class="rounded-xl border border-[#e1d8ca] bg-white p-5"><p class="text-xs text-slate-500">Documentos</p><p class="mt-2 font-bold">Em breve</p><p class="mt-1 text-xs text-slate-500">App Storage pendente</p></div></section>
            <section class="mt-6 overflow-hidden rounded-xl border border-[#e1d8ca] bg-white"><div class="border-b border-[#e1d8ca] px-5 py-4"><h2 class="text-sm font-bold">As suas consultas</h2><p class="mt-1 text-xs text-slate-500">Histórico de marcações</p></div><?php if (!$appointments): ?><p class="px-5 py-10 text-center text-sm text-slate-500">Ainda não existem consultas associadas.</p><?php else: ?><?php foreach ($appointments as $appointment): ?><div class="flex flex-col gap-2 border-b border-[#eee7dc] px-5 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-bold"><?= e($appointment['department_name']) ?></p><p class="mt-1 text-xs text-slate-500"><?= e((new DateTimeImmutable($appointment['date']))->format('d/m/Y')) ?> às <?= e($appointment['time']) ?> · <?= e($appointment['doctor_name']) ?></p></div><span class="w-fit rounded-full bg-[#e8f0e8] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-teal"><?= e($appointment['status']) ?></span></div><?php endforeach; ?><?php endif; ?></section>
            <section class="mt-6 overflow-hidden rounded-xl border border-[#e1d8ca] bg-white"><div class="border-b border-[#e1d8ca] px-5 py-4"><h2 class="text-sm font-bold">Falar com a equipa</h2><p class="mt-1 text-xs text-slate-500">Envie uma mensagem segura sobre o seu acompanhamento.</p></div><div class="max-h-80 space-y-3 overflow-y-auto px-5 py-5"><?php if (!$messages): ?><p class="py-6 text-center text-sm text-slate-500">Ainda não há mensagens. Escreva abaixo para iniciar.</p><?php else: ?><?php foreach ($messages as $message): ?><div class="<?= $message['sender_role'] === 'patient' ? 'text-right' : 'text-left' ?>"><div class="<?= $message['sender_role'] === 'patient' ? 'bg-teal text-white' : 'bg-[#e8f0e8] text-ink' ?> inline-block max-w-[85%] rounded-2xl px-4 py-3 text-left text-sm"><p><?= nl2br(e($message['body'])) ?></p><p class="mt-2 text-[10px] opacity-60"><?= e((new DateTimeImmutable($message['created_at']))->format('d/m H:i')) ?></p></div></div><?php endforeach; ?><?php endif; ?></div><form method="post" class="flex gap-2 border-t border-[#e1d8ca] p-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="message"><input name="body" required maxlength="2000" placeholder="Escreva a sua mensagem…" class="min-w-0 flex-1 rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-3 py-3 text-sm outline-none focus:border-teal"><button class="rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white hover:bg-[#185b58]">Enviar</button></form></section>
            <div class="mt-6 grid gap-4 sm:grid-cols-2"><div class="rounded-xl border border-dashed border-[#d7cebf] bg-white/60 p-5"><p class="font-bold">Documentos e receitas</p><p class="mt-2 text-xs leading-5 text-slate-500">A área está preparada para receber receitas e resultados assim que o App Storage estiver disponível.</p></div><div class="rounded-xl border border-dashed border-[#d7cebf] bg-white/60 p-5"><p class="font-bold">Contacto seguro</p><p class="mt-2 text-xs leading-5 text-slate-500">As mensagens ficam associadas ao seu registo clínico.</p></div></div>
        <?php endif; ?>
    </main>
    <?php page_end(); exit;
}

if (in_array($page, ['dashboard', 'appointments', 'patients'], true)) {
    $user = require_staff();
    $today = (new DateTimeImmutable())->format('Y-m-d');
    $stmt = $pdo->query("SELECT a.*, p.name AS patient_name, p.medical_record_number, d.name AS department_name, doc.name AS doctor_name FROM appointments a JOIN patients p ON p.id = a.patient_id JOIN departments d ON d.id = a.department_id JOIN doctors doc ON doc.id = a.doctor_id ORDER BY a.date DESC, a.time ASC");
    $allAppointments = $stmt->fetchAll();
    $stmt = $pdo->query('SELECT * FROM patients ORDER BY created_at DESC');
    $patients = $stmt->fetchAll();
    page_start('Consola operacional', $user);
    ?>
    <div class="flex min-h-screen"><div class="hidden md:block"><?php staff_sidebar($user, $page); ?></div><div class="min-w-0 flex-1"><main class="mx-auto max-w-6xl px-5 py-8 sm:px-8 sm:py-10"><div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-teal">Central de consultas</p><h1 class="mt-2 text-3xl font-bold tracking-[-.04em]"><?= $page === 'dashboard' ? 'Visão geral' : ($page === 'patients' ? 'Pacientes' : 'Consultas') ?></h1></div><a href="<?= url('logout') ?>" class="text-xs font-bold text-slate-500 hover:text-teal">Terminar sessão</a></div>
    <?php if ($page === 'dashboard'): ?><section class="mt-8 grid gap-4 sm:grid-cols-4"><?php $cards = [['Consultas hoje', count(array_filter($allAppointments, fn($a) => $a['date'] === $today)), 'bg-[#e8f0e8]'], ['Em espera', count(array_filter($allAppointments, fn($a) => $a['date'] === $today && $a['status'] === 'waiting')), 'bg-[#f8eadc]'], ['Concluídas', count(array_filter($allAppointments, fn($a) => $a['date'] === $today && $a['status'] === 'completed')), 'bg-[#e5e9f7]'], ['Pacientes', count($patients), 'bg-white']]; foreach ($cards as [$label, $value, $color]): ?><div class="rounded-xl border border-[#e1d8ca] <?= $color ?> p-5"><p class="text-xs text-slate-500"><?= $label ?></p><p class="mt-2 text-3xl font-bold"><?= $value ?></p></div><?php endforeach; ?></section><section class="mt-6 overflow-hidden rounded-xl border border-[#e1d8ca] bg-white"><div class="border-b border-[#e1d8ca] px-5 py-4"><h2 class="text-sm font-bold">Agenda recente</h2></div><?php foreach (array_slice($allAppointments, 0, 8) as $appointment): ?><div class="flex flex-col gap-1 border-b border-[#eee7dc] px-5 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-bold"><?= e($appointment['patient_name']) ?></p><p class="text-xs text-slate-500"><?= e($appointment['department_name']) ?> · <?= e($appointment['doctor_name']) ?></p></div><span class="text-xs font-bold text-teal"><?= e($appointment['date']) ?> · <?= e($appointment['time']) ?></span></div><?php endforeach; ?></section>
    <?php elseif ($page === 'patients'): ?><section class="mt-8 overflow-hidden rounded-xl border border-[#e1d8ca] bg-white"><div class="border-b border-[#e1d8ca] px-5 py-4"><h2 class="text-sm font-bold">Registos de pacientes</h2></div><?php foreach ($patients as $patient): ?><div class="flex items-center justify-between border-b border-[#eee7dc] px-5 py-4 last:border-0"><div><p class="text-sm font-bold"><?= e($patient['name']) ?></p><p class="mt-1 font-mono-ui text-[10px] text-slate-500"><?= e($patient['medical_record_number']) ?> · <?= e($patient['phone']) ?></p></div><span class="text-xs text-slate-500"><?= age_from_date($patient['birth_date']) ?> anos</span></div><?php endforeach; ?></section>
    <?php else: ?><section class="mt-8 overflow-hidden rounded-xl border border-[#e1d8ca] bg-white"><div class="border-b border-[#e1d8ca] px-5 py-4"><h2 class="text-sm font-bold">Agenda operacional</h2></div><?php foreach ($allAppointments as $appointment): ?><div class="flex flex-col gap-3 border-b border-[#eee7dc] px-5 py-4 last:border-0 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-bold"><?= e($appointment['patient_name']) ?></p><p class="mt-1 text-xs text-slate-500"><?= e($appointment['date']) ?> · <?= e($appointment['time']) ?> · <?= e($appointment['doctor_name']) ?></p></div><form method="post" class="flex items-center gap-2"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update-status"><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><select name="status" class="rounded-lg border border-[#d7cebf] bg-[#fffdf8] px-2 py-2 text-xs"><option value="scheduled" <?= $appointment['status'] === 'scheduled' ? 'selected' : '' ?>>Agendada</option><option value="waiting" <?= $appointment['status'] === 'waiting' ? 'selected' : '' ?>>Em espera</option><option value="in_progress" <?= $appointment['status'] === 'in_progress' ? 'selected' : '' ?>>Em atendimento</option><option value="completed" <?= $appointment['status'] === 'completed' ? 'selected' : '' ?>>Concluída</option><option value="cancelled" <?= $appointment['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelada</option></select><button class="rounded-lg bg-teal px-3 py-2 text-xs font-bold text-white">Guardar</button></form></div><?php endforeach; ?></section><?php endif; ?></main></div></div>
    <?php page_end(); exit;
}

if ($page === 'login' || $page === 'register') {
    $isLogin = $page === 'login';
    page_start($isLogin ? 'Entrar' : 'Criar conta');
    ?>
    <main class="noise flex min-h-screen items-center justify-center px-5 py-12"><div class="w-full max-w-md"><a href="<?= url('home') ?>" class="mb-8 flex items-center justify-center gap-3"><img src="/logo.svg" class="size-10 rounded-xl" alt=""><div><p class="text-sm font-bold">Hospital de Malanje</p><p class="font-mono-ui text-[9px] uppercase tracking-wider text-slate-500">Portal de consultas</p></div></a><div class="rounded-2xl border border-[#e1d8ca] bg-white p-6 shadow-soft sm:p-8"><p class="font-mono-ui text-[10px] uppercase tracking-[.18em] text-teal"><?= $isLogin ? 'Acesso seguro' : 'Nova conta' ?></p><h1 class="mt-2 text-2xl font-bold"><?= $isLogin ? 'Bem-vindo de volta' : 'Crie a sua conta' ?></h1><form method="post" class="mt-6 space-y-4"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="<?= $isLogin ? 'login' : 'register' ?>"><?php if (!$isLogin): ?><label class="block text-xs font-bold text-slate-500">Nome completo<input name="name" required class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label><?php endif; ?><label class="block text-xs font-bold text-slate-500">Email<input type="email" name="email" required class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label><label class="block text-xs font-bold text-slate-500">Palavra-passe<input type="password" name="password" required minlength="8" class="mt-2 w-full rounded-lg border border-[#d7cebf] px-3 py-3 text-sm outline-none focus:border-teal"></label><button class="w-full rounded-lg bg-teal px-4 py-3 text-sm font-bold text-white"><?= $isLogin ? 'Entrar no portal' : 'Criar conta' ?></button></form><p class="mt-6 text-center text-xs text-slate-500"><?= $isLogin ? 'Ainda não tem conta?' : 'Já tem uma conta?' ?> <a class="font-bold text-teal hover:underline" href="<?= url($isLogin ? 'register' : 'login') ?>"><?= $isLogin ? 'Criar conta' : 'Entrar' ?></a></p></div></div></main>
    <?php page_end(); exit;
}

page_start('Hospital de Malanje');
?>
<main class="noise min-h-screen">
    <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-6 sm:px-8"><a href="<?= url('home') ?>" class="flex items-center gap-3"><img src="/logo.svg" class="size-10 rounded-xl" alt=""><div><p class="text-sm font-bold">Hospital de Malanje</p><p class="font-mono-ui text-[9px] uppercase tracking-wider text-slate-500">Portal de consultas</p></div></a><a href="<?= url('login') ?>" class="rounded-lg border border-[#d7cebf] bg-white px-4 py-2 text-sm font-bold hover:border-teal hover:text-teal">Entrar</a></header>
    <section class="mx-auto grid max-w-6xl gap-10 px-5 pb-20 pt-16 sm:px-8 lg:grid-cols-[1.1fr_.9fr] lg:items-center"><div><p class="font-mono-ui text-[10px] font-bold uppercase tracking-[.2em] text-teal">Cuidado mais próximo, informação mais clara</p><h1 class="mt-4 max-w-2xl text-4xl font-bold leading-[1.05] tracking-[-.05em] sm:text-6xl">A sua saúde, acompanhada com confiança.</h1><p class="mt-6 max-w-xl text-base leading-7 text-slate-500 sm:text-lg">Consulte marcações, histórico de atendimentos e comunique-se com a equipa do Hospital de Malanje numa área segura.</p><div class="mt-8 flex flex-col gap-3 sm:flex-row"><a href="<?= url('register') ?>" class="rounded-lg bg-teal px-5 py-3 text-center text-sm font-bold text-white hover:bg-[#185b58]">Criar conta →</a><a href="<?= url('login') ?>" class="rounded-lg border border-[#d7cebf] bg-white px-5 py-3 text-center text-sm font-bold hover:border-teal hover:text-teal">Já tenho conta</a></div></div><div class="rounded-3xl border border-[#e1d8ca] bg-white p-6 shadow-soft sm:p-8"><p class="font-mono-ui text-[10px] uppercase tracking-wider text-slate-500">O seu portal</p><h2 class="mt-2 text-xl font-bold">Tudo num só lugar</h2><div class="mt-6 space-y-3"><?php foreach (['Próxima consulta', 'Histórico clínico', 'Mensagens seguras'] as $index => $label): ?><div class="flex items-center gap-3 rounded-xl border border-[#eee7dc] p-4"><span class="font-mono-ui text-[10px] text-teal">0<?= $index + 1 ?></span><span class="text-sm font-bold"><?= $label ?></span><span class="ml-auto text-slate-400">›</span></div><?php endforeach; ?></div><div class="mt-5 rounded-xl bg-[#f5eee3] p-4"><p class="text-xs font-bold">Acesso protegido</p><p class="mt-1 text-xs leading-5 text-slate-500">Apenas você e os profissionais autorizados podem consultar os dados.</p></div></div></section>
    <footer class="mx-auto max-w-6xl border-t border-[#e1d8ca] px-5 py-6 text-xs text-slate-500 sm:px-8">Hospital de Malanje · Central de consultas</footer>
</main>
<?php page_end(); ?>