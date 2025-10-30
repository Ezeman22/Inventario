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

        //Cerrar el vale actual
        $conexion->query("UPDATE valeproduccion 
                        SET estado='terminada', cantidad_producida=1 
                        WHERE id_vale=$id_vale");

        //Actualizar cantidad_producida en la orden de producción
        $conexion->query("UPDATE ordenproduccion 
                        SET cantidad_producida = cantidad_producida + 1 
                        WHERE id_orden='$codigoop'");

        //Obtener los componentes utilizados en este vale
        $sqlComp = "SELECT vpd.producto_hijo, fd.cantidad
                    FROM valeproducciondetalle vpd
                    INNER JOIN valeproduccion vp ON vp.id_vale = vpd.id_vale
                    INNER JOIN formuladetalle fd ON fd.componentes = vpd.producto_hijo
                    INNER JOIN ordenproduccion op ON op.id_orden = vp.codigoop
                    WHERE vpd.id_vale = $id_vale
                    GROUP BY vpd.producto_hijo";
        $resComp = $conexion->query($sqlComp);

        //Actualizar stock de cada componente utilizado
        if ($resComp && $resComp->num_rows > 0) {
            while ($comp = $resComp->fetch_assoc()) {
                $producto = $comp["producto_hijo"];
                $cantidadUsada = floatval($comp["cantidad"]);

                // Disminuir stock en el depósito
                $conexion->query("
                    UPDATE productodetalle 
                    SET stock = GREATEST(stock - $cantidadUsada, 0),
                        fecha_movimiento = NOW()
                    WHERE codigoproducto = '$producto'
                    LIMIT 1
                ");
            }
        }

        //Generar nuevo número de vale
        $sqlNum = "SELECT IFNULL(MAX(numero),0)+1 AS nuevoNumero FROM valeproduccion";
        $nuevoNumero = $conexion->query($sqlNum)->fetch_assoc()['nuevoNumero'];

        //Crear nuevo vale
        $codigoVale = 'VP' . str_pad($nuevoNumero, 5, '0', STR_PAD_LEFT);
        $sqlInsert = "INSERT INTO valeproduccion 
                    (codigo, numero, codigoop, fecha, cantidad_planificada, cantidad_producida, estado)
                    VALUES ('$codigoVale', $nuevoNumero, '$codigoop', NOW(), $cantidad_planificada, 0, 'pendiente')";
        $conexion->query($sqlInsert);
        $idNuevoVale = $conexion->insert_id;

        //Consultar la cantidad actualizada
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

    case "vales_por_orden":
        if (!isset($_GET["id_orden"])) {
            echo json_encode(["error" => "Falta parámetro id_orden"]);
            exit;
        }

        $id_orden = intval($_GET["id_orden"]);

        $sql = "SELECT v.id_vale, v.codigo, v.fecha, v.estado
                FROM valeproduccion v
                WHERE v.codigoop  = $id_orden
                ORDER BY v.fecha DESC";

        $res = $conexion->query($sql);
        $vales = [];
        while ($row = $res->fetch_assoc()) {
            $vales[] = $row;
        }

        echo json_encode($vales);
        break;

    case "detalle_vale":
        if (!isset($_GET["id_vale"])) {
            echo json_encode(["error" => "Falta parámetro id_vale"]);
            exit;
        }

        $id_vale = intval($_GET["id_vale"]);

        $sql = "SELECT 
                    vd.producto_hijo,
                    p.nombre,
                    vd.nropuesto,
               
                    DATE(vd.fecha) AS fecha
                FROM valeproducciondetalle vd
                LEFT JOIN productos p ON p.codigoproducto = vd.producto_hijo
                WHERE vd.id_vale = $id_vale
                ORDER BY vd.nropuesto, p.nombre;";

        $res = $conexion->query($sql);
        $detalle = [];
        while ($row = $res->fetch_assoc()) {
            $detalle[] = $row;
        }

        echo json_encode($detalle);
        break;

    case "validar_puesto_anterior":
        $input = json_decode(file_get_contents("php://input"), true);
        $codigoop = intval($input["codigoop"]);
        $nroformula = $conexion->real_escape_string($input["nroformula"]);
        $id_vale = intval($input["id_vale"]);
        $puestoActual = intval($input["puesto_actual"]);

        // Si es el primer puesto, no hay nada que validar
        if ($puestoActual <= 1) {
            echo json_encode(["permitido" => true]);
            break;
        }

        // ✅ Obtenemos el producto de la orden de producción
        $sqlProducto = "SELECT producto FROM ordenproduccion WHERE id_orden = $codigoop LIMIT 1";
        $resProducto = $conexion->query($sqlProducto);
        $producto = $resProducto->fetch_assoc()["producto"] ?? "";

        // ✅ Calculamos el puesto anterior
        $puestoAnterior = $puestoActual - 1;

        // ✅ Consultamos si el puesto anterior tiene todos los componentes terminados
        $sql = "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN vpd.estado = 'terminado' THEN 1 ELSE 0 END) AS terminados
                FROM formuladetalle fd
                LEFT JOIN valeproducciondetalle vpd 
                    ON vpd.producto_hijo = fd.componentes
                    AND vpd.nropuesto = $puestoAnterior
                    AND vpd.id_vale = $id_vale
                WHERE fd.nroformula = '$nroformula'
                AND fd.puesto = '$puestoAnterior'
                AND fd.producto = '$producto'";

        $res = $conexion->query($sql);
        $row = $res->fetch_assoc();

        $total = intval($row["total"]);
        $terminados = intval($row["terminados"]);
        $permitido = ($total > 0 && $terminados === $total);

        echo json_encode(["permitido" => $permitido]);
        break;
        
    default:
        echo json_encode(["error" => "Acción no válida"]);
}

$conexion->close();
?>


