<?php
declare(strict_types=1);

final class PortalController
{
    public function index(array $user): never
    {
        $patient = $user['patient_id'] ? Patient::find((int) $user['patient_id']) : null;
        $appointments = $patient ? Appointment::forPatient((int) $patient['id']) : [];
        $now = new DateTimeImmutable();
        $upcoming = array_values(array_filter(
            $appointments,
            static function (array $appointment) use ($now): bool {
                $appointmentAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $appointment['date'] . ' ' . $appointment['time']);
                return $appointmentAt
                    && $appointmentAt >= $now
                    && !in_array($appointment['status'], ['completed', 'cancelled'], true);
            }
        ));
        usort($upcoming, static function (array $left, array $right): int {
            return [$left['date'], $left['time']] <=> [$right['date'], $right['time']];
        });

        View::render('portal/index', [
            'user' => $user,
            'patient' => $patient,
            'appointments' => $appointments,
            'nextAppointment' => $upcoming[0] ?? null,
            'completedAppointments' => count(array_filter(
                $appointments,
                static fn (array $appointment): bool => $appointment['status'] === 'completed'
            )),
            'messages' => $patient ? Message::forPatient((int) $patient['id']) : [],
            'documents' => $patient ? Clinical::documentsForPatient((int) $patient['id']) : [],
            'prescriptions' => $patient ? Clinical::prescriptionsForPatient((int) $patient['id']) : [],
            'results' => $patient ? Clinical::resultsForPatient((int) $patient['id']) : [],
            'settings' => Clinical::settings(),
            'departments' => $patient ? Appointment::activeDepartments() : [],
            'doctors' => $patient ? Appointment::activeDoctors() : [],
            'availableTimes' => Appointment::availableTimes(),
        ], 'Portal do paciente');
    }

    public function linkPatient(array $user): never
    {
        $patient = Patient::findByRecordAndPhone(
            (string) ($_POST['medical_record_number'] ?? ''),
            (string) ($_POST['phone'] ?? '')
        );
        if (!$patient) {
            flash('Não encontrámos um registo com esses dados.');
        } else {
            Account::linkPatient((int) $user['id'], (int) $patient['id']);
            flash('Registo clínico ligado à sua conta.');
        }
        redirect_to(url('portal'));
    }

    public function bookAppointment(array $user): never
    {
        if (!$user['patient_id']) {
            flash('Ligue primeiro o seu registo clínico para marcar uma consulta.');
            redirect_to(url('portal'));
        }

        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        $type = (string) ($_POST['type'] ?? '');
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $dateValue = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $appointmentAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time);
        $now = new DateTimeImmutable();

        if (
            !$departmentId
            || !$doctorId
            || !$dateValue
            || $dateValue->format('Y-m-d') !== $date
            || !$appointmentAt
            || $appointmentAt <= $now
            || !in_array($time, Appointment::availableTimes(), true)
            || !in_array($type, ['first_visit', 'follow_up'], true)
            || mb_strlen($notes) > 500
        ) {
            flash('Verifique a data, o horário e os dados da consulta.');
            redirect_to(url('portal') . '#marcar-consulta');
        }

        if (!Appointment::doctorBelongsToDepartment($doctorId, $departmentId)) {
            flash('O médico seleccionado não pertence a esse departamento.');
            redirect_to(url('portal') . '#marcar-consulta');
        }

        if (!Appointment::slotIsAvailable($doctorId, $date, $time, (int) $user['patient_id'])) {
            flash('Esse horário já não está disponível. Escolha outro horário.');
            redirect_to(url('portal') . '#marcar-consulta');
        }

        Appointment::createForPatient(
            (int) $user['patient_id'],
            $departmentId,
            $doctorId,
            $date,
            $time,
            $type,
            $notes,
            'pending'
        );
        flash('Pedido enviado. A equipa irá confirmar a sua consulta.');
        redirect_to(url('portal') . '#consultas');
    }

    public function cancelAppointment(array $user): never
    {
        $appointmentId = (int) ($_POST['appointment_id'] ?? 0);
        if ($user['patient_id'] && Appointment::cancelForPatient($appointmentId, (int) $user['patient_id'])) {
            Audit::record((int) $user['id'], 'appointment.cancelled', 'appointment', (string) $appointmentId);
            flash('Pedido de consulta cancelado.');
        } else {
            flash('Esta consulta já não pode ser cancelada.');
        }
        redirect_to(url('portal') . '#consultas');
    }

    public function updateProfile(array $user): never
    {
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $neighborhood = trim((string) ($_POST['neighborhood'] ?? ''));
        if (!$user['patient_id'] || !$phone || mb_strlen($phone) > 40 || mb_strlen($neighborhood) > 120) {
            flash('Indique um telefone válido.');
            redirect_to(url('portal') . '#perfil');
        }
        Patient::updateContact((int) $user['patient_id'], $phone, $neighborhood);
        Audit::record((int) $user['id'], 'patient.contact_updated', 'patient', (string) $user['patient_id']);
        flash('Os seus dados foram actualizados.');
        redirect_to(url('portal') . '#perfil');
    }

    public function changePassword(array $user): never
    {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
        if (mb_strlen($new) < 8 || $new !== $confirmation || !Account::changePassword((int) $user['id'], $current, $new)) {
            flash('Não foi possível actualizar a palavra-passe. Confirme a actual e use pelo menos 8 caracteres.');
            redirect_to(url('portal') . '#perfil');
        }
        flash('A sua palavra-passe foi actualizada.');
        redirect_to(url('portal') . '#perfil');
    }

    public function sendMessage(array $user): never
    {
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($user['patient_id'] && mb_strlen($body) > 0 && mb_strlen($body) <= 2000) {
            Message::createForPatient(
                (int) $user['patient_id'],
                Appointment::latestDoctorForPatient((int) $user['patient_id']),
                (int) $user['id'],
                $body
            );
            flash('Mensagem enviada à equipa.');
        }
        redirect_to(url('portal'));
    }

    public function downloadDocument(array $user): never
    {
        $documentId = (int) ($_GET['id'] ?? 0);
        $document = null;
        if ($user['role'] === 'patient' && $user['patient_id']) {
            $document = Clinical::documentForPatient($documentId, (int) $user['patient_id']);
        } elseif (can_access($user, 'patients')) {
            $document = Clinical::documentForStaff($documentId);
        }
        if (!$document) {
            http_response_code(404);
            exit('Documento não encontrado.');
        }
        Audit::record((int) $user['id'], 'document.downloaded', 'patient_document', (string) $document['id']);
        header('Content-Type: ' . $document['mime_type']);
        header('Content-Length: ' . $document['file_size']);
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $document['file_name']) . '"');
        echo $document['file_content'];
        exit;
    }
}