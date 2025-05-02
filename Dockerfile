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

# Tạo thư mục vector_db nếu chưa tồn tại
RUN mkdir -p vector_db

# Mở port 55050
EXPOSE 55050

# Lệnh để chạy ứng dụng
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "55050"]
