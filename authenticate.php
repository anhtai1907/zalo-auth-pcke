<?php
require_once __DIR__ . '/helper/ZaloHelper.php';

use Zalo\Authentication\ZaloToken;

$error        = null;
$zaloToken    = null;
$oaId         = $_GET['oa_id'] ?? null;
try {
    $zaloHelper = new ZaloHelper();
    $zaloToken  = $zaloHelper->authenticate();
} catch (Exception $exception) {
    $error = $exception->getMessage();
}

$isSuccessful = ($zaloToken instanceof ZaloToken) && ($error === null);
$accessToken  = $isSuccessful ? $zaloToken->getAccessToken() : null;
$refreshToken = $isSuccessful ? $zaloToken->getRefreshToken() : null;
$expiresAt    = $isSuccessful ? $zaloToken->getAccessTokenExpiresAt() : null;
?>
<!DOCTYPE html>
<html>
<!-- <head> -->
    <title>Zalo OA Authorization</title>
    <meta charset="UTF-8">
</head>
<body>
    <script>
        // Pass the result to the parent window
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
</body>
</html>