<?php
// إعدادات الاتصال بـ MySQL بتاع Laragon
$host = '127.0.0.1';
$user = 'root'; // غيّر لو مختلف
$password = ''; // غيّر لو فيه كلمة مرور
$port = 3307; // تغيير لـ 3307 بناءً على إعداداتك

// الاتصال بـ MySQL
$conn = new mysqli($host, $user, $password, '', $port);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

echo "⏳ Checking database 'pos'...\n";

// فحص لو قاعدة البيانات موجودة
$result = $conn->query("SHOW DATABASES LIKE 'pos'");
if ($result->num_rows == 0) {
    echo "❌ Database 'pos' not found, creating it...\n";
    $sql = "CREATE DATABASE pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
    if ($conn->query($sql) === TRUE) {
        echo "✅ Database 'pos' created\n";
    } else {
        die("❌ Error creating database: " . $conn->error);
    }

    echo "⏳ Running migrations and seeding...\n";
    // تنفيذ الأوامر عبر shell
    $laravelPath = __DIR__; // المسار الحالي للمشروع
    $phpPath = 'C:\\laragon\\bin\\php\\php-8.2.27-nts-Win32-vs16-x64\\php.exe'; // غيّر المسار لو مختلف

    // الخطوة 1: تنفيذ الميجرشن
    $command1 = "\"$phpPath\" artisan migrate:fresh";
    echo "⏳ Executing: $command1\n";
    exec($command1, $output1, $return_var1);
    if ($return_var1 === 0) {
        echo "✅ Migration completed\n";
    } else {
        echo "❌ Error during migration: " . implode("\n", $output1) . "\n";
        die;
    }

    // الخطوة 2: تنفيذ DatabaseSeeder
    $command2 = "\"$phpPath\" artisan db:seed --class=DatabaseSeeder";
    echo "⏳ Executing: $command2\n";
    exec($command2, $output2, $return_var2);
    if ($return_var2 === 0) {
        echo "✅ DatabaseSeeder completed\n";
    } else {
        echo "❌ Error during DatabaseSeeder: " . implode("\n", $output2) . "\n";
        die;
    }

    // الخطوة 3: تنفيذ RolePermissionSeeder
    $command3 = "\"$phpPath\" artisan db:seed --class=RolePermissionSeeder";
    echo "⏳ Executing: $command3\n";
    exec($command3, $output3, $return_var3);
    if ($return_var3 === 0) {
        echo "✅ RolePermissionSeeder completed\n";
    } else {
        echo "❌ Error during RolePermissionSeeder: " . implode("\n", $output3) . "\n";
        die;
    }

    echo "✅ Migrations and seeding completed\n";
} else {
    echo "✅ Database 'pos' already exists\n";
}

$conn->close();
?>
