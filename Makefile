#@author Fred Brooker <git@gscloud.cz>
include .env
all: info

info:
	@echo "\e[1;32m👾 Welcome to ${APP_NAME}"
	@echo ""
	@echo "🆘 \e[0;1mmake build\e[0m - build Docker image"
	@echo "🆘 \e[0;1mmake run\e[0m - run Docker image and show web browser"
	@echo "🆘 \e[0;1mmake push\e[0m - push Docker image into the registry"
	@echo "🆘 \e[0;1mmake start\e[0m - start container"
	@echo "🆘 \e[0;1mmake stop\e[0m - stop container"
	@echo "🆘 \e[0;1mmake kill\e[0m - kill container"
	@echo "🆘 \e[0;1mmake execbash\e[0m - exec bash in the container"
	@echo "🆘 \e[0;1mmake du\e[0m - update container"
	@echo ""
	@echo "🆘 \e[0;1mmake install\e[0m - install"
	@echo ""
	@echo "🆘 \e[0;1mmake clear\e[0m - clear all temporary files"
	@echo "🆘 \e[0;1mmake clearcache\e[0m - clear cache"
	@echo "🆘 \e[0;1mmake clearci\e[0m - clear CI logs"
	@echo "🆘 \e[0;1mmake clearlogs\e[0m - clear logs"
	@echo "🆘 \e[0;1mmake cleartemp\e[0m - clear temp"
	@echo ""
	@echo "🆘 \e[0;1mmake doctor\e[0m - run Tesseract doctor"
	@echo "🆘 \e[0;1mmake gulp\e[0m - install/update Gulp installation"
	@echo "🆘 \e[0;1mmake update\e[0m - update dependencies"
	@echo "🆘 \e[0;1mmake test\e[0m - run local integration test"
	@echo "🆘 \e[0;1mmake prod\e[0m - run production integration test"
	@echo "🆘 \e[0;1mmake unit\e[0m - run unit test"
	@echo "🆘 \e[0;1mmake sync\e[0m - sync to the remote"
	@echo ""
	@echo "🆘 \e[0;1mmake docs\e[0m - build documentation"
	@echo ""
	@echo "🆘 \e[0;1mmake everything\e[0m - run: doctor clear unit test update sync prod"
	@echo "🆘 \e[0;1mmake image\e[0m - run: doctor clear unit test update build run"

docs:
	@echo "🔨 \e[1;32m Creating documentation\e[0m\n"
	@cat TECHNICAL_DETAILS_EN.md | grep ^# | sed 'G;' > TECHNICAL_DETAILS_EN_OUTLINE.md
	@cat TECHNICAL_DETAILS_CZ.md | grep ^# | sed 'G;' > TECHNICAL_DETAILS_CZ_OUTLINE.md
	@bash ./bin/create_pdf.sh

update:
	@bash ./bin/update.sh
	@make clear
	@echo ""

unit:
	@bash ./cli.sh unit

clear:
	@bash ./cli.sh clearall

clearcache:
	@bash ./cli.sh clearcache

clearci:
	@bash ./cli.sh clearci

clearlogs:
	@bash ./cli.sh clearlogs

cleartemp:
	@bash ./cli.sh cleartemp

install:
	@bash ./bin/install.sh

doctor:
	@bash ./cli.sh doctor

sync:
	@bash ./bin/sync.sh x
	@bash ./bin/sync.sh b
	@bash ./bin/sync.sh a

test:
	@bash ./cli.sh unit
	@bash ./cli.sh local

prod:
	@bash ./cli.sh prod

gulp:
	@echo "🔨 \e[1;32m Setting gulp\e[0m\n"
	@bash ./bin/gulp.sh

build:
	@echo "🔨 \e[1;32m Building image\e[0m\n"
	@bash ./bin/build.sh

push:
	@echo "🔨 \e[1;32m Pushing image to Docker.io\e[0m\n"
	@bash ./bin/push.sh

run:
	@echo "🔨 \e[1;32m Running container\e[0m\n"
	@bash ./bin/run.sh

start:
	@echo "🔨 \e[1;32m Starting container\e[0m\n"
	@bash ./bin/start.sh

stop:
	@echo "🔨 \e[1;32m Stopping container\e[0m\n"
	@bash ./bin/stop.sh

kill:
	@echo "🔨 \e[1;32m Killing container\e[0m\n"
	@bash ./bin/kill.sh

execbash:
	@bash ./bin/execbash.sh

du:
	@echo "🔨 \e[1;32m Updating\e[0m\n"
	@bash ./bin/update_docker.sh

# update and test local + sync to remote and test
everything: doctor clear update test sync prod

# build docker image
image: doctor clear update test build run
