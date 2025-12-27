<?php
require_once __DIR__ . '/lib/auth.php';
ensure_session();
session_unset();
session_destroy();
header('Location: /cantine_scolaire/index.php');
exit;
