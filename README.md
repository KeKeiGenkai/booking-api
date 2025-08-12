# Тестовое задание - REST API для бронирования спортивных площадок

REST API для бронирования временных слотов на спортивной площадке с проверкой пересечений по времени и авторизацией по API токену.

## Техническое задание

### Модели данных
- **users**: id, name, api_token, timestamps
- **bookings**: id, user_id (FK), timestamps
- **booking_slots**: id, booking_id (FK), start_time, end_time, timestamps

### Правила
1. При создании брони пользователь может указать несколько временных слотов
2. При обновлении можно:
   - Изменить один слот (по slot_id)
   - Добавить новый слот к уже существующему заказу
3. Временные слоты не должны пересекаться с другими слотами в системе
4. Временные слоты не должны пересекаться внутри заказа
5. Нельзя удалять или обновлять чужие бронирования

### Требования
- PHP 8.1+
- Composer
- MySQL/PostgreSQL
- Laravel 12

### Установка
```
git clone https://github.com/your-username/booking-api.git
cd booking-api

composer install

cp .env.example .env

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=booking_api
DB_USERNAME=root
DB_PASSWORD=

php artisan key:generate

php artisan migrate:fresh --seed

php artisan serve
```

## Эндпоинты

Все запросы должны содержать заголовок авторизации:

Authorization: Bearer YOUR_API_TOKEN


### Тестовые токены
После выполнения сидов доступны пользователи:
- `test-token-1` - Иван Иванов
- `test-token-2` - Петр Петров  
- `test-token-3` - Сидор Сидоров



## Примеры использования

### Создание бронирования
```
curl -X POST "http://localhost:8000/api/bookings" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer test-token-1" \
  -d '{
    "slots": [
      {
        "start_time": "2025-06-25T12:00:00",
        "end_time": "2025-06-25T13:00:00"
      },
      {
        "start_time": "2025-06-25T13:30:00",
        "end_time": "2025-06-25T14:30:00"
      }
    ]
  }'
```

### Получение списка бронирований
```
curl -X GET "http://localhost:8000/api/bookings" \
  -H "Authorization: Bearer test-token-1"
```

### Обновление слота
```
curl -X PATCH "http://localhost:8000/api/bookings/1/slots/1" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer test-token-1" \
  -d '{
    "start_time": "2025-06-25T15:00:00",
    "end_time": "2025-06-25T16:00:00"
  }'
```

### Добавление слота к заказу
```
curl -X POST "http://localhost:8000/api/bookings/1/slots" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer test-token-1" \
  -d '{
    "start_time": "2025-06-25T17:00:00",
    "end_time": "2025-06-25T18:00:00"
  }'
```

### Удаление заказа
```
curl -X DELETE "http://localhost:8000/api/bookings/1" \
  -H "Authorization: Bearer test-token-1"
```

## Тестирование

### Запуск тестов
```
# Все тесты
php artisan test

# Только API тесты
php artisan test tests/Feature/BookingApiTest.php

# С подробным выводом
php artisan test --verbose
```

/
