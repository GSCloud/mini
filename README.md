Tesseract MINI framework
========================

for building microsites and apps
--------------------------------


* **PHP 7.3 OOP**
* **MVP** architectural pattern
* **Progressive Web App** (PWA)
* **CLI functions**
* **Google OAuth 2.0** sign-in
* jQuery **asynchronous client code**
* **access limiter**
* **advanced caching** and **assets versioning**
* **alpha** and **beta** versions
* **API calls**


Components:

* **CakePHP cache** library
* **Halite** encrypted cookies using **Sodium**
* **Markdown** library
* **NEON** library
* **Nette Tracy** debugger
* **Alto Router**
* **monolog** logging
* **mustache** templates
* support for **Cloudflare**
* support for **Google Cloud Platform**
* support for **PHP Google Analytics**
* support for other **Nette functions**


Model View Presenter architectural pattern:

* **Model** = multidimensional data array
* **View** = mustache template
* **Presenter** = singleton class extending abstract presenter


Execution flow:

* **www/index.php** - may set some constants
* **Bootstrap.php** - environment setup
* **app/App.php** - main app, loads Presenter


API calls:

[/api/v1/GetCall1](/api/v1/GetCall1)
[/api/v1/GetCall2/someparameter](/api/v1/GetCall2/someparameter)


License: MIT, website: [https://mini.gscloud.cz](https://mini.gscloud.cz)
repository: [https://github.com/GSCloud/mini](https://github.com/GSCloud/mini)
