<?php
require __DIR__ . '/vendor/autoload.php';

$url = trim(file_get_contents('connectionurl'));

$db = new SQLite3('place.db');

$db->busyTimeout(100);
$db->exec('
    CREATE TABLE IF NOT EXISTS place (
        timestamp INTEGER,
        x INTEGER,
        y INTEGER,
        color INTEGER,
        author TEXT
    )
');

$insertStatement = $db->prepare('
  INSERT INTO place (timestamp, x, y, color, author)
  VALUES (:timestamp, :x, :y, :color, :author)
');

function savePixel($x, $y, $color, $author)
{
    global $insertStatement;

    $insertStatement->bindValue(':timestamp', time());
    $insertStatement->bindValue(':x', $x, PDO::PARAM_INT);
    $insertStatement->bindValue(':y', $y, PDO::PARAM_INT);
    $insertStatement->bindValue(':color', $color, PDO::PARAM_INT);
    $insertStatement->bindValue(':author', $author, PDO::PARAM_STR);

    $insertStatement->execute();
}


\Ratchet\Client\connect($url)->then(
    function (Ratchet\Client\WebSocket $connection) {
        $connection->on(
            'message',
            function ($message) use ($connection) {
                echo "Received: $message\n";
                $response = json_decode($message, true);

                if ($response != null) {
                    $type = $response['type'];

                    $payload = $response['payload'];
                    $x = $payload['x'];
                    $y = $payload['y'];
                    $color = $payload['color'];
                    $author = $payload['author'];

                    savePixel($x, $y, $color, $author);
                }
            }
        );

        $connection->on(
            'close',
            function ($code = null, $reason = null) {
                echo "Connection closed ($code - $reason)\n";
            }
        );

        $connection->on(
            'error',
            function ($error) {
                echo "error $error";
            }
        );
    },
    function ($e) {
        echo "Could not connect: {$e->getMessage()}\n";
    }
);
