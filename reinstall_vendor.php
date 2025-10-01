<?php
// reinstall_vendor.php

// اسم الفايلات اللي عايز تمسحها
$vendorDir = __DIR__ . '/vendor';
$lockFile = __DIR__ . '/composer.lock';

// دالة مسح المجلد كامل
function deleteDir($dirPath) {
    if (! is_dir($dirPath)) return;
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
    }
    rmdir($dirPath);
}

// 1. مسح vendor
if (is_dir($vendorDir)) {
    deleteDir($vendorDir);
    echo "✅ Vendor directory deleted.<br>";
} else {
    echo "⚠️ Vendor directory not found.<br>";
}

// 2. مسح composer.lock
if (file_exists($lockFile)) {
    unlink($lockFile);
    echo "✅ composer.lock deleted.<br>";
} else {
    echo "⚠️ composer.lock not found.<br>";
}

// 3. تشغيل composer install
echo "⏳ Running composer install...<br>";
$output = shell_exec('composer install 2>&1');
echo "<pre>$output</pre>";
