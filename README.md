Tesseract MINI framework
========================

for building microsites and apps
--------------------------------


* PHP 7.3 OOP
* MVP architectural pattern
* Progressive Web App (PWA)
* minimalistic design
* Google OAuth 2.0 sign-in
* asynchronous jQuery client code
* CLI functions


Components:

* CakePHP cache library
* Halite encrypted cookies using Sodium library
* Markdown library for building HTML
* NEON library for reading INI files
* Nette Tracy debugger
* access limiter
* fast Alto Router
* monolog logging services
* mustache templates
* support for Cloudflare
* support for Google Cloud Platform
* support for PHP Google Analytics
* support for other Nette functions


Model View Presenter architectural pattern:

* **Model** = data array
* **View** = mustache template
* **Presenter** = singleton class extending abstract presenter


Execution flow:

* **www/index.php** - may set some constants
* **Bootstrap.php** - environment setup
* **app/App.php** - main app, loads Presenter

License: MIT, website: [https://mini.gscloud.cz](https://mini.gscloud.cz)
repository: [https://github.com/GSCloud/mini](https://github.com/GSCloud/mini)
