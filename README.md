# Transkriptor

Transkriptor é uma ferramenta self-hosted para transcrição de áudio e vídeo utilizando Inteligência Artificial de ponta (Whisper) rodando 100% localmente na sua máquina, garantindo total privacidade dos seus dados.

## Recursos
- Transcrição de áudio e vídeo usando `faster-whisper`.
- Suporte a modelos locais sem limite de uso ou mensalidade.
- Acompanhamento de progresso em tempo real no dashboard.
- Exportação nos formatos **TXT, PDF, Word (DOCX), SRT e VTT**.
- Gestão de fila em background (Worker).
- Totalmente compatível com Docker para instalação em 1 minuto.

## Instalação Rápida (Recomendado: via Docker)

A forma mais fácil de rodar o Transkriptor é usando o Docker Compose. O Docker já configura o PHP, o Python, o FFmpeg e o banco de dados MySQL automaticamente.

### Requisitos
- [Docker](https://www.docker.com/products/docker-desktop)
- [Docker Compose](https://docs.docker.com/compose/install/)

### Passo a Passo

1. Abra o terminal na pasta do projeto e execute:
```bash
docker-compose up -d --build
```

2. Pronto! O Docker irá construir a imagem, baixar os modelos necessários e ligar 3 serviços em background:
   - **Banco de Dados (MySQL)**
   - **Servidor Web (Apache + PHP)**
   - **Worker (Fila de transcrição rodando em segundo plano)**

3. Acesse a aplicação no seu navegador:
[http://localhost](http://localhost)

> O painel administrativo e a tela de login já estarão disponíveis.
> Credenciais padrão de admin (se criadas automaticamente) ou você pode criar um novo usuário acessando a página de registro `http://localhost/public/register.php`.

---

## Usando a Aplicação
- Faça o upload de um arquivo de vídeo (ex: MP4) ou áudio (ex: MP3).
- O arquivo será enviado para a nuvem local.
- O Worker do Docker, que roda em segundo plano constantemente, irá capturar o arquivo na fila.
- Você não precisa manter nenhum terminal aberto! O Worker fica rodando "invisível" gerenciando toda a fila 24/7.
- Acompanhe o progresso em tempo real na tela Dashboard.

## Tecnologias Utilizadas
- **Backend:** PHP 8 (PDO, MVC)
- **Frontend:** Vanilla JS, CSS3 Glassmorphism
- **AI Core:** Python 3, faster-whisper, ctranslate2
- **Audio Processing:** FFmpeg
- **Exportação:** mPDF, PHPWord
- **Containerização:** Docker e Docker Compose
