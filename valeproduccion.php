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
        $cantidad_planificada = intval($data['cantidad_planificada']);
        $producto_padre = $conexion->real_escape_string($data['producto_padre']);

        $sql = "SELECT * FROM valeproduccion 
                WHERE codigoop='$codigoop' AND estado='pendiente' 
                LIMIT 1";
        $res = $conexion->query($sql);

        if ($res && $res->num_rows > 0) {
            $vale = $res->fetch_assoc();
        } else {
            $sqlNum = "SELECT IFNULL(MAX(numero),0)+1 AS nuevoNumero FROM valeproduccion";
            $nuevoNumero = $conexion->query($sqlNum)->fetch_assoc()['nuevoNumero'];

            $sqlInsert = "INSERT INTO valeproduccion (codigo, numero, codigoop, fecha, cantidad_planificada, estado)
                        VALUES (CONCAT('VP', LPAD($nuevoNumero, 5, '0')), $nuevoNumero, '$codigoop', NOW(), $cantidad_planificada, 'pendiente')";
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
        $id_vale = isset($_GET['id_vale']) ? intval($_GET['id_vale']) : 0;

        $sql = "SELECT 
                    fd.componentes AS codigoproducto, 
                    p.nombre, 
                    fd.cantidad, 
                    fd.puesto AS puesto, 
                    fd.id_formuladetalle AS orden,
                    CASE 
                        WHEN vpd.id_detalle IS NOT NULL THEN 1 
                        ELSE 0 
                    END AS ensamblado
                FROM formuladetalle fd
                INNER JOIN productos p ON p.codigoproducto = fd.componentes
                LEFT JOIN valeproducciondetalle vpd 
                    ON vpd.producto_hijo = fd.componentes 
                    AND vpd.id_vale = '$id_vale'
                WHERE fd.nroformula='$nroformula' 
                AND fd.puesto='$puesto'
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
        $nropuesto = intval($data['nropuesto']);

        // Verificar si ya existe ese componente
        $check = $conexion->query("SELECT 1 FROM valeproducciondetalle 
                                WHERE id_vale=$id_vale 
                                AND producto_hijo='$producto_hijo' 
                                AND nropuesto=$nropuesto");
        if ($check && $check->num_rows > 0) {
            echo json_encode(["success" => true, "message" => "Ya ensamblado"]);
            break;
        }

        // Insertar detalle
        $sql = "INSERT INTO valeproducciondetalle (id_vale, producto_padre, producto_hijo, nropuesto, fecha, estado, nroserie)
                VALUES ($id_vale, '$producto_padre', '$producto_hijo', $nropuesto, NOW(), 'terminado', CONCAT($id_vale, '-', $nropuesto,'-', '$producto_padre' ) )";
        $ok = $conexion->query($sql);

        echo json_encode(["success" => $ok]);
        break;
    
    case "cerrar_y_crear_vale":
        $id_vale = intval($data["id_vale"]);
        $codigoop = $conexion->real_escape_string($data["codigoop"]);
        $producto_padre = $conexion->real_escape_string($data["producto_padre"]);
        $cantidad_planificada = intval($data["cantidad_planificada"]);

        // 🔒 1️⃣ Cerrar el vale actual
        $conexion->query("UPDATE valeproduccion 
                        SET estado='terminada', cantidad_producida=1 
                        WHERE id_vale=$id_vale");

        // 🔄 2️⃣ Actualizar cantidad_producida en la orden de producción
        $conexion->query("UPDATE ordenproduccion 
                        SET cantidad_producida = cantidad_producida + 1 
                        WHERE id_orden='$codigoop'");

        // 🔢 3️⃣ Generar nuevo número de vale
        $sqlNum = "SELECT IFNULL(MAX(numero),0)+1 AS nuevoNumero FROM valeproduccion";
        $nuevoNumero = $conexion->query($sqlNum)->fetch_assoc()['nuevoNumero'];

        // 🆕 4️⃣ Crear nuevo vale
        $codigoVale = 'VP' . str_pad($nuevoNumero, 5, '0', STR_PAD_LEFT);
        $sqlInsert = "INSERT INTO valeproduccion 
                    (codigo, numero, codigoop, fecha, cantidad_planificada, cantidad_producida, estado)
                    VALUES ('$codigoVale', $nuevoNumero, '$codigoop', NOW(), $cantidad_planificada, 0, 'pendiente')";
        $conexion->query($sqlInsert);
        $idNuevoVale = $conexion->insert_id;

        // 📊 5️⃣ Consultar la cantidad actualizada
        $res = $conexion->query("SELECT cantidad_producida FROM ordenproduccion WHERE id_orden='$codigoop'");
        $nuevaCant = $res->fetch_assoc()['cantidad_producida'];

        echo json_encode([
            "success" => true,
            "nueva_cantidad_producida" => $nuevaCant,
            "nuevo_vale" => [
                "id_vale" => $idNuevoVale,
                "numero" => $nuevoNumero,
                "codigo" => $codigoVale,
                "estado" => "pendiente"
            ]
        ]);
        break;

    case "verificar_cierre_vale":
        $id_vale = intval($data['id_vale']);
        $codigoop = $conexion->real_escape_string($data['codigoop']);
        $nroformula = $conexion->real_escape_string($data['nroformula']);

        // Total de componentes de la fórmula
        $total = $conexion->query("SELECT COUNT(*) AS total FROM formuladetalle WHERE nroformula='$nroformula'")
                        ->fetch_assoc()['total'];

        // Total ensamblados (vale actual)
        $hechos = $conexion->query("SELECT COUNT(*) AS hechos FROM valeproducciondetalle WHERE id_vale=$id_vale")
                        ->fetch_assoc()['hechos'];

        $completo = ($hechos >= $total);

        echo json_encode(["success" => true, "completo" => $completo]);
        break;

    default:
        echo json_encode(["error" => "Acción no válida"]);
}

$conexion->close();
?>


