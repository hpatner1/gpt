<?php
/** Authentication guards and helpers. */

require_once __DIR__ . '/../config.php';

function require_guest(): void
{
    if (current_user_id() !== null) {
        redirect('dashboard.php');
    }
}

function require_login(): void
{
    if (current_user_id() === null) {
        redirect('index.php');
    }
}
