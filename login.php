<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KunturVR | Portal de Minería</title>
    <!-- Fuentes cambiadas: Poppins y Montserrat para mejor armonía con #023675 -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&family=Montserrat:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #023675;
            --accent: #00d4ff;
            --neon: #00f2ff;
            --dark-bg: #010a14;
            --glass: rgba(2, 54, 117, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            /* Fuente principal cambiada a Poppins */
            font-family: 'Poppins', sans-serif;
            background: var(--dark-bg);
            color: #fff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
            /* Fondo con malla tecnológica */
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(2, 54, 117, 0.3) 0%, transparent 80%),
                linear-gradient(rgba(0, 212, 255, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 212, 255, 0.05) 1px, transparent 1px);
            background-size: 100% 100%, 30px 30px, 30px 30px;
        }

        /* Contenedor Principal Responsive */
        .main-wrapper {
            width: 100%;
            max-width: 450px;
            padding: 20px;
            z-index: 10;
        }

        .auth-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border: 2px solid rgba(0, 212, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            position: relative;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(0, 212, 255, 0.1);
            overflow: hidden;
        }

        /* Detalle decorativo de esquina (Look Industrial/VR) */
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 40px; height: 40px;
            border-top: 4px solid var(--accent);
            border-left: 4px solid var(--accent);
            border-radius: 18px 0 0 0;
        }

        .logo-box {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-box img {
            width: 140px;
            filter: drop-shadow(0 0 15px var(--accent));
            animation: pulse-glow 3s infinite ease-in-out;
        }

        h2 {
            /* Título con Montserrat para dar énfasis */
            font-family: 'Montserrat', sans-serif;
            font-size: 1.5rem;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 25px;
            color: var(--accent);
            font-weight: 700;
        }

        /* Estilo de Inputs mejorado */
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }

        .input-group input, .input-group select {
            width: 100%;
            padding: 12px 15px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(0, 212, 255, 0.3);
            border-radius: 5px;
            color: #fff;
            /* Fuente para inputs también Poppins */
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .input-group input:focus, .input-group select:focus {
            outline: none;
            border-color: var(--neon);
            box-shadow: 0 0 15px rgba(0, 242, 255, 0.4);
            background: rgba(0, 0, 0, 0.6);
        }

        /* Estilo para los placeholders */
        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.6);
            font-weight: 300;
            font-size: 0.9rem;
        }

        /* Botón con efecto Neón */
        .btn-glow {
            width: 100%;
            padding: 15px;
            background: transparent;
            border: 2px solid var(--accent);
            color: var(--accent);
            /* Botón con Montserrat para destacar */
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            border-radius: 5px;
            position: relative;
            overflow: hidden;
            transition: 0.4s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-glow:hover {
            background: var(--accent);
            color: var(--primary);
            box-shadow: 0 0 30px var(--accent);
        }

        .form-footer {
            margin-top: 25px;
            text-align: center;
            font-size: 0.9rem;
            color: #bbb;
            font-weight: 300;
        }

        .form-footer a {
            color: var(--neon);
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
        }

        .form-footer a:hover {
            text-shadow: 0 0 10px var(--neon);
        }

        /* Animaciones y Estados */
        .hidden { display: none; }

        @keyframes pulse-glow {
            0%, 100% { filter: drop-shadow(0 0 10px var(--accent)); transform: scale(1); }
            50% { filter: drop-shadow(0 0 25px var(--neon)); transform: scale(1.05); }
        }

        /* Adaptabilidad (Responsive) */
        @media (max-width: 480px) {
            .auth-card {
                padding: 30px 20px;
                border-radius: 0;
                border-left: none;
                border-right: none;
            }
            .main-wrapper {
                padding: 0;
            }
            h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <div class="auth-card">
            <div class="logo-box">
                <img src="imagenes/logo.png" alt="KunturVR Logo">
            </div>

            <div id="login-panel">
                <h2>Acceso Seguro</h2>
                <form action="auth.php" method="POST">
                    <div class="input-group">
                        <input type="text" name="usuario" placeholder="USUARIO" required>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" placeholder="CLAVE DE ACCESO" required>
                    </div>
                    <button type="submit" class="btn-glow">Iniciar Secuencia</button>
                </form>
                <div class="form-footer">
                    ¿NUEVO OPERADOR? <a href="javascript:void(0)" onclick="toggleAuth()">SOLICITAR REGISTRO</a>
                </div>
            </div>

            <div id="register-panel" class="hidden">
                <h2>Registro de Personal</h2>
                <form action="register.php" method="POST">
                    <div class="input-group">
                        <input type="text" name="nombre" placeholder="NOMBRE COMPLETO" required>
                    </div>
                    <div class="input-group">
                        <input type="text" name="usuario" placeholder="USUARIO" required>
                    </div>
                    <div class="input-group">
                        <select name="id_cliente" required>
                            <option value="" disabled selected>SELECCIONAR CLIENTE</option>
                            <option value="1">MINA ANTAMINA</option>
                            <option value="2">CERRO VERDE</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <select name="id_rol" required>
                            <option value="" disabled selected>RANGO / ROL</option>
                            <option value="2">SUPERVISOR</option>
                            <option value="3">OPERADOR VR</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <input type="password" name="password" placeholder="ESTABLECER CLAVE" required>
                    </div>
                    <button type="submit" class="btn-glow">Confirmar Alta</button>
                </form>
                <div class="form-footer">
                    ¿YA REGISTRADO? <a href="javascript:void(0)" onclick="toggleAuth()">VOLVER AL LOGIN</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleAuth() {
            const login = document.getElementById('login-panel');
            const register = document.getElementById('register-panel');
            
            // Animación simple de desvanecimiento
            login.classList.toggle('hidden');
            register.classList.toggle('hidden');
        }
    </script>
</body>
</html>