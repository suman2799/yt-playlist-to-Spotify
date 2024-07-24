<?php
// Function to load environment variables from a .env file
function loadEnv($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("File not found: " . $filePath);
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load environment variables
loadEnv(__DIR__ . '/.env');

// Get environment variables
$spotifyClientId = $_ENV['SPOTIFY_CLIENT_ID'];
$spotifyClientSecret = $_ENV['SPOTIFY_CLIENT_SECRET'];
$youtubeApiKey = $_ENV['YOUTUBE_API_KEY'];

// Function to handle API requests
function handleRequest() {
    global $spotifyClientId, $spotifyClientSecret, $youtubeApiKey;

    // Get action from request
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        case 'fetchYouTubePlaylistData':
            fetchYouTubePlaylistData($_POST['playlistId']);
            break;
        case 'getPlaylistName':
            getPlaylistName($_POST['playlistId']);
            break;
        case 'fetchAccessToken':
            fetchAccessToken($_POST['code']);
            break;
        case 'createSpotifyPlaylist':
            createSpotifyPlaylist($_POST['playlistName'], $_POST['accessToken']);
            break;
        case 'searchSongOnSpotify':
            searchSongOnSpotify($_POST['songTitle'], $_POST['accessToken']);
            break;
        case 'addSongToSpotifyPlaylist':
            addSongToSpotifyPlaylist($_POST['playlistId'], $_POST['trackUri'], $_POST['accessToken']);
            break;
        case 'getURL':
            header('Content-Type: application/json');
            getURL();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Fetch YouTube playlist data
function fetchYouTubePlaylistData($playlistId) {
    global $youtubeApiKey;
    header('Content-Type: application/json');

    $apiUrl = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId=$playlistId&key=$youtubeApiKey";
    $response = file_get_contents($apiUrl);

    if ($response === FALSE) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to fetch YouTube playlist data']);
        return;
    }

    echo $response;
}

// Fetch YouTube playlist name
function getPlaylistName($playlistId) {
    global $youtubeApiKey;
    header('Content-Type: application/json');

    $apiUrl = "https://www.googleapis.com/youtube/v3/playlists?part=snippet&id=$playlistId&key=$youtubeApiKey";
    $response = file_get_contents($apiUrl);

    if ($response === FALSE) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to fetch YouTube playlist name']);
        return;
    }

    $data = json_decode($response, true);
    $playlistName = $data['items'][0]['snippet']['title'] ?? '';

    echo json_encode(['playlistName' => $playlistName]);
}

// Function to fetch access token from Spotify
function fetchAccessToken($code) {
    global $spotifyClientId, $spotifyClientSecret;

    $redirectUri = 'https://suman2799.infinityfreeapp.com'; // Your redirect URI
    $apiUrl = "https://accounts.spotify.com/api/token";
    $data = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri
    ]);

    $options = [
        'http' => [
            'header' => [
                "Authorization: Basic " . base64_encode("{$spotifyClientId}:{$spotifyClientSecret}"),
                "Content-Type: application/x-www-form-urlencoded"
            ],
            'method'  => 'POST',
            'content' => $data
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch access token']);
    } else {
        echo $response;
    }
}

// Create Spotify playlist
function createSpotifyPlaylist($playlistName, $accessToken) {
    $url = 'https://api.spotify.com/v1/me/playlists';
    header('Content-Type: application/json');
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];
    $data = json_encode([
        'name' => $playlistName,
        'public' => false
    ]);

    $options = [
        'http' => [
            'header' => $headers,
            'method' => 'POST',
            'content' => $data
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create Spotify playlist']);
    } else {
        // Decode the JSON response
        $responseData = json_decode($response, true);

        // Check if 'id' is set in the response
        if (isset($responseData['id'])) {
            $playlistId = $responseData['id'];
            echo json_encode(['playlistId' => $playlistId]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Playlist ID not found in the response']);
        }
    }
}

// Search for a song on Spotify
function searchSongOnSpotify($songTitle, $accessToken) {
    $url = 'https://api.spotify.com/v1/search?type=track&q=' . urlencode($songTitle);
    header('Content-Type: application/json');
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $options = [
        'http' => [
            'header' => $headers,
            'method' => 'GET'
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to search song on Spotify']);
        return;
    } 

    // Decode the JSON response
    $responseData = json_decode($response, true);

    // Check if 'items' and 'snippet' are set
    if (isset($responseData['tracks']['items'][0])) {
        $spotifyTrack = $responseData['tracks']['items'][0];
        echo json_encode(['spotifyTrack' => $spotifyTrack]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Song name not found in the response']);
    }
}

// Add a song to a Spotify playlist
function addSongToSpotifyPlaylist($playlistId, $trackUri, $accessToken) {
    $url = "https://api.spotify.com/v1/playlists/$playlistId/tracks";
    header('Content-Type: application/json');
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];
    $data = json_encode([
        'uris' => [$trackUri]
    ]);

    $options = [
        'http' => [
            'header' => $headers,
            'method' => 'POST',
            'content' => $data
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($response === FALSE) {
        http_response_code(400);
        echo json_encode(['error' => 'Failed to add song to Spotify playlist']);
        return;
    }

    echo $response;
}

// Get URL function
function getURL() {
    global $spotifyClientId;
    $redirectUri = 'https://suman2799.infinityfreeapp.com';
    $scopes = 'playlist-modify-private'; // Add any required scopes
    $authUrl = "https://accounts.spotify.com/authorize?client_id={$spotifyClientId}&redirect_uri=" . urlencode($redirectUri) . "&scope=" . urlencode($scopes) . "&response_type=code&show_dialog=true";

    echo json_encode(['authUrl' => $authUrl]);
}

// Check if it's an API request
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    handleRequest();
    exit;
}
?>