# Hướng dẫn triển khai ChatBotKinhTe lên Server Linux với Nginx

Hướng dẫn này mô tả chi tiết các bước cần thiết để triển khai ứng dụng ChatBotKinhTe lên máy chủ Linux sử dụng Nginx làm web server với tên miền "chatbotkinhte.example.com".

## Mục lục
1. [Yêu cầu hệ thống](#yêu-cầu-hệ-thống)
2. [Chuẩn bị môi trường](#chuẩn-bị-môi-trường)
3. [Triển khai mã nguồn](#triển-khai-mã-nguồn)
4. [Cấu hình biến môi trường](#cấu-hình-biến-môi-trường)
5. [Cấu hình Nginx](#cấu-hình-nginx)
6. [Cấu hình SSL/TLS](#cấu-hình-ssltls)
7. [Khởi động dịch vụ](#khởi-động-dịch-vụ)
8. [Kiểm tra và xử lý sự cố](#kiểm-tra-và-xử-lý-sự-cố)

## Yêu cầu hệ thống

- Server Linux (Ubuntu 20.04 hoặc CentOS 8 được khuyến nghị)
- Nginx
- PHP 8.0+ với các extensions: mbstring, xml, curl, mysql, zip
- Composer 2.x
- Python 3.10+ 
- Node.js 14+ và npm/yarn
- MySQL 8.0+
- Tên miền đã trỏ về địa chỉ IP của server

## Chuẩn bị môi trường

### 1. Cài đặt các gói cần thiết

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y nginx python3 python3-pip python3-venv php8.1-fpm php8.1-mbstring php8.1-xml php8.1-curl php8.1-mysql php8.1-zip mysql-server nodejs npm unzip git

# CentOS/RHEL
sudo dnf install -y epel-release
sudo dnf install -y nginx python3 python3-pip python3-devel php php-fpm php-mbstring php-xml php-curl php-mysqlnd php-zip mysql-server nodejs npm unzip git
```

### 2. Cài đặt Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

## Triển khai mã nguồn

### 1. Clone hoặc upload mã nguồn

```bash
mkdir -p /var/www
cd /var/www
git clone https://your-repository-url.git chatbotkinhte
cd chatbotkinhte
```

### 2. Thiết lập backend (Python FastAPI)

```bash
cd /var/www/chatbotkinhte
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt

# Tạo các thư mục cần thiết
mkdir -p vector_db
mkdir -p storage
```

### 3. Thiết lập frontend (Laravel)

```bash
cd /var/www/chatbotkinhte/web
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan key:generate

# Thiết lập quyền
sudo chown -R www-data:www-data /var/www/chatbotkinhte
sudo chmod -R 755 /var/www/chatbotkinhte/storage
sudo chmod -R 755 /var/www/chatbotkinhte/web/storage
sudo chmod -R 755 /var/www/chatbotkinhte/web/bootstrap/cache
```

## Cấu hình biến môi trường

### 1. Cấu hình backend (.env)

Tạo hoặc sửa file `.env` ở thư mục gốc của dự án:

```bash
cd /var/www/chatbotkinhte
cp .env.example .env # nếu có file mẫu
```

Chỉnh sửa file `.env`:

```
# Chatbot API URL
CHATBOT_API_URL=https://chatbotkinhte.example.com/api/v1/chat/chat-direct
API_PORT=55050
API_HOST=127.0.0.1  # Sử dụng địa chỉ localhost/loopback để giữ API private

# Cấu hình khác tùy theo dự án
```

### 2. Cấu hình frontend (web/.env)

```bash
cd /var/www/chatbotkinhte/web
cp .env.example .env # nếu có file mẫu
```

Chỉnh sửa file `web/.env`:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://chatbotkinhte.example.com

# Kết nối API
CHATBOT_API_URL=https://chatbotkinhte.example.com/api

# Cấu hình database, cache và các cài đặt khác...
```

## Cấu hình Nginx

### 1. Tạo file cấu hình cho Nginx

```bash
sudo nano /etc/nginx/sites-available/chatbotkinhte.example.com
```

Thêm cấu hình sau:

```nginx
# Chuyển hướng HTTP sang HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name chatbotkinhte.example.com;
    return 301 https://$host$request_uri;
}

# HTTPS server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name chatbotkinhte.example.com;
    
    # SSL configuration
    ssl_certificate /etc/letsencrypt/live/chatbotkinhte.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/chatbotkinhte.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers 'ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256';
    
    # Root directory for Laravel frontend
    root /var/www/chatbotkinhte/web/public;
    index index.php index.html;
    
    # Frontend routes
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    # PHP-FPM configuration
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock; # Adjust PHP version as needed
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Backend API routes
    location /api {
        proxy_pass http://127.0.0.1:55050;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
    }
    
    # Cache & security
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires max;
        log_not_found off;
    }
    
    location ~ /\.ht {
        deny all;
    }
}
```

### 2. Kích hoạt cấu hình Nginx

```bash
sudo ln -s /etc/nginx/sites-available/chatbotkinhte.example.com /etc/nginx/sites-enabled/
sudo nginx -t  # Kiểm tra cú pháp cấu hình
sudo systemctl reload nginx
```

## Cấu hình SSL/TLS

Sử dụng Certbot để cài đặt chứng chỉ SSL/TLS miễn phí từ Let's Encrypt:

```bash
# Ubuntu
sudo apt install -y certbot python3-certbot-nginx

# CentOS
sudo dnf install -y certbot python3-certbot-nginx

# Lấy chứng chỉ
sudo certbot --nginx -d chatbotkinhte.example.com
```

## Khởi động dịch vụ

### 1. Tạo Systemd service cho backend API

```bash
sudo nano /etc/systemd/system/chatbotkinhte.service
```

Thêm nội dung sau:

```ini
[Unit]
Description=ChatBotKinhTe API Service
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/chatbotkinhte
Environment="PATH=/var/www/chatbotkinhte/venv/bin"
ExecStart=/var/www/chatbotkinhte/venv/bin/python main.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### 2. Khởi động và kích hoạt dịch vụ

```bash
sudo systemctl daemon-reload
sudo systemctl start chatbotkinhte
sudo systemctl enable chatbotkinhte
sudo systemctl status chatbotkinhte
```

## Kiểm tra và xử lý sự cố

### 1. Kiểm tra log

```bash
# Kiểm tra log Nginx
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/log/nginx/access.log

# Kiểm tra log backend
sudo journalctl -u chatbotkinhte.service -f

# Kiểm tra log Laravel
tail -f /var/www/chatbotkinhte/web/storage/logs/laravel.log
```

### 2. Kiểm tra kết nối

```bash
# Kiểm tra API
curl -I https://chatbotkinhte.example.com/api/v1/chat/health

# Kiểm tra website
curl -I https://chatbotkinhte.example.com
```

### 3. Xử lý các vấn đề thường gặp

1. **Vấn đề quyền truy cập**
   ```bash
   sudo chown -R www-data:www-data /var/www/chatbotkinhte
   sudo find /var/www/chatbotkinhte/storage -type d -exec chmod 755 {} \;
   sudo find /var/www/chatbotkinhte/web/storage -type d -exec chmod 755 {} \;
   ```

2. **Vấn đề Firewall**
   ```bash
   # Ubuntu
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   
   # CentOS
   sudo firewall-cmd --permanent --add-service=http
   sudo firewall-cmd --permanent --add-service=https
   sudo firewall-cmd --reload
   ```

3. **Khởi động lại các dịch vụ**
   ```bash
   sudo systemctl restart php8.1-fpm # Điều chỉnh phiên bản PHP nếu cần
   sudo systemctl restart nginx
   sudo systemctl restart chatbotkinhte
   ```

---

Sau khi hoàn thành các bước trên, ChatBotKinhTe của bạn sẽ có thể hoạt động trên tên miền https://chatbotkinhte.example.com với Nginx làm web server. 