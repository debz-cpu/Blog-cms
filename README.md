# Blog CMS — Symfony 8

A blog content management system built with Symfony 8, Doctrine ORM, and Bootstrap 5.

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
DATABASE_URL="mysql://root:@127.0.0.1:3306/blog_cms?serverVersion=8.0.32&charset=utf8mb4"
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

## Features

### Public (no login required)
| Page | URL |
|---|---|
| Blog homepage / post list | `/` or `/posts` |
| Read a post + approved comments | `/post/{id}` |
| Register | `/register` |
| Login | `/login` |

### Authenticated users (`ROLE_USER`)
| Action | URL |
|---|---|
| Create a new post (with image) | `/post/new` |
| Submit a comment on a post | `/post/{id}` |

### Admins (`ROLE_ADMIN`)
| Action | URL |
|---|---|
| Approve a comment | `/comment/approve/{id}` |
| Delete a comment | `/comment/delete/{id}` |

---

## Entities

### User
- `email` (unique identifier)
- `password` (bcrypt/auto hashed)
- `roles` (JSON array)
- `isVerified` (email verification flag)

### Post
- `title`
- `content` (text)
- `image` (filename, stored in `public/uploads/posts/`)
- `createdAt`, `updatedAt`
- Belongs to a `User`

### Comment
- `content` (text)
- `createdAt`
- `isApproved` (boolean, default false — requires admin approval)
- Belongs to a `Post` and a `User`

---

## File Uploads

Post images are uploaded to:
```
public/uploads/posts/
```

Handled by `App\Service\FileUploader`. Accepted types: JPEG, PNG, GIF. Max size: 2MB.

---

## Security

- Login uses a custom `LoginAuthenticator` (form-based, email + password)
- Successful login redirects to `/`
- Logout redirects to `/login`
- Remember me: 7 days
- Comments require approval before appearing publicly

---

## Project Structure

```
src/
  Controller/
    PostController.php       # list, show, new post
    CommentController.php    # approve, delete comment
    SecurityController.php   # login/logout
    RegistrationController.php
  Entity/
    Post.php
    Comment.php
    User.php
  Form/
    Type/
      PostType.php
      CommentType.php
    RegistrationFormType.php
  Service/
    FileUploader.php
  Security/
    LoginAuthenticator.php
templates/
  post/
    index.html.twig   # blog homepage
    show.html.twig    # single post + comments
    new.html.twig     # create post form
  base.html.twig      # layout + navbar
migrations/           # Doctrine DB migrations
public/uploads/posts/ # uploaded post images
```
