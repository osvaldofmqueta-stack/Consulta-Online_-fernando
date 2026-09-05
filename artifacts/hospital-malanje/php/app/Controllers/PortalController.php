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