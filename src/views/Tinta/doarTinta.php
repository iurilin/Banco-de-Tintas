<?php
require_once('../../../config/database.php');
require_once('../../../src/models/tinta.php');
require_once('../../../src/models/doacao.php');
require_once('../../../src/models/ponto_coleta.php');
require('../../../src/controllers/auth.php');

verificarSessao();
$isLoggedIn = isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] === true;
$fk_usuario_id_usuario = $_SESSION['id_usuario'];

$stmt = $pdo->query("SELECT * FROM ponto_coleta");
$pontos_coleta = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = ''; // Initialize message variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imageData = null;
    if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
        $imageData = file_get_contents($_FILES['imagem']['tmp_name']);
    }

    $tinta = new Tinta(
        $_POST['cor'],
        $_POST['quantidade'],
        $_POST['aplicacao'],
        $_POST['marca'],
        $imageData,
        $_POST['tamanho'],
        $_POST['acabamento'],
        $_POST['dt_validade'],
        $_POST['ponto_coleta']
    );

    $doacao = new Doacao(
        $_POST['horario'],
        $_POST['diaDoacao'],
        null, 
        $fk_usuario_id_usuario
    );

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO tintas (cor_tinta, quantidade, aplicacao, marca, imagem, embalagem, acabamento, dt_validade, fk_ponto_coleta_cod_ponto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $corTinta = $tinta->getCorTinta();
        $quantidade = $tinta->getQuantidade();
        $aplicacao = $tinta->getAplicacao();
        $marca = $tinta->getMarca();
        $imagem = $tinta->getImagem();
        $embalagem = $tinta->getEmbalagem();
        $acabamento = $tinta->getAcabamento();
        $dtValidade = $tinta->getDtValidade();
        $fkPontoColeta = $tinta->getFkPontoColetaCodPonto();

        $stmt->bindParam(1, $corTinta);
        $stmt->bindParam(2, $quantidade);
        $stmt->bindParam(3, $aplicacao);
        $stmt->bindParam(4, $marca);
        $stmt->bindParam(5, $imagem, PDO::PARAM_LOB);
        $stmt->bindParam(6, $embalagem);
        $stmt->bindParam(7, $acabamento);
        $stmt->bindParam(8, $dtValidade);
        $stmt->bindParam(9, $fkPontoColeta);
        $stmt->execute();

        $lastInsertId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO doacao_doar (horario_disp, dias_disp, fk_tintas_cod_tinta, fk_usuario_id_usuario) VALUES (?, ?, ?, ?)");
        $horarioDisp = $doacao->getHorarioDisp();
        $diasDisp = $doacao->getDiasDisp();
        $fkUsuarioIdUsuario = $doacao->getFkUsuarioIdUsuario();

        $stmt->execute([
            $horarioDisp,
            $diasDisp,
            $lastInsertId,
            $fkUsuarioIdUsuario
        ]);

        $pdo->commit();
        $message = "Doação cadastrada com sucesso!";
    } catch(PDOException $e) {
        $pdo->rollBack();
        $message = "Erro ao cadastrar doação: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Doação - PHPaintHub</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
    :root {
        --primary-bg: #0d1b2a;
        --secondary-bg: #1b263b;
        --text-color: #e0e1dd;
        --accent-color: #84a98c;
        --button-color: #52796f;
        --button-hover: #354f52;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: Arial, sans-serif;
    }

    body {
        background: linear-gradient(var(--primary-bg), var(--secondary-bg));
        color: var(--text-color);
        min-height: 100vh;
        padding-bottom: 2rem;
    }

    .container {
        max-width: 800px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .form-section {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 10px;
        padding: 2rem;
        backdrop-filter: blur(5px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .form-title {
        text-align: center;
        margin-bottom: 2rem;
        color: var(--accent-color);
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: bold;
    }

    input, select, textarea {
        width: 100%;
        padding: 0.8rem;
        border-radius: 4px;
        border: 1px solid var(--text-color);
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-color);
        margin-top: 0.25rem;
    }

    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 5px var(--accent-color);
    }

    button {
        background: var(--button-color);
        color: var(--text-color);
        padding: 1rem 2rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        font-size: 1.1rem;
        margin-top: 1rem;
    }

    button:hover {
        background: var(--button-hover);
        box-shadow: 0 0 15px var(--accent-color);
    }

    #map {
        height: 400px;
        width: 100%;
        margin-bottom: 1rem;
    }

    .login-btn {
        border: 1px solid var(--text-color);
        padding: 0.5rem 1rem;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .login-btn:hover {
        background: var(--text-color);
        color: var(--primary-bg);
    }

    @media (max-width: 580px) {
        .nav {
            flex-direction: row; /* Mantém a imagem e os links na mesma linha */
            justify-content: space-between; /* Espaço entre os elementos */
            align-items: center; /* Alinha verticalmente no centro */
        }

        .nav a {
            padding: 6px 10px; /* Diminui o espaçamento entre os links */
        }

        .dropdown {
            margin-top: 0; /* Remove o espaço adicional do dropdown */
        }
    }

    header {
            background-color: #212123;
            padding: 10px 20px;
            margin-bottom: 80px;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;

        }

        .nav a {
            color: #e0e1dd;
            text-decoration: none;
            padding: 8px 15px;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 5px;
            font-size: 20px;
        }

        .nav a:hover {
            background-color: #00177e;
            color: #fff;
        }

        /* Dropdown */
        .dropdown {
            position: relative;
            display: inline-block;
        }
                /* Ajustes de imagem */
        .nav img {
            width: 110px; /* Ajuste o tamanho da imagem */
            height: auto;
            margin-right: 5px; /* Adiciona 5px de espaço à direita da imagem */
        }   

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background-color: rgba(0, 0, 0, 0.9);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            border-radius: 5px;
            min-width: 150px;
            z-index: 10;
        }

        .dropdown-menu a {
            display: block;
            padding: 10px;
            color: #e0e1dd;
            text-decoration: none;
            transition: background-color 0.3s, color 0.3s;
        }

        .dropdown-menu a:hover {
            background-color: #4163fc;
            color: #fff;
        }

        .dropdown:hover .dropdown-menu {
            display: block;
        }
        
        .message {
            background-color: var(--accent-color);
            color: var(--primary-bg);
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            font-size: 1.2rem;
            text-align: center;
        }


</style>

</head>
<body>

<header>
    <nav class="nav">
        <!-- Logo -->
        <div><img src="../../imgs/logo_tintas.png" alt="Logo"></div>

        <!-- Navegação -->
        <div class="flex items-center space-x-6">
            <a href="../home.php">Home</a>
            <!-- Dropdown para "Catálogo" -->
            <div class="dropdown">
                <a href="#" class="hover:text-gray-400">Catálogo</a>
                <div class="dropdown-menu">
                    <a href="../tinta/catalog.php">Retirar Tinta</a>
                    <a href="../tinta/doarTinta.php">Doar Tinta</a>
                </div>
            </div>
            <?php if ($isLoggedIn): ?>
                <a href="../Enter/User/logout.php" class="user-status">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['email'] ?? 'Usuário'); ?>
                </a>
            <?php else: ?>
                <a href="enter/user/userlogin.php" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            <?php endif; ?>
    </nav>
</header>
<div class="container">
        <div class="form-section">
            <h1 class="form-title">Cadastro de Doação de Tinta</h1>
            
            <?php if (!empty($message)): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>

            <form id="paintDonationForm" action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="cor">Cor da tinta</label>
                    <input type="text" id="cor" name="cor" required>
                </div>

                <div class="form-group">
                    <label for="quantidade">Quantidade de tinta</label>
                    <input type="text" id="quantidade" name="quantidade" required>
                </div>

                <div class="form-group">
                    <label for="aplicacao">Indicação de aplicação da Tinta</label>
                    <textarea id="aplicacao" name="aplicacao" rows="3" required></textarea>
                </div>

                <div class="form-group">
                    <label for="marca">Marca da tinta</label>
                    <select id="marca" name="marca" required>
                        <option value="">Selecione a marca</option>
                        <option value="premium">Premium</option>
                        <option value="standard">Standard</option>
                        <option value="Econômica">Econômica</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="imagem">Escolha uma imagem:</label>
                    <input type="file" name="imagem" id="imagem" required>
                </div>

                <div class="form-group">
                    <label for="tamanho">Tamanho da embalagem</label>
                    <select id="tamanho" name="tamanho" required>
                        <option value="">Selecione o tamanho</option>
                        <option value="¼ de galão (900 ml)">¼ de galão (900 ml)</option>
                        <option value="Galão 3,6 Litros">Galão 3,6 Litros</option>
                        <option value="Lata 18 litros">Lata 18 litros</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="acabamento">Acabamento da tinta</label>
                    <select id="acabamento" name="acabamento" required>
                        <option value="">Selecione o acabamento</option>
                        <option value="Fosco">Fosco</option>
                        <option value="Acetinado">Acetinado</option>
                        <option value="Brilhante">Brilhante</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="dt_validade">Data de validade da tinta</label>
                    <input type="date" id="dt_validade" name="dt_validade" required>
                </div>

                <div class="form-group">
                    <label for="ponto_coleta">Ponto de coleta</label>
                    <select id="ponto_coleta" name="ponto_coleta" required>
                        <option value="">Selecione o ponto de coleta</option>
                        <?php foreach ($pontos_coleta as $ponto): ?>
                            <option value="<?php echo $ponto['cod_ponto']; ?>" data-lat="<?php echo $ponto['latitude']; ?>" data-lng="<?php echo $ponto['longitude']; ?>">
                                <?php echo $ponto['endereco'] . ', ' . $ponto['cidade']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="map"></div>
                </div>

                <div class="form-group">
                    <label for="diaDoacao">Dias disponíveis para doação</label>
                    <select id="diaDoacao" name="diaDoacao" required>
                        <option value="">Selecione o dia</option>
                        <option value="Segunda-feira">Segunda-feira</option>
                        <option value="Terça-feira">Terça-feira</option>
                        <option value="Quarta-feira">Quarta-feira</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="horario">Horário disponível</label>
                    <select id="horario" name="horario" required>
                        <option value="">Selecione o horário</option>
                        <option value="Das 8:00 às 11:00">Das 8:00 às 11:00</option>
                        <option value="Das 13:00 às 17:00">Das 13:00 às 17:00</option>
                    </select>
                </div>

                <button type="submit" name="submit">Cadastrar Doação</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var map = L.map('map').setView([-23.1857, -46.8978], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        var pontosColeta = <?php echo json_encode($pontos_coleta); ?>;
        var markers = [];

        pontosColeta.forEach(function(ponto) {
            var marker = L.marker([ponto.latitude, ponto.longitude])
                .addTo(map)
                .bindPopup(ponto.endereco + ', ' + ponto.cidade);

            markers.push(marker);
        });

        document.getElementById('ponto_coleta').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var lat = selectedOption.getAttribute('data-lat');
            var lng = selectedOption.getAttribute('data-lng');
            if (lat && lng) {
                map.setView([lat, lng], 15);
                markers.forEach(function(marker) {
                    if (marker.getLatLng().lat == lat && marker.getLatLng().lng == lng) {
                        marker.openPopup();
                    }
                });
            }
        });
    });
    </script>
</body>
</html>