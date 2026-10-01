.PHONY: help
help: ## Displays this list of targets with descriptions
	@echo "The following commands are available:\n"
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: docs
docs: ## Generate projects docs (from "Documentation" directory)
	mkdir -p Documentation-GENERATED-temp

	docker run --rm --pull always -v "$(shell pwd)":/project -t ghcr.io/typo3-documentation/render-guides:latest --config=Documentation

.PHONY: docs-fast
docs-fast: ## Generate projects docs (from "Documentation" directory)
	mkdir -p Documentation-GENERATED-temp

	docker run --rm -v "$(shell pwd)":/project -t ghcr.io/typo3-documentation/render-guides:latest --config=Documentation

.PHONY: docs-watch
docs-watch: ## Watch for changes and regenerate docs automatically
	@echo "Watching for changes in Documentation directory..."
	@while inotifywait -r -e modify,create,delete,move Documentation/ 2>/dev/null; do \
    	echo "Changes detected, regenerating documentation..."; \
    	$(MAKE) docs-fast; \
    	echo "Documentation updated at $$(date)"; \
	done

.PHONY: watch-install
watch-install: ## Install inotify-tools for file watching (Ubuntu/Debian)
	sudo apt-get update && sudo apt-get install -y inotify-tools
.PHONY: test-matrix
test-matrix: ## Run the cross-version test matrix (TYPO3 9-14, one branch each) in disposable DDEV labs
	Build/Scripts/runTests.sh

.PHONY: test-matrix-live
test-matrix-live: ## Same, plus real Microsoft Graph sends (CLI + frontend getEnv) and the browser check per major
	Build/Scripts/runTests.sh --live

.PHONY: test-matrix-status
test-matrix-status: ## Show the state of each test lab
	Build/Scripts/runTests.sh --status

.PHONY: install-hooks
install-hooks: ## Gate tag pushes on a green matrix (all worktrees of this clone)
	git config core.hooksPath "$(CURDIR)/.githooks"
	@echo "pre-push hook enabled: pushing a tag now runs the matrix for its branch."

.PHONY: test-matrix-clean
test-matrix-clean: ## Delete every test lab, DDEV project and git worktree the matrix created
	Build/Scripts/runTests.sh --destroy
