<?php
session_start();
echo "<h1>Session Debug Information</h1>";
echo "<p>नीचे आपके सेशन में संग्रहीत सभी जानकारी दी गई है। कृपया जांचें कि <strong>block_id</strong> यहाँ मौजूद है या नहीं।</p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>