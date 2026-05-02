# Blog CMS — Symfony 8

A blog content management system built with Symfony 8, Doctrine ORM, and MySQL. Currently implements user registration, email verification, and authentication. Post management is in progress.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Symfony 8.0 |
| ORM | Doctrine ORM 3 + Migrations |
| Database | MySQL 8.0 |
| Auth | Symfony Security + custom `LoginAuthenticator` |
| Email | Symfony Mailer + SymfonyCasts Verify Email |
| Frontend | Symfony AssetMapper + Stimulus + Turbo |
| Admin | EasyAdmin Bundle 5 (installed, not yet configured) |
| PHP | >= 8.4 |

---

## Requirements

- PHP >= 8.4
- MySQL 8.0+
- Composer
- XAMPP (or any local server with Apache + MySQL)

---

## Setup

### 1. Install dependencies

```bash
composer install
```

### 2. Configure environment

Copy `.env` to `.env.local` and set your database credentials:

```bash
cp .env .env.local
```

Edit `.env.local`:

```
DATABASE_URL="mysql://root:@127.0.0.1:3306/Blog-cms?serverVersion=8.0.32&charset=utf8mb4"
```

If you need email verification, also configure the mailer:

```
MAILER_DSN=smtp://user:pass@smtp.example.com:587
```

### 3. Create database and run migrations

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 4. Start the server

Using Symfony CLI:
```bash
symfony server:start
```

Or PHP built-in server:
```bash
php -S 127.0.0.1:8000 -t public
```

Then open: [http://127.0.0.1:8000](http://127.0.0.1:8000)

---

## Implemented Features

### Authentication
- User registration with email + password
- Email verification via signed URL (SymfonyCasts)
- Form login (email + password) with CSRF protection
- Remember me (7 days)
- Logout

### Access Control
| Path | Access |
|---|---|
| `/login` | Public |
| `/register` | Public |
| Everything else | `ROLE_USER` required |

---

## Entities

### User
- `id`
- `email` (unique, normalized to lowercase)
- `password` (hashed via `auto` algorithm)
- `roles` (JSON array — always includes `ROLE_USER`)
- `isVerified` (boolean, default `false`)
- `posts` (one-to-many → `Post`)

### Post *(entity exists, controller/templates not yet built)*
- `id`
- `title`
- `content` (text)
- `image` (VARCHAR 125 — filename)
- `createdAt`, `updatedAt`
- `user` (many-to-one → `User`)

---

## Routes

| Name | Method | Path | Description |
|---|---|---|---|
| `app_login` | GET/POST | `/login` | Login form |
| `app_logout` | GET | `/logout` | Logout |
| `app_register` | GET/POST | `/register` | Registration form |
| `app_verify_email` | GET | `/verify/email` | Email verification |

---

## Project Structure

```
src/
  Controller/
    RegistrationController.php   # register + email verify
    SecurityController.php       # login/logout
  Entity/
    User.php
    Post.php
  Form/
    RegistrationFormType.php
  Security/
    LoginAuthenticator.php
    EmailVerifier.php
  Repository/
    UserRepository.php
    PostRepository.php
templates/
  base.html.twig
  registration/
    register.html.twig
    confirmation_email.html.twig
  security/
    login.html.twig
migrations/
  Version20260502094431.php   # creates user + messenger_messages tables
```

---

## Known Issues / In Progress

- `doctrine:migrations:list` and `doctrine:migrations:status` may throw a `MetadataStorageError` — `doctrine:migrations:migrate` works fine
- Post CRUD (controller, templates, form) not yet implemented
- No admin panel wired up yet (EasyAdmin is installed)
