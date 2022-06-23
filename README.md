# CILogon Auth Module
About
The CILogon Auth module enables Single Sign-on via the CILogon.org service, which supports authorization from a large number of academic institutions and other organizations around the world. See full list of supported identity providers at https://cilogon.org

About CILogon: CILogon service provides an integrated open source identity and access management platform for research collaborations. 
Supported identity providers (You can try logging on with your preferred provider): https://cilogon.org/testidp/  
More information available at https://www.cilogon.org/home

Module requirements
1. Your site must use HTTPS
2. Register your website to use the CILogon service at https://cilogon.org/oauth2/register 
    a. Set call back url as: https://example.com/cilogon-auth/cilogon  (where example.com is your site's base path). Your callback URL must use HTTPS. 
    b. Required scope: openid
    c. Recommended scopes to make use of all module features: email and org.cilogon.userinfo 
3. You may also request CILogon to limit identity provider to one or more organization. See how to customize CILogon at https://www.cilogon.org/faq#h.p_wZRnibtF7rz7


Optional requirement
This module also integrates the User Restriction module, which allows a high degree of automation and control for user registration and subsequent sign-in. 

Uses
Allows users to sign-in and register on Drupal site via the CILogon Single Sign-on service. It also allows custom user restriction via integration with the User Restriction module.

Permissions
1. Administer CILogon auth client
      Users in roles with this permission allows them to administer module settings.
2. Manage own CILogon auth account
      Users in roles with this permission allows them to connect/disconnect their cilogon auth account.
3. Set own password for CILogon auth account
      Users in roles with this permission allows them to set their own password.

Administration
1. Site configuration (Required)
    Add the CILogon Auth block to your site's login page
    a. Go to structure -> Block Layout
    b. Place block in the content section.
    c. Restrict the login page to /user/login.

2. CILogon Auth configuration (Required)
    a. Go to Configurations -> Web Services -> CILogon Auth
    b. Enable the CILogon checkbox
    c. Enter your Client ID and Secret that you got when your registered for cilogon.
    d. Press save configuration and your setup is complete. See the settings section for features.

3. CILogon Auth settings (Optional)
    A. Username generation scheme
       Choose username generation scheme when accounts are created/registered through this module.
        a. default (e.g. cilogon_hashValue)
        b. email (e.g. john@example.com). Only works if you requested email scope during CILogon service registration.
        c. email prefix (e.g. john parsed from john@example.com). Only works if you requested email scope during CILogon service registration.
        d. custom prefix (e.g. setting prefix as 'user' will generate usernames as user1, user2 ...)
    B. Override registration settings
        Allows registration via this module, even when site's Account settings restricts registration to "Adminstrators only".
    C. Unblock account during registration
         Unblocks users registerd by this module, even when site's Account settings restricts it to "Visitor, but require admin approval".
    D. Show connected IDP of user
        If enabled, the users page will show the idp name that user connected with through cilogon. Only works if you requested email scope during CILogon service registration.
    E. Enable user restrictions
        If enabled, user restriction rules will apply to for user sign-on and registration via this module. Only available, when your site has User Restrictions module installed.
    F. Save user claims on every login   
        If disabled, user claims will only be saved when the account is first created.
    G. Automatically connect existing users
        If disabled, authentication will fail for existing email addresses.
    H. Logon block description
        Will display HTML or regular text under the CILogon Auth block title.
        
4. Module Settings Behavior 

This module provides a way to override site-wide registration and account blocking after registration if desired. The following table provides mapping between this module's settings and site-wide registration settings
CILogon Auth Settings are located at /admin/config/services/cilogon-auth
Site-wide Registration Settings are located at admin/config/people/accounts under "Who can register accounts"
(1 is enabled, 0 is disabled)

+--------------------------------------------------------------------------------------------------------------------------------+
| CILogon_Auth Settings                   | Site-wide Registration Settings                          |                           |
+--------------------------------------------------------------------------------------------------------------------------------+
| Override registration | Unblock account | Admin Only       | Visitors | Vistor with Admin approval | Result                    |
+--------------------------------------------------------------------------------------------------------------------------------+
| 1                     | 1               | 1                | 0        | 0                          | Create & Unblock Account  |
---------------------------------------------------------------------------------------------------------------------------------+
| 1                     | 0               | 1                | 0        | 0                          | Create & Block Account    |
+--------------------------------------------------------------------------------------------------------------------------------+
| 0                     | 1               | 1                | 0        | 0                          | Account Creation denied   |
+--------------------------------------------------------------------------------------------------------------------------------+
| 0                     | 0               | 1                | 0        | 0                          | Account Creation denied   |
+--------------------------------------------------------------------------------------------------------------------------------+
| 1                     | 1               | 0                | 1        | 0                          | Create & Unblock Account  |
+--------------------------------------------------------------------------------------------------------------------------------+
| 1                     | 0               | 0                | 1        | 0                          | Create & Unblock Account  |
+--------------------------------------------------------------------------------------------------------------------------------+
| 0                     | 1               | 0                | 1        | 0                          | Create & Unblock Account  |
+--------------------------------------------------------------------------------------------------------------------------------+
| 0                     | 0               | 0                | 1        | 0                          | Create & Unblock Account  |
+--------------------------------------------------------------------------------------------------------------------------------+
| 1                     | 1               | 0                | 0        | 1                          | Create & Unblock Account  |
---------------------------------------------------------------------------------------------------------------------------------+
| 1                     | 0               | 0                | 0        | 1                          | Create & Block Account    |
+--------------------------------------------------------------------------------------------------------------------------------+
| 0                     | 1               | 0                | 0        | 1                          | Create & Block Account    |
+--------------------------------------------------------------------------------------------------------------------------------+
| 0                     | 0               | 0                | 0        | 1                          | Create & Block Account    |
+--------------------------------------------------------------------------------------------------------------------------------+