<?php

namespace WHMCS\Module\Addon\MassCpanelPasswordReset;

use WHMCS\Database\Capsule;

class Manager
{
    public function getServices($filter)
    {
        $query = Capsule::table('tblhosting')
            ->join('tblproducts', 'tblhosting.packageid', '=', 'tblproducts.id')
            ->where('tblhosting.domainstatus', 'Active')
            ->where('tblproducts.servertype', 'cpanel')
            ->select('tblhosting.id', 'tblhosting.domain', 'tblproducts.name as product_name', 'tblhosting.username');

        if (!empty($filter['product_id'])) {
            $query->where('tblhosting.packageid', $filter['product_id']);
        }

        if (!empty($filter['server_id'])) {
            $query->where('tblhosting.server', $filter['server_id']);
        }

        return $query->get();
    }

    /**
     * resetPassword Service
     * Generates a new random password, updates the service, pushes change to server, and sends email.
     *
     * @param int $serviceId
     * @return array ['success' => bool, 'message' => string]
     */
    public function resetPassword($serviceId)
    {
        try {
            // 1. Generate new password
            $newPassword = $this->generateRandomPassword();

            // 2. Update service password in WHMCS DB using API to ensure encryption handling
            $updateResult = localAPI('UpdateClientProduct', [
                'serviceid' => $serviceId,
                'password' => $newPassword
            ]);

            if ($updateResult['result'] != 'success') {
                return ['success' => false, 'message' => "Failed to update password in DB for Service ID $serviceId: " . $updateResult['message']];
            }

            // 3. Push password change to cPanel server
            // Using ModuleChangePw API (ModuleChangePassword was incorrect)
            $moduleResult = localAPI('ModuleChangePw', [
                'serviceid' => $serviceId,
                'servicepassword' => $newPassword
            ]);

            if ($moduleResult['result'] != 'success') {
                return ['success' => false, 'message' => "Module Command Failed for Service ID $serviceId: " . $moduleResult['message']];
            }

            // 4. Send Welcome Email
            // Pass password in customvars to ensure it's available in the template.
            // For localAPI, customvars should be a base64 encoded serialized array OR a raw array depending on version/context.
            // Documentation suggests localAPI handles raw arrays better for internal calls.
            // Let's try raw array first, as base64 might have failed.
            $emailResult = localAPI('SendEmail', [
                'messagename' => 'Hosting Account Welcome Email',
                'id' => $serviceId,
                'customvars' => [
                    'service_password' => $newPassword,
                    'password' => $newPassword
                ]
            ]);

            if ($emailResult['result'] != 'success') {
                return ['success' => true, 'message' => "Password reset successful, but email failed to send. Error: " . ($emailResult['message'] ?? 'Unknown Error')];
            }

            return ['success' => true, 'message' => 'Success'];

        }
        catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function processReset($serviceIds)
    {
        $results = [];
        foreach ($serviceIds as $serviceId) {
            $res = $this->resetPassword($serviceId);
            $results[$serviceId] = $res;
        }
        return $results;
    }

    private function generateRandomPassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        return substr(str_shuffle($chars), 0, $length);
    }

    public function renderFilterForm($vars)
    {
        // Fetch products and servers for dropdowns
        $products = Capsule::table('tblproducts')->where('servertype', 'cpanel')->get();
        // Server groups or individual servers? Usually servers assigned to products. 
        // Showing all cPanel servers is fine.
        $servers = Capsule::table('tblservers')->where('type', 'cpanel')->get();

        $productOptions = '<option value="">Any cPanel Product</option>';
        foreach ($products as $product) {
            $selected = (isset($_REQUEST['product_id']) && $_REQUEST['product_id'] == $product->id) ? 'selected' : '';
            $productOptions .= "<option value=\"{$product->id}\" {$selected}>{$product->name}</option>";
        }

        $serverOptions = '<option value="">Any cPanel Server</option>';
        foreach ($servers as $server) {
            $selected = (isset($_REQUEST['server_id']) && $_REQUEST['server_id'] == $server->id) ? 'selected' : '';
            $serverOptions .= "<option value=\"{$server->id}\" {$selected}>{$server->name} ({$server->hostname})</option>";
        }

        $moduleLink = $vars['modulelink'];

        return <<<HTML
        <div class="row">
            <div class="col-md-12">
            <form method="post" action="{$moduleLink}&filter=1" class="form-horizontal">
                <div class="form-group">
                    <label class="col-md-2 control-label">Product</label>
                    <div class="col-md-4">
                        <select name="product_id" class="form-control">
                            {$productOptions}
                        </select>
                    </div>
                </div>
                 <div class="form-group">
                    <label class="col-md-2 control-label">Server</label>
                    <div class="col-md-4">
                        <select name="server_id" class="form-control">
                            {$serverOptions}
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-md-offset-2 col-md-4">
                        <input type="submit" value="Search / Filter" class="btn btn-primary" />
                    </div>
                </div>
            </form>
            </div>
        </div>
        <hr>
HTML;
    }

    public function renderServiceList($services, $vars)
    {
        $rows = '';

        // Capture current filter params to append to links/forms
        $productId = $_REQUEST['product_id'] ?? '';
        $serverId = $_REQUEST['server_id'] ?? '';
        $filterParams = "&filter=1&product_id={$productId}&server_id={$serverId}";

        foreach ($services as $service) {
            $serviceId = $service->id;
            $resetUrl = $vars['modulelink'] . '&action=reset_single&id=' . $serviceId . $filterParams;

            $rows .= <<<HTML
            <tr>
                <td><input type="checkbox" name="service_ids[]" value="{$serviceId}" class="service-checkbox"></td>
                <td><a href="clientsservices.php?id={$serviceId}" target="_blank">{$serviceId}</a></td>
                <td><a href="http://{$service->domain}" target="_blank">{$service->domain}</a></td>
                <td>{$service->username}</td>
                <td>{$service->product_name}</td>
                <td>
                    <a href="{$resetUrl}" class="btn btn-xs btn-danger" onclick="return confirm('Reset password for this service?');">Reset Now</a>
                </td>
            </tr>
HTML;
        }

        $moduleLink = $vars['modulelink'];

        return <<<HTML
        <h3>Found Active cPanel Services</h3>
        <form method="post" action="{$moduleLink}&action=reset_bulk">
            <input type="hidden" name="filter" value="1">
            <input type="hidden" name="product_id" value="{$productId}">
            <input type="hidden" name="server_id" value="{$serverId}">
            
            <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th width="20"><input type="checkbox" id="selectAll"></th>
                        <th>ID</th>
                        <th>Domain</th>
                        <th>Username</th>
                        <th>Product</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {$rows}
                </tbody>
            </table>
            </div>
            <p>
                <!-- Changed to type="button" and added ID for AJAX handling -->
                <button type="button" id="startMassReset" class="btn btn-danger btn-lg">
                    <i class="fas fa-trash-alt"></i> Mass Reset Selected Passwords
                </button>
            </p>
        </form>

        <!-- Status Modal/Div -->
        <div id="resetStatus" style="display:none; margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
            <h4>Reset Progress</h4>
            <div class="progress">
                <div id="resetProgressBar" class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%"></div>
            </div>
            <div id="resetLog" style="max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px; border: 1px solid #eee; padding: 5px; background: #fff;"></div>
        </div>

        <script>
            $(document).ready(function () {
                // Select All Handler
                $('#selectAll').change(function () {
                    var checkboxes = $(this).closest('form').find(':checkbox');
                    checkboxes.prop('checked', $(this).is(':checked'));
                });

                // Mass Reset Handler
                $('#startMassReset').click(function () {
                    var selected = [];
                    $('.service-checkbox:checked').each(function () {
                        selected.push($(this).val());
                    });

                    if (selected.length === 0) {
                        alert('Please select at least one service.');
                        return;
                    }

                    if (!confirm('WARNING: You are about to reset passwords for ' + selected.length + ' services. This will be processed one by one to avoid server load. Continue?')) {
                        return;
                    }

                    // UI Prep
                    $('#resetStatus').show();
                    $('#resetLog').html('Starting...<br>');
                    $(this).prop('disabled', true);

                    var total = selected.length;
                    var current = 0;

                    // Recursive function to process queue
                    function processNext() {
                        if (current >= total) {
                            $('#resetLog').append('<strong>Completed!</strong><br>');
                            $('#resetProgressBar').removeClass('active').addClass('progress-bar-success');
                            $('#startMassReset').prop('disabled', false).text('Reset Complete');
                            return;
                        }

                        var serviceId = selected[current];
                        var percent = Math.round(((current + 1) / total) * 100);

                        $('#resetLog').append('Resetting Service ID ' + serviceId + '... ');
                        $('#resetProgressBar').css('width', percent + '%').text(percent + '%');

                        $.ajax({
                            url: '{$moduleLink}&action=reset_ajax',
                            type: 'POST',
                            data: { id: serviceId },
                            dataType: 'json',
                            success: function (response) {
                                if (response.success) {
                                    $('#resetLog').append('<span style="color:green;">Success</span><br>');
                                } else {
                                    $('#resetLog').append('<span style="color:red;">Failed: ' + response.message + '</span><br>');
                                }
                            },
                            error: function () {
                                $('#resetLog').append('<span style="color:red;">System Error</span><br>');
                            },
                            complete: function () {
                                current++;
                                // 2 Second Delay
                                setTimeout(processNext, 2000);
                            }
                        });
                    }

                    // Start processing
                    processNext();
                });
            });
        </script>
HTML;
    }
}