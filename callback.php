<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Carga las variables de entorno desde el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__);  // __DIR__ es el directorio actual (raíz del proyecto)
$dotenv->load();

// Iniciar la sesión
session_start();

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

// consultar la base de datos para obtener el token de acceso de Spotify y guardarlo en la sesión si no está allí ya
if (!isset($_SESSION['authorize_code'])) {
    $sql = "SELECT * FROM options WHERE name = 'authorize_code'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        var_dump($row);

        // calcular cuanto tiempo ha pasado desde que se guardo el token en horas, días y minutos
        $date1 = new DateTime($row['date']);
        $date2 = new DateTime();
        $diff = $date2->diff($date1);

        $hours = $diff->h;
        $hours = $hours + ($diff->days * 24);
        echo $hours . " horas, ";
        /*
        echo $diff->i . " minutos, ";
        echo $diff->s . " segundos, ";
        echo $diff->days . " días, ";
        echo $diff->m . " meses, ";
        echo $diff->y . " años, ";*/

        // si han pasado más de 1 hora desde que se guardo el token, se debe obtener uno nuevo
        if ($hours > 24 || $row['value'] == '') {
                // Carga las variables de entorno desde el archivo .env
                $clientId = $_ENV['SPOTIPY_CLIENT_ID']; // o  getenv('APP_NAME');
                $redirectUri = $_ENV['SPOTIPY_REDIRECT_URI'];
                $scopes = 'user-read-currently-playing'; // El scope que necesitas

                $authorizeUrl = 'https://accounts.spotify.com/authorize?' . http_build_query([
                        'client_id' => $clientId,
                        'response_type' => 'code',
                        'redirect_uri' => $redirectUri,
                        'scope' => $scopes
                    ]);

                echo '<a href="' . htmlspecialchars($authorizeUrl) . '">Autorizar Spotify</a>';

        } else {
            // guardar la autorización en la sesión
            $_SESSION['authorize_code'] = $row['value'];
            // redirect to index.php
            header('Location: index.php');
        }

    }

}

?>