# Chat System

Chat application built with Laravel 10, Inertia.js and Vue 3.

## Status

In progress.

- Done: registration, login, email verification, password reset, profile management, conversation list with unread counts, database schema for conversations and messages
- Next: sending messages, opening a single conversation, real-time updates

## Tech stack

PHP 8.1, Laravel 10, Inertia.js, Vue 3, Tailwind CSS, Vite, MySQL

## Getting started

```bash
git clone https://github.com/belalyasin/chat-system.git
cd chat-system
composer install
npm install
cp .env.example .env      # set the DB_* values
php artisan key:generate
php artisan migrate
npm run dev               # in one terminal
php artisan serve         # in another
```
