# MMORating.top Integration Guide / Руководство по интеграции MMORating.top

## English / Английский

### Overview

This guide explains how to integrate and configure MMORating.top voting system into your WoW Simple Registration website. MMORating.top is a voting rating system that allows players to vote for your server and receive rewards.

### Prerequisites

- Your server must be registered and approved on [MMORating.top](https://mmorating.top/)
- You need an API key from MMORating.top (starts with `mmr_`)

### Step 1: Get Your API Key

1. Log in to your account on [MMORating.top](https://mmorating.top/)
2. Navigate to "Мои сервера" (My Servers)
3. Find your server (it must be approved by administrators)
4. In the "API для поощрения за голосование" (API for vote rewards) section, click "Создать API ключ" (Create API key)
5. Copy the API key (it starts with `mmr_`)

⚠️ **Important**: Keep your API key secure! Never publish it publicly.

### Step 2: Configure the Voting Site

1. Open `application/config/config.php`
2. Find the `$config['vote_sites']` array
3. The MMORating.top entry is already added in the sample configuration. You need to uncomment it and add your API key:

```php
$config['vote_sites'] = array(
    // ... other voting sites ...
    array(
        'image' => 'https://mmorating.top/images/buttons/mmorating88x31.png',
        'site_url' => 'https://mmorating.top/',
        'api_type' => 'mmorating',
        'api_key' => 'mmr_your_api_key_here' // Replace with your actual API key
    )
);
```

Alternatively, you can use the global API key setting:

```php
$config['mmorating_api_key'] = 'mmr_your_api_key_here';
```

### Step 3: How It Works

The integration supports multiple verification methods:

1. **By Character Name** - If players vote with their character name, the system can verify and reward them by character name
2. **By Email** - For Battle.net accounts, verification can be done by email address
3. **By IP Address** - Fallback method for verification

When a player clicks the MMORating.top banner:
- The system checks via API if the player has already voted
- If voted: Reward is given immediately
- If not voted: Player is redirected to MMORating.top to vote

### Step 4: Optional - Character Name Field

The voting form includes an optional "Character Name" field. Players can enter their character name for more accurate vote verification. This is especially useful if you reward players by character name.

### Step 5: Verify Vote After Return

After voting on MMORating.top, players can verify their vote by visiting:
```
?verify_vote=1&account=account_name&siteid=site_id&character_name=character_name
```

Or the system will automatically check when they click the vote button again.

### API Endpoints Used

- **Check Vote**: `https://mmorating.top/api/v1/vote/check-flexible`
- **Server Info**: `https://mmorating.top/api/v1/server/info`

### Troubleshooting

- **"API ключ для MMORating.top не настроен"** - Make sure you've added your API key in the configuration
- **Vote not detected** - Ensure the player voted with the same character name/email/IP that matches your verification method
- **API timeout** - Check your server's internet connection and firewall settings

---

## Русский / Russian

### Обзор

Это руководство объясняет, как интегрировать и настроить систему голосования MMORating.top на ваш сайт WoW Simple Registration. MMORating.top - это рейтинговая система голосования, которая позволяет игрокам голосовать за ваш сервер и получать награды.

### Требования

- Ваш сервер должен быть зарегистрирован и одобрен на [MMORating.top](https://mmorating.top/)
- Вам нужен API ключ от MMORating.top (начинается с `mmr_`)

### Шаг 1: Получение API ключа

1. Войдите в свой аккаунт на [MMORating.top](https://mmorating.top/)
2. Перейдите в раздел "Мои сервера"
3. Найдите ваш сервер (он должен быть одобрен администрацией)
4. В разделе "API для поощрения за голосование" нажмите "Создать API ключ"
5. Скопируйте полученный API ключ (он начинается с `mmr_`)

⚠️ **Важно**: Храните ваш API ключ в безопасности! Не публикуйте его в открытом доступе.

### Шаг 2: Настройка сайта голосования

1. Откройте файл `application/config/config.php`
2. Найдите массив `$config['vote_sites']`
3. Запись для MMORating.top уже добавлена в пример конфигурации. Вам нужно раскомментировать её и добавить ваш API ключ:

```php
$config['vote_sites'] = array(
    // ... другие сайты голосования ...
    array(
        'image' => 'https://mmorating.top/images/buttons/mmorating88x31.png',
        'site_url' => 'https://mmorating.top/',
        'api_type' => 'mmorating',
        'api_key' => 'mmr_ваш_api_ключ_здесь' // Замените на ваш реальный API ключ
    )
);
```

Альтернативно, вы можете использовать глобальную настройку API ключа:

```php
$config['mmorating_api_key'] = 'mmr_ваш_api_ключ_здесь';
```

### Шаг 3: Как это работает

Интеграция поддерживает несколько методов проверки:

1. **По имени персонажа** - Если игроки голосуют с указанием имени персонажа, система может проверить и выдать награду по имени персонажа
2. **По email** - Для Battle.net аккаунтов проверка может выполняться по email адресу
3. **По IP адресу** - Резервный метод проверки

Когда игрок нажимает на баннер MMORating.top:
- Система проверяет через API, голосовал ли игрок уже
- Если голосовал: Награда выдается немедленно
- Если не голосовал: Игрок перенаправляется на MMORating.top для голосования

### Шаг 4: Опционально - Поле имени персонажа

Форма голосования включает опциональное поле "Имя персонажа". Игроки могут ввести имя своего персонажа для более точной проверки голоса. Это особенно полезно, если вы выдаете награды по имени персонажа.

### Шаг 5: Проверка голоса после возврата

После голосования на MMORating.top игроки могут проверить свой голос, посетив:
```
?verify_vote=1&account=имя_аккаунта&siteid=номер_сайта&character_name=имя_персонажа
```

Или система автоматически проверит при следующем нажатии на кнопку голосования.

### Используемые API endpoints

- **Проверка голоса**: `https://mmorating.top/api/v1/vote/check-flexible`
- **Информация о сервере**: `https://mmorating.top/api/v1/server/info`

### Решение проблем

- **"API ключ для MMORating.top не настроен"** - Убедитесь, что вы добавили ваш API ключ в конфигурацию
- **Голос не обнаружен** - Убедитесь, что игрок голосовал с тем же именем персонажа/email/IP, который соответствует вашему методу проверки
- **Таймаут API** - Проверьте интернет-соединение вашего сервера и настройки файрвола

---

## Additional Resources / Дополнительные ресурсы

- [MMORating.top Website](https://mmorating.top/)
- [MMORating.top API Documentation](https://mmorating.top/) - Check the API documentation section on the website
