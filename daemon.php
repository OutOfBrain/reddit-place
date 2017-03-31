<?php
require __DIR__ . '/vendor/autoload.php';

$url = getConnectionUrl();
echo "found connection url $url\n";

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
    $insertStatement->bindValue(':x', $x);
    $insertStatement->bindValue(':y', $y);
    $insertStatement->bindValue(':color', $color);
    $insertStatement->bindValue(':author', $author);

    $insertStatement->execute();
}

function getConnectionUrl()
{
    $placeContent = file_get_contents("http://reddit.com/r/place");
    preg_match('/(wss:.*?)"/', $placeContent, $matches);
    return $matches[1];
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

                    switch ($type) {
                        case 'place':
                            $x = $payload['x'];
                            $y = $payload['y'];
                            $color = $payload['color'];
                            $author = $payload['author'];

                            savePixel($x, $y, $color, $author);
                            break;

                        case 'batch-place':
                            foreach ($payload as $pixel) {
                                $x = $pixel['x'];
                                $y = $pixel['y'];
                                $color = $pixel['color'];
                                $author = $pixel['author'];

                                savePixel($x, $y, $color, $author);
                            }
                            break;
                    }
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
