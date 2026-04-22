<?php
function runError404()
{
    header('HTTP/1.1 404 Not Found');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
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
            color: #3498db;
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
        .search-box {
            margin: 20px 0;
        }
        .search-box input {
            padding: 10px;
            width: 70%;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .search-box button {
            padding: 10px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .search-box button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔍</div>
        <h1>404</h1>
        <h2>Page Not Found</h2>
        <p>The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        <div class="search-box">
            <input type="text" placeholder="Search for content...">
            <button>Search</button>
        </div>
        <a href="/" class="button">Return to Homepage</a>
    </div>
</body>
</html>
HTML;

    echo $html;
    die();
}