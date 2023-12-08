# Locales for OpenVK
So, there is a locales contained here for [OpenVK](../../../).

## Contributing

You can do the Pull Request for changing, updating or adding your language to this repo.

Don't forget to add theese lines to `list.yml`, if you're adding your new language:

```yaml
  - code: "tr"
    flag: "tr"
    name: "Turkish"
    native_name: "Türkçe"
    author: "Bedirhan Kurt (WindOWZ)"
```

Where:
- `code` is for language code
- `flag` is for flag code (because some language may have some variation)
- `name` is for English naming of your language
- `native_name` is for native naming for language (for example: "Русский" or "Հայերեն")
- `author` is for... author, of course!

And also, please, when you're commiting, do the comment like this: `{Some name for language or languages}: {your comment about changes}`

For example:
- `Russian: Fixed typo in Profile Info`
- `Armenian: Using չ instead of ճ`
- `Turkish: Updated strings`
