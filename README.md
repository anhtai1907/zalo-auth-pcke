# Zalo Auth PCKE

Zalo-Auth-PCKE is a project that allows users to authenticate and authorize for an app with Zalo using the Proof Key for Code Exchange (PCKE) technique. It provides a secure and reliable way to integrate Zalo authentication into your application.

## Features

- Seamless Zalo authentication and authorization flow using PCKE.
- Secure handling of user credentials and access tokens.
- Integration with Zalo API for retrieving user information and performing app-specific actions.

## Installation

- Clone the repository

    ```bash
    git clone https://github.com/anhtai1907/zalo-auth-pcke.git
    cd zalo-auth-pcke
    ```

- Install dependencies:
    ```bash
    composer install
    ```

- Copy the example environment file and rename it to .env:

    ```bash
    cp .env.example .env
    ```

- Open the .env file and update the configuration variables with your Zalo app credentials.

- Move the project folder to your web server directory.

## Usage

Access <your-url>/zalo.php, authenticate, and get the access token and refresh token in the /authenticate.php

```php
// authenticate.php

// Perform any necessary post-authentication actions using the access token
<script>
    const message = {
        status: '<?= $isSuccessful ? 'true' : 'false' ?>',
        oaAuth: {
            oaId: '<?= $oaId ?>',
            accessToken: '<?= $accessToken ?? "" ?>',
            refreshToken: '<?= $refreshToken ?? "" ?>'
        },
        error: '<?= $error ?>'
    };

    if (window.opener) {
        window.opener.postMessage(JSON.stringify(message), window.origin);
    }

    // Close the popup window
    window.close();
</script>
```

Customize the authentication and authorization workflow to match your project's requirements. You can modify the provided examples or create your own logic.

## Contributing

Contributions are always welcome!

If you find any issues or have suggestions for improvements, please feel free to submit a pull request or open an issue in the repository.

## License

This project is licensed under the [MIT License](https://choosealicense.com/licenses/mit)