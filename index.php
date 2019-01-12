<?php
    require_once 'api.php';
    $imageMagick = new ImageMagick();

    $callMethod = $_SERVER['REQUEST_METHOD'];
    switch ($callMethod) {
        case 'POST':
            $imageMagick->post($_POST);
        break;
        case 'GET':
            $imageMagick->get($_GET);
        break;
        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'server method ' . $callMethod . ' not found!'
            ]);
        break;
    }
