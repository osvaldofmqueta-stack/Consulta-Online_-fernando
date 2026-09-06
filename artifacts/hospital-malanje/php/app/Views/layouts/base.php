<?php
// Layout comum: recebe $content já renderizado e envolve-o com metadados,
// estilos globais, navegação opcional e a mensagem flash da sessão.
$notice = flash();
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Hospital de Malanje</title>
    <link rel="icon" href="<?= e(asset_url('favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('app.css')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <!-- Utilitários visuais pequenos que complementam as classes Tailwind geradas. -->
    <style>body{font-family:'DM Sans',sans-serif;background:#fbf8f1;color:#21413f}.font-mono-ui{font-family:'Space Mono',monospace}.noise{background-image:radial-gradient(rgba(33,65,63,.05) .7px,transparent .7px);background-size:7px 7px}.shadow-soft{box-shadow:0 18px 45px rgba(33,65,63,.08)}</style>
</head>
<body class="min-h-screen">
<?php if ($notice): ?><div class="fixed right-5 top-5 z-50 rounded-xl border border-teal/20 bg-white px-4 py-3 text-sm shadow-soft"><?= e($notice) ?></div><?php endif; ?>
<?= $content ?>
</body>
</html>