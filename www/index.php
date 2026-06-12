<?php
declare(strict_types=1);
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
?><!doctype html>
<html lang="nl">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>West land | OSRS Clan</title>
    <link rel="stylesheet" href="public/styles.css">
  </head>
  <body>
    <div id="app">
      <main class="empty">
        West land wordt geladen. Zie je dit lang staan, controleer dan of PHP en de database actief zijn.
      </main>
    </div>
    <script src="public/app.js"></script>
  </body>
</html>
