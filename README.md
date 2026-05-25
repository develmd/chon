# CHECKON - Система контроля посещаемости по QR-кодам

## Описание
Система для отметки посещаемости студентов через QR-коды с автоматическими уведомлениями родителям.

## Функционал
- ✅ Управление студентами (CRUD)
- ✅ Управление расписанием (CRUD)
- ✅ Генерация QR-кодов для занятий
- ✅ Сканирование через камеру телефона
- ✅ Автоопределение опозданий
- ✅ Email уведомления родителям
- ✅ Панель преподавателя в реальном времени
- ✅ Отчёты и экспорт в Excel

## Технологии
- PHP 8+
- SQLite
- Bootstrap 5
- HTML5 QR Code Scanner

## Установка
1. Склонируйте репозиторий
2. Запустите локальный сервер (Например: XAMPP)
3. Откройте `http://127.0.0.1:8080`

## Тестовые данные
- **Студенты:** 6 человек (ИП-10, ИП-12, и т.д)
- **Преподаватели:** ivanov/ivanov123, petrov/petrov123

## Лицензия
MIT

# Команда проекта CHECKON

| Роль | ФИО | Обязанности | GitHub |
|------|-----|-------------|--------|
| Team Lead | Житников Елисей | Руководство | [@??](https://github.com/??) |
| Backend | Ротарь Никита | БД, PHP-логика, API | [@NellorYT](https://github.com/NellorYT) |
| Frontend | Ротарь Никита | HTML/CSS, Bootstrap | [@NellorYT](https://github.com/NellorYT) |
| Fullstack | Ротарь Никита | QR-сканер, уведомления | [@NellorYT](https://github.com/NellorYT) |
| QA / Документалист | Маслов Павел | Тестирование, отчёты | [@maslovpalmih](https://github.com/maslovpalmih) |

## Распределение задач
- Backend: students.php, schedule.php, generate_qr.php
- Frontend: navbar.php, все стили и адаптив
- Fullstack: scan.php, teacher_panel.php, notifications.php
- QA: attendance.php, reports.php, тестирование
