# ChatBotKinhTe

Chatbot thông minh chuyên về kinh tế sử dụng mô hình ngôn ngữ LLM.

## Cài đặt

### Bước 1: Clone repository

```bash
git clone https://github.com/your-username/ChatBotKinhTe.git
cd ChatBotKinhTe
```

### Bước 2: Tạo môi trường ảo và cài đặt thư viện

```bash
python -m venv venv
source venv/bin/activate  # Trên Windows: venv\Scripts\activate
pip install --upgrade pip
pip install -r requirements.txt
```

### Bước 3: Tải model và tạo vector database

```bash
# Tải model
python download_models.py

# Tạo vector database
python create_vector_store.py
```

### Bước 4: Cấu hình

```bash
cp .env.example .env
# Chỉnh sửa file .env theo nhu cầu
```

## Chạy ứng dụng

### Backend

```bash
python main.py
```

Backend API sẽ chạy tại `http://localhost:8080/api/v1/`

### Frontend

```bash
cd web
php artisan serve
```

Frontend sẽ chạy tại `http://localhost:8000/`

## Lưu ý

- Repository này không chứa các file model và vector database do kích thước lớn. Hãy sử dụng script `download_models.py` để tải model và `create_vector_store.py` để tạo vector database.