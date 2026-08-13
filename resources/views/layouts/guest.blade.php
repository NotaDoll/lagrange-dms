<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login') — La Grange DMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bs-primary: #630a4b;
            --bs-primary-rgb: 99, 10, 75;
            --bs-btn-primary-bg: #630a4b;
            --bs-btn-primary-border-color: #630a4b;
            --bs-btn-primary-hover-bg: #4f083d;
            --bs-btn-primary-hover-border-color: #4f083d;
            --bs-btn-primary-active-bg: #43072f;
            --bs-btn-primary-active-border-color: #43072f;
        }

        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-5">
                <div class="text-center mb-4">
                    <img src="{{ asset('images/lg-logo.png') }}" alt="La Grange DMS" style="max-width: 220px; height: auto; display: inline-block;">
                </div>
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
