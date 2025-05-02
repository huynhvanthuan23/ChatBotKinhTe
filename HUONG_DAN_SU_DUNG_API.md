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