<?php
/**
 * Operações da equipa hospitalar.
 *
 * Este controlador aplica a autorização de alto nível e prepara os dados
 * necessários para dashboards, agenda, pacientes, ficha clínica e gestão.
 * As consultas SQL especializadas permanecem nos modelos.
 */
declare(strict_types=1);

final class StaffController
{
    /** Monta o resumo operacional adequado ao papel autenticado. */
    public function dashboard(array $user): never
    {
        $appointments = $this->appointmentsForUser($user);
        $patients = $this->patientsForUser($user);
        $today = (new DateTimeImmutable())->format('Y-m-d');
        View::render('staff/index', [
            'user' => $user,
            'appointments' => $appointments,
            'patients' => $patients,
            'today' => $today,
        ], 'Consola operacional');
    }

    /** Mostra agenda, filtros e fila de atendimento da equipa. */
    public function appointments(array $user): never
    {
        $filters = [
            'date' => $this->dateFilter($_GET['date'] ?? ''),
            'from' => $this->dateFilter($_GET['from'] ?? ''),
            'to' => $this->dateFilter($_GET['to'] ?? ''),
            'department_id' => (int) ($_GET['department_id'] ?? 0),
            'doctor_id' => (int) ($_GET['doctor_id'] ?? 0),
            'status' => (string) ($_GET['status'] ?? ''),
            'search' => trim((string) ($_GET['search'] ?? '')),
        ];
        $doctorScope = $this->doctorScope($user);
        if ($doctorScope !== null) {
            $filters['doctor_id'] = $doctorScope;
        }
        $appointments = Appointment::forStaff($filters, $doctorScope);
        $queue = array_values(array_filter(
            $appointments,
            static fn (array $appointment): bool => in_array($appointment['status'], ['waiting', 'in_progress'], true)
        ));
        $doctors = Appointment::activeDoctors();
        if ($doctorScope !== null) {
            $doctors = array_values(array_filter($doctors, static fn (array $doctor): bool => (int) $doctor['id'] === $doctorScope));
        }
        View::render('staff/appointments', [
            'user' => $user,
            'appointments' => $appointments,
            'queue' => $queue,
            'filters' => $filters,
            'patients' => $this->patientsForUser($user),
            'departments' => Appointment::activeDepartments(),
            'doctors' => $doctors,
            'availableTimes' => Appointment::availableTimes(),
        ], 'Consultas');
    }

    /** Valida e cria uma consulta interna já confirmada. */
    public function createAppointment(array $user): never
    {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        $type = (string) ($_POST['type'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $appointmentAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
        $doctorScope = $this->doctorScope($user);

        if (
            !$patientId
            || !Patient::find($patientId)
            || !$departmentId
            || !$doctorId
            || ($doctorScope !== null && $doctorId !== $doctorScope)
            || ($doctorScope !== null && !Appointment::patientHasDoctor($patientId, $doctorScope))
            || !$dateValue
            || $dateValue->format('Y-m-d') !== $date
            || !$appointmentAt
            || $appointmentAt <= new DateTimeImmutable()
            || !in_array($time, Appointment::availableTimes(), true)
            || !in_array($type, ['first_visit', 'follow_up'], true)
            || mb_strlen($notes) > 500
            || !Appointment::doctorBelongsToDepartment($doctorId, $departmentId)
        ) {
            flash('Verifique o paciente, profissional, data, horário e departamento.');
            redirect_to(url('appointments'));
        }
        if (!Appointment::slotIsAvailable($doctorId, $date, $time, $patientId)) {
            flash('Esse horário já está ocupado para o médico ou paciente.');
            redirect_to(url('appointments'));
        }

        Appointment::createForPatient($patientId, $departmentId, $doctorId, $date, $time, $type, $notes, 'scheduled');
        Audit::record((int) $user['id'], 'appointment.created_by_staff', 'appointment', null, $date . ' ' . $time);
        flash('Consulta criada e confirmada na agenda.');
        redirect_to(url('appointments'));
    }

    /** Reagenda uma consulta futura sem quebrar a associação do médico. */
    public function rescheduleAppointment(array $user): never
    {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $appointmentAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
        $doctorScope = $this->doctorScope($user);
        if (!$appointmentId || !$dateValue || $dateValue->format('Y-m-d') !== $date || !$appointmentAt || $appointmentAt <= new DateTimeImmutable() || !in_array($time, Appointment::availableTimes(), true)) {
            flash('Escolha uma data e horário futuros válidos.');
            redirect_to(url('appointments'));
        }
        if (!Appointment::rescheduleForStaff($appointmentId, $date, $time, $doctorScope)) {
            flash('Não foi possível reagendar. Confirme se a consulta está activa e o horário está livre.');
            redirect_to(url('appointments'));
        }
        Audit::record((int) $user['id'], 'appointment.rescheduled', 'appointment', (string) $appointmentId, $date . ' ' . $time);
        flash('Consulta reagendada.');
        redirect_to(url('appointments'));
    }

    /** Cancela uma consulta a partir da agenda da equipa. */
    public function cancelAppointment(array $user): never
    {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $doctorScope = $this->doctorScope($user);
        if ($appointmentId && Appointment::updateStatusForStaff($appointmentId, 'cancelled', $doctorScope)) {
            Audit::record((int) $user['id'], 'appointment.cancelled_by_staff', 'appointment', (string) $appointmentId);
            flash('Consulta cancelada.');
        } else {
            flash('Não foi possível cancelar esta consulta.');
        }
        redirect_to(url('appointments'));
    }

    /** Mostra pacientes filtrados pelo papel e pelo médico autenticado. */
    public function patients(array $user): never
    {
        $filters = [
            'search' => trim((string) ($_GET['search'] ?? '')),
            'status' => in_array($_GET['status'] ?? '', ['active', 'inactive'], true) ? (string) $_GET['status'] : '',
        ];
        $doctorScope = $this->doctorScope($user);
        View::render('staff/patients', [
            'user' => $user,
            'patients' => Patient::forStaff($filters, $doctorScope),
            'filters' => $filters,
            'canManagePatients' => in_array($user['role'], ['admin', 'receptionist'], true),
        ], 'Pacientes');
    }

    /** Cria um registo clínico depois de validar e evitar duplicados. */
    public function createPatient(array $user): never
    {
        if (!in_array($user['role'], ['admin', 'receptionist'], true)) {
            flash('Apenas a recepção e a administração podem criar pacientes.');
            redirect_to(url('patients'));
        }
        [$name, $sex, $birthDate, $phone, $neighborhood, $patientType] = $this->patientFormValues();
        if (!$this->validPatientValues($name, $sex, $birthDate, $phone, $neighborhood, $patientType)) {
            flash('Preencha correctamente o nome, sexo, data de nascimento e telefone.');
            redirect_to(url('patients'));
        }
        if (Patient::findSimilar($name, $birthDate, $phone)) {
            flash('Já existe um paciente com os mesmos dados principais.');
            redirect_to(url('patients'));
        }
        $patientId = Patient::create($name, $sex, $birthDate, $phone, $neighborhood, $patientType);
        Audit::record((int) $user['id'], 'patient.created', 'patient', (string) $patientId, $name);
        flash('Paciente criado. O número de processo foi atribuído automaticamente.');
        redirect_to(url('patient', ['id' => $patientId]));
    }

    /** Actualiza dados demográficos e classificação clínica do paciente. */
    public function updatePatient(array $user): never
    {
        if (!in_array($user['role'], ['admin', 'receptionist'], true)) {
            flash('Não tem permissão para editar pacientes.');
            redirect_to(url('patients'));
        }
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        [$name, $sex, $birthDate, $phone, $neighborhood, $patientType] = $this->patientFormValues();
        if (!$patientId || !Patient::find($patientId) || !$this->validPatientValues($name, $sex, $birthDate, $phone, $neighborhood, $patientType)) {
            flash('Os dados do paciente não são válidos.');
            redirect_to(url('patients'));
        }
        Patient::update($patientId, $name, $sex, $birthDate, $phone, $neighborhood, $patientType);
        Audit::record((int) $user['id'], 'patient.updated', 'patient', (string) $patientId);
        flash('Dados do paciente actualizados.');
        redirect_to(url('patient', ['id' => $patientId]));
    }

    /** Activa ou desactiva um paciente sem apagar o histórico. */
    public function togglePatient(array $user): never
    {
        if (!in_array($user['role'], ['admin', 'receptionist'], true)) {
            flash('Não tem permissão para alterar o estado de pacientes.');
            redirect_to(url('patients'));
        }
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $active = ($_POST['active'] ?? '0') === '1';
        if ($patientId && Patient::setActive($patientId, $active)) {
            Audit::record((int) $user['id'], $active ? 'patient.activated' : 'patient.deactivated', 'patient', (string) $patientId);
            flash($active ? 'Paciente activado.' : 'Paciente desactivado.');
        } else {
            flash('Não foi possível alterar o estado do paciente.');
        }
        redirect_to(url('patients'));
    }

    /** Actualiza o estado da consulta e regista a operação na auditoria. */
    public function updateStatus(array $user): never
    {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        Appointment::updateStatusForStaff($appointmentId, $status, $this->doctorScope($user));
        Audit::record((int) $user['id'], 'appointment.status_updated', 'appointment', (string) $appointmentId, $status);
        flash('Estado da consulta atualizado.');
        redirect_to(url('appointments'));
    }

    /** Abre a ficha clínica depois de verificar o acesso ao paciente. */
    public function patient(array $user): never
    {
        $patientId = (int) ($_GET['id'] ?? 0);
        $patient = Patient::find($patientId);
        if (!$patient) {
            flash('Paciente não encontrado.');
            redirect_to(url('patients'));
        }
        if ($user['role'] === 'doctor' && (!$user['doctor_id'] || !Appointment::patientHasDoctor($patientId, (int) $user['doctor_id']))) {
            flash('Este paciente não está associado à sua agenda.');
            redirect_to(url('patients'));
        }
        View::render('staff/patient', [
            'user' => $user,
            'patient' => $patient,
            'appointments' => Appointment::forPatient($patientId),
            'messages' => Message::forPatient($patientId),
            'documents' => Clinical::documentsForPatient($patientId),
            'prescriptions' => Clinical::prescriptionsForPatient($patientId),
            'results' => Clinical::resultsForPatient($patientId),
            'doctors' => Appointment::activeDoctors(),
        ], 'Ficha do paciente');
    }

    /** Renderiza a versão de impressão e audita a exportação da ficha. */
    public function printPatient(array $user): never
    {
        $patientId = (int) ($_GET['id'] ?? 0);
        $patient = Patient::find($patientId);
        if (!$patient) {
            flash('Paciente não encontrado.');
            redirect_to(url('patients'));
        }
        if ($user['role'] === 'doctor' && (!$user['doctor_id'] || !Appointment::patientHasDoctor($patientId, (int) $user['doctor_id']))) {
            flash('Este paciente não está associado à sua agenda.');
            redirect_to(url('patients'));
        }
        $clinicalAccess = can_access($user, 'clinical');
        Audit::record((int) $user['id'], 'patient_record.printed', 'patient', (string) $patientId);
        View::render('staff/patient-print', [
            'user' => $user,
            'patient' => $patient,
            'appointments' => Appointment::forPatient($patientId),
            'documents' => $clinicalAccess ? Clinical::documentsForPatient($patientId) : [],
            'prescriptions' => $clinicalAccess ? Clinical::prescriptionsForPatient($patientId) : [],
            'results' => $clinicalAccess ? Clinical::resultsForPatient($patientId) : [],
            'clinicalAccess' => $clinicalAccess,
        ], 'Ficha clínica · ' . $patient['name']);
    }

    /** Carrega a caixa geral ou a caixa limitada do médico. */
    public function messages(array $user): never
    {
        $messages = $user['role'] === 'doctor'
            ? ($user['doctor_id'] ? Message::inboxForDoctor((int) $user['doctor_id']) : [])
            : Message::inbox();
        View::render('staff/messages', [
            'user' => $user,
            'messages' => $messages,
            'unreadCount' => Message::unreadCount(),
        ], 'Mensagens');
    }

    /** Guarda uma resposta da equipa na conversa do paciente. */
    public function replyMessage(array $user): never
    {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        $body = trim((string) ($_POST['body'] ?? ''));
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        if ($patientId && mb_strlen($body) > 0 && mb_strlen($body) <= 2000) {
            Message::createForStaff($patientId, $doctorId ?: null, (int) $user['id'], (string) $user['role'], $body);
            Audit::record((int) $user['id'], 'message.sent', 'patient', (string) $patientId);
            flash('Resposta enviada ao paciente.');
        } else {
            flash('Escreva uma resposta válida.');
        }
        redirect_to(url('messages'));
    }

    /** Valida tipo/tamanho e guarda um documento clínico privado. */
    public function uploadDocument(array $user): never
    {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        if (!$this->doctorCanAccessPatient($user, $patientId)) {
            flash('Não tem acesso a este paciente.');
            redirect_to(url('patients'));
        }
        $title = trim((string) ($_POST['title'] ?? ''));
        $category = (string) ($_POST['category'] ?? 'document');
        $file = $_FILES['file'] ?? null;
        $allowedCategories = ['document', 'prescription', 'result'];
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (
            !$patientId
            || !$title
            || mb_strlen($title) > 160
            || !in_array($category, $allowedCategories, true)
            || !$file
            || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || (int) ($file['size'] ?? 0) > 10 * 1024 * 1024
        ) {
            flash('Indique o título e um ficheiro válido até 10 MB.');
            redirect_to(url('patient', ['id' => $patientId]) . '#documentos');
        }
        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mimeType, $allowedTypes, true)) {
            flash('Formato não suportado. Use PDF, JPG, PNG ou WEBP.');
            redirect_to(url('patient', ['id' => $patientId]) . '#documentos');
        }
        $content = file_get_contents((string) $file['tmp_name']);
        if ($content === false) {
            flash('Não foi possível ler o ficheiro.');
            redirect_to(url('patient', ['id' => $patientId]) . '#documentos');
        }
        Clinical::saveDocument(
            $patientId,
            (int) $user['id'],
            $title,
            $category,
            basename((string) $file['name']),
            $mimeType,
            (int) $file['size'],
            $content
        );
        Audit::record((int) $user['id'], 'document.uploaded', 'patient', (string) $patientId, $title);
        flash('Documento guardado na ficha do paciente.');
        redirect_to(url('patient', ['id' => $patientId]) . '#documentos');
    }

    /** Regista uma receita associada ao paciente autorizado. */
    public function createPrescription(array $user): never
    {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        if (!$this->doctorCanAccessPatient($user, $patientId)) {
            flash('Não tem acesso a este paciente.');
            redirect_to(url('patients'));
        }
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $medication = trim((string) ($_POST['medication'] ?? ''));
        $dosage = trim((string) ($_POST['dosage'] ?? ''));
        $frequency = trim((string) ($_POST['frequency'] ?? ''));
        $duration = trim((string) ($_POST['duration'] ?? ''));
        $instructions = trim((string) ($_POST['instructions'] ?? ''));
        if (!$patientId || !$medication || !$dosage || !$frequency || !$duration || mb_strlen($instructions) > 1000) {
            flash('Preencha todos os campos obrigatórios da receita.');
            redirect_to(url('patient', ['id' => $patientId]) . '#receitas');
        }
        Clinical::createPrescription($patientId, $doctorId, $appointmentId, $medication, $dosage, $frequency, $duration, $instructions);
        Audit::record((int) $user['id'], 'prescription.created', 'patient', (string) $patientId, $medication);
        flash('Receita emitida e disponível na área do paciente.');
        redirect_to(url('patient', ['id' => $patientId]) . '#receitas');
    }

    /** Publica um resultado clínico para o paciente autorizado. */
    public function createResult(array $user): never
    {
        $patientId = (int) ($_POST['patient_id'] ?? 0);
        if (!$this->doctorCanAccessPatient($user, $patientId)) {
            flash('Não tem acesso a este paciente.');
            redirect_to(url('patients'));
        }
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $resultText = trim((string) ($_POST['result_text'] ?? ''));
        if (!$patientId || !$title || !$resultText || mb_strlen($title) > 160 || mb_strlen($resultText) > 5000) {
            flash('Preencha o título e o resultado.');
            redirect_to(url('patient', ['id' => $patientId]) . '#resultados');
        }
        Clinical::createResult($patientId, $doctorId, $appointmentId, $title, $resultText);
        Audit::record((int) $user['id'], 'result.created', 'patient', (string) $patientId, $title);
        flash('Resultado publicado na área do paciente.');
        redirect_to(url('patient', ['id' => $patientId]) . '#resultados');
    }

    /** Calcula indicadores agregados de consultas por período. */
    public function reports(array $user): never
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : (new DateTimeImmutable('-30 days'))->format('Y-m-d');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : (new DateTimeImmutable())->format('Y-m-d');
        $db = Database::connection();
        $summaryStmt = $db->prepare('SELECT COUNT(*) AS total, SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN status = \'cancelled\' THEN 1 ELSE 0 END) AS cancelled, COUNT(DISTINCT patient_id) AS patients FROM consultas WHERE date BETWEEN ? AND ?');
        $summaryStmt->execute([$from, $to]);
        $byDepartment = $db->prepare('SELECT d.name, COUNT(*) AS total FROM consultas a JOIN departamentos d ON d.id = a.department_id WHERE a.date BETWEEN ? AND ? GROUP BY d.name ORDER BY total DESC');
        $byDepartment->execute([$from, $to]);
        $byStatus = $db->prepare('SELECT status, COUNT(*) AS total FROM consultas WHERE date BETWEEN ? AND ? GROUP BY status ORDER BY total DESC');
        $byStatus->execute([$from, $to]);
        View::render('staff/reports', [
            'user' => $user,
            'from' => $from,
            'to' => $to,
            'summary' => $summaryStmt->fetch() ?: ['total' => 0, 'completed' => 0, 'cancelled' => 0, 'patients' => 0],
            'byDepartment' => $byDepartment->fetchAll(),
            'byStatus' => $byStatus->fetchAll(),
        ], 'Relatórios');
    }

    /** Mostra definições hospitalares disponíveis ao administrador. */
    public function settings(array $user): never
    {
        View::render('staff/settings', ['user' => $user, 'settings' => Clinical::settings()], 'Definições');
    }

    /** Valida e guarda definições gerais do hospital. */
    public function saveSettings(array $user): never
    {
        foreach (['hospital_name', 'appointment_notice', 'working_hours'] as $key) {
            $value = trim((string) ($_POST[$key] ?? ''));
            if ($value !== '' && mb_strlen($value) <= 500) {
                Clinical::saveSetting($key, $value);
            }
        }
        Audit::record((int) $user['id'], 'settings.updated');
        flash('Definições guardadas.');
        redirect_to(url('settings'));
    }

    /** Lista contas internas e médicos disponíveis para associação. */
    public function users(array $user): never
    {
        View::render('staff/users', ['user' => $user, 'staffAccounts' => Account::allStaff(), 'doctors' => Appointment::activeDoctors()], 'Utilizadores');
    }

    /** Actualiza papel, estado e associação clínica de um utilizador. */
    public function updateUser(array $user): never
    {
        $id = (int) ($_POST['account_id'] ?? 0);
        $role = (string) ($_POST['role'] ?? '');
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        if ($role === 'doctor' && (!$doctorId || !Appointment::activeDoctorExists($doctorId))) {
            flash('Associe a conta ao médico correspondente antes de guardar.');
            redirect_to(url('users'));
        }
        if (!Account::updateStaff($id, $role, isset($_POST['active']), $doctorId)) {
            flash('Não foi possível actualizar este utilizador.');
            redirect_to(url('users'));
        }
        Audit::record((int) $user['id'], 'user.updated', 'account', (string) $id);
        flash('Utilizador atualizado.');
        redirect_to(url('users'));
    }

    /** Cria uma conta interna a partir do painel administrativo. */
    public function createUser(array $user): never
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? '');
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        if (
            mb_strlen($name) < 2
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($password) < 8
            || !in_array($role, ['admin', 'doctor', 'receptionist'], true)
            || ($role === 'doctor' && (!$doctorId || !Appointment::activeDoctorExists($doctorId)))
        ) {
            if ($role === 'doctor' && (!$doctorId || !Appointment::activeDoctorExists($doctorId))) {
                flash('Associe o novo utilizador ao médico correspondente.');
                redirect_to(url('users'));
            }
            flash('Preencha nome, email, papel e uma palavra-passe com pelo menos 8 caracteres.');
            redirect_to(url('users'));
        }
        try {
            $id = Account::createStaff($name, $email, $password, $role, $doctorId ?: null);
            Audit::record((int) $user['id'], 'user.created', 'account', (string) $id);
            flash('Utilizador criado.');
        } catch (PDOException | InvalidArgumentException $error) {
            flash($error instanceof InvalidArgumentException ? 'Associe o novo utilizador a um médico activo.' : 'Este email já está registado.');
        }
        redirect_to(url('users'));
    }

    /** Mostra departamentos e médicos configurados no hospital. */
    public function directory(array $user): never
    {
        View::render('staff/directory', [
            'user' => $user,
            'departments' => HospitalDirectory::departments(),
            'doctors' => HospitalDirectory::doctors(),
        ], 'Médicos e departamentos');
    }

    /** Cria um novo departamento activo. */
    public function createDepartment(array $user): never
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $color = trim((string) ($_POST['color'] ?? ''));
        if (!$name || mb_strlen($name) > 120) {
            flash('Indique o nome do departamento.');
            redirect_to(url('directory'));
        }
        HospitalDirectory::createDepartment($name, $color);
        Audit::record((int) $user['id'], 'department.created', 'department', null, $name);
        flash('Departamento criado.');
        redirect_to(url('directory'));
    }

    /** Actualiza um departamento sem remover consultas antigas. */
    public function updateDepartment(array $user): never
    {
        $id = (int) ($_POST['department_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($id && $name) {
            HospitalDirectory::updateDepartment($id, $name, isset($_POST['active']));
            Audit::record((int) $user['id'], 'department.updated', 'department', (string) $id);
            flash('Departamento actualizado.');
        }
        redirect_to(url('directory'));
    }

    /** Cria um médico associado a um departamento existente. */
    public function createDoctor(array $user): never
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $specialty = trim((string) ($_POST['specialty'] ?? ''));
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        if (!$name || !$specialty || !$departmentId) {
            flash('Preencha nome, especialidade e departamento.');
            redirect_to(url('directory'));
        }
        HospitalDirectory::createDoctor($name, $specialty, $departmentId);
        Audit::record((int) $user['id'], 'doctor.created', 'doctor', null, $name);
        flash('Médico criado.');
        redirect_to(url('directory'));
    }

    /** Actualiza perfil, departamento e estado de um médico. */
    public function updateDoctor(array $user): never
    {
        $id = (int) ($_POST['doctor_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $specialty = trim((string) ($_POST['specialty'] ?? ''));
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        if ($id && $name && $specialty && $departmentId) {
            HospitalDirectory::updateDoctor($id, $name, $specialty, $departmentId, isset($_POST['active']));
            Audit::record((int) $user['id'], 'doctor.updated', 'doctor', (string) $id);
            flash('Médico actualizado.');
        }
        redirect_to(url('directory'));
    }

    /** Aplica isolamento de agenda quando a conta pertence a um médico. */
    private function appointmentsForUser(array $user): array
    {
        if ($user['role'] !== 'doctor') {
            return Appointment::all();
        }
        return $user['doctor_id'] ? Appointment::forDoctor((int) $user['doctor_id']) : [];
    }

    /** Devolve o médico limitador ou null para recepção/administração. */
    private function doctorScope(array $user): ?int
    {
        return $user['role'] === 'doctor' ? (int) ($user['doctor_id'] ?? 0) : null;
    }

    /** Aceita apenas datas ISO para evitar filtros inválidos. */
    private function dateFilter(mixed $value): string
    {
        $value = (string) $value;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /** Normaliza os campos enviados pelos formulários de paciente. */
    private function patientFormValues(): array
    {
        return [
            trim((string) ($_POST['name'] ?? '')),
            trim((string) ($_POST['sex'] ?? '')),
            trim((string) ($_POST['birth_date'] ?? '')),
            trim((string) ($_POST['phone'] ?? '')),
            trim((string) ($_POST['neighborhood'] ?? '')),
            trim((string) ($_POST['patient_type'] ?? 'general')),
        ];
    }

    /** Valida formato, limites e enums dos dados do paciente. */
    private function validPatientValues(string $name, string $sex, string $birthDate, string $phone, string $neighborhood, string $patientType = 'general'): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
        return mb_strlen($name) >= 2
            && mb_strlen($name) <= 190
            && in_array($sex, ['female', 'male', 'other', 'not_informed'], true)
            && $date instanceof DateTimeImmutable
            && $date->format('Y-m-d') === $birthDate
            && $date <= new DateTimeImmutable('today')
            && mb_strlen($phone) >= 5
            && mb_strlen($phone) <= 40
            && mb_strlen($neighborhood) <= 160
            && in_array($patientType, Patient::TYPES, true);
    }

    /** Devolve pacientes completos para a equipa ou limitados ao médico. */
    private function patientsForUser(array $user): array
    {
        if ($user['role'] !== 'doctor') {
            return Patient::all();
        }
        return $user['doctor_id'] ? Patient::forDoctor((int) $user['doctor_id']) : [];
    }

    /** Centraliza a verificação de acesso clínico do médico ao paciente. */
    private function doctorCanAccessPatient(array $user, int $patientId): bool
    {
        return $user['role'] !== 'doctor'
            || ($user['doctor_id'] && Appointment::patientHasDoctor($patientId, (int) $user['doctor_id']));
    }
}