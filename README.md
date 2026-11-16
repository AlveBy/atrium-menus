# Atrium Menus

Menus for [Atrium](https://github.com/AlveBy/atrium) built in the admin: per site and per language, items in a tree edited by dragging, links to addresses or to records of any module, and items that unfold a module's records by themselves.

## Install

```bash
composer require alveby/atrium-menus
php artisan atrium:module:install alveby/atrium-menus
```

Add `--demo` for a header and a footer menu in the installer's language (any of the panel's 15), plus English, German and Russian ones, made of the installed modules.

## Use

```blade
<x-atrium-menu code="header" />
```

```php
$menu = atrium_menu('footer');
```

With the public theme (`alveby/atrium-theme`) the `header` and `footer` menus appear in the theme's header and footer by themselves. The current page and the items above it are marked (`aria-current="page"`, `is-active`).

The guide: [docs/menus.md](https://github.com/AlveBy/atrium/blob/main/docs/menus.md) ([по-русски](https://github.com/AlveBy/atrium/blob/main/docs/menus.ru.md)).

## Tests

From the repository root:

```bash
vendor/bin/phpunit packages/menus/tests
```

The tests cover the admin form and the tree, the API, the component and the marking of the current page, unfolded modules, the demo and a site without the module, on SQLite, MySQL and PostgreSQL.

## Contributing

This repository is a read-only copy of `packages/menus` in [AlveBy/atrium](https://github.com/AlveBy/atrium). Issues and pull requests go there.

## License

MIT
