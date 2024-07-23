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

    $redirectUri = 'https://suman2799.github.io/yt-playlist-to-Spotify'; // Your redirect URI
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
    $redirectUri = 'https://suman2799.github.io/yt-playlist-to-Spotify';
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

<!-- *******************************************************HTML************************************************************** -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convert YouTube Playlist to Spotify</title>

    <style>

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-Black.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-Black.otf') format('opentype');
            font-weight: 900;
        }

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-BlackItalic.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-BlackItalic.otf') format('opentype');
            font-style: italic;
            font-weight: 900;
        }

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-Bold.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-Bold.otf') format('opentype');
            font-weight: bold;
        }

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-BoldItalic.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-BoldItalic.otf') format('opentype');
            font-style: italic;
            font-weight: bold;
        }

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-Book.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-Book.otf') format('opentype');
            font-weight: normal;
        }

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-BookItalic.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-BookItalic.otf') format('opentype');
            font-style: italic;
            font-weight: normal;
        }

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-Light.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-Light.otf') format('opentype');
            font-weight: 300;
        }

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-LightItalic.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-LightItalic.otf') format('opentype');
            font-style: italic;
            font-weight: 300;
        }

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-Medium.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-Medium.otf') format('opentype');
            font-weight: 500;
        }

        @font-face {
            font-family: 'Circular';
            /* src: url('CircularStd-MediumItalic.otf') format('opentype'); */
            src: local(''),
                    url('fonts/CircularStd-MediumItalic.otf') format('opentype');
            font-style: italic;
            font-weight: 500;
        }

        body {
            font-family: "Circular", Arial, sans-serif; /* Spotify's font */
            font-weight: 700;
            background-color: #191414; /* Spotify's background color */
            color: #FFFFFF; /* White text */
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        h1 {
            font-family: 'Circular';
            letter-spacing: -2px;
            display: block;
            font-size: 2em;
            margin-block-start: 0.67em;
            margin-block-end: 0.67em;
            margin-inline-start: 0px;
            margin-inline-end: 0px;
            font-weight: bolder;
            unicode-bidi: isolate;
        }

        .container {
            max-width: 500px;
            width: 100%;
            margin: 30px;
            padding: 20px;
            border-radius: 10px;
            background-color: #1DB954; /* Spotify's green accent color */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .input-group {
            margin-bottom: 20px;
        }

        input[type="text"] {
            width: 90%;
            padding: 10px;
            border: none;
            border-radius: 5px;
            margin-top: 5px;
        }

        button {
            font-family: 'Circular';
            font-weight: bolder;
            font-size: x-large;
            letter-spacing: -2px;
            padding: 10px 20px;
            background-color: #191414;
            color: #FFFFFF;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #383838; /* Darker shade of Spotify's background color */
        }

        .wait {
            background-color: #ffbb00; /* Blue background */
            color: #FFFFFF; /* White text */
            font-weight: bold;
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
            display: none; /* Initially hidden */
        }

        .message {
            background-color: #007bff; /* Blue background */
            color: #FFFFFF; /* White text */
            font-weight: bold;
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
            display: none; /* Initially hidden */
        }

        .error {
            background-color: #FF4136; /* Spotify's red accent color */
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>

</head>
<body>
    <!-- Login Button Container -->
    <div class="container" id="loginContainer" style="display: block;">
        <h1>YouTube Playlist to Spotify</h1>
        <button id="loginBtn">Login with Spotify</button>
    </div>

    <!-- Playlist Conversion Container -->
    <div class="container" id="playlistContainer" style="display: none;">
        <h1>YouTube Playlist to Spotify</h1>
        <div class="input-group">
            <label for="playlistUrl">Enter YouTube Playlist URL:</label>
            <input type="text" id="playlistUrl" name="playlistUrl">
        </div>
        <button onclick="main()">Submit</button>
        <div class="wait" id="waitMessage" style="display: none;"></div>
        <div class="message" id="successMessage" style="display: none;"></div>
        <div class="error" id="errorMessage" style="display: none;"></div>
    </div>

    <script>

        async function fetchYouTubePlaylistData(youtubePlaylistId) {
            const response = await fetch('index.php?action=fetchYouTubePlaylistData', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'playlistId': youtubePlaylistId
                })
            });
            const data = await response.json();
            
            console.log(data);

            if (response.status < 200 || response.status >= 300) {
                showError("Error fetching YouTube playlist data.");
            }

            return data;
        }

        async function getPlaylistName(playlistId) {
            const response = await fetch('index.php?action=getPlaylistName', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'playlistId': playlistId
                })
            });
            const data = await response.json();
            
            console.log(data);

            if (response.status < 200 || response.status >= 300) {
                showError("Error getting playlist name.");
            }

            return data;
        }

        async function createSpotifyPlaylist(playlistName, accessToken) {
            const response = await fetch('index.php?action=createSpotifyPlaylist', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'playlistName': playlistName,
                    'accessToken': accessToken
                })
            });
            const data = await response.json();

            if (response.status >= 400) {
                throw new Error(data.error || 'Failed to create Spotify playlist');
            }

            return data;
        }

        async function searchSongOnSpotify(songTitle, accessToken) {
            const response = await fetch('index.php?action=searchSongOnSpotify', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'songTitle': songTitle,
                    'accessToken': accessToken
                })
            });
            const data = await response.json();

            if (response.status >= 400) {
                throw new Error(data.error || 'Failed to search song on Spotify');
            }

            return data;
        }

        async function addSongToSpotifyPlaylist(playlistId, trackUri, accessToken) {
            const response = await fetch('index.php?action=addSongToSpotifyPlaylist', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'playlistId': playlistId,
                    'trackUri': trackUri,
                    'accessToken': accessToken
                })
            });
            const data = await response.json();

            if (response.status >= 400) {
                throw new Error(data.error || 'Failed to add song to Spotify playlist');
            }

            return data;
        }

        async function fetchAccessToken(code) {
            const response = await fetch('index.php?action=fetchAccessToken', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    'code': code
                })
            });
            const data = await response.json();

            if (response.status >= 400) {
                throw new Error(data.error || 'Failed to fetch access token');
            }

            return data.access_token;
        }

        async function convertYouTubePlaylistToSpotify(youtubePlaylistId, spotifyPlaylistName, accessToken) {
            try {
                const youtubePlaylistData = await fetchYouTubePlaylistData(youtubePlaylistId);
                const spotifyPlaylist = await createSpotifyPlaylist(spotifyPlaylistName, accessToken);
                console.log("spotifyPlaylistName:" + spotifyPlaylistName + "spotifyPlaylist:" + spotifyPlaylist.playlistId);

                for (const item of youtubePlaylistData.items) {
                    const title = item.snippet.title;
                    const songTitle = title.replace(/[^a-zA-Z0-9\s]/g, '');

                    console.log("songTitle: " + songTitle);

                    const spotifyTrack = await searchSongOnSpotify(songTitle, accessToken);

                    console.log("spotifyTrack.uri: " + spotifyTrack.spotifyTrack.uri);

                    if (spotifyTrack) {
                        await addSongToSpotifyPlaylist(spotifyPlaylist.playlistId, spotifyTrack.spotifyTrack.uri, accessToken);

                        document.getElementById('waitMessage').innerHTML = `Song added: ${songTitle}`;
                    } else {
                        showError(`Song not found on Spotify: ${songTitle}`);
                    }
                }

                document.getElementById('waitMessage').style.display = 'none';
                document.getElementById('successMessage').style.display = 'block';
                document.getElementById('successMessage').innerHTML = "Playlist conversion completed successfully.";
            } catch (error) {
                showError(error.message);
            }
        }

        async function main() {
            try {
                document.getElementById('waitMessage').style.display = 'block';
                document.getElementById('waitMessage').innerHTML = "Please wait a while...";

                const playlistUrl = document.getElementById('playlistUrl').value;
                const plIds = playlistUrl.match(/list=([a-zA-Z0-9_-]+)/);
                const playlistId = plIds[1];

                console.log("playlistId: " + playlistId);

                const urlParams = new URLSearchParams(window.location.search);
                const code = urlParams.get('code');
                const accessToken = await fetchAccessToken(code);

                console.log("accessToken:" + accessToken);

                const playlistName = await getPlaylistName(playlistId);

                console.log("playlistName: " + playlistName.playlistName);

                convertYouTubePlaylistToSpotify(playlistId, playlistName.playlistName, accessToken);
            } catch (error) {
                showError(error.message);
            }
        }

        function showError(message) {
            document.getElementById('errorMessage').style.display = 'block';
            document.getElementById('errorMessage').innerHTML = message;
        }

        document.getElementById('loginBtn').addEventListener('click', async () => {
            const response = await fetch('index.php?action=getURL');
            const data = await response.json();

            if (response.status >= 400) {
                throw new Error(data.error || 'Failed to fetch URL');
            }

            window.open(data.authUrl, '_self');
        });

        const urlParams = new URLSearchParams(window.location.search);
        const code = urlParams.get('code');

        if (code) {
            document.getElementById('loginContainer').style.display = 'none';
            document.getElementById('playlistContainer').style.display = 'block';
        }
    </script>
</body>
</html>
