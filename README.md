Tesseract MINI framework
========================

for building microsites and apps
--------------------------------

* **PHP 7.3+ OOP**
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
* **NE-ON** library
* **Nette Tracy** debugger
* **Alto Router**
* **monolog** logging
* **mustache** templates
* support for **Cloudflare**
* support for **Google Cloud Platform**
* support for **PHP Google Analytics**

Architecture:

* **Model** = multi-dimensional data array
* **View** = mustache templates
* **Presenter** = Singleton presenters extending abstract presenter

API calls examples:

* **[API dashboard](/api)**
* **[API call example #1](/api/v1/GetCall1)**
* **[API call example #2](/api/v1/GetCall2/someparameter)**

Execution flow:

* **www/index.php** - entry point
* **Bootstrap.php** - core setup (constants, debugger, model)
* **app/App.php** - main app (routing, mapping, logging)
* **app/Presenter.php** - presenters (content)

Configuration:

* **config.neon** - configuration
* **config_private.neon** - private configuration
* **app/router.neon** - routing table

How to:

* **./INSTALL.sh** - installer
* **./UPDATE.sh** - updater
* **./cli.sh doctor** - installation doctor
* **./cli.sh local** - local integration test

License: **MIT**

* app: **[https://mini.gscloud.cz](https://mini.gscloud.cz)**
* git: **[https://github.com/GSCloud/mini](https://github.com/GSCloud/mini)**
