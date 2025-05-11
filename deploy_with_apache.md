# Hướng dẫn triển khai ChatBotKinhTe lên Server Linux với Apache

Hướng dẫn này mô tả chi tiết các bước cần thiết để triển khai ứng dụng ChatBotKinhTe lên máy chủ Linux sử dụng Apache làm web server với tên miền "chatbotkinhte.example.com".

## Mục lục
1. [Yêu cầu hệ thống](#yêu-cầu-hệ-thống)
2. [Chuẩn bị môi trường](#chuẩn-bị-môi-trường)
3. [Triển khai mã nguồn](#triển-khai-mã-nguồn)
4. [Cấu hình biến môi trường](#cấu-hình-biến-môi-trường)
5. [Cấu hình Apache](#cấu-hình-apache)
6. [Cấu hình SSL/TLS](#cấu-hình-ssltls)
7. [Khởi động dịch vụ](#khởi-động-dịch-vụ)
8. [Kiểm tra và xử lý sự cố](#kiểm-tra-và-xử-lý-sự-cố)

## Yêu cầu hệ thống

- Server Linux (Ubuntu 20.04 hoặc CentOS 8 được khuyến nghị)
- Apache 2.4+ với mod_rewrite và mod_proxy
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
sudo apt install -y apache2 python3 python3-pip python3-venv php libapache2-mod-php php-mbstring php-xml php-curl php-mysql php-zip mysql-server nodejs npm unzip git

# CentOS/RHEL
sudo dnf install -y epel-release
sudo dnf install -y httpd python3 python3-pip python3-devel php php-mbstring php-xml php-curl php-mysqlnd php-zip mysql-server nodejs npm unzip git
```

### 2. Kích hoạt các module cần thiết cho Apache

```bash
# Ubuntu/Debian
sudo a2enmod rewrite proxy proxy_http proxy_balancer lbmethod_byrequests ssl headers
sudo systemctl restart apache2

# CentOS/RHEL
sudo sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/g' /etc/httpd/conf.modules.d/00-base.conf
sudo sed -i 's/#LoadModule proxy_module/LoadModule proxy_module/g' /etc/httpd/conf.modules.d/00-proxy.conf
sudo sed -i 's/#LoadModule proxy_http_module/LoadModule proxy_http_module/g' /etc/httpd/conf.modules.d/00-proxy.conf
sudo systemctl restart httpd
```

### 3. Cài đặt Composer

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
# Ubuntu/Debian
sudo chown -R www-data:www-data /var/www/chatbotkinhte
# CentOS/RHEL
sudo chown -R apache:apache /var/www/chatbotkinhte

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

## Cấu hình Apache

### 1. Tạo VirtualHost cho Apache

```bash
# Ubuntu/Debian
sudo nano /etc/apache2/sites-available/chatbotkinhte.example.com.conf

# CentOS/RHEL
sudo nano /etc/httpd/conf.d/chatbotkinhte.example.com.conf
```

Thêm cấu hình sau:

```apache
# Chuyển hướng HTTP sang HTTPS
<VirtualHost *:80>
    ServerName chatbotkinhte.example.com
    Redirect permanent / https://chatbotkinhte.example.com/
</VirtualHost>

# HTTPS server
<VirtualHost *:443>
    ServerName chatbotkinhte.example.com
    ServerAdmin webmaster@example.com
    DocumentRoot /var/www/chatbotkinhte/web/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/chatbotkinhte.example.com/cert.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/chatbotkinhte.example.com/privkey.pem
    SSLCertificateChainFile /etc/letsencrypt/live/chatbotkinhte.example.com/chain.pem
    
    # Various SSL options
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLHonorCipherOrder on
    SSLCompression off
    
    # Frontend configuration
    <Directory /var/www/chatbotkinhte/web/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # Backend API proxy configuration
    ProxyPreserveHost On
    
    # API routes
    <Location /api>
        ProxyPass http://localhost:55050
        ProxyPassReverse http://localhost:55050
    </Location>
    
    # Log configuration
    ErrorLog ${APACHE_LOG_DIR}/chatbotkinhte-error.log
    CustomLog ${APACHE_LOG_DIR}/chatbotkinhte-access.log combined
    
    # Additional security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</VirtualHost>
```

### 2. Kích hoạt cấu hình Apache

```bash
# Ubuntu/Debian
sudo a2ensite chatbotkinhte.example.com.conf
sudo apache2ctl configtest  # Kiểm tra cú pháp cấu hình
sudo systemctl reload apache2

# CentOS/RHEL
sudo apachectl configtest  # Kiểm tra cú pháp cấu hình
sudo systemctl reload httpd
```

## Cấu hình SSL/TLS

Sử dụng Certbot để cài đặt chứng chỉ SSL/TLS miễn phí từ Let's Encrypt:

```bash
# Ubuntu/Debian
sudo apt install -y certbot python3-certbot-apache

# CentOS/RHEL
sudo dnf install -y certbot python3-certbot-apache

# Lấy chứng chỉ
sudo certbot --apache -d chatbotkinhte.example.com
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
# Ubuntu/Debian
User=www-data
Group=www-data
# CentOS/RHEL (bỏ comment dòng dưới và comment 2 dòng trên nếu dùng CentOS/RHEL)
# User=apache
# Group=apache
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
# Kiểm tra log Apache
# Ubuntu/Debian
sudo tail -f /var/log/apache2/chatbotkinhte-error.log
sudo tail -f /var/log/apache2/chatbotkinhte-access.log

# CentOS/RHEL
sudo tail -f /var/log/httpd/chatbotkinhte-error.log
sudo tail -f /var/log/httpd/chatbotkinhte-access.log

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
   # Ubuntu/Debian
   sudo chown -R www-data:www-data /var/www/chatbotkinhte
   
   # CentOS/RHEL
   sudo chown -R apache:apache /var/www/chatbotkinhte
   
   sudo find /var/www/chatbotkinhte/storage -type d -exec chmod 755 {} \;
   sudo find /var/www/chatbotkinhte/web/storage -type d -exec chmod 755 {} \;
   ```

2. **Vấn đề SELinux (CentOS/RHEL)**
   ```bash
   # Cho phép Apache truy cập network (cho proxy)
   sudo setsebool -P httpd_can_network_connect 1
   
   # Thiết lập context cho thư mục web
   sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/chatbotkinhte/web/storage(/.*)?"
   sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/chatbotkinhte/web/bootstrap/cache(/.*)?"
   sudo restorecon -Rv /var/www/chatbotkinhte/web
   ```

3. **Vấn đề Firewall**
   ```bash
   # Ubuntu/Debian
   sudo ufw allow 80/tcp
   sudo ufw allow 443/tcp
   
   # CentOS/RHEL
   sudo firewall-cmd --permanent --add-service=http
   sudo firewall-cmd --permanent --add-service=https
   sudo firewall-cmd --reload
   ```

4. **Khởi động lại các dịch vụ**
   ```bash
   # Ubuntu/Debian
   sudo systemctl restart apache2
   
   # CentOS/RHEL
   sudo systemctl restart httpd
   
   sudo systemctl restart chatbotkinhte
   ```

5. **Vấn đề với module .htaccess**
   ```bash
   # Đảm bảo mod_rewrite đã được kích hoạt
   # Ubuntu/Debian
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   
   # Kiểm tra file .htaccess của Laravel
   cat /var/www/chatbotkinhte/web/public/.htaccess
   ```

---

Sau khi hoàn thành các bước trên, ChatBotKinhTe của bạn sẽ có thể hoạt động trên tên miền https://chatbotkinhte.example.com với Apache làm web server. 