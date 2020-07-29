# Convert a webpage to an image or pdf using headless Chrome

The package can convert a webpage to an image or pdf. The conversion is done behind the scenes by [Puppeteer](https://github.com/GoogleChrome/puppeteer) which controls a headless version of Google Chrome.

## PHP 5.6 Support

This package base on **spatie/browsershot** package, built to support PHP 5.6 version.

Here's a quick example:

```php
use Ngekoding\Browsershot\Browsershot;

// a pdf will be saved
Browsershot::url('https://example.com')->save('path/to/result.pdf');
```

You can also use an arbitrary html input, simply replace the `url` method with `html`:

```php
Browsershot::html('<h1>Not just hello world!</h1>')->save('path/to/result.pdf');
```

Features: 
- Converting a webpage to pdf.
- Converting a webpage to image (coming soon).

## How to install

`composer require ngekoding/browsershot`

Please go to the original package for full documentation.

[Documentation](https://github.com/spatie/browsershot)

## Important

You have to install the Puppeteer node library to make this package worked.

This package requires node 7.6.0 or higher and the Puppeteer Node library.

On MacOS you can install Puppeteer in your project via NPM:

```bash
npm install puppeteer
```

Or you could opt to just install it globally

```bash
npm install puppeteer --global
