<?php
function runError500()
{
    header('HTTP/1.1 500 Internal Server Error');

    $html = <<<HTML
 <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 Internal Server Error</title>
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
            color: #e67e22;
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
        .details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            text-align: left;
            font-family: monospace;
            color: #777;
            font-size: 14px;
        }
        .details p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="icon">⚠️</div>
    <h1>500</h1>
    <h2>Internal Server Error</h2>
    <p>Sorry, something went wrong on our servers. We're working to fix the issue as soon as possible.</p>
    <div class="details">
        <p>Error ID: <?php echo uniqid(); ?></p>
        <p>Time: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    <a href="/" class="button">Refresh Page</a>
    <a href="/contact" class="button" style="background-color: #2ecc71; margin-left: 10px;">Report Issue</a>
</div>
</body>
</html>
HTML;

    echo $html;
    die();
}