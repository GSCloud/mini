Tesseract MINI framework
========================

for building microsites and apps
--------------------------------

* **PHP 7.3 OOP**
* **MVP** architectural pattern
* **Progressive Web App** (PWA)
* **CLI functions**
* **Google OAuth 2.0** sign-in
* **asynchronous client code** (Vanilla JS, jQuery)
* **access limiter**
* **advanced caching** and **assets versioning**, * **alpha** and **beta** versions
* **API calls**

Components:

* **CakePHP cache** library
* **Halite** encrypted cookies
* **Markdown** library
* **NEON** library
* **Nette Tracy** debugger
* **Alto Router**
* **monolog** logging
* **mustache** templates
* support for **Cloudflare**
* support for **Google Cloud Platform**
* support for **PHP Google Analytics**

Model View Presenter architectural pattern:

* **Model** = multidimensional data array
* **View** = mustache template
* **Presenter** = Singleton extending abstract presenter

API calls examples:

* **[/api/v1/GetCall1](/api/v1/GetCall1)**
* **[/api/v1/GetCall2/someparameter](/api/v1/GetCall2/someparameter)**

Execution flow:

* **www/index.php** - entry point
* **Bootstrap.php** - setup
* **app/App.php** - main app
* **app/*Presenter.php** - presenters

Configuration:

* **config.neon** - configuration
* **config_private.neon** - private configuration
* **app/router*.neon** - routing tables

License: **MIT**

* project website: **[https://mini.gscloud.cz](https://mini.gscloud.cz)**
* git repository: **[https://github.com/GSCloud/mini](https://github.com/GSCloud/mini)**
