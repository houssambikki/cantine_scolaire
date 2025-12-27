<?php
// Fonctions de session et de protection des pages selon le rôle.
require_once __DIR__ . '/db.php';

function ensure_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function is_logged_in(?string $role = null): bool {
    ensure_session();
    if (!isset($_SESSION['role'])) {
        return false;
    }
    return $role ? $_SESSION['role'] === $role : true;
}

function require_role(string $role): void {
    ensure_session();
    if (!is_logged_in($role)) {
        $redirect = $role === 'admin' ? '/cantine_scolaire/admin/login.php' : '/cantine_scolaire/eleve/login.php';
        header("Location: {$redirect}");
        exit;
    }
}

function redirect_if_logged_in(): void {
    if (is_logged_in('admin')) {
        header('Location: /cantine_scolaire/admin/dashboard.php');
        exit;
    }
    if (is_logged_in('eleve')) {
        header('Location: /cantine_scolaire/eleve/dashboard.php');
        exit;
    }
}
