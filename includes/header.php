<?php
if (!isset($pageTitle)) $pageTitle = 'E-Commerce Core';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/style.css') ?>">
</head>
<body>
<script>
function openSidebar() {
    document.querySelector('.sidebar')?.classList.add('open');
    document.querySelector('.sidebar-backdrop')?.classList.add('show');
}
function closeSidebar() {
    document.querySelector('.sidebar')?.classList.remove('open');
    document.querySelector('.sidebar-backdrop')?.classList.remove('show');
}
</script>
<div class="sidebar-backdrop" onclick="closeSidebar()"></div>