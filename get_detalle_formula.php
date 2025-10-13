<?php
include "conexion.php";

$nroformula = $_GET["nroformula"];

$sql = "SELECT fd.componentes, fd.cantidad, p.nombre, pd.stock, pr.tipoproducto
        FROM formuladetalle fd
        JOIN productos p ON fd.componentes = p.codigoproducto
        JOIN productodetalle pd ON fd.componentes = pd.codigoproducto
        JOIN productos pr ON fd.componentes = pr.codigoproducto
        WHERE fd.nroformula = '$nroformula'";

$result = $conn->query($sql);

$detalle = [];
while($row = $result->fetch_assoc()){
    $detalle[] = $row;
}

echo json_encode($detalle);
