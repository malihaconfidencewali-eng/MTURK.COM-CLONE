<?php
require 'db.php';
session_unset();
session_destroy();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Logged out</title>
<style>body{font-family:Inter,Arial;text-align:center;padding:60px;background:#fff}</style>
</head><body>
<h3>You are logged out.</h3>
<script>setTimeout(()=>window.location='index.php',700);</script>
</body></html>
