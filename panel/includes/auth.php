<?php
session_start();
if (empty($_SESSION['panel_admin'])) {
    header('Location: /login.php');
    exit;
}
