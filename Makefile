#@author Filip Oščádal <git@gscloud.cz>
include .env

all: info
info:
	@echo "\e[1;32m👾 Welcome to ${APP_NAME}\n"

	@echo "🆘 \e[0;1mmake build\e[0m - build Docker image"
	@echo "🆘 \e[0;1mmake push\e[0m - push Docker image into the registry"
	@echo "🆘 \e[0;1mmake run\e[0m - run Docker image\n"
	@echo "🆘 \e[0;1mmake du\e[0m - update running Docker container"

	@echo "🆘 \e[0;1mmake install\e[0m - install"
	@echo "🆘 \e[0;1mmake doctor\e[0m - Tesseract doctor"
	@echo "🆘 \e[0;1mmake gulp\e[0m - install/update Gulp installation"
	@echo "🆘 \e[0;1mmake update\e[0m - update dependencies"
	@echo "🆘 \e[0;1mmake test\e[0m - local integration test"
	@echo "🆘 \e[0;1mmake prod\e[0m - production integration test"
	@echo "🆘 \e[0;1mmake sync\e[0m - sync to the remote"
	@echo "🆘 \e[0;1mmake docs\e[0m - build documentation"
docs:
	@echo "🔨 \e[1;32m Creating documentation\e[0m\n"
	@bash ./bin/create_pdf.sh
update:
	@bash ./bin/update.sh
install:
	@bash ./bin/install.sh
doctor:
	@bash ./cli.sh doctor
sync:
	@bash ./bin/sync.sh x
	@bash ./bin/sync.sh b
	#@bash ./bin/sync.sh a
test:
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
	@echo "🔨 \e[1;32m Pushing image\e[0m\n"
	@bash ./bin/push.sh
run:
	@echo "🔨 \e[1;32m Starting image\e[0m\n"
	@bash ./bin/run.sh
du:
	@echo "🔨 \e[1;32m Updating\e[0m\n"
	@bash ./bin/update_docker.sh
everything: doctor test update sync prod
reimage: doctor test update build run
