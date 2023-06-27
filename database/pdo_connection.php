<?php 
$ServerName="localhost";
$userName="root";
$password="";

try {
    $conn=new PDO("mysql:host=$ServerName;dbname=animate-form-1",$userName,$password);

    $conn->setAttribute(PDO::ATTR_ERRMODE , PDO::ERRMODE_EXCEPTION);

    echo "success";
    
} catch (PDOException $e) {
    echo "connection field".$e->getMessage();
}

?>