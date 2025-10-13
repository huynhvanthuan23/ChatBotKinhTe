## Hướng dẫn clone dự án và setup web Laravel

```bash
# Clone dự án về máy
git clone https://github.com/huynhvanthuan23/ChatBotKinhTe.git

# Vào thư mục web
cd web

# Cài đặt các package của Laravel
composer install

# Tạo file cấu hình môi trường từ file mẫu
cp .env.example .env

# Tạo khóa ứng dụng Laravel
php artisan key:generate

# Tạo autoload mới cho composer
composer dump-autoload

# Chạy migration tạo bảng trong database
php artisan migrate

# Seed dữ liệu mặc định vào database (nếu có)
php artisan db:seed

# Khởi động server Laravel
php artisan serve



# Hướng dẫn sử dụng API cho ChatBot Kinh Tế

Chatbot hỗ trợ hai loại API: Google Gemini và OpenAI (GPT-4o Mini). Dưới đây là hướng dẫn chi tiết về cách cấu hình và sử dụng.

## 1. Cấu hình API

### Cấu hình trong file .env

File `.env` chứa các cấu hình quan trọng để kết nối với API:

```
# API Configuration
USE_API=true
# Loại API (google hoặc openai)
API_TYPE=google

# Google API settings
GOOGLE_API_KEY=your_google_api_key_here
GOOGLE_MODEL=gemini-1.5-pro

# OpenAI API settings
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-4o-mini
```
### lấy data kinh tế để vào thư mụcmục vector_db\core_data
link core_data:https://drive.google.com/drive/folders/1eODxZ6LP29lnMkd9oEFB6UXgu0dfiWE1?usp=sharing

### Chuyển đổi giữa Google Gemini và OpenAI

Để chuyển đổi giữa hai loại API, bạn chỉ cần thay đổi giá trị `API_TYPE` trong file `.env`:

- Sử dụng Google Gemini: `API_TYPE=google`
- Sử dụng OpenAI: `API_TYPE=openai`

## 2. Lấy API Key

### Google Gemini API

1. Truy cập [Google AI Studio](https://ai.google.dev/)
2. Đăng nhập với tài khoản Google của bạn
3. Vào mục "API keys" để tạo hoặc lấy API key
4. Sao chép API key và thêm vào file `.env` ở trường `GOOGLE_API_KEY`

### OpenAI API

1. Truy cập [OpenAI Platform](https://platform.openai.com/)
2. Đăng nhập hoặc đăng ký tài khoản
3. Vào mục "API keys" để tạo API key mới
4. Sao chép API key và thêm vào file `.env` ở trường `OPENAI_API_KEY`

## 3. Các model hỗ trợ

### Google Gemini
- gemini-1.5-pro (mặc định)
- gemini-1.5-flash
- gemini-pro
- và các model khác từ Google

### OpenAI
- gpt-4o-mini (mặc định)
- gpt-4o
- gpt-3.5-turbo
- và các model khác từ OpenAI

Để thay đổi model, cập nhật giá trị tương ứng trong file `.env`:
- `GOOGLE_MODEL=tên_model` cho Google Gemini
- `OPENAI_MODEL=tên_model` cho OpenAI

## 4. Kiểm tra API đang sử dụng

Bạn có thể kiểm tra thông tin API đang sử dụng bằng cách gọi API endpoint:

```
http://localhost:55050/api/v1/chat/service-info
```

Response sẽ hiển thị thông tin API đang được sử dụng:

```json
{
  "service_type": "API",
  "api_type": "Google",
  "model": "gemini-1.5-pro",
  "status": "active"
}
```

hoặc:

```json
{
  "service_type": "API",
  "api_type": "OpenAI",
  "model": "gpt-4o-mini",
  "status": "active"
}
```

## 5. Khởi động lại Docker sau khi thay đổi

Sau khi thay đổi cấu hình API trong file `.env`, bạn cần khởi động lại Docker container:

```bash
docker-compose down
docker-compose up -d
```

## 6. Lưu ý

- API key có giới hạn về số request, hãy sử dụng hợp lý
- Một số model có thể yêu cầu tài khoản trả phí
- Luôn bảo mật API key của bạn
- File vector_db chứa dữ liệu vector để truy vấn, không phụ thuộc vào loại API sử dụng 

## 7. Bảo mật API

Để bảo vệ API và tránh lộ thông tin nhạy cảm như OpenAI API key, hệ thống đã được triển khai cơ chế xác thực API key:

### Kích hoạt hệ thống API key

1. Tạo API key mới:
   ```bash
   php artisan api:key:generate
   ```

2. Lệnh trên sẽ tự động thêm API key vào file `.env` của Laravel. Nếu không thể tự động cập nhật, bạn có thể thêm thủ công:
   ```
   API_KEY=your_generated_api_key
   ```

### Sử dụng API key cho các requests

Khi gọi các API endpoints từ bên ngoài, thêm header `X-API-KEY` với giá trị là API key đã tạo:

```bash
curl -X GET https://api.chatbotkinhte.example.com/api/documents/123/info \
  -H "X-API-KEY: your_generated_api_key"
```

### Các endpoints được bảo vệ

Tất cả API endpoints đã được bảo vệ bằng middleware `api.key`, ngoại trừ một số endpoints công khai như `/api/info`.

### Khi triển khai lên server

Khi triển khai ứng dụng lên server production:

1. Đảm bảo file `.env` chứa API_KEY với giá trị mạnh
2. Không bao giờ chia sẻ API key trong mã nguồn công khai
3. Chỉ cung cấp API key cho các ứng dụng và người dùng được ủy quyền
4. Thay đổi API key định kỳ để tăng cường bảo mật
5. Giám sát logs để phát hiện các nỗ lực truy cập trái phép

Với cơ chế này, ứng dụng của bạn sẽ được bảo vệ khỏi các truy cập trái phép và tránh lộ thông tin nhạy cảm khi triển khai lên server public. 