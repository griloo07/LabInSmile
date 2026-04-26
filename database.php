<?php
$conn = new mysqli("localhost", "root", "", "laboratorio");

if ($conn->connect_error) {
    die("Erro na ligação à base de dados: " . $conn->connect_error);
}
?>