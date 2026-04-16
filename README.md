# AI-Bolit

Сканер **AI-Bolit** для поиска вредоносного и подозрительного кода в файлах сайта (режим CLI и веб).

## Требования

- **PHP 8.0+** (рекомендуется актуальный PHP 8.2/8.3)
- Расширения: желательно `mbstring`; для `scanner.php` нужны `pdo_mysql` и доступ к MySQL

## Основной сканер (`ai-bolit.php`)

Справка по аргументам:

```bash
php ai-bolit.php --help
```

Примеры:

```bash
php ai-bolit.php --mode=2
php ai-bolit.php --mode=1
php ai-bolit.php --path=/path/to/site/public_html/
php ai-bolit.php --memory=512M --size=900K --delay=500
```

Рядом с `ai-bolit.php` должна лежать база **`AIBOLIT-WHITELIST.db`**, если вы используете белые списки из комплекта.

### Веб-доступ

В начале `ai-bolit.php` задайте константу `PASS` вместо плейсхолдера `????????????????`, иначе при открытии из браузера скрипт завершится с `Forbidden`.

### Лицензия и использование

В шапке `ai-bolit.php` указаны ограничения правообладателя (коммерческое использование, исходный код и сигнатуры). Соблюдайте условия, на которых вы получили продукт.

## Вспомогательный сканер каталога (`scanner.php`)

Скрипт обходит дерево файлов, сохраняет структуру в MySQL и может отправить отчёт о новых/удалённых/изменённых файлах.

**Секреты не хранятся в репозитории.** Скопируйте пример конфигурации и отредактируйте под себя:

```bash
cp scanner.config.example.php scanner.config.php
```

В PowerShell: `Copy-Item scanner.config.example.php scanner.config.php`

Файл `scanner.config.php` перечислен в `.gitignore` и не должен коммититься.

### Утечки в истории Git

Ранее в репозитории могли оказаться реальные пароли или пути. После перехода на `scanner.config.php` **смените пароли БД и другие секреты**. Если репозиторий публичный, рассмотрите очистку истории (`git filter-repo` / BFG) — обычный коммит не удаляет старые значения из прошлых ревизий.

## Прочее

- В корне лежит `.gitignore` (IDE, локальный конфиг сканера, `.env`).
- Опечатка в старых инструкциях: имя файла **`ai-bolit.php`**, не `ai-boilit.php`.

### English (CLI quick reference)

Full CLI power is available when running AI-Bolit locally or over SSH. Examples:

```bash
php ai-bolit.php --help
php ai-bolit.php --mode=2
php ai-bolit.php --path=/home/user/site/public_html/ --mode=2 --cms=wordpress
```

Batch scan of multiple site roots (Linux / macOS):

```bash
find /var/www/user/data/www -maxdepth 1 -type d -exec php ai-bolit.php --path={} --mode=2 \;
```

---

Репозиторий и документация поддерживаются **[студией Павла Сайка](https://palpalych.ru/)** — разработка сайтов, SEO и сопровождение проектов.
