<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 Page Not Found</title>
<style type="text/css">
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    min-height: 100%;
    height: 100%;
}

body {
    background-color: #f0f0f0;
    color: #333;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 20px;
}

#container {
    text-align: center;
    max-width: 600px;
}

.error-code-label {
    font-size: 14px;
    color: #999;
    margin-bottom: 10px;
    font-weight: 500;
}

h1 {
    font-size: 84px;
    font-weight: 600;
    color: #038F5C;
    border: none;
    padding: 0;
    margin: 0 0 20px 0;
    line-height: 1.2;
}

p {
    font-size: 16px;
    color: #666;
    margin: 0 0 40px 0;
    line-height: 1.6;
}

a {
    display: inline-block;
    padding: 12px 32px;
    background: linear-gradient(135deg, #1AA346 0%, #0e7a32 100%);
    color: #ffffff;
    text-decoration: none;
    border-radius: 4px;
    font-weight: 500;
    font-size: 15px;
    transition: background-color .3s ease;
}

a:hover {
    background-color: #555;
}

code {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    background-color: #f3f4f6;
    border: 1px solid #e5e7eb;
    color: #111827;
    display: block;
    margin: 14px 0;
    padding: 12px 10px;
    border-radius: 6px;
    text-align: left;
    overflow-x: auto;
}

@media (max-width: 768px) {
    h1 {
        font-size: 64px;
        margin-bottom: 16px;
    }
    
    p {
        font-size: 14px;
        margin-bottom: 32px;
    }
}
</style>
</head>
<body>
    <div id="container">
        <div class="error-code-label"><?php echo $heading; ?></div>
        <h1>404</h1>
        <?php echo $message; ?>
        <br><a href="https://app.derzsystem.com">Back to Dashboard</a>
    </div>
</body>
</html>