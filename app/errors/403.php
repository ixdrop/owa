<?php
function runError403()
{
    header('HTTP/1.1 403 Forbidden');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            text-align: center;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 600px;
            width: 90%;
        }
        h1 {
            color: #e74c3c;
            font-size: 72px;
            margin: 0;
            line-height: 1;
        }
        h2 {
            margin-top: 10px;
            color: #555;
            font-weight: normal;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        p {
            line-height: 1.6;
            margin: 20px 0;
            color: #666;
        }
        .button {
            display: inline-block;
            background-color: #3498db;
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔒</div>
        <h1>403</h1>
        <h2>Access Denied</h2>
        <p>Sorry, you don't have permission to access this resource. Please check your credentials or contact the site administrator if you believe this is an error.</p>
        <a href="/" class="button">Go to Homepage</a>
    </div>
</body>
</html>
HTML;

    echo $html;
    die();
}