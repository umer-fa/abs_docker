Error Code | Message | Possible Resolution
--- | --- | ---
TOKEN_ALREADY_SUPPLIED | Cannot create new session; Session token header was already specified | n/a
API_CONTROLLER_DISABLED | This endpoint is currently disabled | n/a
INTERNAL_ERROR  | An internal error has occurred | n/a
PLATFORM_ACCESS_ERROR | An error occurred while retrieving API access triggers | n/a
ALREADY_LOGGED_IN | You are already logged in! | Redirect user to authenticated screen (i.e. dashboard)
RECAPTCHA_REQ | ReCaptcha validation is required | n/a
RECAPTCHA_FAILED | ReCaptcha validation was failed | Reset reCaptcha on user screen
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
USERNAME_LEN_MIN | Username is too short | Focus on input field
USERNAME_LEN_MAX | Username is too long | Focus on input field
USERNAME_INVALID | Username contains an illegal character | Focus in input field
USERNAME_DUP | Username is already taken | Focus on input field
PASSWORD_REQ | Password is required | Focus on input field
PASSWORD_LEN_MIN | Password must be 8 digits or longer | Focus on input field
PASSWORD_LEN_MAX | Password cannot exceed 32 characters | Focus on input field
PASSWORD_WEAK | Password is too weak | Focus on input field, Suggest a stronger password
PASSWORD_CONFIRM_MATCH | You must retype same password | Focus on input field
PASSWORD_INCORRECT | Incorrect password | Focus on input field
COUNTRY_INVALID | Select a country | Focus on input field
TERMS_UNCHECKED | You must agree with our Terms & Conditions | Focus on checkbox
LOGIN_ID_REQ | Login ID is required | Focus on field
LOGIN_ID_INVALID | Login ID contains an illegal character | Focus on field
LOGIN_ID_UNKNOWN | No such user is registered | Focus on field
USER_STATUS_DISABLED | User account status is disabled | Focus on field
