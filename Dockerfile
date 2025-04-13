FROM python:3.9-slim

WORKDIR /app

# Cài đặt các gói phụ thuộc
RUN apt-get update && apt-get install -y \
    build-essential \
    libffi-dev \
    git \
    && rm -rf /var/lib/apt/lists/*

# Sao chép requirements.txt và cài đặt các thư viện Python
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Sao chép các file cần thiết từ dự án vào container
COPY main.py .
COPY create_vector_store.py .
COPY app/ ./app/
COPY routes/ ./routes/
COPY resources/ ./resources/
COPY models/ ./models/
COPY .env.example ./.env

# Tạo thư mục data và vector_db nếu chưa tồn tại
RUN mkdir -p data/Bao_moi data/thoi_bao_tai_chinh data/vneconomy data/Vnexpress vector_db

# Mở port 55050
EXPOSE 55050

# Lệnh để chạy ứng dụng
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "55050"]
