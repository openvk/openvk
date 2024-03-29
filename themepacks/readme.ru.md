# OpenVK Themepacks

Эта папка содержит все темы, которые могут быть использованы пользователями инстанса.

## Как создавать свои темы?

Создайте директорию, название которого должно содержать только латинские буквы и цифры, и создайте там файл `theme.yml`, и заполняем его следующим содержанием:

```yaml
id: vk2007
version: "0.0.1.0"
openvk_version: 0
enabled: 1
metadata:
    name:
        _: "V Kontakte 2007"
        en: "V Kontakte 2007"
        ru: "В Контакте 2007"
    author: "Veselcraft"
    description: "V Kontakte-stylized theme by 2007 era"
```

**Где:** 

`id` - название папки

`version` - версия темы

`openvk_version` - версия OpenVK *(стоит оставить 0)*

`metadata`:

* `name` - имя темы для конечного пользователя. Внутри её можно оставить названия для разных языков. `_` (нижнее подчеркивание) - для всех языков.

Далее, в `stylesheet.css` вставляем любой CSS код, с помощью которого вы можете изменить элементы сайта. Если вам нужны дополнительные картинки или ресурсы, то для этого просто создайте папку `res`, и в CSS коде обращайтесь к ресурсам через путь `/themepack/{название директории}/{версия темы}/resource/{ресурс}`.

Для поддержки новогоднего настроения, которое включается автоматически с 1 декабря по 15 января, создайте файл `xmas.css` в папку `res`, и внесите вам нужные изменения.

**В конце концов, иерархия директории с темой должна выглядеть вот так:**

```
vk2007:
- res
  - {ресурсы}
- stylesheet.css
- theme.yml
```
