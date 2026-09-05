<?php
declare(strict_types=1);

final class PortalController
{
    public function index(array $user): never
    {
        $patient = $user['patient_id'] ? Patient::find((int) $user['patient_id']) : null;
        $appointments = $patient ? Appointment::forPatient((int) $patient['id']) : [];
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $upcoming = array_values(array_filter(
            $appointments,
            static fn (array $appointment): bool =>
                $appointment['date'] >= $today
                && !in_array($appointment['status'], ['completed', 'cancelled'], true)
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

        if (
            !$departmentId
            || !$doctorId
            || !$dateValue
            || $dateValue->format('Y-m-d') !== $date
            || $dateValue < new DateTimeImmutable('today')
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
            $notes
        );
        flash('Pedido de consulta enviado. A equipa irá confirmar a marcação.');
        redirect_to(url('portal') . '#consultas');
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
}