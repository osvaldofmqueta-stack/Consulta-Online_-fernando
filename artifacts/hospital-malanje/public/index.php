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
require dirname(__DIR__) . '/php/app/Models/Clinical.php';
require dirname(__DIR__) . '/php/app/Models/Audit.php';
require dirname(__DIR__) . '/php/app/Models/Directory.php';
require dirname(__DIR__) . '/php/app/Services/EmailService.php';
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
    if ($action === 'request-password-reset') $auth->requestPasswordReset();
    if ($action === 'reset-password') $auth->resetPassword();
    if ($action === 'link-patient' && $account) $portal->linkPatient($account);
    if ($action === 'book-appointment' && $account && $account['role'] === 'patient') $portal->bookAppointment($account);
    if ($action === 'cancel-appointment' && $account && $account['role'] === 'patient') $portal->cancelAppointment($account);
    if ($action === 'update-profile' && $account && $account['role'] === 'patient') $portal->updateProfile($account);
    if ($action === 'change-password' && $account) $portal->changePassword($account);
    if ($action === 'message' && $account) $portal->sendMessage($account);
    if ($action === 'update-status' && $account && can_access($account, 'appointments')) $staff->updateStatus($account);
    if ($action === 'reply-message' && $account && can_access($account, 'messages')) $staff->replyMessage($account);
    if ($action === 'upload-document' && $account && can_access($account, 'clinical')) $staff->uploadDocument($account);
    if ($action === 'create-prescription' && $account && can_access($account, 'clinical')) $staff->createPrescription($account);
    if ($action === 'create-result' && $account && can_access($account, 'clinical')) $staff->createResult($account);
    if ($action === 'save-settings' && $account && can_access($account, 'settings')) $staff->saveSettings($account);
    if ($action === 'update-user' && $account && can_access($account, 'users')) $staff->updateUser($account);
    if ($action === 'create-user' && $account && can_access($account, 'users')) $staff->createUser($account);
    if ($action === 'create-department' && $account && can_access($account, 'settings')) $staff->createDepartment($account);
    if ($action === 'update-department' && $account && can_access($account, 'settings')) $staff->updateDepartment($account);
    if ($action === 'create-doctor' && $account && can_access($account, 'settings')) $staff->createDoctor($account);
    if ($action === 'update-doctor' && $account && can_access($account, 'settings')) $staff->updateDoctor($account);
}

if ($page === 'logout') (new AuthController())->logout();
if ($page === 'login') (new AuthController())->showLogin();
if ($page === 'register') (new AuthController())->showRegister();
if ($page === 'forgot-password') (new AuthController())->showForgotPassword();
if ($page === 'reset-password') (new AuthController())->showResetPassword();
if ($page === 'portal') {
    if (!$account) redirect_to(url('login'));
    (new PortalController())->index($account);
}
if ($page === 'document-download') {
    if (!$account) redirect_to(url('login'));
    (new PortalController())->downloadDocument($account);
}
if ($page === 'dashboard' || $page === 'appointments' || $page === 'patients') {
    if (!$account || !can_access($account, $page === 'dashboard' ? 'dashboard' : $page)) redirect_to($account ? url('dashboard') : url('login'));
    $controller = new StaffController();
    if ($page === 'appointments') $controller->appointments($account);
    if ($page === 'patients') $controller->patients($account);
    $controller->dashboard($account);
}
if (in_array($page, ['messages', 'reports', 'settings', 'users', 'directory', 'patient'], true)) {
    if (!$account || $account['role'] === 'patient') redirect_to($account ? url('portal') : url('login'));
    $controller = new StaffController();
    if ($page === 'messages' && can_access($account, 'messages')) $controller->messages($account);
    if ($page === 'reports' && can_access($account, 'reports')) $controller->reports($account);
    if ($page === 'settings' && can_access($account, 'settings')) $controller->settings($account);
    if ($page === 'users' && can_access($account, 'users')) $controller->users($account);
    if ($page === 'directory' && can_access($account, 'settings')) $controller->directory($account);
    if ($page === 'patient') $controller->patient($account);
    redirect_to(url('dashboard'));
}
(new HomeController())->index();