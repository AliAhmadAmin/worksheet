    <!-- Application Scripts -->
<?php
$coreScripts = ['assets/js/app.js', 'assets/js/mistakes.js'];
$scriptsToLoad = (isset($pageScripts) && is_array($pageScripts))
    ? array_merge($coreScripts, $pageScripts)
    : [
        'assets/js/app.js',
        'assets/js/sheets.js',
        'assets/js/tasks.js',
        'assets/js/reports.js',
        'assets/js/hr.js',
        'assets/js/employees.js',
        'assets/js/programming.js',
        'assets/js/newsroom.js',
        'assets/js/roster.js',
        'assets/js/whatsapp_attendance.js',
        'assets/js/zkteco_attendance.js'
    ];

$uniqueScripts = array_unique($scriptsToLoad);
foreach ($uniqueScripts as $scriptRel):
    $fullPath = __DIR__ . '/../' . $scriptRel;
    if (!file_exists($fullPath)) continue;
    $v = filemtime($fullPath);
?>
    <script src="<?= htmlspecialchars($scriptRel) ?>?v=<?= $v ?>"></script>
<?php endforeach; ?>
</body>
</html>
