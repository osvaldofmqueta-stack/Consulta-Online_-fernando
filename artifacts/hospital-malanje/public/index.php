<?php
declare(strict_types=1);

session_start();
require dirname(__DIR__) . '/php/app/Core/Database.php';
require dirname(__DIR__) . '/php/app/Core/Support.php';
require dirname(__DIR__) . '/php/app/Core/View.php';
require dirname(__DIR__) . '/php/app/Models/Account.php';
require dirname(__DIR__) . '/php/app/Models/Patient.php';
require dirname(__DIR__) . '/php/app/Models/Appointment.php';
require dirname(__DIR__) . '/php/app/Models/Message.php';
require dirname(__DIR__) . '/php/app/Controllers/HomeController.php';
require dirname(__DIR__) . '/php/app/Controllers/AuthController.php';
require dirname(__DIR__) . '/php/app/Controllers/PortalController.php';
require dirname(__DIR__) . '/php/app/Controllers/StaffController.php';

Account::ensureSchema();
$account = !empty($_SESSION['account_id']) ? Account::find((int) $_SESSION['account_id']) : null;
$page = $_GET['page'] ?? ($account ? ($account['role'] === 'patient' ? 'portal' : 'dashboard') : 'home');

if ($page === 'home' && $account) {
    redirect_to($account['role'] === 'patient' ? url('portal') : url('dashboard'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $auth = new AuthController();
    $portal = new PortalController();
    $staff = new StaffController();
    if ($action === 'register') $auth->register();
    if ($action === 'login') $auth->login();
    if ($action === 'link-patient' && $account) $portal->linkPatient($account);
    if ($action === 'book-appointment' && $account && $account['role'] === 'patient') $portal->bookAppointment($account);
    if ($action === 'message' && $account) $portal->sendMessage($account);
    if ($action === 'update-status' && $account && $account['role'] !== 'patient') $staff->updateStatus($account);
}

if ($page === 'logout') (new AuthController())->logout();
if ($page === 'login') (new AuthController())->showLogin();
if ($page === 'register') (new AuthController())->showRegister();
if ($page === 'portal') {
    if (!$account) redirect_to(url('login'));
    (new PortalController())->index($account);
}
if ($page === 'dashboard' || $page === 'appointments' || $page === 'patients') {
    if (!$account || $account['role'] === 'patient') redirect_to($account ? url('portal') : url('login'));
    $controller = new StaffController();
    if ($page === 'appointments') $controller->appointments($account);
    if ($page === 'patients') $controller->patients($account);
    $controller->dashboard($account);
}
(new HomeController())->index();