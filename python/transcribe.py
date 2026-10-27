import sys
import json
import argparse
import os

# Desabilita verificação de SSL (útil para redes corporativas ou antivírus que injetam certificados self-signed)
os.environ["CURL_CA_BUNDLE"] = ""
os.environ["REQUESTS_CA_BUNDLE"] = ""

from faster_whisper import WhisperModel

def main():
    parser = argparse.ArgumentParser(description="Transcreve áudio usando faster-whisper")
    parser.add_argument("--audio", required=True, help="Caminho do arquivo de áudio")
    parser.add_argument("--model", default="small", help="Modelo do Whisper (tiny, base, small, medium, large-v3)")
    parser.add_argument("--language", default="auto", help="Idioma (ex: pt, en, es) ou auto")
    parser.add_argument("--device", default="cpu", help="Dispositivo para processamento (cpu ou cuda)")
    
    args = parser.parse_args()

    # Otimização básica: int8 para CPU. Para CUDA pode ser float16.
    compute_type = "int8" if args.device == "cpu" else "float16"
    
    try:
        model = WhisperModel(args.model, device=args.device, compute_type=compute_type)
        
        language = None if args.language == "auto" else args.language
        
        segments, info = model.transcribe(args.audio, language=language, beam_size=5)
        
        result_segments = []
        full_text = []
        
        for segment in segments:
            progress = min(100, int((segment.end / info.duration) * 100)) if info.duration > 0 else 0
            print(f"PROGRESS:{progress}", flush=True)
            result_segments.append({
                "start": round(segment.start, 2),
                "end": round(segment.end, 2),
                "text": segment.text.strip()
            })
            full_text.append(segment.text.strip())
            
        output = {
            "status": "success",
            "language": info.language,
            "duration": round(info.duration, 2),
            "text": "\n".join(full_text),
            "segments": result_segments
        }
        
        print("JSON_START", flush=True)
        print(json.dumps(output, ensure_ascii=False))
        
    except Exception as e:
        error_output = {
            "status": "error",
            "message": str(e)
        }
        print(json.dumps(error_output, ensure_ascii=False))
        sys.exit(1)

if __name__ == "__main__":
    main()
