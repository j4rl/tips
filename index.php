<?php
require_once __DIR__ . '/auth.php';
if (current_user()) {
  header('Location: ' . base_url('/admin/index.php'));
} else {
  header('Location: ' . base_url('/admin/login.php'));
}
exit;

