# Azure AD Live Lookup For HelpSpot
[![PHP Composer](https://github.com/helpspot/AzureLiveLookup/actions/workflows/php-build.yml/badge.svg)](https://github.com/helpspot/AzureLiveLookup/actions/workflows/php-build.yml)

Azure AD Live Lookup for HelpSpot is a command line application that allows for livelookups against Azure AD using the Microsoft Graph API.

## Setup

This application needs to be copied to a directory on your HelpSpot server that the web server process can execute and access. It will be called from HelpSpot via a PHP `exec()` function call.

Requires: PHP 8.0

### Download
Download the latest release from https://github.com/helpspot/AzureLiveLookup/releases/latest/download/AzureLiveLookup.zip

### Azure AD App and .env Setup
This application requires a .env file where your Microsoft Client settings are stored. To create this env file we'll need to follow these steps generate the file and then set up an enterprise app in Azure AD:
1. Copy the .env.example file to .env
2. Copy your Microsoft Tenant ID to the `MS_TENANT_ID` setting. To find your Microsoft Tenant ID, log in to Microsoft Azure. From the menu, select `Azure Active Directory > Overview`. You can find the Tenant ID within the `Overview` tab. 
3. In Azure Active Directory click on `App registrations`.
4. Click on `New Registration`.
5. Give the Application a name and select `Accounts in this organizational directory only` for the `Supported account type`.
6. Copy the `Application ID` to the `MS_CLIENT_ID` variable in your `.env` file.
7. Select `API permissions` and add the `User.Read`, `User.Read.All` and `User.ReadBasic.All` permissions.
8. Select `Certificates and Secrets` and create a new `Client Secret`.
9. Copy the `Value` to the `MS_CLIENT_SECRET` variable in your `.env` file.


### Assign Azure Enterprise App Permissions
The default search that is performed in this example needs the User.Read, User.Read.All and User.ReadBasic.All api permissions. These permissions are managed under the `API permissions` tab of your app registration.
<img width="1037" alt="Screen Shot 2022-10-24 at 11 55 22 AM" src="https://user-images.githubusercontent.com/1500588/197593443-16bf7b02-b11b-44d1-b9dc-a16f4507c749.png">


### Live Lookup Source Setup in HelpSpot
In HelpSpot:
1. Navigate to `Admin > Settings > Live Lookup`
2. Give the new source a name and select `Command Line` for the `Lookup Via` setting.
3. The `Path to Script` settings needs the full path to your php cli executable followed by the full path to your azurelivelookup app with the parameter `livelookup`. 

Example:
```
/usr/bin/php8.0 /var/www/helpspot/public/custom_code/AzureLiveLookup/azurelivelookup livelookup
```
4. Save your settings.

Your Live Lookup source will now be available in the request view. By default the Live Lookup integration searched via email address for matches in your directory.

## Customization

* `app/Commands/Livelookup.php` - Contains the `users()` function that performs the search against Azure AD. You can customize the search string used along with the fields returned by changing the `$query` variable in that function. 
* `resources/views/livelookup.blade.php` - Contains the XML output that is returned from the command. This can be customized using [Laravel Blade](https://laravel.com/docs/9.x/blade) formatting. 

For full documentation, visit [laravel-zero.com](https://laravel-zero.com/).

## License

Azure AD Live Lookup for HelpSpot is an open-source software licensed under the MIT license.
