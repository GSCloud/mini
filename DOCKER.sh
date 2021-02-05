#!/bin/bash

# connect to the running container and run CSV updater
docker exec -ti tesseract ./docker_updater.sh

# connect to the running container and run bash
docker exec -ti tesseract bash

# connect to the running container and run PHP CLI
# docker exec -ti tesseract ./cli.sh