<?php
// Simple script to save base64 screenshot data to a file
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!empty($data['image']) && !empty($data['filename'])) {
        $filename = preg_replace('/[^a-z0-9_-]/', '', $data['filename']) . '.jpg';
        $image = str_replace('data:image/jpeg;base64,', '', $data['image']);
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = base64_decode($image);
        file_put_contents(__DIR__ . '/screenshots/' . $filename, $image);
        echo json_encode(['success' => true, 'file' => $filename]);
    } else {
        echo json_encode(['success' => false]);
    }
}
