<?php
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/db.php';

echo "=====================================\n";
echo " Transcritor Worker CLI Iniciado     \n";
echo "=====================================\n";
echo "Aguardando tarefas...\n";

$db = getDB();

while (true) {
    file_put_contents(__DIR__ . '/../storage/worker_ping.txt', time());
    $task = null;
    try {
        // Busca a próxima tarefa PENDING
        $db->beginTransaction();
        $stmt = $db->query("SELECT * FROM transcriptions WHERE status = 'PENDING' ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
        $task = $stmt->fetch();
        
        if (!$task) {
            $db->rollBack();
            sleep(5); // Aguarda 5 segundos antes de checar novamente
            continue;
        }

        // Atualiza para PROCESSING
        $updateStmt = $db->prepare("UPDATE transcriptions SET status = 'PROCESSING' WHERE id = ?");
        $updateStmt->execute([$task['id']]);
        $db->commit();
        
        echo "\n[" . date('Y-m-d H:i:s') . "] [Task {$task['id']}] Iniciando processamento do arquivo: {$task['original_filename']}\n";
        
        $originalFilePath = STORAGE_PATH . '/uploads/' . $task['stored_filename'];
        $audioFilePath = STORAGE_PATH . '/audio/' . pathinfo($task['stored_filename'], PATHINFO_FILENAME) . '.wav';
        
        // ----------------------------------------------------
        // Passo 1: Extrair áudio via FFmpeg
        // ----------------------------------------------------
        echo "[Task {$task['id']}] Extraindo áudio usando FFmpeg...\n";
        $ffmpegPath = getenv('FFMPEG_PATH') ?: 'ffmpeg';
        // Converte para WAV 16kHz, mono (formato ideal exigido pelo Whisper)
        $cmdFfmpeg = escapeshellarg($ffmpegPath) . " -i " . escapeshellarg($originalFilePath) . " -ar 16000 -ac 1 -c:a pcm_s16le -y " . escapeshellarg($audioFilePath) . " 2>&1";
        
        exec($cmdFfmpeg, $outputFfmpeg, $returnFfmpeg);
        
        if ($returnFfmpeg !== 0 || !file_exists($audioFilePath)) {
            $ffmpegLog = implode("\n", $outputFfmpeg);
            throw new Exception("Erro ao extrair áudio com FFmpeg. Código: $returnFfmpeg\nSaída do FFmpeg:\n$ffmpegLog");
        }

        // ----------------------------------------------------
        // Passo 3: Executar Whisper (Python) em tempo real
        // ----------------------------------------------------
        $pythonPath = escapeshellarg(getenv('PYTHON_PATH') ?: 'python');
        $scriptPath = escapeshellarg(__DIR__ . '/../python/transcribe.py');
        $audioArg   = escapeshellarg($audioFilePath);
        $modelArg   = escapeshellarg($task['model']);
        $langArg    = $task['language'] === 'auto' ? '' : '--language ' . escapeshellarg($task['language']);

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if ($isWindows) {
            $cmdWhisper = "set PYTHONIOENCODING=utf-8 && $pythonPath $scriptPath --audio $audioArg --model $modelArg $langArg";
        } else {
            $cmdWhisper = "PYTHONIOENCODING=utf-8 $pythonPath $scriptPath --audio $audioArg --model $modelArg $langArg";
        }
        
        echo "[Task {$task['id']}] Transcrevendo com faster-whisper (Modelo: {$task['model']}, Idioma: {$task['language']})...\n";
        echo "[Task {$task['id']}] Acompanhando progresso em tempo real...\n";
        
        $descriptorspec = [
            0 => ["pipe", "r"], 
            1 => ["pipe", "w"], 
            2 => ["pipe", "w"] 
        ];

        $process = proc_open($cmdWhisper, $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new Exception("Falha ao iniciar processo do Whisper.");
        }

        stream_set_blocking($pipes[1], false);
        
        $jsonOutput = "";
        $isJson = false;
        
        while (true) {
            file_put_contents(__DIR__ . '/../storage/worker_ping.txt', time());
            $status = proc_get_status($process);
            while (($line = fgets($pipes[1])) !== false) {
                if (!$isJson) {
                    $trimmed = trim($line);
                    if ($trimmed === "JSON_START") {
                        $isJson = true;
                    } elseif (strpos($trimmed, "PROGRESS:") === 0) {
                        $prog = (int)trim(substr($trimmed, 9));
                        $db->query("UPDATE transcriptions SET progress = $prog WHERE id = " . $task['id']);
                    } elseif ($trimmed !== '') {
                        echo "[Whisper] " . $trimmed . "\n";
                    }
                } else {
                    $jsonOutput .= $line;
                }
            }
            if (!$status['running']) {
                // Esvaziar buffers finais
                while (($line = fgets($pipes[1])) !== false) {
                    if (!$isJson) {
                        $trimmed = trim($line);
                        if ($trimmed === "JSON_START") {
                            $isJson = true;
                        } elseif (strpos($trimmed, "PROGRESS:") === 0) {
                            $prog = (int)trim(substr($trimmed, 9));
                            $db->query("UPDATE transcriptions SET progress = $prog WHERE id = " . $task['id']);
                        } elseif ($trimmed !== '') {
                            echo "[Whisper] " . $trimmed . "\n";
                        }
                    } else {
                        $jsonOutput .= $line;
                    }
                }
                break;
            }
            usleep(200000); // 0.2s
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $returnWhisper = proc_close($process);

        if ($returnWhisper !== 0 || empty($jsonOutput)) {
            throw new Exception("Erro no Whisper: $stderr");
        }
        
        $result = json_decode($jsonOutput, true);
        if (!$result) {
            throw new Exception("Erro ao decodificar JSON gerado pelo Whisper.");
        }

        // ----------------------------------------------------
        // Passo 3: Salvar resultados
        // ----------------------------------------------------
        echo "[Task {$task['id']}] Transcrição finalizada com sucesso!\n";
        
        $completedStmt = $db->prepare("UPDATE transcriptions SET status = 'COMPLETED', transcription_text = ?, duration = ?, language = ?, completed_at = NOW() WHERE id = ?");
        
        $segmentsFile = STORAGE_PATH . '/transcriptions/' . pathinfo($task['stored_filename'], PATHINFO_FILENAME) . '.json';
        file_put_contents($segmentsFile, json_encode($result['segments'], JSON_UNESCAPED_UNICODE));
        
        $durationSeconds = intval($result['duration']);
        $durationFormatted = sprintf('%02d:%02d:%02d', ($durationSeconds/3600),($durationSeconds/60%60), $durationSeconds%60);

        // Extrai o texto completo concatenando os segmentos
        $fullText = "";
        foreach ($result['segments'] as $seg) {
            $fullText .= $seg['text'] . " ";
        }
        $fullText = trim($fullText);

        $completedStmt->execute([
            $fullText, 
            $durationFormatted, 
            strtoupper($result['language']), 
            $task['id']
        ]);

    } catch (PDOException $e) {
        echo "\n[Erro Crítico no Banco de Dados] " . $e->getMessage() . "\n";
        echo "A conexão caiu. O worker será encerrado para evitar loops. Por favor, rode 'php worker.php' novamente.\n";
        exit(1);
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        echo "[Erro na Task] " . $e->getMessage() . "\n";
        
        // Marca como ERRO no banco
        if (is_array($task) && isset($task['id'])) {
            try {
                $errStmt = $db->prepare("UPDATE transcriptions SET status = 'ERROR', error_message = ? WHERE id = ?");
                $errStmt->execute([$e->getMessage(), $task['id']]);
            } catch (Exception $ex) {
                // Ignore if it fails to update the error
            }
        }
    }
}
