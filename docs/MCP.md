# Подключение MCP-сервера SiteOps к DarkStyle

MCP-сервер **SiteOps** даёт ИИ-ассистенту (Claude) доступ к данным сайта на 1С-Битрикс: инфоблоки, разделы, элементы, свойства, цены и остатки, сброс кеша.
Исходный код и релизы: <https://github.com/EugeneSlusar/siteops.orgbox.net>.

SiteOps в этом репозитории не хранится. Он ставится на сервер отдельно и обновляется из релизов.

## Как это устроено на dark-style.ru

| | |
|---|---|
| Адрес MCP | `https://dark-style.ru/site.ops/mcp` |
| Админка SiteOps | `https://dark-style.ru/site.ops/admin/` (вход администратором Битрикса) |
| Папка на сервере | `~/dark-style.ru/public_html/site.ops/` (рядом с `bitrix/` и `upload/`) |
| Настройки и токен | `site.ops/_data/settings.php` (отдельные для этого сайта) |
| Подключение | OAuth по ссылке: токен вручную не нужен |
| Коннектор в claude.ai | `dark-style.ru/site.ops/mcp` |

Инфоблоки сайта, которые видит MCP:

| ID | Код | Название |
|---|---|---|
| 1 | `orgbox_baseshop_products` | Товары |
| 4 | `orgbox_baseshop_home_banners` | Баннеры на главной |
| 2 | `orgbox_baseshop_orders` | Заказы |

ID относятся к конкретному сайту и могут отличаться на другой установке. Актуальный список выдаёт инструмент `iblock_list`.

### Две формы установки: почему именно `site.ops`

SiteOps ставится двумя способами, и у них **разные адреса**:

- **Папка `site.ops`** в корне сайта: адрес `/site.ops/mcp`, есть OAuth. Используется на dark-style.ru.
- **Модуль Битрикса** в `/local/modules/orgbox.siteops/`: адрес `/bitrix/tools/orgbox_siteops/mcp.php`, только токен, без OAuth.

Первая версия SiteOps была поставлена в DarkStyle как модуль («служебный модуль управления SEO»: `local/modules/orgbox.siteops`, `local/api/orgbox-siteops/v1.php`, заглушка `bitrix/admin/orgbox_siteops_settings.php`).
Он **удалён** и заменён папкой `site.ops`. Адрес `/site.ops/mcp` у модуля не работает, поэтому подключать коннектор нужно только к папке.

## Установка на сервер (SSH)

Нужны PHP 8.1+, HTTPS, расширения `curl`, `zip`, `sodium`, Apache с `.htaccess` и `AllowOverride All`. Подробности и вариант для nginx: [INSTALL.md в SiteOps](https://github.com/EugeneSlusar/siteops.orgbox.net/blob/main/docs/INSTALL.md).

Версию (`0.9.2` ниже) замените на нужную со страницы [Releases](https://github.com/EugeneSlusar/siteops.orgbox.net/releases). Команда остановится, если хэш архива не совпадёт:

```bash
cd ~/dark-style.ru/public_html \
&& curl -fsSLO https://github.com/EugeneSlusar/siteops.orgbox.net/releases/download/v0.9.2/site.ops-0.9.2.zip \
&& curl -fsSLO https://github.com/EugeneSlusar/siteops.orgbox.net/releases/download/v0.9.2/site.ops-0.9.2.zip.sha256 \
&& sha256sum -c site.ops-0.9.2.zip.sha256 \
&& unzip -q site.ops-0.9.2.zip \
&& rm site.ops-0.9.2.zip site.ops-0.9.2.zip.sha256 \
&& mkdir -p site.ops/_data && chmod 775 site.ops/_data \
&& ls -d site.ops bitrix upload
```

Проверка. Первая команда должна ответить `404` с `{"error":"Не найдено."}`, пока MCP выключен, и `401` после включения; вторая всегда `403`:

```bash
curl -si -X POST -H 'Content-Type: application/json' -d '{}' https://dark-style.ru/site.ops/mcp | head -1
curl -sI https://dark-style.ru/site.ops/lib/OrgBox/SiteOps/Bootstrap.php | head -1
```

Ответы `301` (не работает `.htaccess` или `AllowOverride`), `500` (ошибка PHP или ограничения хостинга) и `404` с HTML-страницей сайта (папка лежит не в корне сайта) разобраны в [QUICKSTART SiteOps](https://github.com/EugeneSlusar/siteops.orgbox.net/blob/main/docs/QUICKSTART.md).

## Настройка в админке

Откройте `https://dark-style.ru/site.ops/admin/` под администратором Битрикса:

1. Включите **MCP-сервер**.
2. Включите **подключение по ссылке (OAuth)**, в «Адрес сайта» укажите `https://dark-style.ru`.
3. В **ID инфоблоков** перечислите нужные. Пустой список означает «читать любые, писать нельзя».
4. **Режим** оставьте «только чтение». Режим «чтение и запись» включайте на время работы и только для нужных инфоблоков, перед этим сделайте резервную копию базы.
5. Сохраните. Страница сама проверит запись чтением.

## Подключение коннектора в claude.ai

**Settings → Connectors → Add custom connector**, адрес `https://dark-style.ru/site.ops/mcp`. Затем ссылка, вход администратором Битрикса и «Разрешить».

Подключения видны и отзываются на странице настроек SiteOps («Подключения (OAuth)»).

Инструменты нового коннектора появляются в **новой** сессии, в уже идущей они могут не загрузиться.

### Несколько сайтов

К разным сайтам подключаются разные коннекторы, и инструменты у них называются по имени коннектора: `mcp__dark-style_ru_site_ops_mcp__…` у DarkStyle, а у другого сайта своё имя.
Все вызовы к DarkStyle должны идти только через `dark-style_ru_site_ops_mcp`. Перед работой проверьте `iblock_list`: у DarkStyle тип инфоблоков `orgbox_baseshop` и три инфоблока из таблицы выше. Если вместо них видны инфоблоки с кодами `aspro_max_*` и префиксом `[SM]`, вы подключены не к тому сайту.

## Деплой и обновление

- Папка `site.ops/` **не входит в репозиторий** и добавлена в `.gitignore` (`/public_html/site.ops/`). Деплой по `main` (`.github/workflows/deploy.yml` → `.deploy/sync_repo_from_git.sh` на сервере) её не должен трогать. После деплоя проверьте, что папка на месте и `/site.ops/mcp` отвечает `401`.
- Обновляется SiteOps кнопкой на странице настроек (в разделе «Обновления»), а не через git. Модуль ничего не обновляет сам.
- Не возвращайте в репозиторий `local/modules/orgbox.siteops` и `local/api/orgbox-siteops`: дублирование модуля и папки `site.ops` только запутывает.

## Безопасность

- Режим по умолчанию — только чтение, запись разрешена только для перечисленных инфоблоков.
- Хранится только хэш токена (SHA-256). Токены и секреты в репозиторий не добавляйте.
- Заказы (`orgbox_baseshop_orders`) содержат данные покупателей: не добавляйте их в список без необходимости.
- Папки `lib/` и `_data/` закрыты `.htaccess`; проверка `403` из раздела установки подтверждает это.
