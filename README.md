# ChatBot Kinh Tế

Tài liệu hướng dẫn cài đặt, cấu hình và sử dụng dự án ChatBot Kinh Tế.


## Mục lục

1. [Hướng dẫn Cài đặt & Khởi chạy](#hướng-dẫn-cài-đặt--khởi-chạy)
2. [Cấu hình API (Google Gemini & OpenAI)](#cấu-hình-api-google-gemini--openai)
3. [Hướng dẫn lấy API Key](#hướng-dẫn-lấy-api-key)
4. [Bảo mật API của ứng dụng](#bảo-mật-api-của-ứng-dụng)
5. [Lưu ý quan trọng](#lưu-ý-quan-trọng)

## Hướng dẫn Cài đặt & Khởi chạy

Thực hiện các bước sau để cài đặt và chạy ứng dụng web Laravel.

**1. Clone dự án**
Sao chép mã nguồn từ repository về máy của bạn:
```bash
git clone https://github.com/huynhvanthuan23/ChatBotKinhTe.git
```

**2. Di chuyển vào thư mục `web`**
```bash
cd web
```

**3. Cài đặt các thư viện PHP**
Sử dụng Composer để cài đặt các package cần thiết:
```bash
composer install
```

**4. Tải và đặt dữ liệu kinh tế**
Tải thư mục `core_data` từ link Google Drive dưới đây và đặt nó vào trong thư mục `vector_db` của dự án.
- **Link tải:** [https://drive.google.com/drive/folders/1eODxZ6LP29lnMkd9oEFB6UXgu0dfiWE1?usp=sharing](https://drive.google.com/drive/folders/1eODxZ6LP29lnMkd9oEFB6UXgu0dfiWE1?usp=sharing)

Cấu trúc thư mục cuối cùng sẽ là: `your-project/vector_db/core_data`.

**5. Tạo file môi trường `.env`**
Sao chép file cấu hình mẫu:
```bash
cp .env.example .env
```

**6. Tạo khóa ứng dụng (App Key)**
Lệnh này sẽ sinh ra một khóa bảo mật cho ứng dụng Laravel:
```bash
php artisan key:generate
```

**7. Tối ưu hóa Autoload**
Cập nhật file autoload của Composer để tối ưu hiệu suất:
```bash
composer dump-autoload
```

**8. Chạy Migrations**
Tạo các bảng cần thiết trong cơ sở dữ liệu của bạn:
```bash
php artisan migrate
```

**9. (Tùy chọn) Seed dữ liệu mẫu**
Thêm dữ liệu mặc định vào database nếu có:
```bash
php artisan db:seed
```

**10. Khởi động server**
Chạy server phát triển của Laravel (mặc định tại `http://127.0.0.1:8000`):
```bash
php artisan serve
```

---

## Cấu hình API (Google Gemini & OpenAI)

Chatbot hỗ trợ hai loại API: **Google Gemini** và **OpenAI (GPT)**.

### 1. Cấu hình trong file `.env`

Mở file `.env` và cập nhật các biến sau để kết nối với API mong muốn.

```dotenv
# Bật/tắt việc sử dụng API bên ngoài
USE_API=true

# Chọn loại API: 'google' hoặc 'openai'
API_TYPE=google

# --- Cấu hình cho Google Gemini ---
GOOGLE_API_KEY=your_google_api_key_here
GOOGLE_MODEL=gemini-1.5-pro

# --- Cấu hình cho OpenAI ---
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-4o-mini
```

### 2. Chuyển đổi giữa các API

Để thay đổi nhà cung cấp API, bạn chỉ cần chỉnh sửa giá trị của `API_TYPE` trong file `.env`:
- **Sử dụng Google Gemini**:
  ```dotenv
  API_TYPE=google
  ```
- **Sử dụng OpenAI**:
  ```dotenv
  API_TYPE=openai
  ```

### 3. Các model được hỗ trợ

Bạn có thể thay đổi model bằng cách cập nhật các biến `GOOGLE_MODEL` hoặc `OPENAI_MODEL`.

- **Google Gemini:**
  - `gemini-1.5-pro` (mặc định)
  - `gemini-1.5-flash`
  - `gemini-pro`
- **OpenAI:**
  - `gpt-4o-mini` (mặc định)
  - `gpt-4o`
  - `gpt-3.5-turbo`

### 4. Kiểm tra API đang hoạt động

Sử dụng endpoint sau để xem thông tin về dịch vụ API đang được cấu hình:
```bash
http://localhost:8000/api/v1/chat/service-info
```

**Phản hồi nếu dùng Google:**
```json
{
  "service_type": "API",
  "api_type": "Google",
  "model": "gemini-1.5-pro",
  "status": "active"
}
```
**Phản hồi nếu dùng OpenAI:**
```json
{
  "service_type": "API",
  "api_type": "OpenAI",
  "model": "gpt-4o-mini",
  "status": "active"
}
```

### 5. Khởi động lại Docker (Nếu sử dụng)

Nếu bạn đang chạy dự án với Docker, sau khi thay đổi file `.env`, hãy khởi động lại các container để áp dụng cấu hình mới.

Tắt các container:
```bash
docker-compose down
```
Khởi động lại ở chế độ nền:
```bash
docker-compose up -d
```

---

## Hướng dẫn lấy API Key

### Google Gemini API

1.  Truy cập trang [Google AI Studio](https://ai.google.dev/).
2.  Đăng nhập bằng tài khoản Google của bạn.
3.  Chọn mục **"API keys"** để tạo hoặc lấy API key đã có.
4.  Sao chép API key và dán vào biến `GOOGLE_API_KEY` trong file `.env`.

### OpenAI API

1.  Truy cập trang [OpenAI Platform](https://platform.openai.com/).
2.  Đăng nhập hoặc đăng ký tài khoản.
3.  Vào mục **"API keys"** để tạo một API key mới.
4.  Sao chép API key và dán vào biến `OPENAI_API_KEY` trong file `.env`.

---

## Bảo mật API của ứng dụng

Để bảo vệ các endpoint của ứng dụng khỏi truy cập trái phép, hệ thống sử dụng cơ chế xác thực bằng API key riêng.

### 1. Kích hoạt hệ thống API key

Chạy lệnh sau để tạo một API key mới:
```bash
php artisan api:key:generate
```
Lệnh này sẽ tự động thêm key vào file `.env`. Nếu không, bạn có thể thêm thủ công:
```dotenv
API_KEY=your_generated_api_key
```

### 2. Sử dụng API key khi gọi API

Khi gửi request đến các endpoint được bảo vệ, bạn cần thêm header `X-API-KEY` với giá trị là key đã tạo.

**Ví dụ với cURL:**
```bash
curl -X GET http://localhost:8000/api/documents/123/info \
  -H "X-API-KEY: your_generated_api_key"
```

### 3. Triển khai trên server production

- **Bảo mật file `.env`**: Đảm bảo file `.env` chứa `API_KEY` mạnh và không bị lộ.
- **Không chia sẻ key**: Không bao giờ đưa API key vào mã nguồn công khai (ví dụ: commit lên Git).
- **Ủy quyền**: Chỉ cung cấp API key cho các ứng dụng và người dùng được phép.
- **Xoay vòng key**: Thay đổi API key định kỳ để tăng cường bảo mật.

---

## Lưu ý quan trọng

- **Giới hạn API**: Các API key của Google và OpenAI có giới hạn sử dụng. Hãy dùng một cách hợp lý.
- **Chi phí**: Một số model có thể yêu cầu tài khoản trả phí.
- **Bảo mật key**: Luôn giữ bí mật API key của bạn, không chia sẻ công khai.
- **Dữ liệu Vector**: Thư mục `vector_db` chứa dữ liệu đã được vector hóa để truy vấn ngữ nghĩa, hoạt động độc lập và không phụ thuộc vào loại API (Google/OpenAI) bạn đang sử dụng.