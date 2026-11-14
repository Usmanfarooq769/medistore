<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-bs-theme="light" data-header-styles="light" data-menu-styles="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#8b7eff">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/medistore-icon-192.png">

    <title>{{ setting('store_name', 'MediStore') }} – Medical Store Management</title>

    <script>
        // apply saved theme before first paint (Rixzo uses data-theme-mode, Bootstrap uses data-bs-theme)
        (function () {
            var t = localStorage.getItem('theme') || 'light', h = document.documentElement;
            h.setAttribute('data-theme-mode', t);
            h.setAttribute('data-bs-theme', t);
            h.setAttribute('data-header-styles', t);
            h.setAttribute('data-menu-styles', t);
        })();
    </script>

    <!-- Rixzo theme (Bootstrap 5.3 build + theme styles + Remix icons) -->
    <link href="/rixzo/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="/rixzo/css/styles.min.css" rel="stylesheet">
    <link href="/rixzo/css/icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;600&display=swap" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    @vite(['resources/js/app.js'])
</head>
<body>
    <div id="app"></div>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
