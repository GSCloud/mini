# Tesseract MINI

Demo: **[mini.gscloud.cz](https://mini.gscloud.cz)**  
Repository:  **[github.com/GSCloud/mini](https://github.com/GSCloud/mini)**  
Distributed under **MIT** license.

## Building Microsites & Apps

Pure **PHP OOP** 7.4/8.0+, using **Composer**, **MVP** architecture, installable **Progressive Web App**.  
There is a **CLI** support, **Google OAuth 2.0** sign-in, **asynchronous client code** using Vanilla JS or jQuery (*quite easy to modify*), implemented **access limiter** and **advanced caching**, automatic assets **versioning and branching**.  
Tesseract MINI has built-in generated **REST API** and **CI** testing.

## Components

**CakePHP** cache, **libSodium Halite** encryption, **NE-ON** config notation, **Tracy** debugger, **Alto** router, **Monolog** logger, and **Mustache** templates.

## Architecture

**MVP** (Model View Presenter), where:

* **Model** is a multi-dimensional array,
* **View** is a set of mustache templates,
* **Presenter** is a set of classes extending basic abstract presenter.

REST API: **[/api](/api)**
