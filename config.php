<?php
$host="localhost";
$username="root";
$password="";
$databse="taxi_management";

$conn=mysqli_connect($host,$username,$password,$database);
if(!$conn){
    die("databse connection failed: ".mysqli_connect_error());
    
}
?>