<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

$conexion = new mysqli("localhost", "root", "", "inventario"); // <-- cambia por tus datos
if ($conexion->connect_error) {
    die(json_encode(["error" => "Error de conexión: " . $conexion->connect_error]));
}

$data = json_decode(file_get_contents("php://input"), true);
$accion = $_GET['accion'] ?? ($data['accion'] ?? '');

switch ($accion) {

    // 🔹 Crear o recuperar vale pendiente
    case "crear_o_recuperar_vale":
        $codigoop = $conexion->real_escape_string($data['codigoop']);
        $nropuesto = intval($data['nropuesto']);
        $cantidad_planificada = intval($data['cantidad_planificada']);
        $producto_padre = $conexion->real_escape_string($data['producto_padre']);

        // Buscar vale pendiente
        $sql = "SELECT * FROM valeproduccion 
                WHERE codigoop='$codigoop' AND nropuesto='$nropuesto' AND estado='pendiente' 
                LIMIT 1";
        $res = $conexion->query($sql);

        if ($res && $res->num_rows > 0) {
            $vale = $res->fetch_assoc();
        } else {
            // Crear nuevo número de vale
            $sqlNum = "SELECT IFNULL(MAX(numero),0)+1 AS nuevoNumero FROM valeproduccion";
            $nuevoNumero = $conexion->query($sqlNum)->fetch_assoc()['nuevoNumero'];

            $sqlInsert = "INSERT INTO valeproduccion (codigo, numero, codigoop, fecha, cantidad_producida, cantidad_planificada, nropuesto, estado)
                          VALUES (CONCAT('VP', LPAD($nuevoNumero, 5, '0')), $nuevoNumero, '$codigoop', NOW(), 0, $cantidad_planificada, $nropuesto, 'pendiente')";
            $conexion->query($sqlInsert);
            $id_vale = $conexion->insert_id;

            $vale = [
                "id_vale" => $id_vale,
                "numero" => $nuevoNumero,
                "codigoop" => $codigoop,
                "estado" => "pendiente"
            ];
        }

        echo json_encode($vale);
        break;

    // 🔹 Obtener componentes por fórmula y puesto
    case "componentes":
        $nroformula = $conexion->real_escape_string($_GET['nroformula']);
        $puesto = $conexion->real_escape_string($_GET['puesto']);

        $sql = "SELECT fd.componentes AS codigoproducto, p.nombre, fd.cantidad, fd.puesto AS puesto, fd.id_formuladetalle AS orden
                FROM formuladetalle fd
                INNER JOIN productos p ON p.codigoproducto = fd.componentes
                WHERE fd.nroformula='$nroformula' AND fd.puesto='$puesto'
                ORDER BY fd.id_formuladetalle";
        $res = $conexion->query($sql);
        $salida = [];
        while ($row = $res->fetch_assoc()) {
            $salida[] = $row;
        }
        echo json_encode($salida);
        break;

    // 🔹 Ensamblar un componente (guardar detalle del vale)
    case "ensamblar":
        $id_vale = intval($data['id_vale']);
        $codigoop = $conexion->real_escape_string($data['codigoop']);
        $producto_padre = $conexion->real_escape_string($data['producto_padre']);
        $producto_hijo = $conexion->real_escape_string($data['producto_hijo']);

        // Insertar el detalle del vale
        $sql = "INSERT INTO valeproducciondetalle (codigo_vale, producto_padre, producto_hijo, fecha, estado)
                VALUES ('$id_vale', '$producto_padre', '$producto_hijo', NOW(), 'terminado')";
        $ok = $conexion->query($sql);

        echo json_encode(["success" => $ok]);
        break;

    // 🔹 Cerrar vale (cuando se ensamblan todos los componentes)
    case "cerrar_vale":
        $id_vale = intval($data['id_vale']);
        $codigoop = $conexion->real_escape_string($data['codigoop']);

        // Marcar vale como terminado
        $conexion->query("UPDATE valeproduccion SET estado='terminada', cantidad_producida=1 WHERE id_vale=$id_vale");

        // Sumar cantidad_producida en la orden de producción
        $conexion->query("UPDATE ordenproduccion SET cantidad_producida = cantidad_producida + 1 WHERE id_orden='$codigoop'");

        // Obtener nuevo total
        $res = $conexion->query("SELECT cantidad_producida FROM ordenproduccion WHERE id_orden='$codigoop'");
        $nuevaCant = $res->fetch_assoc()['cantidad_producida'];

        echo json_encode(["success" => true, "nueva_cantidad_producida" => $nuevaCant]);
        break;

    default:
        echo json_encode(["error" => "Acción no válida"]);
}

$conexion->close();
?>


