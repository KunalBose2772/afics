<?php
// doc/index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Documantraa</title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0d2b4a">
    <script>
        // Check if service worker is supported
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('sw.js').then(function(registration) {
                    console.log('SW Registered!', registration.scope);
                    // Redirect to CRM after registering SW
                    window.location.replace("crm/login");
                }).catch(function(err) {
                    window.location.replace("crm/login");
                });
            });
        } else {
            window.location.replace("crm/login");
        }
    </script>
</head>
<body style="background-color: #0d2b4a; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; color: white; font-family: sans-serif;">
    <p>Loading Documantraa CRM...</p>
</body>
</html>