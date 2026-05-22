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
