<!DOCTYPE html>
<html>
<head>
    <title>Zalo OA Authorization</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="./assets/styles.css">
</head>
<body>
    <div class="container">
        <div>
            <button id="btn-zalo" onclick="login()">
                <img src="./assets/icon-zalo.png"/>
                <span>Continue with Zalo</span>
            </button>
        </div>
    </div>
    <script>
        const isIFrame = () => window.self !== window.top;
        const sendMessage = (message) => {
            window.parent.postMessage(
                JSON.stringify({ ...message }),
                '*'
            )
        }
        const login = () => {
            window.open(
                "authenticate.php",
                "ZaloPopup",
                "popup"
            );
        }

        window.addEventListener("message", (event) => {
            if (event.origin !== window.origin) return;

            try {
                const data = JSON.parse(event.data);

                // Post message to the parent window
                if (isIFrame()) {
                    if (data?.status === 'true') {
                        sendMessage(data.oaAuth);
                    } else {
                        sendMessage({error: data?.error ?? 'Unexpected error'});
                    }
                }
            } catch (error) {
                console.log('Failed to parse message from popup', event.data);
            }
        });
    </script>
</body>
</html>