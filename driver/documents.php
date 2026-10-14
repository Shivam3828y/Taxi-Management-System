<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['driver_id'])) { header('Location: login.php'); exit; }
$driver_id = (int) $_SESSION['driver_id'];
$stmt = mysqli_prepare($conn, 'SELECT name, phone, driving_license, document_type, document_number, status FROM drivers WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $driver_id);
mysqli_stmt_execute($stmt);
$driver = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$driver) { session_destroy(); header('Location: login.php'); exit; }
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Documents | Taxi Management System</title><link rel="stylesheet" href="../css/style.css"></head><body>
<header><h1>Taxi Management System</h1></header><main class="container"><nav><a href="dashboard.php">Dashboard</a> | <a href="profile.php">Profile</a> | <a href="logout.php">Logout</a></nav>
<section class="card"><h2>My Documents</h2><p>These are the identity details submitted with your driver application.</p><table><tr><th>Driver</th><td><?= e($driver['name']) ?></td></tr><tr><th>Phone</th><td><?= e($driver['phone']) ?></td></tr><tr><th>Driving license</th><td><?= e($driver['driving_license']) ?></td></tr><tr><th>Document type</th><td><?= e($driver['document_type'] ?: 'Not provided') ?></td></tr><tr><th>Document number</th><td><?= e($driver['document_number'] ?: 'Not provided') ?></td></tr><tr><th>Account status</th><td><?= e($driver['status']) ?></td></tr></table></section></main></body></html>
