<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

session_start(); // Asegúrate de iniciar la sesión

// Carga las variables de entorno desde el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__);  // __DIR__ es el directorio actual (raíz del proyecto)
$dotenv->load();

// Accede a las variables de entorno
$clientId = $_ENV['SPOTIPY_CLIENT_ID']; // o  getenv('APP_NAME');
$clientSecret = $_ENV['SPOTIPY_CLIENT_SECRET'];
$redirectUri = $_ENV['SPOTIPY_REDIRECT_URI'];

// revisar si existe un _GET con el código de autorización, si existe grabarlo en la sesión y la base de datos
if (isset($_GET['code'])) {
    $_SESSION['authorize_code'] = $_GET['code'];
    //conecta a la base de datos
    $servername = $_ENV['DB_HOST'];
    $username = $_ENV['DB_USER'];
    $password = $_ENV['DB_PASS'];
    $dbname = $_ENV['DB_NAME'];

    // Conectar a la base de datos
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Revisa si la conexión a la base de datos fue exitosa
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // guardar el código de autorización en la base de datos
    $sql = "UPDATE options SET value = '" . $_SESSION['authorize_code'] . "', date = NOW() WHERE name = 'authorize_code'";
    $conn->query($sql);
    $conn->close();
}

// si no hay un código de autorización en la sesión, redirigir a callback.php
if (!isset($_SESSION['authorize_code'])) {
    header('Location: callback.php');
    die();
}

// si el token de acceso no está en la sesión o es nulo, obtenerlo
if (!isset($_SESSION['access_token']) || $_SESSION['access_token'] == '') {
    $tokenUrl = 'https://accounts.spotify.com/api/token';
    $data = [
        'grant_type' => 'authorization_code',
        'code' => $_SESSION['authorize_code'],
        'redirect_uri' => $redirectUri
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
    ]);

    $response = curl_exec($ch);

    var_dump($response);

    curl_close($ch);

    $tokenData = json_decode($response, true);

    var_dump($tokenData);
    // si el token de acceso no está en la respuesta, redirigir a callback.php, limpiar la sesión y eliminar el valor de la base de datos
    if (!isset($tokenData['access_token'])) {
        unset($_SESSION['authorize_code']);
        //conecta a la base de datos
        $servername = $_ENV['DB_HOST'];
        $username = $_ENV['DB_USER'];
        $password = $_ENV['DB_PASS'];
        $dbname = $_ENV['DB_NAME'];

        // Conectar a la base de datos
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Revisa si la conexión a la base de datos fue exitosa
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // guardar el código de autorización en la base de datos
        $sql = "UPDATE options SET value = '', date = NOW() WHERE name = 'authorize_code'";
        $conn->query($sql);
        $conn->close();
        header('Location: callback.php');
        die();
    } else {
        $_SESSION['access_token'] = $tokenData['access_token'];
    }


}else{

    $accessToken = $_SESSION['access_token'];

    $apiUrl = 'https://api.spotify.com/v1/me/player/currently-playing';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    var_dump($data);

    if ($data) {
        if (isset($data['item'])) {
            $songName = $data['item']['name'];
            $artistName = $data['item']['artists'][0]['name'];
            //duration in minutes and seconds
            $duration = $data['item']['duration_ms'];
            $duration = $duration / 1000;
            $minutes = floor($duration / 60);
            $seconds = $duration % 60;


            //echo "Está sonando: " . $songName . " de " . $artistName;
            // mostrar imagen del album
            //echo '<img src="' . $data['item']['album']['images'][0]['url'] . '" alt="Portada del álbum">';
        } else {
            echo "No está sonando nada en este momento.";
        }
    } else {
        echo "Error al obtener la información: " . $response;
    }

}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Spotify API</title>
    <style>
        html, body {
            background: black;
        }
        .container {
            display: flex;
            /*justify-content: center;*/
            align-items: center;
            /*height: 100vh;*/
        }
        .player {
            display: flex;
            //justify-content: center;
            align-items: center;
            background-color: #f1f1f1;
            padding: 7px 14px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 400px;
        }
        .album img {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            margin-right: 20px;
        }
        .song {
            display: flex;
            flex-direction: column;
        }
        .title {
            font-size: 1.3em;
            font-weight: bold;
        }
        .artist {
            font-size: 1.2em;
            color: #333;
        }
        .time {
            font-size: 1.1em;
            color: #333;
        }


    </style>
</head>
<body>
    <div class="container">
        <div class="player">
            <div class="album">
                <img src="<?php echo $data['item']['album']['images'][0]['url']; ?>" alt="Portada del álbum">
            </div>
            <div class="song">
                <div class="title"><?php echo $songName; ?></div>
                <div class="artist"><?php echo $artistName; ?></div>
                <div class="time"><?php echo $minutes . ":" . $seconds; ?></div>
            </div>

        </div>

    </div>

<!-- script para refrescar la hoja cada 5 segundos -->
<!--script>
    setTimeout(function(){
        location.reload();
    }, 10000);
</script -->

</body>
</html>


