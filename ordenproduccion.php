<?php
include "conexion.php";
header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

switch($method){
    // 📌 Listar todas las órdenes
    case "GET":
        if(isset($_GET["id"])){
            $id = $_GET["id"];
            $sql = "SELECT o.*, f.producto AS producto_formula
                    FROM ordenproduccion o
                    JOIN formula f ON o.nroformula = f.nroformula
                    WHERE o.id_orden = $id";
            $res = $conn->query($sql);
            $orden = $res->fetch_assoc();

            $det = [];
            $res = $conn->query("SELECT * FROM ordenproducciondetalle WHERE id_orden = $id");
            while($r = $res->fetch_assoc()) $det[] = $r;

            echo json_encode(["orden"=>$orden,"detalle"=>$det]);
            exit;
        }

        $result = $conn->query("SELECT * FROM ordenproduccion ORDER BY fecha DESC");
        $data = [];
        while($row = $result->fetch_assoc()){ $data[] = $row; }
        echo json_encode($data);
        break;

    // 📌 Crear nueva orden
    case "POST":
        $data = json_decode(file_get_contents("php://input"), true);
        $producto = $data["producto"];
        $nroformula = $data["nroformula"];
        $cantidad = $data["cantidad"];
        $fecha = date("Y-m-d");

        $sql = "INSERT INTO ordenproduccion (producto, nroformula, cantidad, fecha)
                VALUES ('$producto','$nroformula','$cantidad','$fecha')";
        $conn->query($sql);
        $id = $conn->insert_id;

        foreach($data["detalle"] as $d){
            $sql = "INSERT INTO ordenproducciondetalle (id_orden, componente, cantidad)
                    VALUES ($id,'{$d['componente']}','{$d['cantidad']}')";
            $conn->query($sql);
        }

        echo json_encode(["success"=>true,"id"=>$id]);
        break;

    // 📌 Eliminar orden
    case "DELETE":
        $data = json_decode(file_get_contents("php://input"), true);
        $id = $data["id"];
        $conn->query("DELETE FROM ordenproducciondetalle WHERE id_orden=$id");
        $conn->query("DELETE FROM ordenproduccion WHERE id_orden=$id");
        echo json_encode(["success"=>true]);
        break;
}
$conn->close();
?>