<?php

declare(strict_types=1);

/**
 * Passwort-Gate für HTML-Rapor (Admin + öffentlicher Link).
 *
 * @var string|null $spReportAuthError
 */

$spReportAuthError = $spReportAuthError ?? null;
$action = (string)($_SERVER['REQUEST_URI'] ?? '');
if ($action === '') {
    $action = '/';
}

?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rapor — Anmeldung</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(145deg, #0f172a 0%, #1e3a5f 55%, #0f172a 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #e2e8f0;
        }
        .card {
            width: 100%;
            max-width: 400px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
        }
        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.35rem;
            font-weight: 600;
        }
        p.sub {
            margin: 0 0 1.5rem;
            font-size: 0.9rem;
            color: #94a3b8;
        }
        label {
            display: block;
            font-size: 0.85rem;
            margin-bottom: 0.35rem;
            color: #cbd5e1;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(15, 23, 42, 0.6);
            color: #f8fafc;
            font-size: 1rem;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: #38bdf8;
        }
        button {
            margin-top: 1.25rem;
            width: 100%;
            padding: 0.7rem;
            border: none;
            border-radius: 8px;
            background: #0ea5e9;
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #0284c7; }
        .err {
            margin-bottom: 1rem;
            padding: 0.65rem 0.75rem;
            border-radius: 8px;
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.45);
            color: #fecaca;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Kanal-Rapor</h1>
        <p class="sub">Bitte Passwort eingeben, um den Bericht zu öffnen.</p>
        <?php if ($spReportAuthError !== null && $spReportAuthError !== '') { ?>
            <div class="err"><?= \htmlspecialchars($spReportAuthError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php } ?>
        <form method="post" action="<?= \htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
            <label for="sp_report_password">Passwort</label>
            <input type="password" id="sp_report_password" name="sp_report_password" autocomplete="current-password" required autofocus>
            <button type="submit">Anmelden</button>
        </form>
    </div>
</body>
</html>
