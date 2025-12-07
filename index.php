<?php

session_start();

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'padel2'); 

$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$mysqli->set_charset("utf8mb4");

if ($mysqli->connect_error) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        http_response_code(500);
        die(json_encode(['success' => false, 'message' => "ERROR DB: No se pudo conectar: " . $mysqli->connect_error]));
    } else {
        die("ERROR: No se pudo conectar a la base de datos. Verifique la configuración. Detalles: " . $mysqli->connect_error);
    }
}

$id_usuario_actual = $_SESSION['id_usuario'] ?? null;
$nombre_usuario_simulado = $_SESSION['nombre_usuario'] ?? 'Invitado';

if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// --- ARRAY FIJO DE DETALLES Y URLs DE IMAGEN ---
$detalles_fijos = [
    1 => [
        'descripcion' => 'Marco de carbono, balance ofensivo y gran punto dulce para golpes potentes.',
        'imagen_url' => 'https://www.elneverazo.com/wp-content/uploads/2025/03/Fenix-Black-Leo-1.jpg',
        'badge' => 'Top ventas'
    ],
    2 => [
        'descripcion' => 'Mayor control y precision para jugadores que dominan la estrategia.',
        'imagen_url' => 'https://justpadel.com/cdn/shop/files/427989824_781506747343327_5435887916175475402_n.jpg?v=1707909634&width=896',
        'badge' => 'Nuevo'
    ],
    3 => [
        'descripcion' => 'Pelotas de alta visibilidad con presion duradera, perfectas para torneos.',
        'imagen_url' => 'https://cdn.almacen.do/2024/08/Pelotas-de-Padel-Wilson-Padel-Premier-Speed-Raw-600x583.jpg',
        'badge' => 'Pack'
    ],
    4 => [
        'descripcion' => 'Paletero de excelente calidad con espacios para ropa, paletas, pelotas y zapatos.',
        'imagen_url' => 'https://www.padelnuestro.com/media/catalog/product/B/K/BKOR_OP_1200_12000_0762.jpg',
        'badge' => 'Club'
    ]
];
// ------------------------------------------------

$productos = [];
$sql_productos = "SELECT id_producto, nombre_producto, precio_producto FROM productos ORDER BY id_producto ASC";
if ($result = $mysqli->query($sql_productos)) {
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id_producto'];
        
        // Combina los datos de la DB con los detalles fijos
        if (isset($detalles_fijos[$id])) {
             $row['descripcion'] = $detalles_fijos[$id]['descripcion'];
             $row['imagen_url'] = $detalles_fijos[$id]['imagen_url'];
             $row['badge'] = $detalles_fijos[$id]['badge'];
        } else {
             // Fallback si no hay datos fijos
             $row['descripcion'] = 'Detalles no disponibles.';
             $row['imagen_url'] = ''; 
             $row['badge'] = 'Stock';
        }
        
        $productos[] = $row;
    }
    $result->free();
} else {
    error_log("Error al cargar productos: " . $mysqli->error);
}


if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    $action = $_GET['action'];

    switch ($action) {
        
        case 'register':
            $nombre = trim($_POST['nombre'] ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $contrasena = $_POST['contrasena'] ?? '';

            if (empty($nombre) || empty($apellido) || empty($correo) || empty($contrasena)) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
                exit;
            }
            
            $contrasena_hash = $contrasena; 

            $sql = "INSERT INTO usuarios (nombre, apellido, correo, contrasena) VALUES (?, ?, ?, ?)";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("ssss", $nombre, $apellido, $correo, $contrasena_hash);
                if ($stmt->execute()) {
                    $_SESSION['id_usuario'] = $stmt->insert_id;
                    $_SESSION['nombre_usuario'] = $nombre . ' ' . $apellido;
                    echo json_encode(['success' => true, 'message' => 'Registro exitoso. Bienvenido.']);
                } else {
                    if ($mysqli->errno == 1062) { 
                        echo json_encode(['success' => false, 'message' => 'El correo ya está registrado.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error DB al registrar: ' . $stmt->error]);
                    }
                }
                $stmt->close();
            } else {
                 echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta de registro.']);
            }
            break;

        case 'login':
            $correo = trim($_POST['correo'] ?? '');
            $contrasena = $_POST['contrasena'] ?? '';

            if (empty($correo) || empty($contrasena)) {
                echo json_encode(['success' => false, 'message' => 'Correo y contraseña son obligatorios.']);
                exit;
            }

            $sql = "SELECT id_usuario, nombre, apellido, contrasena FROM usuarios WHERE correo = ?";
            if ($stmt = $mysqli->prepare($sql)) {
                $stmt->bind_param("s", $correo);
                $stmt->execute();
                $resultado = $stmt->get_result();

                if ($resultado->num_rows == 1) {
                    $usuario = $resultado->fetch_assoc();
                    
                    if ($contrasena === $usuario['contrasena']) { 
                        $_SESSION['id_usuario'] = $usuario['id_usuario'];
                        $_SESSION['nombre_usuario'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                        echo json_encode(['success' => true, 'message' => 'Inicio de sesión exitoso.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'No existe un usuario con ese correo.']);
                }
                $stmt->close();
            } else {
                 echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta de login.']);
            }
            break;

        case 'add':
            $id_producto = (int) ($_POST['id_producto'] ?? 0);
            $cantidad = (int) ($_POST['cantidad'] ?? 1); 

            $producto_seleccionado = null;
            // Busca en el array de productos ya combinados
            foreach ($productos as $p) {
                if ((int)$p['id_producto'] === $id_producto) {
                    $producto_seleccionado = $p;
                    break;
                }
            }

            if (!$producto_seleccionado || $cantidad < 1) {
                echo json_encode(['success' => false, 'message' => 'Producto no encontrado o cantidad inválida.']);
                exit;
            }
            
            if (isset($_SESSION['carrito'][$id_producto])) {
                $_SESSION['carrito'][$id_producto]['cantidad'] += $cantidad;
            } else {
                $_SESSION['carrito'][$id_producto] = [
                    'id_producto' => $id_producto,
                    'nombre_producto' => $producto_seleccionado['nombre_producto'],
                    'precio_unidad' => (float)$producto_seleccionado['precio_producto'],
                    'cantidad' => $cantidad
                ];
            }
            
            $total_items = array_sum(array_column($_SESSION['carrito'], 'cantidad'));
            
            echo json_encode(['success' => true, 'message' => "Producto añadido al carrito. Total: {$total_items} items."]);
            break;

        case 'get':
            $items = array_values($_SESSION['carrito']);
            echo json_encode(['success' => true, 'items' => $items]);
            break;
            
        case 'checkout':
            if (!$id_usuario_actual) {
                echo json_encode(['success' => false, 'message' => 'Debe iniciar sesión para procesar el pago.']);
                exit;
            }

            if (empty($_SESSION['carrito'])) {
                echo json_encode(['success' => false, 'message' => 'El carrito está vacío, no hay nada que facturar.']);
                exit;
            }

            $success_count = 0;
            $error_message = '';
            
            $sql = "INSERT INTO factura (id_usuario, id_producto, cantidad, precio_unidad, fecha_factura) 
                    VALUES (?, ?, ?, ?, NOW())";
            
            if ($stmt = $mysqli->prepare($sql)) {
                
                foreach ($_SESSION['carrito'] as $item) {
                    $id_prod = $item['id_producto'];
                    $cant = $item['cantidad'];
                    $precio_unidad = $item['precio_unidad'];
                    
                    $stmt->bind_param("iiid", $id_usuario_actual, $id_prod, $cant, $precio_unidad);
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    } else {
                        $error_message .= " Error al facturar ID {$id_prod}: {$stmt->error};";
                    }
                }
                
                $stmt->close();

                if ($success_count > 0) {
                    $_SESSION['carrito'] = []; 
                    echo json_encode([
                        'success' => true, 
                        'message' => "Se facturaron {$success_count} productos exitosamente y se vació el carrito."
                    ]);
                } else {
                     echo json_encode(['success' => false, 'message' => "No se pudo facturar ningún producto. Errores: {$error_message}"]);
                }

            } else {
                 echo json_encode(['success' => false, 'message' => 'Error al preparar la consulta de facturación.']);
            }
            break;
            
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true, 'message' => 'Sesión cerrada.']);
            break;


        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }

    $mysqli->close();
    exit;
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Smash Padel Shop - E-commerce</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        body {
            font-family: 'Montserrat', Arial, Helvetica, sans-serif;
            background: #0c0c0c;
            color: #f5f5f5;
        }

        header {
            background: #000;
            border-bottom: 2px solid #ff5300;
            padding: 12px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .logo-box {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            background: #111;
            border: 2px solid #ff5300;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #ff5300;
            font-weight: 800;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .logo-text span:first-child {
            color: #ff5300;
            text-transform: uppercase;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .logo-text span:last-child {
            font-size: 11px;
            color: #cccccc;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        nav a {
            color: #f5f5f5;
            text-decoration: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background .2s, color .2s;
        }

        nav a:hover {
            background: #ff5300;
            color: #0c0c0c;
        }

        nav .btn-cta {
            background: #ff5300;
            color: #0c0c0c;
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: 600;
        }

        #user-name {
            font-size: 13px;
            color: #ff5300;
            font-weight: 600;
            margin-left: 6px;
        }
        
        #logout-btn {
            background: none;
            border: 1px solid #ff5300;
            color: #ff5300;
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
        }

        main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 22px 16px 40px;
        }

        .hero {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            padding: 20px;
            background: radial-gradient(circle at top left, #ff5300 0, #111 45%, #000 80%);
            border-radius: 14px;
            box-shadow: 0 0 24px rgba(0, 0, 0, .7);
            margin-bottom: 26px;
        }

        .hero-text {
            flex: 1 1 260px;
        }

        .hero-text h1 {
            font-size: 30px;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 8px;
        }

        .hero-text h1 span {
            color: #ff5300;
        }

        .hero-text p {
            font-size: 14px;
            color: #f0f0f0;
            max-width: 430px;
            margin-bottom: 12px;
        }

        .hero-tag {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #ffd4b1;
            margin-bottom: 14px;
        }

        .hero-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 999px;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
            font-weight: 600;
            transition: background .2s, border-color .2s;
        }

        .btn-primary {
            background: #ff5300;
            color: #0c0c0c;
        }

        .btn-primary:hover {
            background: #ffa154;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #f5f5f5;
            color: #f5f5f5;
        }

        .btn-outline:hover {
            background: #f5f5f5;
            color: #0c0c0c;
        }

        .hero-side {
            flex: 0 0 260px;
            min-height: 150px;
            background: #111;
            border-radius: 14px;
            border: 1px solid #333;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 12px;
        }

        .hero-side-top {
            font-size: 12px;
            color: #cccccc;
        }

        .hero-badge {
            align-self: flex-start;
            background: #ff5300;
            color: #0c0c0c;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hero-side-bottom {
            font-size: 11px;
            color: #999;
        }

        section {
            margin-bottom: 26px;
        }

        .section-title {
            display: flex;
            justify-content:space-between;
            align-items:flex-end;
            margin-bottom:10px;
        }

        .section-title h2 {
            font-size:18px;
            text-transform:uppercase;
            color:#ff5300;
        }

        .section-title span {
            font-size:12px;
            color:#aaaaaa;
        }

        .categories {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
            gap:12px;
            margin-bottom:8px;
        }

        .cat-card {
            background:#111;
            border-radius:10px;
            padding:12px;
            border:1px solid #222;
            display:flex;
            flex-direction:column;
            justify-content:space-between;
        }

        .cat-card h3 {
            font-size:15px;
            margin-bottom:4px;
        }

        .cat-card p {
            font-size:12px;
            color:#cccccc;
            margin-bottom:8px;
        }

        .cat-tag {
            font-size:11px;
            color:#ff5300;
        }

        .products-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:16px;
        }

        .product-card {
            background:#111;
            border-radius:10px;
            border:1px solid #222;
            padding:12px;
            display:flex;
            flex-direction:column;
            gap:6px;
            box-shadow:0 0 12px rgba(0,0,0,.5);
        }

        .product-thumb {
            height:130px;
            border-radius:8px;
            background:linear-gradient(135deg,#1a1a1a,#000);
            border:1px solid #333;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:11px;
            color:#888;
            text-transform:uppercase;
            overflow:hidden;
        }
        
        .product-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }


        .product-card h3 {
            font-size:15px;
            text-transform:uppercase;
        }

        .product-card p {
            font-size:12px;
            color:#cccccc;
        }

        .product-meta {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-top:4px;
        }

        .price {
            color:#ff5300;
            font-weight:700;
            font-size:15px;
        }

        .badge {
            font-size:10px;
            text-transform:uppercase;
            letter-spacing:.5px;
            padding:2px 6px;
            border-radius:999px;
        }

        .badge-new {
            background:#ff5300;
            color:#0c0c0c;
        }

        .badge-hot {
            background:#ffffff;
            color:#0c0c0c;
        }

        .product-actions {
            margin-top:6px;
            display:flex;
            gap:6px;
        }

        .cart-summary {
            background:#111;
            border-radius:10px;
            padding:12px;
            border:1px solid #222;
        }

        .cart-summary table {
            width:100%;
            border-collapse:collapse;
            font-size:12px;
            margin-top:6px;
        }

        .cart-summary th,
        .cart-summary td {
            border-bottom:1px solid #222;
            padding:4px 2px;
            text-align:left;
        }

        .cart-summary th {
            color:#ff5300;
            text-transform:uppercase;
            font-size:11px;
        }
        
        .cart-summary tr:last-child td {
             border-bottom: none;
        }


        .cart-total {
            display:flex;
            justify-content:space-between;
            margin-top:6px;
            font-size:14px;
            font-weight:700;
            padding: 6px 2px 0 2px;
            border-top: 1px solid #444;
        }
        
        .cart-total span {
             color: #cccccc;
             font-weight: 400;
        }


        .cart-note {
            font-size:11px;
            color:#aaaaaa;
            margin-top:4px;
        }

        .benefits {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
            gap:12px;
        }

        .benefit {
            background:#111;
            border-radius:10px;
            padding:10px 12px;
            border:1px solid #222;
        }

        .benefit h3 {
            font-size:14px;
            margin-bottom:4px;
        }

        .benefit p {
            font-size:12px;
            color:#cccccc;
        }

        input,
        button {
            font-family:'Montserrat',Arial,Helvetica,sans-serif;
        }

        input {
            width:100%;
            background:#111;
            border:1px solid #333;
            border-radius:6px;
            padding:6px 8px;
            font-size:13px;
            color:#f5f5f5;
        }

        input:focus {
            outline:none;
            border-color:#ff5300;
        }

        button {
            border:none;
            cursor:pointer;
        }

        footer {
            border-top:1px solid #222;
            padding:12px 16px;
            font-size:11px;
            color:#aaaaaa;
            text-align:center;
            background:#000;
            margin-top:20px;
        }

        .message-box {
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-size: 12px;
            font-weight: 600;
            display: none;
        }

        .message-success {
            background: #28a745;
            color: #fff;
        }

        .message-error {
            background: #dc3545;
            color: #fff;
        }


        @media (max-width:720px){
            header{
                flex-direction:column;
                align-items:flex-start;
                gap:10px;
            }
            nav{
                justify-content:flex-start;
            }
        }
    </style>
</head>

<body onload="renderCart()">

<header>
    <div class="logo-box">
        <div class="logo-icon">⚡</div>
        <div class="logo-text">
            <span>Smash Padel</span>
            <span>Power on court</span>
        </div>
    </div>
    <nav>
        <a href="#productos">Productos</a>
        <a href="#carrito">Carrito</a>
        <a href="#perfil" class="btn-cta">Mi cuenta</a>
        <span id="user-name">Hola, <?php echo htmlspecialchars($nombre_usuario_simulado); ?></span> 
        <?php if ($id_usuario_actual): ?>
             <button id="logout-btn" onclick="logout()">Salir</button>
        <?php endif; ?>
    </nav>
</header>

<main>

    <section class="hero">
        <div class="hero-text">
            <h1>Domina la pista con <span>Smash Padel</span></h1>
            <p>Equipamiento de padel diseñado para jugadores que buscan potencia, control y estilo. Raquetas, pelotas y accesorios listos para tu proximo partido.</p>
            <p class="hero-tag">Coleccion 2025 · Edicion limitada · Envíos a todo el pais</p>
            <div class="hero-actions">
                <a href="#productos" class="btn btn-primary">Ver todos los productos</a>
                <a href="#perfil" class="btn btn-outline">Crear cuenta</a>
            </div>
        </div>
        <div class="hero-side">
            <div class="hero-side-top">
                <div class="hero-badge">Nuevo</div>
                <p>Raqueta Smash Thunder Pro con nucleo de carbono y balance ofensivo. Ideal para jugadores que quieren mas velocidad en cada golpe.</p>
            </div>
           
        </div>
    </section>
    
    <div class="message-box" id="feedback-message"></div>

    <section id="productos">
        <div class="section-title">
            <h2>Productos destacados</h2>
            <span>Ideal para comenzar tu carrito</span>
        </div>
        <div class="products-grid">
            <?php foreach ($productos as $producto): ?>
                <article class="product-card">
                    <div class="product-thumb">
                        <img src="<?php echo htmlspecialchars($producto['imagen_url']); ?>" 
                             alt="<?php echo htmlspecialchars($producto['nombre_producto']); ?>" 
                             style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
                    </div>
                    <h3><?php echo htmlspecialchars($producto['nombre_producto']); ?></h3>
                    <p><?php echo htmlspecialchars($producto['descripcion']); ?></p>
                    <div class="product-meta">
                        <span class="price">$<?php echo number_format($producto['precio_producto'], 2); ?></span>
                        <span class="badge badge-hot"><?php echo htmlspecialchars($producto['badge']); ?></span>
                    </div>
                    <div class="product-actions">
                        <button class="btn btn-primary add-cart" 
                                data-id="<?php echo $producto['id_producto']; ?>" 
                                data-price="<?php echo $producto['precio_producto']; ?>">
                            Agregar al carrito
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="carrito">
        <div class="section-title">
            <h2>Carrito </h2>
            <span>Al procesar el pago su producto ya estara listo y nos comunicaremos con usted para la entrega</span>
        </div>
        <div class="cart-summary">
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cant.</th>
                        <th>Precio Unidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody id="cart-body">
                    <tr><td colspan="4">Cargando carrito...</td></tr>
                </tbody>
            </table>
            <div class="cart-total">
                <span>Total a Pagar</span>
                <strong id="cart-total">$0.00</strong>
            </div>
           
            <div style="margin-top:8px;">
                <button class="btn btn-primary" onclick="procesarPago()">Procesar pago (Guarda en `factura`)</button>
            </div>
        </div>
    </section>

    <section id="perfil">
        <div class="section-title">
            <h2>Mi cuenta</h2>
            <span>Conexion con la tabla `usuarios`</span>
        </div>
        <div class="benefits">
            <div class="benefit">
                <h3>Iniciar sesión</h3>
                <p style="margin:6px 0 4px;">Correo</p>
                <input type="email" id="login-email" placeholder="usuario@smashpadel.com">
                <p style="margin:6px 0 4px;">Contraseña</p>
                <input type="password" id="login-password" placeholder="******">
                <button class="btn btn-primary" style="margin-top:8px;" onclick="login()">Entrar</button>
               
            </div>
            <div class="benefit">
                <h3>Registrarse</h3>
                <p style="margin-bottom:4px;">Nombre</p>
                <input type="text" id="reg-name" placeholder="Nombre">
                <p style="margin:6px 0 4px;">Apellido</p>
                <input type="text" id="reg-apellido" placeholder="Apellido">
                <p style="margin:6px 0 4px;">Correo</p>
                <input type="email" id="reg-email" placeholder="email@ejemplo.com">
                <p style="margin:6px 0 4px;">Contraseña</p>
                <input type="password" id="reg-pass" placeholder="contraseña">
                <button class="btn btn-outline" style="margin-top:8px;" onclick="register()">Registrarse</button>
            </div>
            <div class="benefit">
                <h3>Estado de Sesión</h3>
                <p>Usuario Actual: <strong><?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Invitado'); ?></strong></p>
                <p>ID de Sesión: <strong><?php echo htmlspecialchars($_SESSION['id_usuario'] ?? 'N/A'); ?></strong></p>
                <p style="margin-top:6px;">
                    El carrito requiere **iniciar sesion** para procesar el pago y procesar su pago.
                </p>
            </div>
        </div>
    </section>
    
    <section>
        <div class="section-title">
            <h2>Por que Smash Padel</h2>
            <span>Ventajas para tus clientes</span>
        </div>
        <div class="benefits">
            <div class="benefit">
                <h3>Envios rapidos</h3>
                <p>Entregas en 24-48h para que tu proximo partido sea lo mejor</p>
            </div>
            <div class="benefit">
                <h3>Materiales premium</h3>
                <p>Carbono, EVA y textiles de alta calidad pensados para jugadores exigentes.</p>
            </div>
            <div class="benefit">
                <h3>Soporte al jugador</h3>
                <p>Asesoria para ayudarte a elegir la raqueta que mejor se adapte a tu estilo.</p>
            </div>
        </div>
    </section>

</main>

<footer>
    Smash Padel Shop - Proyecto academico de e-commerce
</footer>

<script>
    const API_URL = 'index.php';
    const msgBox = document.getElementById('feedback-message');
    
    const PRODUCTOS_DATA = <?php echo json_encode($productos); ?>;


    function showMessage(message, isSuccess = true) {
        msgBox.textContent = message;
        msgBox.className = isSuccess ? 'message-box message-success' : 'message-box message-error';
        msgBox.style.display = 'block';
        setTimeout(() => {
            msgBox.style.display = 'none';
        }, 5000);
    }
    
    function refreshPage() {
        window.location.reload();
    }

    function register() {
        const nombre = document.getElementById('reg-name').value;
        const apellido = document.getElementById('reg-apellido').value;
        const correo = document.getElementById('reg-email').value;
        const contrasena = document.getElementById('reg-pass').value;

        fetch(API_URL + '?action=register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `nombre=${encodeURIComponent(nombre)}&apellido=${encodeURIComponent(apellido)}&correo=${encodeURIComponent(correo)}&contrasena=${encodeURIComponent(contrasena)}` 
        })
        .then(response => response.json())
        .then(data => {
            showMessage(data.message, data.success);
            if (data.success) {
                setTimeout(refreshPage, 1000);
            }
        })
        .catch(error => {
            console.error('Error de registro:', error);
            showMessage('Error de red al registrar.', false);
        });
    }

    function login() {
        const correo = document.getElementById('login-email').value;
        const contrasena = document.getElementById('login-password').value;

        fetch(API_URL + '?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `correo=${encodeURIComponent(correo)}&contrasena=${encodeURIComponent(contrasena)}`
        })
        .then(response => response.json())
        .then(data => {
            showMessage(data.message, data.success);
            if (data.success) {
                setTimeout(refreshPage, 1000);
            }
        })
        .catch(error => {
            console.error('Error de login:', error);
            showMessage('Error de red al iniciar sesion.', false);
        });
    }
    
    function logout() {
        fetch(API_URL + '?action=logout')
        .then(response => response.json())
        .then(data => {
            showMessage(data.message, data.success);
            if (data.success) {
                setTimeout(refreshPage, 1000);
            }
        })
        .catch(error => {
            console.error('Error de logout:', error);
            showMessage('Error de red al cerrar sesion.', false);
        });
    }
    

    function procesarPago() {
        if (!'<?php echo $id_usuario_actual; ?>') {
            showMessage('ERROR: Debe iniciar sesion para procesar el pago.', false);
            return;
        }

        if (confirm("¿Desea confirmar el pago?")) {
            fetch(API_URL + '?action=checkout', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, true);
                    renderCart(); 
                } else {
                    showMessage(`Fallo al facturar: ${data.message}`, false);
                }
            })
            .catch(error => {
                console.error('Error de red:', error);
                showMessage('Error de red al procesar el pago.', false);
            });
        }
    }

    function renderCart() {
        fetch(API_URL + '?action=get')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('cart-body');
                const totalEl = document.getElementById('cart-total');
                tbody.innerHTML = '';
                let total = 0;

                if (data.success && data.items.length > 0) {
                    data.items.forEach(item => {
                       
                        const precio = parseFloat(item.precio_unidad) || 0; 
                        const cantidad = parseInt(item.cantidad);
                        const sub = precio * cantidad;
                        total += sub;

                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${item.nombre_producto}</td>
                            <td>${cantidad}</td>
                            <td>$${precio.toFixed(2)}</td>
                            <td>$${sub.toFixed(2)}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    totalEl.textContent = '$' + total.toFixed(2);
                } else if (data.success) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999;">El carrito está vacío.</td></tr>';
                    totalEl.textContent = '$0.00';
                } else {
                    showMessage(data.message, false);
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#dc3545;">ERROR: ' + data.message + '</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error al cargar carrito:', error);
                showMessage('Error de red al intentar cargar el carrito.', false);
                document.getElementById('cart-body').innerHTML = '<tr><td colspan="4" style="text-align:center; color:#dc3545;">Error de red o DB.</td></tr>';
            });
    }

    document.querySelectorAll('.add-cart').forEach(btn => {
        btn.addEventListener('click', () => {
            const id_producto = parseInt(btn.dataset.id);
            
            fetch(API_URL + '?action=add', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `id_producto=${id_producto}&cantidad=1` 
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => { throw new Error(text) });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, true);
                        renderCart();
                    } else {
                        showMessage('Error al añadir: ' + data.message, false);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Error de red al intentar añadir al carrito.', false);
                });
        });
    });

</script>

</body>
</html>
