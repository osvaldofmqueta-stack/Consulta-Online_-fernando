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
        if (!$account || !password_verify((string) ($_POST['password'] ?? ''), $account['password_hash'])) {
            flash('Email ou palavra-passe incorretos.');
            redirect_to(url('login'));
        }
        session_regenerate_id(true);
        $_SESSION['account_id'] = (int) $account['id'];
        redirect_to($account['role'] === 'patient' ? url('portal') : url('dashboard'));
    }

    public function logout(): never
    {
        session_destroy();
        redirect_to(url('home'));
    }
}