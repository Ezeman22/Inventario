<?php
include "conexion.php";
header("Content-Type: application/json");

$method = $_SERVER["REQUEST_METHOD"];

switch ($method) {

    // 📌 Listar todas las órdenes de producción (para el selector)
    case "GET":
        if (isset($_GET["accion"]) && $_GET["accion"] == "ordenes") {
            $sql = "SELECT o.id_orden, o.producto, p.nombre, o.cantidad AS cantidad_planificada
                    FROM ordenproduccion o
                    JOIN productos p ON o.producto = p.codigoproducto
                    ORDER BY o.id_orden DESC";
            $result = $conn->query($sql);
            $data = [];
            while ($row = $result->fetch_assoc()) $data[] = $row;
            echo json_encode($data);
            exit;
        }

        // 📌 Detalle de una orden específica
        if (isset($_GET["id_orden"])) {
            $id = intval($_GET["id_orden"]);

            // Datos de la orden
            $sql = "SELECT o.id_orden, o.producto, p.nombre, o.cantidad AS cantidad_planificada, o.nroformula
                    FROM ordenproduccion o
                    JOIN productos p ON o.producto = p.codigoproducto
                    WHERE o.id_orden = $id";
            $res = $conn->query($sql);
            $orden = $res->fetch_assoc();

            // Cantidad producida (sumatoria de vales terminados)
            $sql = "SELECT IFNULL(SUM(v.cantidad_producida),0) AS cantidad_producida
                    FROM valeproduccion v
                    WHERE v.codigoop = $id AND v.estado = 'Terminada'";
            $res = $conn->query($sql);
            $produccion = $res->fetch_assoc()["cantidad_producida"];

            // Listado de vales asociados
            $sql = "SELECT id_vale, codigo, numero, fecha, cantidad_planificada, cantidad_producida, nropuesto, estado
                    FROM valeproduccion
                    WHERE codigoop = $id and estado = 'pendiente'
                    ORDER BY nropuesto, id_vale";
            $res = $conn->query($sql);
            $vales = $res->fetch_assoc();
            //$vales = [];
            //$res = $conn->query($sql);
            //while ($r = $res->fetch_assoc()) $vales[] = $r;


            echo json_encode([
                "orden" => $orden,
                "cantidad_producida" => $produccion,
                "vales" => $vales
            ]);
            exit;
        }
        break;
}
?>
