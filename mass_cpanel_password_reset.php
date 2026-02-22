<?php

use WHMCS\Database\Capsule;
use WHMCS\Module\Addon\MassCpanelPasswordReset\Manager;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function mass_cpanel_password_reset_config()
{
    return [
        'name' => 'Mass cPanel Password Reset',
        'description' => 'Bulk reset cPanel passwords and send welcome emails.',
        'author' => 'GOTMYHOST',
        'language' => 'english',
        'version' => '1.0',
        'fields' => []
    ];
}

function mass_cpanel_password_reset_activate()
{
    return [
        'status' => 'success',
        'description' => 'Module activated successfully.'
    ];
}

function mass_cpanel_password_reset_deactivate()
{
    return [
        'status' => 'success',
        'description' => 'Module deactivated successfully.'
    ];
}

function mass_cpanel_password_reset_output($vars)
{
    $action = $_REQUEST['action'] ?? '';

    // Basic autoloader for the namespace
    require_once __DIR__ . '/lib/Manager.php';

    $manager = new Manager();

    // AJAX Handler for Sequential Processing
    if ($action === 'reset_ajax') {
        $serviceId = $_REQUEST['id'] ?? 0;
        header('Content-Type: application/json');
        if (!$serviceId) {
            echo json_encode(['success' => false, 'message' => 'No Service ID provided']);
            exit;
        }

        try {
            $result = $manager->resetPassword($serviceId);
            echo json_encode($result);
        }
        catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'reset_bulk') {
        $serviceIds = $_POST['service_ids'] ?? [];
        if (!empty($serviceIds)) {
            $results = $manager->processReset($serviceIds);

            $successCount = 0;
            $failCount = 0;
            foreach ($results as $res) {
                if ($res['success']) {
                    $successCount++;
                }
                else {
                    $failCount++;
                }
            }

            if ($failCount == 0) {
                echo '<div class="alert alert-success">Successfully reset passwords for ' . $successCount . ' services. Email notifications sent.</div>';
            }
            else {
                echo '<div class="alert alert-warning">Processed ' . count($results) . ' services. Success: ' . $successCount . ', Failed: ' . $failCount . '. Check logs for details.</div>';
            // Optionally list failures
            }
        }
        else {
            echo '<div class="alert alert-danger">No services selected.</div>';
        }
    }
    elseif ($action === 'reset_single') {
        $serviceId = $_REQUEST['id'] ?? 0;
        if ($serviceId) {
            $res = $manager->resetPassword($serviceId);
            if ($res['success']) {
                echo '<div class="alert alert-success">Password reset successfully for Service ID ' . $serviceId . '. Email notification sent.</div>';
            }
            else {
                echo '<div class="alert alert-danger">Failed to reset password for Service ID ' . $serviceId . ': ' . $res['message'] . '</div>';
            }
        }
    }

    // Render filter form
    echo $manager->renderFilterForm($vars);

    // If filter applied, render service list
    if (isset($_REQUEST['filter']) || isset($_REQUEST['action'])) {
        // Maintain filter state if possible, or just default to showing nothing until filtered again
        // But if we just did a reset, we probably want to see the list again? 
        // Let's rely on the filter params still being in $_REQUEST or just re-render if they passed filter=1

        // Actually if we just did an action, we might not have the filter POSTed again unless we included handled it.
        // For simplicity, if we performed an action, we should probably allow re-listing if parameters persist.
        // But $_POST params for filter won't persist across the action submission unless hidden fields were used.
        // Users might have to filter again. That's acceptable for v1.

        if (isset($_REQUEST['filter'])) {
            $services = $manager->getServices($_REQUEST);
            echo $manager->renderServiceList($services, $vars);
        }
    }
}