<?php

    if(!file_exists(__DIR__ . "/../../config/config.php")){
        echo "Failed to get database config file.";
        echo "Looked at location: " . __DIR__ . "/../../config/config.php";
        exit(1);
    }
    require(__DIR__ . "/../../config/config.php");

    $conn = new mysqli($hostname, $username, $password, $database, $port);
    $conn->query("DELETE FROM `users_tokens` WHERE `valid_till` < CURRENT_TIMESTAMP()");
    exit(0);

?>