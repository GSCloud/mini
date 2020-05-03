Tesseract MINI
==============

building microsites and apps
----------------------------

* Pure **PHP OOP** 7.4+ via **Composer**,
* **MVP** architecture,
* installable **Progressive Web App**,
* **CLI** support,
* **Google OAuth 2.0** sign-in,
* **asynchronous client code** (Vanilla JS, jQuery),
* **access limiter**,
* **caching**, assets **versioning** and **branching**,
* built-in **REST API**,
* **MIT** license.

Components:

* **CakePHP** cache,
* **Halite** encryption,
* **NE-ON** config notation,
* **Tracy** debugger,
* **Alto** router,
* **Monolog** logging,
* **Mustache** templates.

Architecture:

* **Model** is a multi-dimensional array,
* **View** are mustache templates,
* **Presenter** are loadable classes extending abstract presenter.

REST API calls examples:

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

Run:

* **./INSTALL.sh** for quick installer,
* **./UPDATE.sh** to update the installation,
* **./cli.sh doctor** for installation doctor,
* **./cli.sh local** for local integration test.

---

Demo: **[https://mini.gscloud.cz](https://mini.gscloud.cz)**

Repository:  **[https://github.com/GSCloud/mini](https://github.com/GSCloud/mini)**
