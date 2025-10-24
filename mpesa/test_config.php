<?php
// mpesa/test_config.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>M-Pesa Configuration Test</h1>";

$confFile = __DIR__ . '/../conf.php';
if (!file_exists($confFile)) {
    die("❌ conf.php file not found at: $confFile");
}

require_once $confFile;

echo "<h2>Configuration Values:</h2>";
if (isset($conf) && is_array($conf)) {
    foreach ($conf as $key => $value) {
        if (strpos($key, 'mpesa') !== false) {
            $displayValue = $value;
            if (strpos($key, 'secret') !== false || strpos($key, 'key') !== false) {
                $displayValue = substr($value, 0, 10) . '...';
            }
            echo "<strong>$key:</strong> $displayValue<br>";
        }
    }
} else {
    echo "❌ \$conf array not found in conf.php";
}

echo "<h2>Testing MpesaService Initialization:</h2>";
try {
    require_once __DIR__ . '/MpesaService.php';
    $mpesaService = new MpesaService();
    echo "✅ MpesaService initialized successfully!";
} catch (Exception $e) {
    echo "❌ MpesaService initialization failed: " . $e->getMessage();
}
?>
