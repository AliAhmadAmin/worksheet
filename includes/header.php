<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WorkSheet Pro - Hourly Work & Task Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        window.__INITIAL_USER__ = <?= !empty($authUser) ? json_encode($authUser) : 'null' ?>;
        window.__INITIAL_DATE__ = "<?= date('Y-m-d') ?>";
    </script>
</head>
<body>
