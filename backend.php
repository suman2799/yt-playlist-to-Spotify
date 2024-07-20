<?php
// config.php
$config = [
    'SPOTIFY_CLIENT_SECRET' => '__SPOTIFY_CLIENT_SECRET__',
    'SPOTIFY_CLIENT_ID' => '__SPOTIFY_CLIENT_ID__',
    'YOUTUBE_API_KEY' => '__YOUTUBE_API_KEY__',
];

header('Content-Type: application/json');
echo json_encode($config);
