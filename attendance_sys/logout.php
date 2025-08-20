<?php
session_start();

// clear all session variables
$_SESSION = [];

// destroy the session
session_destroy();

// redirect back to login page
header("Location: login.html");
exit;
