# Cahaya Optima Karya HRIS

Sistem HRIS untuk Cahaya Optima Karya. Aplikasi ini menyediakan autentikasi, dashboard admin, dan fondasi modul HR seperti employee, department, branch, shift, job position, dan attendance.

## Setup

```bash
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
```

## Development

```bash
php artisan serve
npm run dev
```

Login default dari seeder:

```text
Email: admin@gmail.com
Password: Password!2
Role: superadmin
```

## Testing

```bash
php artisan test
```
