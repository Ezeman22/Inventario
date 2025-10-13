<?php
include "conexion.php";
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

switch($method){
    case "GET":
        $result = $conn->query("SELECT * FROM productos ORDER BY Id_Producto ASC");
        $productos = [];
        while($row = $result->fetch_assoc()){
            $productos[] = $row;
        }
        echo json_encode($productos);
        break;

    case "POST":
        $data = json_decode(file_get_contents("php://input"), true);
        $sql = "INSERT INTO productos (codigoproducto, nombre, tipoproducto, costoproducto, unidadmedida)
                VALUES ('{$data['codigoproducto']}', '{$data['nombre']}', '{$data['tipoproducto']}',
                        '{$data['costoproducto']}', '{$data['unidadmedida']}')";
        if($conn->query($sql)){
            echo json_encode(["success" => true, "id" => $conn->insert_id]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case "PUT":
        $data = json_decode(file_get_contents("php://input"), true);
        $sql = "UPDATE productos 
                SET nombre='{$data['nombre']}', tipoproducto='{$data['tipoproducto']}',
                    costoproducto='{$data['costoproducto']}', unidadmedida='{$data['unidadmedida']}'
                WHERE codigoproducto='{$data['codigoproducto']}'";
        if($conn->query($sql)){
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;

    case "DELETE":
        $data = json_decode(file_get_contents("php://input"), true);
        $sql = "DELETE FROM productos WHERE codigoproducto='{$data['codigoproducto']}'";
        if($conn->query($sql)){
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => $conn->error]);
        }
        break;
}
?>
