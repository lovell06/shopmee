# Configuration

- Create .env file and config .env (.env.example), then run:
```bash
php artisan key:generate
```

- Install package

```bash 
composer install
```

- Migrate and seed admin account

```bash
php artisan migrate
php artisan db:seed
```

1. Cài đặt Package (Nếu chưa làm)
Nếu bạn chưa cài đặt package, hãy chạy lệnh composer sau trong thư mục dự án:
```Bash
composer require "darkaonline/l5-swagger"
```

2. Publish File Cấu Hình (Configuration)
Chạy lệnh sau để tạo file cấu hình config/l5-swagger.php. File này cho phép bạn tùy chỉnh đường dẫn UI, tiêu đề, và các thiết lập khác:

```Bash
php artisan vendor:publish --provider "Darkaonline\L5Swagger\L5SwaggerServiceProvider"
```
3. Lệnh Chạy / Tạo Tài Liệu Swagger (Quan trọng nhất)
Mỗi khi bạn viết mới hoặc cập nhật các Annotation (chú thích) trong Code, bạn cần chạy lệnh sau để Generate (biên dịch) ra file JSON/YAML cho Swagger UI đọc:
```Bash
php artisan l5-swagger:generate
```

4.Chạy lệnh để xuất hình ảnh
```Bash
php artisan storage:link
```
