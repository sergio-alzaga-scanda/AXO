<?php
require "../config/helpers.php";
checkLogin();
if(!isAdmin()) { die("No autorizado"); }
require "../config/db.php";

$id = $_GET["id"];
$conn->prepare("DELETE FROM tecnicos WHERE id = ?")->execute([$id]);
header("Location: index.php");
