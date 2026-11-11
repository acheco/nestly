# Translations

`lang/es/{auth,pagination,passwords,validation}.php` and `lang/es.json` are a one-off copy generated on 2026-11-11 by
`php artisan lang:add es` (spec 001, R3; D-092). The packages were installed only for that command and removed in the
same commit, so they are not dependencies of this project. The files are maintained by hand from here on: never run
`lang:update` over them.

| Package                     | Version | Reference                                  |
| --------------------------- | ------- | ------------------------------------------ |
| `laravel-lang/lang`         | 15.37.7 | `07c03c1085fd666e6a9c08328ccd414eff4b00a8` |
| `laravel-lang/starter-kits` | 1.17.1  | `1de9547644309a18da42647546beaa1c163a8f35` |
| `laravel-lang/publisher`    | 16.9.0  | `b0d1e7a60d6504ac2f2204315defd243cabe8385` |

Changes after generation:

- The PHP files were formatted with Pint (alignment and line endings only), and `es.json` gained a final newline from
  `vp check`. No key or text changed.
- `validation.php` gained an `attributes` section by hand (`email`, `password`), so field names are in Spanish too
  (D-085).

`tests/Feature/LangParityTest.php` fails when the framework adds keys to its English `auth`, `pagination`, `passwords`
or `validation` files that this copy lacks. The JSON strings have no parity test and are checked by hand (R3).

## License

The translations come from Laravel Lang and are distributed under the MIT License:

```
MIT License

Copyright (c) 2013-2026 Laravel Lang Team
Copyright (c) 2025-2026 Laravel-Lang Team

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

`laravel-lang/lang` carries the 2013-2026 "Laravel Lang Team" notice; `laravel-lang/starter-kits` carries the
2025-2026 "Laravel-Lang Team" notice.
