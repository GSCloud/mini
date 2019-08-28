<?php

define("ROOT", __DIR__ . "/..");

#define("DEBUG", true);

#define("CACHE", ROOT . "/cache");
#define("DATA", ROOT . "/data");
#define("WWW", ROOT . "/www");
#define("CONFIG", ROOT . "/config.neon");
#define("CONFIG_PRIVATE", ROOT . "/config_private.neon");

#define("TEMPLATES", WWW . "/templates");
#define("PARTIALS", WWW . "/partials");
#define("DOWNLOAD", WWW . "/download");
#define("UPLOAD", WWW . "/upload");

#define("TEMP", "/tmp");

require_once ROOT . "/Bootstrap.php";
