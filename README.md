# WHMCS Mass cPanel Password Reset Module

A WHMCS Addon Module to bulk reset cPanel passwords for clients, update them in WHMCS, push changes to the server, and send the "New Account Information" email.

## Features

- **Filter Services**: Filter active services by Product and Server.
- **Bulk Action**: Select multiple services and reset their passwords in one go.
- **Individual Action**: Reset password for a single service.
- **Automated Workflow**:
    1. Generates a secure random password.
    2. Updates the password in WHMCS database (secured).
    3. Calls `ModuleChangePassword` to update the password on the cPanel server.
    4. Calls `SendEmail` to send the "New Account Information" email to the client with the new details.
- **Safety**: Only targets services with `servertype=cpanel` and `domainstatus=Active`.

## Installation

1. Upload the `mass_cpanel_password_reset` directory to your WHMCS installation under `modules/addons/`.
   - Path: `/path/to/whmcs/modules/addons/mass_cpanel_password_reset/`
2. Log in to your WHMCS Admin Area.
3. Go to **Configuration > System Settings > Addon Modules**.
4. Locate **Mass cPanel Password Reset** and click **Activate**.
5. Click **Configure** to grant access rights to specific administrator roles.
6. Click **Save Changes**.

## Usage

1. Go to **Addons > Mass cPanel Password Reset**.
2. Use the filters to find the services you want to reset (e.g., separate by Server or Product).
3. Review the list of active services.
4. **For Bulk Reset**: Select the checkboxes next to the services (or use the "Select All" checkbox in the header) and click **Mass Reset Selected Passwords**.
5. **For Single Reset**: Click the **Reset Now** button next to a specific service.
6. Confirm the action when prompted.

## Requirements

- WHMCS 8.0 or later (tested on logic compatible with modern WHMCS).
- PHP 7.4 or later.
