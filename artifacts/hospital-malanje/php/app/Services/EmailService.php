<?php
declare(strict_types=1);

final class EmailService
{
    public static function sendPasswordReset(string $recipient, string $name, string $resetUrl): bool
    {
        $from = getenv('PASSWORD_RESET_FROM_EMAIL') ?: 'onboarding@resend.dev';
        $payload = json_encode([
            'from' => str_contains($from, '<') ? $from : 'Hospital de Malanje <' . $from . '>',
            'to' => [$recipient],
            'subject' => 'Recuperação de palavra-passe — Hospital de Malanje',
            'html' => '<div style="font-family:Arial,sans-serif;line-height:1.6;color:#21413f"><h2>Recuperação de acesso</h2><p>Olá, ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p><p>Recebemos um pedido para alterar a palavra-passe da sua conta.</p><p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="display:inline-block;background:#1e706c;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none">Definir nova palavra-passe</a></p><p>Este link expira em 60 minutos e só pode ser usado uma vez. Se não fez este pedido, ignore este email.</p></div>',
        ], JSON_THROW_ON_ERROR);

        $process = proc_open(
            ['node', dirname(__DIR__, 2) . '/send-email.mjs'],
            [0 => ['pipe', 'w'], 1 => ['pipe', 'r'], 2 => ['pipe', 'r']],
            $pipes
        );
        if (!is_resource($process)) {
            return false;
        }
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $response = json_decode($output ?: $error, true);
        return $exitCode === 0 && is_array($response) && !empty($response['ok']);
    }
}