FROM python:3.10-slim

WORKDIR /app

# Cài đặt các gói phụ thuộc cần thiết
RUN apt-get update && apt-get install -y \
    build-essential \
    libffi-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

# Sao chép requirements.txt và cài đặt các thư viện Python
COPY requirements.txt .
RUN pip install --upgrade pip
RUN pip install --no-cache-dir -r requirements.txt

# Sao chép các file cần thiết từ dự án vào container
COPY main.py .
COPY disable_pickle.py .
COPY fix_openai_proxy.py .
COPY app/ ./app/
COPY routes/ ./routes/
COPY resources/ ./resources/
COPY .env ./.env

# Tạo các thư mục cần thiết
RUN mkdir -p vector_db
RUN mkdir -p storage

# Thiết lập biến môi trường
ENV DB_FAISS_PATH=/app/vector_db \
    EMBEDDING_MODEL=sentence-transformers/all-MiniLM-L6-v2 \
    ALLOW_PICKLE=true \
    STORAGE_PATH=/app/storage

# Tạo file app.log trống
RUN touch app.log && chmod 666 app.log

# Mở port 55050
EXPOSE 55050

# Lệnh để chạy ứng dụng và tạo file tar cho container
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "55050"]
