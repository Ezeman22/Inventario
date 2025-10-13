<?php
include "conexion.php";

$sql = "SELECT f.nroformula, p.nombre as 'producto' FROM formula f inner join productos p on f.producto = p.codigoproducto;";
$result = $conn->query($sql);

$formulas = [];
while($row = $result->fetch_assoc()){
    $formulas[] = $row;
}

echo json_encode($formulas);
