<?php
header("Access-Control-Allow-Origin: https://pos-nova.vercel.app");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token, Authorization, Accept, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

echo "✅ CORS headers working";
