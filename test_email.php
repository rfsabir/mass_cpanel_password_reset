<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../init.php';

$serviceId = $_GET['id'] ?? 0;

if (!$serviceId) {
    die("Please provide a Service ID via ?id=XXX");
}

echo "<h2>Email Test Debugger</h2>";
echo "<p>Checking available email templates...</p>";

// Fetch Email Templates using API
$getTemplates = localAPI('GetEmailTemplates', ['type' => 'product']);

if ($getTemplates['result'] == 'success') {
    echo "<h3>Available Product Templates</h3>";
    echo "<ul>";
    foreach ($getTemplates['emailtemplates']['emailtemplate'] as $template) {
        echo "<li>" . $template['name'] . " (ID: " . $template['id'] . ")</li>";
    }
    echo "</ul>";

    echo "<p>Please copy the <strong>EXACT</strong> name of the template sending the welcome email, and let me know.</p>";
}
else {
    echo "<p>Failed to fetch templates: " . $getTemplates['message'] . "</p>";
}

echo "<hr>";

echo "<h3>Testing with 'New Account Information'</h3>";
echo "<p>Attempting to send 'New Account Information' to Service ID: $serviceId</p>";

$customVarsArray = [
    'service_password' => 'TestPassword123!',
    'password' => 'TestPassword123!'
];

$results1 = localAPI('SendEmail', [
    'messagename' => 'New Account Information',
    'id' => $serviceId,
    'customvars' => $customVarsArray
]);
echo "<pre>" . print_r($results1, true) . "</pre>";