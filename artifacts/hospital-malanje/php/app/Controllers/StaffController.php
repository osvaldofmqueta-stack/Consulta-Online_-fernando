<?php
declare(strict_types=1);

final class StaffController
{
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

    public function appointments(array $user): never
    {
        View::render('staff/appointments', ['user' => $user, 'appointments' => $this->appointmentsForUser($user)], 'Consultas');
    }

    public function patients(array $user): never
    {
        View::render('staff/patients', ['user' => $user, 'patients' => $this->patientsForUser($user)], 'Pacientes');
    }

    public function updateStatus(array $user): never
    {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? '');
        Appointment::updateStatusForStaff($appointmentId, $status, $user['role'] === 'doctor' && $user['doctor_id'] ? (int) $user['doctor_id'] : null);
        Audit::record((int) $user['id'], 'appointment.status_updated', 'appointment', (string) $appointmentId, $status);
        flash('Estado da consulta atualizado.');
        redirect_to(url('appointments'));
    }

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

    public function reports(array $user): never
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : (new DateTimeImmutable('-30 days'))->format('Y-m-d');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : (new DateTimeImmutable())->format('Y-m-d');
        $db = Database::connection();
        $summaryStmt = $db->prepare('SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE status = \'completed\') AS completed, COUNT(*) FILTER (WHERE status = \'cancelled\') AS cancelled, COUNT(DISTINCT patient_id) AS patients FROM appointments WHERE date BETWEEN ? AND ?');
        $summaryStmt->execute([$from, $to]);
        $byDepartment = $db->prepare('SELECT d.name, COUNT(*) AS total FROM appointments a JOIN departments d ON d.id = a.department_id WHERE a.date BETWEEN ? AND ? GROUP BY d.name ORDER BY total DESC');
        $byDepartment->execute([$from, $to]);
        $byStatus = $db->prepare('SELECT status, COUNT(*) AS total FROM appointments WHERE date BETWEEN ? AND ? GROUP BY status ORDER BY total DESC');
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

    public function settings(array $user): never
    {
        View::render('staff/settings', ['user' => $user, 'settings' => Clinical::settings()], 'Definições');
    }

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

    public function users(array $user): never
    {
        View::render('staff/users', ['user' => $user, 'staffAccounts' => Account::allStaff(), 'doctors' => Appointment::activeDoctors()], 'Utilizadores');
    }

    public function updateUser(array $user): never
    {
        $id = (int) ($_POST['account_id'] ?? 0);
        Account::updateStaff($id, (string) ($_POST['role'] ?? ''), isset($_POST['active']), (int) ($_POST['doctor_id'] ?? 0));
        Audit::record((int) $user['id'], 'user.updated', 'account', (string) $id);
        flash('Utilizador atualizado.');
        redirect_to(url('users'));
    }

    public function createUser(array $user): never
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? '');
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8 || !in_array($role, ['admin', 'doctor', 'receptionist'], true)) {
            flash('Preencha nome, email, papel e uma palavra-passe com pelo menos 8 caracteres.');
            redirect_to(url('users'));
        }
        try {
            $id = Account::createStaff($name, $email, $password, $role, $doctorId ?: null);
            Audit::record((int) $user['id'], 'user.created', 'account', (string) $id);
            flash('Utilizador criado.');
        } catch (PDOException) {
            flash('Este email já está registado.');
        }
        redirect_to(url('users'));
    }

    public function directory(array $user): never
    {
        View::render('staff/directory', [
            'user' => $user,
            'departments' => HospitalDirectory::departments(),
            'doctors' => HospitalDirectory::doctors(),
        ], 'Médicos e departamentos');
    }

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

    private function appointmentsForUser(array $user): array
    {
        if ($user['role'] !== 'doctor') {
            return Appointment::all();
        }
        return $user['doctor_id'] ? Appointment::forDoctor((int) $user['doctor_id']) : [];
    }

    private function patientsForUser(array $user): array
    {
        if ($user['role'] !== 'doctor') {
            return Patient::all();
        }
        return $user['doctor_id'] ? Patient::forDoctor((int) $user['doctor_id']) : [];
    }

    private function doctorCanAccessPatient(array $user, int $patientId): bool
    {
        return $user['role'] !== 'doctor'
            || ($user['doctor_id'] && Appointment::patientHasDoctor($patientId, (int) $user['doctor_id']));
    }
}