<?php
declare(strict_types=1);

final class StaffController
{
    public function dashboard(array $user): never
    {
        $appointments = Appointment::all();
        $patients = Patient::all();
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
        View::render('staff/appointments', ['user' => $user, 'appointments' => Appointment::all()], 'Consultas');
    }

    public function patients(array $user): never
    {
        View::render('staff/patients', ['user' => $user, 'patients' => Patient::all()], 'Pacientes');
    }

    public function updateStatus(array $user): never
    {
        Appointment::updateStatus((int) ($_POST['appointment_id'] ?? 0), (string) ($_POST['status'] ?? ''));
        flash('Estado da consulta atualizado.');
        redirect_to(url('appointments'));
    }
}