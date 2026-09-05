<?php
declare(strict_types=1);

final class AuthController
{
    public function showLogin(): never
    {
        View::render('auth/form', ['mode' => 'login'], 'Entrar');
    }

    public function showRegister(): never
    {
        View::render('auth/form', ['mode' => 'register'], 'Criar conta');
    }

    public function showForgotPassword(): never
    {
        View::render('auth/form', ['mode' => 'forgot'], 'Recuperar acesso');
    }

    public function showResetPassword(): never
    {
        View::render('auth/form', ['mode' => 'reset', 'token' => (string) ($_GET['token'] ?? '')], 'Definir nova palavra-passe');
    }

    public function register(): never
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($password) < 8) {
            flash('Preencha o nome, um email válido e uma palavra-passe com pelo menos 8 caracteres.');
            redirect_to(url('register'));
        }
        try {
            session_regenerate_id(true);
            $_SESSION['account_id'] = Account::create($name, $email, $password);
            flash('Conta criada. Ligue agora o seu registo clínico.');
            redirect_to(url('portal'));
        } catch (PDOException) {
            flash('Este email já está registado.');
            redirect_to(url('register'));
        }
    }

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

    public function requestPasswordReset(): never
    {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $reset = Account::createPasswordReset($email);
            if ($reset) {
                $requestScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $requestHost = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                $baseUrl = getenv('APP_URL') ?: $requestScheme . '://' . ($requestHost ?: 'localhost');
                $resetUrl = rtrim($baseUrl, '/') . '/' . ltrim(url('reset-password', ['token' => $reset['token']]), '/');
                EmailService::sendPasswordReset((string) $reset['account']['email'], (string) $reset['account']['name'], $resetUrl);
            }
        }
        flash('Se existir uma conta activa com esse email, receberá um link de recuperação.');
        redirect_to(url('forgot-password'));
    }

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

    public function logout(): never
    {
        session_destroy();
        redirect_to(url('home'));
    }
}