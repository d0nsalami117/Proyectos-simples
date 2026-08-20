<?php
    $servername = "localhost";
    $username = "Donsalami"; 
    $password = "6hVebwYD00?"; 
    $database = "gimnasio";  
    
    $F = mysqli_connect($servername, $username, $password, $database);
    if (!$F) die('Error de conexión: ' . mysqli_connect_error());
    mysqli_set_charset($F, "utf8mb4");

?>