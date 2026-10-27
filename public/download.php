<?php
require_once __DIR__ . '/../app/auth.php';
requireLogin();

if (!isset($_GET['id']) || !isset($_GET['format'])) {
    exit('Invalid request');
}

$id = (int)$_GET['id'];
$format = strtolower($_GET['format']);
$db = getDB();

$sql = "SELECT * FROM transcriptions WHERE id = ?";
if ($_SESSION['user_role'] !== 'admin') {
    $sql .= " AND user_id = " . $_SESSION['user_id'];
}
$stmt = $db->prepare($sql);
$stmt->execute([$id]);
$t = $stmt->fetch();

if (!$t || $t['status'] !== 'COMPLETED') {
    exit('File not found or not completed.');
}

$jsonPath = STORAGE_PATH . '/transcriptions/' . pathinfo($t['stored_filename'], PATHINFO_FILENAME) . '.json';
$segments = file_exists($jsonPath) ? json_decode(file_get_contents($jsonPath), true) : [];

function formatTimeSRT($seconds) {
    $ms = round(($seconds - floor($seconds)) * 1000);
    $s = floor($seconds);
    return sprintf('%02d:%02d:%02d,%03d', ($s/3600),($s/60%60), $s%60, $ms);
}
function formatTimeVTT($seconds) {
    $ms = round(($seconds - floor($seconds)) * 1000);
    $s = floor($seconds);
    return sprintf('%02d:%02d:%02d.%03d', ($s/3600),($s/60%60), $s%60, $ms);
}

$filename = pathinfo($t['original_filename'], PATHINFO_FILENAME);

if ($format === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'.txt"');
    echo "TRANSCRIÇÃO\n";
    echo "Arquivo: " . $t['original_filename'] . "\n";
    echo "Idioma: " . strtoupper($t['language']) . "\n";
    echo "====================================\n\n";
    echo $t['transcription_text'];
} 
elseif ($format === 'srt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'.srt"');
    $index = 1;
    foreach($segments as $seg) {
        echo $index++ . "\n";
        echo formatTimeSRT($seg['start']) . " --> " . formatTimeSRT($seg['end']) . "\n";
        echo $seg['text'] . "\n\n";
    }
}
elseif ($format === 'vtt') {
    header('Content-Type: text/vtt; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'.vtt"');
    echo "WEBVTT\n\n";
    foreach($segments as $seg) {
        echo formatTimeVTT($seg['start']) . " --> " . formatTimeVTT($seg['end']) . "\n";
        echo $seg['text'] . "\n\n";
    }
}
elseif ($format === 'pdf') {
    require_once __DIR__ . '/../vendor/autoload.php';
    $options = new \Dompdf\Options();
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new \Dompdf\Dompdf($options);

    $html = '<h2 style="color: #333;">Transcrição de Vídeo</h2>';
    $html .= '<p><strong>Arquivo:</strong> ' . htmlspecialchars($t['original_filename']) . '<br>';
    $html .= '<strong>Data:</strong> ' . date('d/m/Y', strtotime($t['created_at'])) . '<br>';
    $html .= '<strong>Duração:</strong> ' . $t['duration'] . '<br>';
    $html .= '<strong>Idioma:</strong> ' . strtoupper($t['language']) . '<br>';
    $html .= '<strong>Modelo:</strong> ' . $t['model'] . '</p><hr>';
    
    foreach($segments as $seg) {
        $html .= '<p><strong style="color: #666; font-size: 12px;">' . formatTimeSRT($seg['start']) . '</strong><br>' . htmlspecialchars($seg['text']) . '</p>';
    }

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream($filename . ".pdf", ["Attachment" => true]);
}
elseif ($format === 'docx') {
    require_once __DIR__ . '/../vendor/autoload.php';
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    
    $section = $phpWord->addSection();
    
    $section->addText('TRANSCRIÇÃO DE VÍDEO', ['bold' => true, 'size' => 16]);
    $section->addTextBreak(1);
    
    $section->addText('Arquivo: ' . $t['original_filename']);
    $section->addText('Data: ' . date('d/m/Y', strtotime($t['created_at'])));
    $section->addText('Idioma: ' . strtoupper($t['language']));
    $section->addText('Modelo: ' . $t['model']);
    
    $section->addTextBreak(1);
    $section->addLine(['weight' => 1, 'width' => 600, 'height' => 0]);
    $section->addTextBreak(1);

    foreach($segments as $seg) {
        $section->addText(formatTimeSRT($seg['start']), ['bold' => true, 'color' => '555555']);
        $section->addText($seg['text']);
        $section->addTextBreak(1);
    }
    
    $file = tempnam(sys_get_temp_dir(), 'docx');
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($file);
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="'.$filename.'.docx"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    flush();
    readfile($file);
    unlink($file);
}
else {
    exit('Unsupported format');
}
