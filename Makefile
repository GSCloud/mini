all: info

info:
	@echo "\n\e[1;32m👾 Welcome to Tesseract 👾\n"

	@echo "🆘 \e[0;1mmake build\e[0m - build Docker image"
	@echo "🆘 \e[0;1mmake dd\e[0m - update Docker data"
	@echo "🆘 \e[0;1mmake push\e[0m - push image into the registry"
	@echo "🆘 \e[0;1mmake run\e[0m - test Docker image"

	@echo ""

	@echo "🆘 \e[0;1mmake docs\e[0m - build documentation"
	@echo "🆘 \e[0;1mmake doctor\e[0m - Tesseract doctor"
	@echo "🆘 \e[0;1mmake install\e[0m - install/reinstall (safe)"
	@echo "🆘 \e[0;1mmake prodtest\e[0m - production integration test"
	@echo "🆘 \e[0;1mmake sync\e[0m - sync to the remote"
	@echo "🆘 \e[0;1mmake test\e[0m - local integration test"
	@echo "🆘 \e[0;1mmake update\e[0m - update dependencies\n"

docs:
	@/bin/bash ./create_pdf.sh

update:
	@/bin/bash ./UPDATE.sh

install:
	@/bin/bash ./INSTALL.sh

doctor:
	@/bin/bash ./cli.sh doctor

sync:
	@/bin/bash ./SYNC.sh x
	@/bin/bash ./SYNC.sh b

test:
	@/bin/bash ./cli.sh local

prodtest:
	@/bin/bash ./cli.sh prod

build:
	@echo "\n🔨 \e[1;32m Building Docker image\e[0m"
	@/bin/bash ./BUILD.sh

push:
	@echo "\n🔨 \e[1;32m Pushing image to DockerHub\e[0m"
	@docker push gscloudcz/tesseract-lasagna:latest

run:
	@echo "\n🔨 \e[1;32m Testing Docker image\e[0m"
	@/bin/bash ./TESTRUN.sh

dd:
	docker exec -ti tesseract bash ./docker_updater.sh

everything: docs update sync
