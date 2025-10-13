<?php
include "conexion.php";
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

switch($method){
    case "GET":
        if(isset($_GET["accion"]) && $_GET["accion"] === "productos"){
            $result = $conn->query("SELECT codigoproducto, nombre 
                                    FROM productos 
                                    WHERE tipoproducto = 'Producto Terminado'");
            $data = [];
            while($row = $result->fetch_assoc()){ $data[] = $row; }
            echo json_encode($data);
            exit;
        }

       
        if(isset($_GET["producto"])){
            $producto = $_GET["producto"];
            $result = $conn->query("SELECT * FROM formula WHERE producto = '$producto'");
            $data = [];
            while($row = $result->fetch_assoc()){ $data[] = $row; }
            echo json_encode($data);
            exit;
        }

  
        if(isset($_GET["nroformula"])){
            $nroformula = $_GET["nroformula"];
            $sql = "SELECT fd.*, p.nombre, p.tipoproducto 
                    FROM formuladetalle fd
                    JOIN productos p ON fd.componentes = p.codigoproducto
                    WHERE fd.nroformula = '$nroformula'";
            $result = $conn->query($sql);
            $data = [];
            while($row = $result->fetch_assoc()){ $data[] = $row; }
            echo json_encode($data);
            exit;
        }
        break;

  
    case "POST":
        $data = json_decode(file_get_contents("php://input"), true);

        $nroformula = $data["nroformula"];
        $producto = $data["producto"];
        $version = $data["version"];
        $fecha = date("Y-m-d");

        $sql = "INSERT INTO formula (nroformula, producto, version, fecha)
                VALUES ('$nroformula','$producto','$version','$fecha')";
        $conn->query($sql);

        foreach($data["detalle"] as $d){
            $sql = "INSERT INTO formuladetalle  (nroformula, producto, componentes, cantidad, orden, puesto)
                    VALUES                      ('$nroformula','$producto','$comp','$cant','$ord','$pues')";
            $conn->query($sql);
        }

        echo json_encode(["success"=>true]);
        break;

   
    case "DELETE":
        $data = json_decode(file_get_contents("php://input"), true);
        $nroformula = $data["nroformula"];
        $conn->query("DELETE FROM formuladetalle WHERE nroformula='$nroformula'");
        $conn->query("DELETE FROM formula WHERE nroformula='$nroformula'");
        echo json_encode(["success"=>true]);
        break;
}
