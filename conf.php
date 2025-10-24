<?php
// conf.php - Site Configuration

// Make $conf global
global $conf;

$conf = array();

// Site configuration
$conf['site_name'] = "Tickify";
$conf['site_email'] = "info@tickyfii.com";
$conf['site_url'] = "http://localhost/API_D12";

// Site language
$conf['language'] = "en";

// Database constants
$conf['db_type'] = "pdo";
$conf['db_host'] = "localhost";
$conf['db_user'] = "root";
$conf['db_pass'] = "3030chen";
$conf['db_name'] = "grp";

// M-Pesa Configuration
$conf['mpesa_consumer_key'] = '3UnZqOPYBCOv0wKbwiaramvkAFB3YQgEUzi8PnpZtxv9rUKx';
$conf['mpesa_consumer_secret'] = '1GZxhxwWhHFuN4LHcu9U1ysDdNS8XtCTmCJc14QdC65q2VxBxZTe45Qw4D97gb2V';
$conf['mpesa_shortcode'] = '174379';
$conf['mpesa_passkey'] = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
$conf['mpesa_environment'] = 'sandbox';
?>