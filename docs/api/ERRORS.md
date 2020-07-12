Error Code | Message | Possible Resolution
--- | --- | ---
TOKEN_ALREADY_SUPPLIED | Cannot create new session; Session token header was already specified | n/a
API_CONTROLLER_DISABLED | This endpoint is currently disabled | n/a
INTERNAL_ERROR  | An internal error has occurred | n/a
PLATFORM_ACCESS_ERROR | An error occurred while retrieving API access triggers | n/a
AUTH_NOT_LOGGED_IN | You are not logged in | Redirect to login page
AUTH_USER_RETRIEVE_ERROR | Failed to retrieve authenticated user | Redirect to login page 
AUTH_TOKEN_MISMATCH | Session lost; You have logged into a different device | Redirect to login page 
AUTH_USER_DISABLED | Your account is disabled; Contact support | Redirect to login page
AUTH_USER_TIMEOUT | Your session has timed out | Redirect to login page
AUTH_USER_OTP | 2FA authentication is required | Redirect to 2FA page
AUTH_USER_2FA_NOT_SETUP | User has not enabled 2FA authentication | Redirect to dashboard page
OAUTH2_ALREADY_CONNECTED | An OAuth2.0 account for this vendor is already connected | n/a
OAUTH2_NOT_CONNECTED | Not connected to OAuth2.0 vendor | n/a
OAUTH2_EMAIL_MISMATCH | E-mail does not match with OAuth2.0 account | n/a
2FA_TOTP_REQ | TOTP code is required | Focus on input field
2FA_TOTP_INVALID | TOTP code is invalid | Focus on input field
2FA_TOTP_USED | This TOTP is already used | Focus on input field
ALREADY_LOGGED_IN | You are already logged in! | Redirect user to authenticated screen (i.e. dashboard)
RECAPTCHA_REQ | ReCaptcha validation is required | n/a
RECAPTCHA_FAILED | ReCaptcha validation was failed | Reset reCaptcha on user screen
REFERRER_ID_INVALID | Entered referrer ID is invalid | n/a
REFERRER_NOT_FOUND | No such referrer exists | n/a
FIRST_NAME_REQ | First name is required | Focus on input field
FIRST_NAME_LEN | First name must be between 3 and 16 characters | Focus on input field
FIRST_NAME_INVALID | First name contains an illegal character | Focus on input field
LAST_NAME_REQ | Last name is required | Focus on input field
LAST_NAME_LEN | Last name must be between 3 and 16 characters | Focus on input field
LAST_NAME_INVALID | Last name contains an illegal character | Focus on input field
EMAIL_ADDR_REQ | E-mail address is required | Focus on input field
EMAIL_ADDR_INVALID | E-mail address is invalid | Focus on input field
EMAIL_ADDR_LEN | E-mail address is too long | Focus on input field
EMAIL_ADDR_DUP | E-mail address is already registered | Focus on input field
EMAIL_ADDR_UNVERIFIED | Your e-mail address is not verified | Redirect to e-mail verification page
EMAIL_ADDR_VERIFIED | E-mail address is already verified | Redirect to dashboard
EMAIL_VERIFY_REQ_TIMEOUT | You requested verification e-mail less than 15 minutes ago | Response has **"wait"** property with number of minutes user should wait
USERNAME_LEN_MIN | Username is too short | Focus on input field
USERNAME_LEN_MAX | Username is too long | Focus on input field
USERNAME_INVALID | Username contains an illegal character | Focus in input field
USERNAME_DUP | Username is already taken | Focus on input field
PASSWORD_REQ | Password is required | Focus on input field
PASSWORD_LEN_MIN | Password must be 8 digits or longer | Focus on input field
PASSWORD_LEN_MAX | Password cannot exceed 32 characters | Focus on input field
PASSWORD_WEAK | Password is too weak | Focus on input field, Suggest a stronger password
PASSWORD_CONFIRM_MATCH | You must retype same password | Focus on input field
PASSWORD_SAME | This is already your account password; Choose a new one | Focus on input field
PASSWORD_INCORRECT | Incorrect password | Focus on input field
COUNTRY_INVALID | Select a country | Focus on input field
TERMS_UNCHECKED | You must agree with our Terms & Conditions | Focus on checkbox
LOGIN_ID_REQ | Login ID is required | Focus on field
LOGIN_ID_INVALID | Login ID contains an illegal character | Focus on field
LOGIN_ID_UNKNOWN | No such user is registered | Focus on field
VERIFY_CODE_REQ | Verification code cannot be blank | Focus on field
VERIFY_CODE_INVALID | Incorrect validation code | Focus on field
USER_MATCH_COUNTRY | User country does not match | Focus on field
RECOVER_REQ_TIMEOUT | Account recovery was requested less than 15 minutes ago | Response has **"wait"** property with number of minutes user should wait
RECOVER_CODE_REQ | Recovery code is required | Focus on field
RECOVER_CODE_INVALID | Recovery code is incorrect | Focus on field
RECOVER_CODE_EXPIRED | Account recovery code has expired | Redirect to recover screen
