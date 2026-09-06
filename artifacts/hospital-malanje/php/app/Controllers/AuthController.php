<?php
/**
 * Fluxos de autenticação, registo público e recuperação local.
 *
 * O registo público cria apenas pacientes; contas internas são criadas
 * exclusivamente por um administrador.
 */
declare(strict_types=1);

final class AuthController
{
    /** Mostra o formulário de entrada. */
    public function showLogin(): never
    {
        View::render('auth/form', ['mode' => 'login'], 'Entrar');
    }

    /** Mostra o formulário de registo de paciente. */
    public function showRegister(): never
    {
        View::render('auth/form', ['mode' => 'register'], 'Criar conta');
    }

    /** Mostra o pedido de recuperação e, quando existe, o link local. */
    public function showForgotPassword(): never
    {
        $localResetUrl = $_SESSION['local_reset_url'] ?? null;
        unset($_SESSION['local_reset_url']);
        View::render('auth/form', ['mode' => 'forgot', 'localResetUrl' => $localResetUrl], 'Recuperar acesso');
    }

    /** Mostra o formulário que recebe o token de recuperação. */
    public function showResetPassword(): never
    {
        View::render('auth/form', ['mode' => 'reset', 'token' => (string) ($_GET['token'] ?? '')], 'Definir nova palavra-passe');
    }

    /** Valida o registo, cria a conta e tenta ligar o processo clínico. */
    public function register(): never
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $medicalRecordNumber = trim((string) ($_POST['medical_record_number'] ?? ''));
        if (
            mb_strlen($name) < 2
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($password) < 8
            || mb_strlen($phone) < 5
            || mb_strlen($phone) > 40
            || mb_strlen($medicalRecordNumber) > 50
        ) {
            flash('Preencha nome, telefone, email e uma palavra-passe com pelo menos 8 caracteres.');
            redirect_to(url('register'));
        }
        try {
            session_regenerate_id(true);
            $accountId = Account::createPatient($name, $email, $password);
            $_SESSION['account_id'] = $accountId;
            $linkedPatient = $medicalRecordNumber !== ''
                ? Patient::findByRecordAndPhone($medicalRecordNumber, $phone)
                : null;
            if ($linkedPatient) {
                Account::linkPatient($accountId, (int) $linkedPatient['id']);
                flash('Conta criada e registo clínico associado. As suas consultas já estão disponíveis.');
            } else {
                flash('Conta criada. Use o número de processo e o telefone para ligar o seu registo clínico.');
            }
            redirect_to(url('portal'));
        } catch (PDOException) {
            flash('Este email já está registado.');
            redirect_to(url('register'));
        }
    }

    /** Valida credenciais e inicia uma sessão regenerada. */
    public function login(): never
    {
        $account = Account::findByEmail((string) ($_POST['email'] ?? ''));
        if (!$account || !$account['active'] || !password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
            flash('Email ou palavra-passe incorretos.');
            redirect_to(url('login'));
        }
        session_regenerate_id(true);
        $_SESSION['account_id'] = (int) $account['id'];
        redirect_to($account['role'] === 'patient' ? url('portal') : url('dashboard'));
    }

    /** Cria um link local sem revelar se o email existe. */
    public function requestPasswordReset(): never
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $reset = Account::createPasswordReset($email);
            if ($reset) {
                $_SESSION['local_reset_url'] = url('reset-password', ['token' => $reset['token']]);
            }
        }
        flash('Se existir uma conta activa com esse email, será gerado um link local de recuperação.');
        redirect_to(url('forgot-password'));
    }

    /** Valida o token único e actualiza a palavra-passe. */
    public function resetPassword(): never
    {
        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (mb_strlen($password) < 8 || $password !== $confirmation || !Account::resetPassword($token, $password)) {
            flash('O link é inválido ou expirou. Confirme as palavras-passe e tente novamente.');
            redirect_to(url('reset-password', ['token' => $token]));
        }
        flash('Palavra-passe actualizada. Já pode entrar com a nova palavra-passe.');
        redirect_to(url('login'));
    }

    /** Invalida a sessão actual e regressa à página pública. */
    public function logout(): never
    {
        session_destroy();
        redirect_to(url('home'));
    }
}